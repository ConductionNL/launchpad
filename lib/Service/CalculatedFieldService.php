<?php

/**
 * CalculatedFieldService
 *
 * The server side of a widget's calculated fields at save time
 * (widget-calculated-fields, tasks 23-25): every field's stored AST is
 * checked (well-formed, allowlisted functions, argument counts), the fields
 * of the widget and its dashboard neighbours are checked for cycles, and
 * each field is type-checked in dependency order, which sets its
 * `returnType`. A field that fails is refused with the same error envelope
 * the editor's validator gives, so the API answers 400 before anything is
 * persisted. Evaluation for server-side use runs the shared-suite evaluator.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-23
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldMaterialiser;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;
use Throwable;

/**
 * Validates, compiles and evaluates calculated fields on the server.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-23
 */
class CalculatedFieldService {

	/**
	 * Constructor.
	 *
	 * @param CalculatedFieldAstChecker    $astChecker    Structural, allowlist and arity checks.
	 * @param CalculatedFieldTypeChecker   $typeChecker   Type inference.
	 * @param CalculatedFieldCycleDetector $cycleDetector Cycle detection and dependency order.
	 * @param CalculatedFieldEvaluator     $evaluator     The shared-suite evaluator.
	 * @param CalculatedFieldMaterialiser|null $materialiser Materialises server-mode fields on
	 *                                                       OpenRegister; null keeps every field client-side.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-23
	 */
	public function __construct(
		private readonly CalculatedFieldAstChecker $astChecker,
		private readonly CalculatedFieldTypeChecker $typeChecker,
		private readonly CalculatedFieldCycleDetector $cycleDetector,
		private readonly CalculatedFieldEvaluator $evaluator,
		private readonly ?CalculatedFieldMaterialiser $materialiser=null,
	) {
	}//end __construct()

	/**
	 * Validate a widget's calculated fields and set each field's `returnType`.
	 *
	 * @param array<string,mixed>            $widget  The widget: `id` and `calculatedFields`, each
	 *                                                `{name, expression, compiledExpression, ...}`.
	 * @param array<int,array<string,mixed>> $others  The other widgets of its dashboard, `{id, calculatedFields}`.
	 * @param array<string,mixed>            $options `allowedFunctions` (null for every built-in),
	 *                                                `fieldSchema` and `widgetSchemas` for field types,
	 *                                                `sources` (widget id => `register/schema`, see
	 *                                                sourceKey()) to downgrade server-mode fields.
	 *
	 * @return array{fields: list<array<string,mixed>>, order: list<string>, warnings: list<array<string,string>>}
	 *         The fields as given with `returnType` set (and `evaluationMode` downgraded where needed),
	 *         the dependency order, and one warning per downgraded field.
	 *
	 * @throws CalculatedFieldValidationError When a field is malformed or does not type-check.
	 * @throws CalculatedFieldCycleError      When the fields depend on each other in a cycle.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public function validateAndCompile(array $widget, array $others=[], array $options=[]): array {
		$id     = (string) ($widget['id'] ?? '');
		$fields = $this->declaredFields(fields: $widget['calculatedFields'] ?? []);
		$names  = array_map(static fn (array $field): string => $field['name'], $fields);

		$allowed = $options['allowedFunctions'] ?? null;
		foreach ($fields as $field) {
			try {
				$this->astChecker->check(ast: $field['compiledExpression'], calculatedNames: $names, allowed: $allowed);
			} catch (CalculatedFieldValidationError $error) {
				throw $error->forField(field: $field['name']);
			}
		}

		$neighbours = array_values(
			array_filter($others, static fn ($other): bool => is_array($other) === true && (string) ($other['id'] ?? '') !== $id)
		);
		$order      = $this->detectCycles(widgets: array_merge([['id' => $id, 'calculatedFields' => $fields]], $neighbours));

		$typed = $this->typedFields(id: $id, fields: $fields, order: $order, options: $options);
		if (is_array($options['sources'] ?? null) === false) {
			return ['fields' => $typed, 'order' => $order, 'warnings' => []];
		}

		$downgraded = $this->downgradeCrossSource(id: $id, fields: $typed, sources: $options['sources']);
		return ['fields' => $downgraded['fields'], 'order' => $order, 'warnings' => $downgraded['warnings']];
	}//end validateAndCompile()

	/**
	 * Downgrade to client mode every server-mode field whose inputs do not all
	 * live in the widget's own register and schema (REQ-CALC-007).
	 *
	 * A field in `server` or `both` mode is materialised on the widget's source
	 * schema, so it can only read that schema: a field that references a widget
	 * backed by another register or schema, or one whose widget has no
	 * OpenRegister source, is persisted as `client` with a warning.
	 *
	 * @param string                    $id      The widget id.
	 * @param list<array<string,mixed>> $fields  The widget's fields.
	 * @param array<string,string>      $sources Each widget's `register/schema`, '' when it has none.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>}
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
	 */
	public function downgradeCrossSource(string $id, array $fields, array $sources): array {
		$own         = (string) ($sources[$id] ?? '');
		$warnings    = [];
		$sourceCheck = new CalculatedFieldSources();
		foreach ($fields as $index => $field) {
			$mode = $field['evaluationMode'] ?? 'client';
			if ($mode !== 'server' && $mode !== 'both') {
				continue;
			}

			$reason = $sourceCheck->downgradeReason(id: $id, own: $own, ast: $field['compiledExpression'], sources: $sources);
			if ($reason === null) {
				continue;
			}

			$fields[$index]['evaluationMode'] = 'client';
			$warnings[] = [
				'warning' => 'downgraded_evaluation_mode',
				'field'   => (string) $field['name'],
				'reason'  => $reason."; evaluationMode downgraded from '".$mode."' to 'client'",
			];
		}

		return ['fields' => $fields, 'warnings' => $warnings];
	}//end downgradeCrossSource()

