<?php

/**
 * CalculatedFieldMaterialiser
 *
 * Keeps a widget's server-mode calculated fields materialised on its
 * OpenRegister source schema (REQ-CALC-004). Each field in `server` or
 * `both` mode becomes one read-only schema property carrying a JSON-AST
 * `calculation`, which OpenRegister evaluates and stores on every object
 * save, so the value is queryable through the OpenRegister API. The
 * property key is derived from the widget and the field name, which is the
 * field's `serverComputedFieldId`; every key a widget owns shares one
 * prefix, so a save replaces exactly that widget's properties and a delete
 * removes them all, leaving no orphans.
 *
 * OpenRegister is optional: its schema mapper is resolved lazily, and the
 * schema is read and written as the current user, so OpenRegister's own
 * schema access checks decide whether a field may be materialised.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Plans and writes the OpenRegister properties of server-mode fields.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
 */
class CalculatedFieldMaterialiser {

	/**
	 * OpenRegister's schema mapper, held as a string so no OpenRegister symbol loads with this class.
	 */
	private const SCHEMA_MAPPER = 'OCA\\OpenRegister\\Db\\SchemaMapper';

	/**
	 * The prefix every materialised property key starts with.
	 */
	public const KEY_PREFIX = 'launchpad_';

	/**
	 * Constructor.
	 *
	 * @param CalculatedFieldCalculationTranslator $translator The AST translator.
	 * @param ContainerInterface|null              $container  Resolves OpenRegister's schema mapper;
	 *                                                         null when OpenRegister is unavailable.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function __construct(
		private readonly CalculatedFieldCalculationTranslator $translator,
		private readonly ?ContainerInterface $container=null,
	) {
	}//end __construct()

	/**
	 * The prefix of the property keys a widget owns.
	 *
	 * @param string $widgetId The widget id.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public static function widgetPrefix(string $widgetId): string {
		return self::KEY_PREFIX.self::slug(value: $widgetId).'__';
	}//end widgetPrefix()

	/**
	 * The property key of one field, its `serverComputedFieldId`.
	 *
	 * @param string $widgetId The widget id.
	 * @param string $name     The field name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public static function fieldKey(string $widgetId, string $name): string {
		return self::widgetPrefix(widgetId: $widgetId).self::slug(value: $name);
	}//end fieldKey()

	/**
	 * Decide which server-mode fields can be materialised and what they become.
	 *
	 * A field whose expression uses something OpenRegister's engine does not
	 * evaluate alike, whose return type OpenRegister cannot declare, or that
	 * reads a field which is not itself materialised, is downgraded to client
	 * mode with a warning. Materialised fields get their `serverComputedFieldId`;
	 * every other field has it cleared.
	 *
	 * @param string                    $widgetId The widget id.
	 * @param list<array<string,mixed>> $fields   The validated fields (`returnType` set).
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>, properties: array<string,array<string,mixed>>}
	 *         The fields to store, the downgrade warnings, and the properties to write, dependencies first.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-004-server-side-evaluation-via-openregister-computed-fields
	 */
	public function plan(string $widgetId, array $fields): array {
		$candidates = [];
		foreach ($fields as $index => $field) {
			$mode = ($field['evaluationMode'] ?? 'client');
			if (($mode === 'server' || $mode === 'both') && is_string($field['name'] ?? null) === true) {
				$candidates[$field['name']] = $index;
			}
		}

		$warnings = [];
		[$candidates, $expressions, $unsupported] = $this->materialisable(widgetId: $widgetId, fields: $fields, candidates: $candidates);

		foreach ($fields as $index => $field) {
			$name = (string) ($field['name'] ?? '');
			if (isset($candidates[$name]) === true) {
				$fields[$index]['serverComputedFieldId'] = self::fieldKey(widgetId: $widgetId, name: $name);
				continue;
			}

			$fields[$index]['serverComputedFieldId'] = null;
			if (isset($unsupported[$name]) === true) {
				$mode = (string) $field['evaluationMode'];
				$fields[$index]['evaluationMode'] = 'client';
				$warnings[] = $this->downgrade(name: $name, mode: $mode, reason: 'OpenRegister cannot evaluate this expression on the server');
			}
		}

		return [
			'fields'     => array_values($fields),
			'warnings'   => $warnings,
			'properties' => $this->properties(widgetId: $widgetId, fields: $fields, candidates: $candidates, expressions: $expressions),
		];
	}//end plan()

