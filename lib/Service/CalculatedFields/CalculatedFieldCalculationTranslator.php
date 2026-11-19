<?php

/**
 * CalculatedFieldCalculationTranslator
 *
 * Translates a calculated field's AST into OpenRegister's JSON-AST
 * calculation expression (`x-openregister-calculations`), the declarative
 * engine OpenRegister materialises at save time (REQ-CALC-004). Only the
 * part of the language both engines evaluate the same way is translated:
 * literals, the widget's own fields, other materialised fields of the same
 * widget, arithmetic, comparison, logic, `if`, and the functions `abs`,
 * `min`, `max` and `coalesce`. Anything else answers null, and the field
 * stays a client-side field.
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

/**
 * AST to OpenRegister calculation expression.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
 */
class CalculatedFieldCalculationTranslator {

	/**
	 * Binary operators and their OpenRegister operator.
	 *
	 * @var array<string,string>
	 */
	private const BINARY = [
		'+'   => '+',
		'-'   => '-',
		'*'   => '*',
		'/'   => '/',
		'%'   => '%',
		'<'   => 'lt',
		'<='  => 'lte',
		'>'   => 'gt',
		'>='  => 'gte',
		'=='  => 'eq',
		'!='  => 'ne',
		'and' => 'and',
		'or'  => 'or',
	];

	/**
	 * Functions both engines evaluate alike, and their OpenRegister operator.
	 *
	 * @var array<string,string>
	 */
	private const FUNCTIONS = [
		'abs'      => 'abs',
		'min'      => 'min',
		'max'      => 'max',
		'coalesce' => 'coalesce',
	];

	/**
	 * Return types OpenRegister declares a calculation with.
	 *
	 * @var array<string,string>
	 */
	private const TYPES = [
		'number'  => 'number',
		'string'  => 'string',
		'boolean' => 'boolean',
		'date'    => 'date',
	];

	/**
	 * The OpenRegister calculation type for a field's `returnType`.
	 *
	 * @param mixed $returnType The field's return type.
	 *
	 * @return string|null The type, or null when OpenRegister has none for it.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function calculationType(mixed $returnType): ?string {
		if (is_string($returnType) === false) {
			return null;
		}

		return (self::TYPES[$returnType] ?? null);
	}//end calculationType()

	/**
	 * Translate an AST.
	 *
	 * @param mixed                $node     The AST node.
	 * @param string               $widgetId The widget the field belongs to.
	 * @param array<string,string> $keys     Calculated-field name => its OpenRegister calculation key,
	 *                                       for the fields of this widget that are materialised.
	 *
	 * @return mixed The OpenRegister expression, or null when the AST cannot be translated.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function translate(mixed $node, string $widgetId, array $keys): mixed {
		if (is_array($node) === false) {
			return null;
		}

		return match ($node['type'] ?? null) {
			'literal'       => $this->literal(node: $node),
			'fieldRef'      => $this->fieldRef(node: $node, widgetId: $widgetId),
			'calculatedRef' => $this->calculatedRef(node: $node, keys: $keys),
			'binary'        => $this->binary(node: $node, widgetId: $widgetId, keys: $keys),
			'unary'         => $this->unary(node: $node, widgetId: $widgetId, keys: $keys),
			'if'            => $this->conditional(node: $node, widgetId: $widgetId, keys: $keys),
			'call'          => $this->call(node: $node, widgetId: $widgetId, keys: $keys),
			default         => null,
		};
	}//end translate()

	/**
	 * The calculated-field names an AST reads.
	 *
	 * @param mixed $node The AST node.
	 *
	 * @return list<string> The names, each once.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function calculatedNames(mixed $node): array {
		if (is_array($node) === false) {
			return [];
		}

		if (($node['type'] ?? null) === 'calculatedRef' && is_string($node['name'] ?? null) === true) {
			return [$node['name']];
		}

		$names = [];
		foreach (['left', 'right', 'operand', 'cond', 'then', 'else'] as $child) {
			if (isset($node[$child]) === true) {
				$names = array_merge($names, $this->calculatedNames(node: $node[$child]));
			}
		}

		if (is_array($node['args'] ?? null) === true) {
			foreach ($node['args'] as $arg) {
				$names = array_merge($names, $this->calculatedNames(node: $arg));
			}
		}

		return array_values(array_unique($names));
	}//end calculatedNames()

	/**
	 * A literal. A string starting with `$` is left out: OpenRegister would read it as a placeholder.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return array<string,mixed>|null
	 */
	private function literal(array $node): ?array {
		$value = ($node['value'] ?? null);
		if (is_string($value) === true && str_starts_with($value, '$') === true) {
			return null;
		}

		if ($value !== null && is_scalar($value) === false) {
			return null;
		}

		return ['lit' => $value];
	}//end literal()

