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
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;

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
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-23
	 */
	public function __construct(
		private readonly CalculatedFieldAstChecker $astChecker,
		private readonly CalculatedFieldTypeChecker $typeChecker,
		private readonly CalculatedFieldCycleDetector $cycleDetector,
		private readonly CalculatedFieldEvaluator $evaluator,
	) {
	}//end __construct()

	/**
	 * Validate a widget's calculated fields and set each field's `returnType`.
	 *
	 * @param array<string,mixed>            $widget  The widget: `id` and `calculatedFields`, each
	 *                                                `{name, expression, compiledExpression, ...}`.
	 * @param array<int,array<string,mixed>> $others  The other widgets of its dashboard, `{id, calculatedFields}`.
	 * @param array<string,mixed>            $options `allowedFunctions` (null for every built-in),
	 *                                                `fieldSchema` and `widgetSchemas` for field types.
	 *
	 * @return array{fields: list<array<string,mixed>>, order: list<string>} The fields as given with
	 *                                                                         `returnType` set, and the
	 *                                                                         dependency order.
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

		return [
			'fields' => $this->typedFields(id: $id, fields: $fields, order: $order, options: $options),
			'order'  => $order,
		];
	}//end validateAndCompile()

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