	/**
	 * Decide, before a widget is persisted, which of its server-mode fields
	 * will be materialised on OpenRegister and set their `serverComputedFieldId`
	 * (REQ-CALC-004). A field OpenRegister cannot evaluate alike is downgraded
	 * to client mode with a warning; without OpenRegister every server-mode
	 * field is.
	 *
	 * @param string                    $id     The widget id.
	 * @param list<array<string,mixed>> $fields The validated fields, from validateAndCompile().
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>}
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function planServerFields(string $id, array $fields): array {
		if ($this->materialiser === null) {
			return ['fields' => $fields, 'warnings' => []];
		}

		$plan = $this->materialiser->plan(widgetId: $id, fields: $fields);
		if ($plan['properties'] !== [] && $this->materialiser->isAvailable() === false) {
			$down = $this->materialiser->downgradeAll(fields: $plan['fields'], reason: 'OpenRegister is unavailable');
			return ['fields' => $down['fields'], 'warnings' => array_merge($plan['warnings'], $down['warnings'])];
		}

		return ['fields' => $plan['fields'], 'warnings' => $plan['warnings']];
	}//end planServerFields()

	/**
	 * Write a persisted widget's server-mode fields to its OpenRegister
	 * source schema, replacing the properties the widget owned there, and
	 * remove them from the schema it was backed by before when its source
	 * changed (REQ-CALC-004).
	 *
	 * When the schema cannot be written (OpenRegister refuses the current
	 * user, or the schema is gone) nothing is materialised: every server-mode
	 * field is answered as a client field with a warning, for the caller to
	 * persist.
	 *
	 * @param array<string,mixed> $widget   The widget: `id`, `calculatedFields` from planServerFields(),
	 *                                      `source` (its `register/schema`, see sourceKey()).
	 * @param array<string,mixed> $previous The widget before this save: `source` and `calculatedFields`.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>, changed: bool}
	 *         The fields as they now stand, warnings, and whether they differ from the ones given.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-004-server-side-evaluation-via-openregister-computed-fields
	 */
	public function materializeForOpenRegister(array $widget, array $previous=[]): array {
		$id     = (string) ($widget['id'] ?? '');
		$fields = $this->declaredFields(fields: $widget['calculatedFields'] ?? []);
		$none   = ['fields' => $fields, 'warnings' => [], 'changed' => false];
		if ($this->materialiser === null || $this->materialiser->isAvailable() === false) {
			return $none;
		}

		$schema    = CalculatedFieldMaterialiser::schemaOf(sourceKey: (string) ($widget['source'] ?? ''));
		$oldSchema = CalculatedFieldMaterialiser::schemaOf(sourceKey: (string) ($previous['source'] ?? ''));
		$hadServer = self::hasServerFields(fields: $previous['calculatedFields'] ?? []);
		$warnings  = [];
		if ($hadServer === true && $oldSchema !== '' && $oldSchema !== $schema) {
			$warnings = $this->cleanupServerFields(widget: ['id' => $id, 'source' => (string) $previous['source']]);
		}

		$plan = $this->materialiser->plan(widgetId: $id, fields: $fields);
		if ($schema === '' || ($plan['properties'] === [] && ($hadServer === false || $oldSchema !== $schema))) {
			return ['fields' => $fields, 'warnings' => $warnings, 'changed' => false];
		}

		try {
			$this->materialiser->write(schemaId: $schema, widgetId: $id, properties: $plan['properties']);
		} catch (Throwable) {
			$down = $this->materialiser->downgradeAll(fields: $plan['fields'], reason: 'the source schema could not be updated');
			return [
				'fields'   => $down['fields'],
				'warnings' => array_merge($warnings, $down['warnings']),
				'changed'  => $down['warnings'] !== [],
			];
		}

		return ['fields' => $plan['fields'], 'warnings' => $warnings, 'changed' => $plan['fields'] !== $fields];
	}//end materializeForOpenRegister()