	/**
	 * Downgrade every materialised field of a plan to client mode, for when
	 * the schema could not be written.
	 *
	 * @param list<array<string,mixed>> $fields The planned fields.
	 * @param string                    $reason Why.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>}
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
	 */
	public function downgradeAll(array $fields, string $reason): array {
		$warnings = [];
		foreach ($fields as $index => $field) {
			if (($field['serverComputedFieldId'] ?? null) === null) {
				continue;
			}

			$warnings[] = $this->downgrade(name: (string) $field['name'], mode: (string) $field['evaluationMode'], reason: $reason);
			$fields[$index]['evaluationMode']        = 'client';
			$fields[$index]['serverComputedFieldId'] = null;
		}

		return ['fields' => array_values($fields), 'warnings' => $warnings];
	}//end downgradeAll()

	/**
	 * Whether OpenRegister's schema mapper can be reached.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
	 */
	public function isAvailable(): bool {
		return $this->mapper() !== null;
	}//end isAvailable()

	/**
	 * Replace the properties a widget owns on a schema with the given ones.
	 *
	 * Only keys under the widget's prefix are touched; the schema is written
	 * only when they change.
	 *
	 * @param string                             $schemaId   The schema id, uuid or slug.
	 * @param string                             $widgetId   The widget id.
	 * @param array<string,array<string,mixed>>  $properties The properties to own, from plan().
	 *
	 * @return bool True when the schema was written.
	 *
	 * @throws RuntimeException When OpenRegister is unavailable.
	 * @throws Throwable         What OpenRegister throws when the schema cannot be read or written.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-004-server-side-evaluation-via-openregister-computed-fields
	 */
	public function write(string $schemaId, string $widgetId, array $properties): bool {
		$mapper = $this->mapper();
		if ($mapper === null) {
			throw new RuntimeException(message: 'OpenRegister is unavailable.');
		}

		$schema  = $mapper->find(id: $schemaId);
		$current = $schema->getProperties();
		if (is_array($current) === false) {
			$current = [];
		}

		$prefix = self::widgetPrefix(widgetId: $widgetId);
		$next   = [];
		foreach ($current as $key => $property) {
			if (str_starts_with((string) $key, $prefix) === false) {
				$next[$key] = $property;
			}
		}

		foreach ($properties as $key => $property) {
			$next[$key] = $property;
		}

		if ($next === $current) {
			return false;
		}

		$schema->setProperties($next);
		$mapper->update($schema);
		return true;
	}//end write()

	/**
	 * Remove every property a widget owns from a schema (task 28).
	 *
	 * @param string $schemaId The schema id, uuid or slug.
	 * @param string $widgetId The widget id.
	 *
	 * @return bool True when the schema was written.
	 *
	 * @throws RuntimeException When OpenRegister is unavailable.
	 * @throws Throwable         What OpenRegister throws when the schema cannot be read or written.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-28
	 */
	public function remove(string $schemaId, string $widgetId): bool {
		return $this->write(schemaId: $schemaId, widgetId: $widgetId, properties: []);
	}//end remove()

	/**
	 * Narrow the candidates to the fields that can be materialised: drop
	 * each one that cannot be translated or declared, and repeat while that
	 * drops a field another one reads.
	 *
	 * @param string                    $widgetId   The widget id.
	 * @param list<array<string,mixed>> $fields     The fields.
	 * @param array<string,int>         $candidates Server-mode name => index.
	 *
	 * @return array{0: array<string,int>, 1: array<string,array{type: string, expression: mixed}>, 2: array<string,true>}
	 *         The materialisable candidates, their declarations, and the names dropped.
	 */
	private function materialisable(string $widgetId, array $fields, array $candidates): array {
		$unsupported = [];
		do {
			$dropped     = false;
			$keys        = $this->keysOf(widgetId: $widgetId, names: array_keys($candidates));
			$expressions = [];
			foreach ($candidates as $name => $index) {
				$expression = $this->translator->translate(node: ($fields[$index]['compiledExpression'] ?? null), widgetId: $widgetId, keys: $keys);
				$type       = $this->translator->calculationType(returnType: ($fields[$index]['returnType'] ?? null));
				if ($expression === null || $type === null) {
					unset($candidates[$name]);
					$unsupported[$name] = true;
					$dropped = true;
					continue;
				}

				$expressions[$name] = ['type' => $type, 'expression' => $expression];
			}
		} while ($dropped === true);

		return [$candidates, $expressions, $unsupported];
	}//end materialisable()

