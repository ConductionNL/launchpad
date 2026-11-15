<?php

/**
 * CalculatedFieldEvaluator
 *
 * Evaluates a calculated field's compiled AST (the JSON the client-side parser
 * produces) on one row, server side. It mirrors evaluate() in
 * src/utils/calculatedFields/evaluator.js node for node: literals, field and
 * calculated references (including another widget's), unary and binary
 * operators, conditionals and the allowlisted functions, with null
 * propagation and the same failures. The shared suite
 * tests/fixtures/calculated-fields/shared-evaluator-test-suite.json holds both
 * evaluators to the same answers.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues as V;

/**
 * The server-side evaluator of calculated fields.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) CalculatedFieldValues holds pure value rules shared with the functions.
 * @SuppressWarnings(PHPMD.CyclomaticComplexity) applyBinary() is one case per operator, as the client's table.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
class CalculatedFieldEvaluator {

	/**
	 * The evaluation context of the current run.
	 *
	 * @var array{row: mixed, calculated: array<string, mixed>, widgets: array<string, mixed>, widgetCalculated: array<string, mixed>, now: int|float}
	 */
	private array $env = ['row' => [], 'calculated' => [], 'widgets' => [], 'widgetCalculated' => [], 'now' => 0];

	/**
	 * Evaluate an AST on a context.
	 *
	 * @param array<string, mixed>     $ast     The compiled expression.
	 * @param array<string, mixed>     $context `row`, and optionally `calculated`, `widgets` and `widgetCalculated`.
	 * @param int|float|null           $nowMs   The moment `now()` and `today()` read, in epoch milliseconds; the clock when null.
	 *
	 * @return mixed The value, or null when an input is null.
	 *
	 * @throws EvaluationError When the expression fails on these inputs.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
	 */
	public function evaluate(array $ast, array $context = [], int|float|null $nowMs = null): mixed {
		$this->env = [
			'row' => ($context['row'] ?? []),
			'calculated' => (array)($context['calculated'] ?? []),
			'widgets' => (array)($context['widgets'] ?? []),
			'widgetCalculated' => (array)($context['widgetCalculated'] ?? []),
			'now' => ($nowMs ?? (microtime(true) * 1000)),
		];

		return $this->run(node: $ast);
	}//end evaluate()

	/**
	 * Evaluate a field on a row, catching a failure as the client does: the
	 * value, or the error with the expression and the inputs it read.
	 *
	 * @param array<string, mixed> $ast        The compiled expression.
	 * @param string               $expression The expression as written.
	 * @param array<string, mixed> $context    The evaluation context.
	 * @param int|float|null       $nowMs      The moment, in epoch milliseconds.
	 *
	 * @return array{value: mixed, error: string|null, expression: string}
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-008-inline-error-display-preserves-dashboard-usability
	 */
	public function evaluateField(array $ast, string $expression, array $context = [], int|float|null $nowMs = null): array {
		try {
			return ['value' => $this->evaluate(ast: $ast, context: $context, nowMs: $nowMs), 'error' => null, 'expression' => $expression];
		} catch (EvaluationError $error) {
			return ['value' => null, 'error' => $error->getMessage(), 'expression' => $expression];
		}
	}//end evaluateField()

	/**
	 * Evaluate one node.
	 *
	 * @param mixed $node The node.
	 *
	 * @return mixed
	 */
	private function run(mixed $node): mixed {
		$type = '';
		if (is_array($node) === true) {
			$type = (string)($node['type'] ?? '');
		}

		return match ($type) {
			'literal' => ($node['value'] ?? null),
			'fieldRef' => $this->fieldValue(node: $node),
			'calculatedRef' => $this->calculatedValue(name: (string)($node['name'] ?? '')),
			'unary' => $this->unaryValue(node: $node),
			'binary' => $this->binaryValue(node: $node),
			'if' => $this->ifValue(node: $node),
			'call' => $this->callValue(node: $node),
			default => throw new EvaluationError("unknown node '" . $type . "'"),
		};
	}//end run()

	/**
	 * A field of the row, or of another widget (its calculated value first).
	 *
	 * @param array<string, mixed> $node The fieldRef node.
	 *
	 * @return mixed
	 */
	private function fieldValue(array $node): mixed {
		$source = (string)($node['source'] ?? 'self');
		$path = (string)($node['path'] ?? '');
		if ($source === 'self') {
			return V::readPath(value: $this->env['row'], path: $path);
		}

		$id = substr($source, strlen('widget:'));
		$calculated = ($this->env['widgetCalculated'][$id] ?? null);
		if (is_array($calculated) === true && array_key_exists($path, $calculated) === true) {
			return $calculated[$path];
		}

		return V::readPath(value: ($this->env['widgets'][$id] ?? null), path: $path);
	}//end fieldValue()

	/**
	 * Another calculated field of the same widget, or null before it has a value.
	 *
	 * @param string $name The field.
	 *
	 * @return mixed
	 */
	private function calculatedValue(string $name): mixed {
		return ($this->env['calculated'][$name] ?? null);
	}//end calculatedValue()

	/**
	 * A boolean, or the client's "needs a boolean" failure.
	 *
	 * @param mixed  $value The value.
	 * @param string $what  The operator.
	 *
	 * @return bool
	 */
	private static function bool(mixed $value, string $what): bool {
		if (is_bool($value) === false) {
			throw new EvaluationError("'" . $what . "' needs a boolean");
		}

		return $value;
	}//end bool()

	/**
	 * @param array<string, mixed> $node The unary node.
	 *
	 * @return mixed
	 */
	private function unaryValue(array $node): mixed {
		$value = $this->run(node: $node['operand'] ?? null);
		if ($value === null) {
			return null;
		}

		if (($node['op'] ?? '') === 'not') {
			return !self::bool(value: $value, what: 'not');
		}

		return -V::num(value: $value, what: '-');
	}//end unaryValue()

	/**
	 * @param array<string, mixed> $node The binary node.
	 *
	 * @return mixed
	 */
	private function binaryValue(array $node): mixed {
		$left = $this->run(node: $node['left'] ?? null);
		$right = $this->run(node: $node['right'] ?? null);
		if ($left === null || $right === null) {
			return null;
		}

		$op = (string)($node['op'] ?? '');
		return match ($op) {
			'and' => (self::bool(value: $left, what: 'and') && self::bool(value: $right, what: 'and')),
			'or' => (self::bool(value: $left, what: 'or') || self::bool(value: $right, what: 'or')),
			default => self::applyBinary(op: $op, left: $left, right: $right),
		};
	}//end binaryValue()

	/**
	 * An arithmetic, equality or ordering operator on two non-null values.
	 *
	 * @param string $op    The operator.
	 * @param mixed  $left  The left value.
	 * @param mixed  $right The right value.
	 *
	 * @return mixed
	 */
	private static function applyBinary(string $op, mixed $left, mixed $right): mixed {
		switch ($op) {
			case '+':
				return V::finite(value: V::num(value: $left, what: '+') + V::num(value: $right, what: '+'));
			case '-':
				return V::finite(value: V::num(value: $left, what: '-') - V::num(value: $right, what: '-'));
			case '*':
				return V::finite(value: V::num(value: $left, what: '*') * V::num(value: $right, what: '*'));
			case '/':
				$divisor = self::divisor(value: $right, op: '/');
				return V::finite(value: V::num(value: $left, what: '/') / $divisor);
			case '%':
				$divisor = self::divisor(value: $right, op: '%');
				$dividend = V::num(value: $left, what: '%');
				if (is_int($dividend) === true && is_int($divisor) === true) {
					return ($dividend % $divisor);
				}

				return fmod((float)$dividend, (float)$divisor);
			case '==':
				return V::same(left: $left, right: $right);
			case '!=':
				return !V::same(left: $left, right: $right);
		}

		return self::compare(op: $op, left: $left, right: $right);
	}//end applyBinary()

	/**
	 * A divisor that is not zero.
	 *
	 * @param mixed  $value The divisor.
	 * @param string $op    The operator.
	 *
	 * @return int|float
	 */
	private static function divisor(mixed $value, string $op): int|float {
		if (V::same(left: V::num(value: $value, what: $op), right: 0) === true) {
			throw new EvaluationError('division by zero');
		}

		return $value;
	}//end divisor()

	/**
	 * An ordering of two numbers or two strings (ISO dates are strings).
	 *
	 * @param string $op    The operator.
	 * @param mixed  $left  The left value.
	 * @param mixed  $right The right value.
	 *
	 * @return bool
	 */
	private static function compare(string $op, mixed $left, mixed $right): bool {
		$numbers = (V::isNumber(value: $left) === true && V::isNumber(value: $right) === true);
		$strings = (is_string($left) === true && is_string($right) === true);
		if ($numbers === false && $strings === false) {
			throw new EvaluationError("'" . $op . "' needs two numbers, two strings or two dates");
		}

		$order = strcmp((string)$left, (string)$right);
		if ($numbers === true) {
			$order = ((float)$left <=> (float)$right);
		}

		return match ($op) {
			'<' => $order < 0,
			'<=' => $order <= 0,
			'>' => $order > 0,
			'>=' => $order >= 0,
			default => throw new EvaluationError("unknown operator '" . $op . "'"),
		};
	}//end compare()

	/**
	 * @param array<string, mixed> $node The if node.
	 *
	 * @return mixed
	 */
	private function ifValue(array $node): mixed {
		$condition = $this->run(node: $node['cond'] ?? null);
		if ($condition === null) {
			return null;
		}

		if (self::bool(value: $condition, what: 'if') === true) {
			return $this->run(node: $node['then'] ?? null);
		}

		return $this->run(node: $node['else'] ?? null);
	}//end ifValue()

	/**
	 * @param array<string, mixed> $node The call node.
	 *
	 * @return mixed
	 */
	private function callValue(array $node): mixed {
		$name = (string)($node['name'] ?? '');
		if (CalculatedFieldFunctions::has(name: $name) === false) {
			throw new EvaluationError("unknown function '" . $name . "'");
		}

		$args = array_map(fn (mixed $arg): mixed => $this->run(node: $arg), (array)($node['args'] ?? []));
		if (in_array($name, CalculatedFieldFunctions::NULL_AWARE, true) === false && in_array(null, $args, true) === true) {
			return null;
		}

		return CalculatedFieldFunctions::call(name: $name, args: $args, nowMs: $this->env['now']);
	}//end callValue()
}//end class