	/**
	 * Remove every OpenRegister property a widget's server-mode fields own on
	 * its source schema, for when the widget is deleted (task 28).
	 *
	 * @param array<string,mixed> $widget The widget: `id` and `source` (its `register/schema`).
	 *
	 * @return list<array<string,string>> A warning when the properties could not be removed.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-28
	 */
	public function cleanupServerFields(array $widget): array {
		$schema = CalculatedFieldMaterialiser::schemaOf(sourceKey: (string) ($widget['source'] ?? ''));
		if ($this->materialiser === null || $schema === '' || $this->materialiser->isAvailable() === false) {
			return [];
		}

		try {
			$this->materialiser->remove(schemaId: $schema, widgetId: (string) ($widget['id'] ?? ''));
		} catch (Throwable) {
			return [
				[
					'warning' => 'server_fields_not_removed',
					'field'   => '',
					'reason'  => 'the calculated properties on the source schema could not be removed',
				],
			];
		}

		return [];
	}//end cleanupServerFields()

	/**
	 * Whether any of the fields was materialised on OpenRegister.
	 *
	 * @param mixed $fields The stored fields.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-28
	 */
	public static function hasServerFields(mixed $fields): bool {
		if (is_array($fields) === false) {
			return false;
		}

		foreach ($fields as $field) {
			if (is_array($field) === true && is_string($field['serverComputedFieldId'] ?? null) === true && $field['serverComputedFieldId'] !== '') {
				return true;
			}
		}

		return false;
	}//end hasServerFields()

	/**
	 * The `register/schema` key of a placement's content.
	 *
	 * @param mixed $content The placement content.
	 *
	 * @return string `register/schema`, or '' when either is missing.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
	 */
	public static function sourceKey(mixed $content): string {
		return (new CalculatedFieldSources())->sourceKey(content: $content);
	}//end sourceKey()

	/**
	 * The calculated fields of a set of widgets in dependency order.
	 *
	 * @param array<int,array<string,mixed>> $widgets The widgets, `{id, calculatedFields}`.
	 *
	 * @return list<string> The fields as `<widgetId>.<name>`, dependencies first.
	 *
	 * @throws CalculatedFieldCycleError When the fields or the widgets form a cycle.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
	 */
	public function detectCycles(array $widgets): array {
		return array_values($this->cycleDetector->assertAcyclic(widgets: $widgets));
	}//end detectCycles()

