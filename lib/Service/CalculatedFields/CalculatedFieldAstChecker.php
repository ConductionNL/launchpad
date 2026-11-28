<?php

/**
 * CalculatedFieldAstChecker
 *
 * Checks a stored calculated-field AST (`compiledExpression`) at save time,
 * node for node as `isAstNode()` in `src/utils/calculatedFields/ast.js` and
 * the call checks of `src/utils/calculatedFields/parser.js`: every node has
 * exactly its documented keys, every call names a built-in function that the
 * instance allows, with an argument count the function takes. The server
 * does not re-parse the source text (design D3): the AST is the source of
 * truth, so a client that sends a hand-made AST is held to the same rules.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

/**
 * Structural, allowlist and arity checks of a calculated-field AST.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */
class CalculatedFieldAstChecker {

	/**
	 * The keys of each node type, besides `type`.
	 *
	 * @var array<string,list<string>>
	 */
	public const NODE_KEYS = [
		'literal'       => ['value', 'valueType'],
		'fieldRef'      => ['path', 'source'],
		'calculatedRef' => ['name'],
		'binary'        => ['left', 'op', 'right'],
		'unary'         => ['op', 'operand'],
		'if'            => ['cond', 'else', 'then'],
		'call'          => ['args', 'name'],
	];

	/**
	 * The binary operators.
	 *
	 * @var list<string>
	 */
	public const BINARY_OPS = ['+', '-', '*', '/', '%', '<', '<=', '>', '>=', '==', '!=', 'and', 'or'];

	/**
	 * The fewest and most arguments of each built-in function; null for no limit.
	 *
	 * @var array<string,array{0:int,1:int|null}>
	 */
	public const ARITY = [
		'abs'             => [1, 1],
		'round'           => [1, 2],
		'ceil'            => [1, 1],
		'floor'           => [1, 1],
		'min'             => [1, null],
		'max'             => [1, null],
		'pow'             => [2, 2],
		'sqrt'            => [1, 1],
		'log'             => [1, 2],
		'sum'             => [1, null],
		'avg'             => [1, null],
		'count'           => [1, 1],
		'countIf'         => [2, 2],
		'sumIf'           => [3, 3],
		'avgIf'           => [3, 3],
		'now'             => [0, 0],
		'today'           => [0, 0],
		'dateDiff'        => [2, 3],
		'dateAdd'         => [3, 3],
		'formatDate'      => [2, 2],
		'parseDate'       => [1, 2],
		'durationBetween' => [2, 2],
		'durationFormat'  => [1, 2],
		'concat'          => [1, null],
		'upper'           => [1, 1],
		'lower'           => [1, 1],
		'trim'            => [1, 1],
		'contains'        => [2, 2],
		'startsWith'      => [2, 2],
		'endsWith'        => [2, 2],
		'replace'         => [3, 3],
		'format'          => [1, null],
		'coalesce'        => [1, null],
		'ifNull'          => [2, 2],
		'case'            => [2, null],
		'toNumber'        => [1, 1],
		'toString'        => [1, 1],
		'toBool'          => [1, 1],
		'toDate'          => [1, 1],
	];

	/**
	 * Check one AST.
	 *
	 * @param mixed             $ast             The compiled expression.
	 * @param list<string>      $calculatedNames The calculated fields of the widget, which a `calculatedRef` may name.
	 * @param list<string>|null $allowed         The allowed functions; null allows every built-in.
	 *
	 * @return void
	 *
	 * @throws CalculatedFieldValidationError `invalidExpression`, `unknownCalculatedField`, `unknownFunction`,
	 *                                        `functionNotAllowed` or `arityMismatch`.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
	 */
	public function check(mixed $ast, array $calculatedNames=[], ?array $allowed=null): void {
		if ($this->isNode(node: $ast) === false) {
			throw new CalculatedFieldValidationError(
				detail: ['error' => 'invalidExpression', 'message' => 'The compiled expression is not a valid expression tree']
			);
		}

		$this->checkNode(node: $ast, calculatedNames: $calculatedNames, allowed: $allowed);
	}//end check()

	/**
	 * Whether a value is a well-formed node, its children included.
	 *
	 * @param mixed $node The value.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
	 */
	public function isNode(mixed $node): bool {
		if (is_array($node) === false || array_is_list($node) === true) {
			return false;
		}

		$type = $node['type'] ?? null;
		if (is_string($type) === false || array_key_exists($type, self::NODE_KEYS) === false) {
			return false;
		}

		$keys = array_values(array_filter(array_keys($node), static fn ($key): bool => $key !== 'type'));
		sort($keys);
		if ($keys !== self::NODE_KEYS[$type]) {
			return false;
		}

		return $this->hasValidParts(node: $node);
	}//end isNode()