	/**
	 * A field of the widget's own source object.
	 *
	 * @param array<string,mixed> $node     The node.
	 * @param string              $widgetId The widget id.
	 *
	 * @return array<string,string>|null
	 */
	private function fieldRef(array $node, string $widgetId): ?array {
		$source = ($node['source'] ?? null);
		$path   = ($node['path'] ?? null);
		if (is_string($path) === false || $path === '' || str_starts_with($path, '@') === true) {
			return null;
		}

		if ($source !== 'self' && $source !== 'widget:'.$widgetId) {
			return null;
		}

		return ['prop' => $path];
	}//end fieldRef()

	/**
	 * Another materialised field of the same widget, read back from the object.
	 *
	 * @param array<string,mixed>  $node The node.
	 * @param array<string,string> $keys The materialised fields' keys.
	 *
	 * @return array<string,string>|null
	 */
	private function calculatedRef(array $node, array $keys): ?array {
		$name = ($node['name'] ?? null);
		if (is_string($name) === false || isset($keys[$name]) === false) {
			return null;
		}

		return ['prop' => $keys[$name]];
	}//end calculatedRef()

	/**
	 * A binary operator.
	 *
	 * @param array<string,mixed>  $node     The node.
	 * @param string               $widgetId The widget id.
	 * @param array<string,string> $keys     The materialised fields' keys.
	 *
	 * @return array<string,mixed>|null
	 */
	private function binary(array $node, string $widgetId, array $keys): ?array {
		$op = (self::BINARY[(string) ($node['op'] ?? '')] ?? null);
		if ($op === null) {
			return null;
		}

		$left  = $this->translate(node: ($node['left'] ?? null), widgetId: $widgetId, keys: $keys);
		$right = $this->translate(node: ($node['right'] ?? null), widgetId: $widgetId, keys: $keys);
		if ($left === null || $right === null) {
			return null;
		}

		return [$op => [$left, $right]];
	}//end binary()

	/**
	 * Negation or `not`.
	 *
	 * @param array<string,mixed>  $node     The node.
	 * @param string               $widgetId The widget id.
	 * @param array<string,string> $keys     The materialised fields' keys.
	 *
	 * @return array<string,mixed>|null
	 */
	private function unary(array $node, string $widgetId, array $keys): ?array {
		$operand = $this->translate(node: ($node['operand'] ?? null), widgetId: $widgetId, keys: $keys);
		if ($operand === null) {
			return null;
		}

		return match ($node['op'] ?? null) {
			'-'     => ['-' => [$operand]],
			'not'   => ['not' => [$operand]],
			default => null,
		};
	}//end unary()

	/**
	 * A conditional.
	 *
	 * @param array<string,mixed>  $node     The node.
	 * @param string               $widgetId The widget id.
	 * @param array<string,string> $keys     The materialised fields' keys.
	 *
	 * @return array<string,mixed>|null
	 */
	private function conditional(array $node, string $widgetId, array $keys): ?array {
		$parts = [];
		foreach (['cond', 'then', 'else'] as $part) {
			$parts[] = $this->translate(node: ($node[$part] ?? null), widgetId: $widgetId, keys: $keys);
		}

		if (in_array(null, $parts, true) === true) {
			return null;
		}

		return ['if' => $parts];
	}//end conditional()

	/**
	 * A function call both engines evaluate alike.
	 *
	 * @param array<string,mixed>  $node     The node.
	 * @param string               $widgetId The widget id.
	 * @param array<string,string> $keys     The materialised fields' keys.
	 *
	 * @return array<string,mixed>|null
	 */
	private function call(array $node, string $widgetId, array $keys): ?array {
		$op   = (self::FUNCTIONS[(string) ($node['name'] ?? '')] ?? null);
		$args = ($node['args'] ?? null);
		if ($op === null || is_array($args) === false || $args === []) {
			return null;
		}

		$translated = [];
		foreach ($args as $arg) {
			$value = $this->translate(node: $arg, widgetId: $widgetId, keys: $keys);
			if ($value === null) {
				return null;
			}

			$translated[] = $value;
		}

		return [$op => $translated];
	}//end call()
}//end class