	/**
	 * Evaluate a compiled expression on a row, for server-side use.
	 *
	 * @param array<string,mixed> $ast     The compiled expression.
	 * @param array<string,mixed> $row     The source row.
	 * @param array<string,mixed> $context Optional `calculated`, `widgets` and `widgetCalculated` values.
	 * @param int|float|null      $nowMs   The moment `now()` reads, in epoch milliseconds; the clock when null.
	 *
	 * @return mixed The value, or null when an input is null.
	 *
	 * @throws EvaluationError When the expression fails on this row.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
	 */
	public function evaluateExpression(array $ast, array $row, array $context=[], int|float|null $nowMs=null): mixed {
		return $this->evaluator->evaluate(ast: $ast, context: array_merge($context, ['row' => $row]), nowMs: $nowMs);
	}//end evaluateExpression()

	/**
	 * The declared fields, refusing a malformed entry or a duplicate name.
	 *
	 * @param mixed $fields The `calculatedFields` value.
	 *
	 * @return list<array<string,mixed>> The fields.
	 *
	 * @throws CalculatedFieldValidationError `invalidField` or `duplicateField`.
	 */
	private function declaredFields(mixed $fields): array {
		if (is_array($fields) === false || array_is_list($fields) === false) {
			throw new CalculatedFieldValidationError(
				detail: ['error' => 'invalidField', 'message' => 'calculatedFields must be a list of fields']
			);
		}

		$seen = [];
		foreach ($fields as $index => $field) {
			if (is_array($field) === false || is_string($field['name'] ?? null) === false || $field['name'] === ''
				|| is_array($field['compiledExpression'] ?? null) === false
			) {
				throw new CalculatedFieldValidationError(
					detail: [
						'error'   => 'invalidField',
						'index'   => $index,
						'message' => 'Calculated field '.($index + 1).' needs a name and a compiled expression',
					]
				);
			}

			if (isset($seen[$field['name']]) === true) {
				throw new CalculatedFieldValidationError(
					detail: [
						'error'   => 'duplicateField',
						'field'   => $field['name'],
						'message' => "The calculated field '".$field['name']."' is declared twice",
					]
				);
			}

			$seen[$field['name']] = true;
		}//end foreach

		return $fields;
	}//end declaredFields()

	/**
	 * Type-check the widget's fields in dependency order, setting `returnType`.
	 *
	 * @param string                    $id      The widget id.
	 * @param list<array<string,mixed>> $fields  The fields.
	 * @param list<string>              $order   The dependency order of all fields.
	 * @param array<string,mixed>       $options `fieldSchema` and `widgetSchemas`.
	 *
	 * @return list<array<string,mixed>> The fields, in their declared order.
	 *
	 * @throws CalculatedFieldValidationError When a field does not type-check.
	 */
	private function typedFields(string $id, array $fields, array $order, array $options): array {
		$byName = [];
		foreach ($fields as $index => $field) {
			$byName[$field['name']] = $index;
		}

		$schema = null;
		if (is_array($options['fieldSchema'] ?? null) === true) {
			$schema = $options['fieldSchema'];
		}

		$widgetSchemas = [];
		if (is_array($options['widgetSchemas'] ?? null) === true) {
			$widgetSchemas = $options['widgetSchemas'];
		}

		$types  = [];
		$prefix = $id.'.';
		foreach ($order as $node) {
			$name = substr($node, strlen($prefix));
			if (str_starts_with($node, $prefix) === false || isset($byName[$name]) === false) {
				continue;
			}

			$index = $byName[$name];
			try {
				$type = $this->typeChecker->inferType(
					ast: $fields[$index]['compiledExpression'],
					fieldSchema: $schema,
					calculatedTypes: $types,
					widgetSchemas: $widgetSchemas
				);
			} catch (CalculatedFieldValidationError $error) {
				throw $error->forField(field: $name);
			}

			$fields[$index]['returnType'] = $type;
			$types[$name] = $type;
		}//end foreach

		return $fields;
	}//end typedFields()
}//end class