	/**
	 * The schema properties of the materialised fields, dependencies first.
	 *
	 * @param string                                                     $widgetId    The widget id.
	 * @param array<int,array<string,mixed>>                             $fields      The fields.
	 * @param array<string,int>                                          $candidates  Materialised name => index.
	 * @param array<string,array{type: string, expression: mixed}>       $expressions Their declarations.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function properties(string $widgetId, array $fields, array $candidates, array $expressions): array {
		$ordered = [];
		$visit   = function (string $name) use (&$visit, &$ordered, $fields, $candidates): void {
			if (isset($ordered[$name]) === true || isset($candidates[$name]) === false) {
				return;
			}

			$ordered[$name] = false;
			foreach ($this->translator->calculatedNames(node: ($fields[$candidates[$name]]['compiledExpression'] ?? null)) as $dependency) {
				$visit($dependency);
			}

			unset($ordered[$name]);
			$ordered[$name] = true;
		};
		foreach (array_keys($candidates) as $name) {
			$visit((string) $name);
		}

		$properties = [];
		foreach (array_keys($ordered) as $name) {
			$field       = $fields[$candidates[$name]];
			$declaration = $expressions[$name];
			$type        = $declaration['type'];
			if ($type === 'date') {
				$type = 'string';
			}

			$property    = [
				'type'        => $type,
				'title'       => (string) ($field['label'] ?? $name),
				'description' => 'Calculated by LaunchPad widget '.$widgetId.': '.(string) ($field['expression'] ?? $name),
				'readOnly'    => true,
				'calculation' => $declaration,
			];
			if ($declaration['type'] === 'date') {
				$property['format'] = 'date-time';
			}

			$properties[self::fieldKey(widgetId: $widgetId, name: (string) $name)] = $property;
		}

		return $properties;
	}//end properties()

	/**
	 * Field name => property key, for the given names.
	 *
	 * @param string             $widgetId The widget id.
	 * @param array<int,string>  $names    The names.
	 *
	 * @return array<string,string>
	 */
	private function keysOf(string $widgetId, array $names): array {
		$keys = [];
		foreach ($names as $name) {
			$keys[(string) $name] = self::fieldKey(widgetId: $widgetId, name: (string) $name);
		}

		return $keys;
	}//end keysOf()

	/**
	 * A downgrade warning.
	 *
	 * @param string $name   The field name.
	 * @param string $mode   Its requested mode.
	 * @param string $reason Why.
	 *
	 * @return array<string,string>
	 */
	private function downgrade(string $name, string $mode, string $reason): array {
		return [
			'warning' => 'downgraded_evaluation_mode',
			'field'   => $name,
			'reason'  => $reason."; evaluationMode downgraded from '".$mode."' to 'client'",
		];
	}//end downgrade()

	/**
	 * OpenRegister's schema mapper, or null when it cannot be resolved.
	 *
	 * @return object|null
	 */
	private function mapper(): ?object {
		if ($this->container === null) {
			return null;
		}

		try {
			$mapper = $this->container->get(self::SCHEMA_MAPPER);
		} catch (Throwable) {
			return null;
		}

		if (is_object($mapper) === false) {
			return null;
		}

		return $mapper;
	}//end mapper()

	/**
	 * A key-safe form of an id or name.
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private static function slug(string $value): string {
		return (string) preg_replace('/[^A-Za-z0-9_]/', '_', $value);
	}//end slug()
}//end class