	/**
	 * Whether a node with the right keys holds valid values and children.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return bool
	 */
	private function hasValidParts(array $node): bool {
		return match ($node['type']) {
			'literal'       => self::isLiteral(node: $node),
			'fieldRef'      => self::isFieldRef(node: $node),
			'calculatedRef' => is_string($node['name']) === true && $node['name'] !== '',
			'binary'        => in_array($node['op'], self::BINARY_OPS, true) === true
				&& $this->isNode(node: $node['left']) === true && $this->isNode(node: $node['right']) === true,
			'unary'         => ($node['op'] === '-' || $node['op'] === 'not') && $this->isNode(node: $node['operand']) === true,
			'if'            => $this->isNode(node: $node['cond']) === true && $this->isNode(node: $node['then']) === true
				&& $this->isNode(node: $node['else']) === true,
			default         => $this->isCall(node: $node),
		};
	}//end hasValidParts()

	/**
	 * Whether a literal node holds a value of its declared type.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return bool
	 */
	private static function isLiteral(array $node): bool {
		return match ($node['valueType']) {
			'number'  => is_int($node['value']) === true || is_float($node['value']) === true,
			'string'  => is_string($node['value']) === true,
			'boolean' => is_bool($node['value']) === true,
			'null'    => $node['value'] === null,
			default   => false,
		};
	}//end isLiteral()

	/**
	 * Whether a field reference names its own row or another widget, and a path.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return bool
	 */
	private static function isFieldRef(array $node): bool {
		$source = $node['source'];
		if (is_string($source) === false || ($source !== 'self' && str_starts_with($source, 'widget:') === false)) {
			return false;
		}

		return is_string($node['path']) === true && $node['path'] !== '';
	}//end isFieldRef()

	/**
	 * Whether a call node names a function and holds a list of nodes.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return bool
	 */
	private function isCall(array $node): bool {
		if (is_string($node['name']) === false || is_array($node['args']) === false || array_is_list($node['args']) === false) {
			return false;
		}

		foreach ($node['args'] as $arg) {
			if ($this->isNode(node: $arg) === false) {
				return false;
			}
		}

		return true;
	}//end isCall()

	/**
	 * Check the calls and calculated references of a well-formed node.
	 *
	 * @param array<string,mixed> $node            The node.
	 * @param list<string>        $calculatedNames The widget's calculated fields.
	 * @param list<string>|null   $allowed         The allowed functions.
	 *
	 * @return void
	 *
	 * @throws CalculatedFieldValidationError On an unknown name, a disallowed function or a wrong argument count.
	 */
	private function checkNode(array $node, array $calculatedNames, ?array $allowed): void {
		if ($node['type'] === 'calculatedRef' && in_array($node['name'], $calculatedNames, true) === false) {
			throw new CalculatedFieldValidationError(
				detail: [
					'error'   => 'unknownCalculatedField',
					'name'    => $node['name'],
					'message' => "Unknown calculated field '".$node['name']."'",
				]
			);
		}

		if ($node['type'] === 'call') {
			self::checkCall(name: $node['name'], count: count($node['args']), allowed: $allowed);
		}

		foreach (self::childrenOf(node: $node) as $child) {
			$this->checkNode(node: $child, calculatedNames: $calculatedNames, allowed: $allowed);
		}
	}//end checkNode()

	/**
	 * Refuse an unknown or disallowed function, or a wrong argument count.
	 *
	 * @param string            $name    The function.
	 * @param int               $count   The number of arguments.
	 * @param list<string>|null $allowed The allowed functions.
	 *
	 * @return void
	 *
	 * @throws CalculatedFieldValidationError `unknownFunction`, `functionNotAllowed` or `arityMismatch`.
	 */
	private static function checkCall(string $name, int $count, ?array $allowed): void {
		if (array_key_exists($name, self::ARITY) === false) {
			throw new CalculatedFieldValidationError(
				detail: ['error' => 'unknownFunction', 'name' => $name, 'message' => "Unknown function '".$name."'"]
			);
		}

		if ($allowed !== null && in_array($name, $allowed, true) === false) {
			throw new CalculatedFieldValidationError(
				detail: [
					'error'   => 'functionNotAllowed',
					'name'    => $name,
					'message' => "The function '".$name."' is not allowed on this instance",
				]
			);
		}

		[$min, $max] = self::ARITY[$name];
		if ($count >= $min && ($max === null || $count <= $max)) {
			return;
		}

		throw new CalculatedFieldValidationError(
			detail: [
				'error'   => 'arityMismatch',
				'name'    => $name,
				'message' => "'".$name."' takes ".self::arityText(min: $min, max: $max).' argument(s), got '.$count,
			]
		);
	}//end checkCall()

	/**
	 * How many arguments a function takes, in words.
	 *
	 * @param int      $min The fewest.
	 * @param int|null $max The most; null for no limit.
	 *
	 * @return string
	 */
	private static function arityText(int $min, ?int $max): string {
		if ($max === null) {
			return 'at least '.$min;
		}

		if ($min === $max) {
			return (string) $min;
		}

		return $min.' to '.$max;
	}//end arityText()

	/**
	 * The child nodes of a node.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function childrenOf(array $node): array {
		return match ($node['type']) {
			'binary' => [$node['left'], $node['right']],
			'unary'  => [$node['operand']],
			'if'     => [$node['cond'], $node['then'], $node['else']],
			'call'   => $node['args'],
			default  => [],
		};
	}//end childrenOf()
}//end class
