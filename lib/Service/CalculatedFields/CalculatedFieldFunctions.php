<?php

/**
 * CalculatedFieldFunctions
 *
 * The allowlisted functions of a calculated field, server side: math,
 * aggregates, dates and durations, text, null handling and conversion. Each
 * mirrors IMPLEMENTATIONS in src/utils/calculatedFields/evaluator.js, with the
 * same failures and the same messages, so a field gives the same answer on
 * either side.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues as V;

/**
 * The function library of the server-side evaluator.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) CalculatedFieldValues holds pure value rules shared with the functions.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) One method per allowlisted function, as the client keeps them.
 * @SuppressWarnings(PHPMD.TooManyMethods) One private method per allowlisted function.
 * @SuppressWarnings(PHPMD.UnusedPrivateMethod) call() reaches each function's method by name through METHODS.
 */
final class CalculatedFieldFunctions {

	/**
	 * Functions that receive null arguments instead of answering null.
	 */
	public const NULL_AWARE = ['coalesce', 'ifNull', 'case', 'count', 'countIf', 'sumIf', 'avgIf'];

	/**
	 * Function names and the method that implements each.
	 */
	private const METHODS = [
		'abs' => 'abs', 'round' => 'round', 'ceil' => 'ceil', 'floor' => 'floor', 'min' => 'min', 'max' => 'max',
		'pow' => 'pow', 'sqrt' => 'sqrt', 'log' => 'log', 'sum' => 'sum', 'avg' => 'avg',
		'count' => 'count', 'countIf' => 'countIf', 'sumIf' => 'sumIf', 'avgIf' => 'avgIf',
		'now' => 'now', 'today' => 'today', 'dateDiff' => 'dateDiff', 'dateAdd' => 'dateAdd',
		'formatDate' => 'formatDate', 'parseDate' => 'parseDate',
		'durationBetween' => 'durationBetween', 'durationFormat' => 'durationFormat',
		'concat' => 'concat', 'upper' => 'upper', 'lower' => 'lower', 'trim' => 'trim', 'contains' => 'contains',
		'startsWith' => 'startsWith', 'endsWith' => 'endsWith', 'replace' => 'replace', 'format' => 'format',
		'coalesce' => 'coalesce', 'ifNull' => 'ifNull', 'case' => 'caseOf',
		'toNumber' => 'toNumber', 'toString' => 'toText', 'toBool' => 'toBool', 'toDate' => 'toDate',
	];

	private const PATTERN_TOKENS = '/yyyy|MM|dd|HH|mm|ss/';

	/**
	 * Whether a function is implemented.
	 *
	 * @param string $name The function.
	 *
	 * @return bool
	 */
	public static function has(string $name): bool {
		return array_key_exists($name, self::METHODS);
	}//end has()

	/**
	 * Run one function.
	 *
	 * @param string            $name  The function.
	 * @param array<int, mixed> $args  Its evaluated arguments.
	 * @param int|float         $nowMs The evaluation moment in epoch milliseconds.
	 *
	 * @return mixed
	 *
	 * @throws EvaluationError When the function is unknown or fails.
	 */
	public static function call(string $name, array $args, int|float $nowMs): mixed {
		if (self::has(name: $name) === false) {
			throw new EvaluationError("unknown function '" . $name . "'");
		}

		$method = self::METHODS[$name];
		if ($method === 'now' || $method === 'today') {
			return self::$method($nowMs);
		}

		return self::$method($args);
	}//end call()

	/**
	 * @param array<int, mixed> $args [x]
	 *
	 * @return int|float
	 */
	private static function abs(array $args): int|float {
		return abs(V::num(value: $args[0] ?? null, what: 'abs'));
	}//end abs()

	/**
	 * Half away from zero to a number of digits, as the client rounds.
	 *
	 * @param array<int, mixed> $args [x, digits = 0]
	 *
	 * @return int|float
	 */
	private static function round(array $args): int|float {
		$value = V::num(value: $args[0] ?? null, what: 'round');
		$digits = V::num(value: ($args[1] ?? 0), what: 'round');
		$factor = (10 ** V::trunc(value: $digits));
		$scaled = (float)sprintf('%.15g', (abs($value) * $factor));
		$rounded = (round($scaled) / $factor);

		if ($value < 0) {
			$rounded = -$rounded;
		}

		return V::finite(value: $rounded);
	}//end round()

	/**
	 * @param array<int, mixed> $args [x]
	 *
	 * @return float
	 */
	private static function ceil(array $args): float {
		return ceil(V::num(value: $args[0] ?? null, what: 'ceil'));
	}//end ceil()

	/**
	 * @param array<int, mixed> $args [x]
	 *
	 * @return float
	 */
	private static function floor(array $args): float {
		return floor(V::num(value: $args[0] ?? null, what: 'floor'));
	}//end floor()

	/**
	 * The numbers of the arguments, a list argument spread without its nulls.
	 *
	 * @param array<int, mixed> $args The arguments.
	 * @param string            $what The function asking.
	 *
	 * @return array<int, int|float>
	 */
	private static function numbersOf(array $args, string $what): array {
		$numbers = [];
		foreach ($args as $arg) {
			$values = [$arg];
			if (V::isList(value: $arg) === true) {
				$values = array_filter($arg, static fn (mixed $item): bool => $item !== null);
			}

			foreach ($values as $item) {
				$numbers[] = V::num(value: $item, what: $what);
			}
		}

		return $numbers;
	}//end numbersOf()

	/**
	 * @param array<int, mixed> $args Numbers or lists.
	 *
	 * @return int|float|null
	 */
	private static function min(array $args): int|float|null {
		$numbers = self::numbersOf(args: $args, what: 'min');
		if ($numbers === []) {
			return null;
		}

		return min($numbers);
	}//end min()

	/**
	 * @param array<int, mixed> $args Numbers or lists.
	 *
	 * @return int|float|null
	 */
	private static function max(array $args): int|float|null {
		$numbers = self::numbersOf(args: $args, what: 'max');
		if ($numbers === []) {
			return null;
		}

		return max($numbers);
	}//end max()

	/**
	 * @param array<int, mixed> $args [x, y]
	 *
	 * @return int|float
	 */
	private static function pow(array $args): int|float {
		$base = (float)V::num(value: $args[0] ?? null, what: 'pow');
		$exponent = (float)V::num(value: $args[1] ?? null, what: 'pow');

		return V::finite(value: $base ** $exponent);
	}//end pow()

	/**
	 * @param array<int, mixed> $args [x]
	 *
	 * @return float
	 */
	private static function sqrt(array $args): float {
		$value = V::num(value: $args[0] ?? null, what: 'sqrt');
		if ($value < 0) {
			throw new EvaluationError('square root of a negative number');
		}

		return sqrt($value);
	}//end sqrt()

	/**
	 * @param array<int, mixed> $args [x, base?]
	 *
	 * @return float
	 */
	private static function log(array $args): float {
		$value = V::num(value: $args[0] ?? null, what: 'log');
		if ($value <= 0) {
			throw new EvaluationError('logarithm of a number that is not positive');
		}

		if (array_key_exists(1, $args) === false) {
			return log($value);
		}

		$base = V::num(value: $args[1], what: 'log');
		if ($base <= 0 || V::same(left: $base, right: 1) === true) {
			throw new EvaluationError('invalid logarithm base');
		}

		return (log($value) / log($base));
	}//end log()

	/**
	 * @param array<int, mixed> $args Numbers or lists.
	 *
	 * @return int|float
	 */
	private static function sum(array $args): int|float {
		return V::finite(value: array_sum(self::numbersOf(args: $args, what: 'sum')));
	}//end sum()

	/**
	 * @param array<int, mixed> $args Numbers or lists.
	 *
	 * @return int|float|null
	 */
	private static function avg(array $args): int|float|null {
		$numbers = self::numbersOf(args: $args, what: 'avg');
		if ($numbers === []) {
			return null;
		}

		return V::finite(value: array_sum($numbers) / count($numbers));
	}//end avg()

	/**
	 * @param array<int, mixed> $args [items]
	 *
	 * @return int
	 */
	private static function count(array $args): int {
		$items = ($args[0] ?? null);
		if ($items === null) {
			return 0;
		}

		return count(array_filter(V::list(value: $items, what: 'count'), static fn (mixed $item): bool => $item !== null));
	}//end count()

	/**
	 * @param array<int, mixed> $args [items, value]
	 *
	 * @return int
	 */
	private static function countIf(array $args): int {
		$items = ($args[0] ?? null);
		if ($items === null) {
			return 0;
		}

		$value = ($args[1] ?? null);
		$matches = array_filter(
			V::list(value: $items, what: 'countIf'),
			static fn (mixed $item): bool => $item !== null && V::same(left: $item, right: $value) === true
		);

		return count($matches);
	}//end countIf()

	/**
	 * The values whose key, at the same position, is the asked value.
	 *
	 * @param array<int, mixed> $args [values, keys, value]
	 * @param string            $what The function asking.
	 *
	 * @return array<int, int|float>
	 */
	private static function matching(array $args, string $what): array {
		$values = ($args[0] ?? null);
		$keys = ($args[1] ?? null);
		if ($values === null || $keys === null) {
			return [];
		}

		$values = V::list(value: $values, what: $what);
		$keys = V::list(value: $keys, what: $what);
		$picked = [];
		$length = min(count($values), count($keys));
		for ($i = 0; $i < $length; $i++) {
			if ($keys[$i] !== null && V::same(left: $keys[$i], right: ($args[2] ?? null)) === true && $values[$i] !== null) {
				$picked[] = V::num(value: $values[$i], what: $what);
			}
		}

		return $picked;
	}//end matching()

	/**
	 * @param array<int, mixed> $args [values, keys, value]
	 *
	 * @return int|float
	 */
	private static function sumIf(array $args): int|float {
		return array_sum(self::matching(args: $args, what: 'sumIf'));
	}//end sumIf()

	/**
	 * @param array<int, mixed> $args [values, keys, value]
	 *
	 * @return int|float|null
	 */
	private static function avgIf(array $args): int|float|null {
		$picked = self::matching(args: $args, what: 'avgIf');
		if ($picked === []) {
			return null;
		}

		return V::finite(value: array_sum($picked) / count($picked));
	}//end avgIf()

	/**
	 * @param int|float $nowMs The moment.
	 *
	 * @return string
	 */
	private static function now(int|float $nowMs): string {
		return V::isoDate(seconds: (int)floor($nowMs / 1000));
	}//end now()

	/**
	 * @param int|float $nowMs The moment.
	 *
	 * @return string
	 */
	private static function today(int|float $nowMs): string {
		return V::isoDate(seconds: (int)(floor($nowMs / 1000 / V::DAY) * V::DAY));
	}//end today()

	/**
	 * A unit name the date functions know.
	 *
	 * @param string $unit The unit.
	 *
	 * @return string
	 */
	private static function unitOf(string $unit): string {
		if (array_key_exists($unit, V::UNIT_SECONDS) === false && $unit !== 'months' && $unit !== 'years') {
			throw new EvaluationError("unknown unit '" . $unit . "'");
		}

		return $unit;
	}//end unitOf()

	/**
	 * @param array<int, mixed> $args [end, start, unit = days]
	 *
	 * @return int|float
	 */
	private static function dateDiff(array $args): int|float {
		$end = V::dateSeconds(value: $args[0] ?? null);
		$start = V::dateSeconds(value: $args[1] ?? null);
		$unit = self::unitOf(unit: V::str(value: ($args[2] ?? 'days'), what: 'dateDiff'));
		if ($unit === 'months') {
			return V::monthsBetween(end: $end, start: $start);
		}

		if ($unit === 'years') {
			return V::trunc(value: V::monthsBetween(end: $end, start: $start) / 12);
		}

		return V::trunc(value: ($end - $start) / V::UNIT_SECONDS[$unit]);
	}//end dateDiff()

	/**
	 * @param array<int, mixed> $args [date, amount, unit]
	 *
	 * @return string
	 */
	private static function dateAdd(array $args): string {
		$seconds = V::dateSeconds(value: $args[0] ?? null);
		$amount = (int)V::trunc(value: V::num(value: $args[1] ?? null, what: 'dateAdd'));
		$unit = self::unitOf(unit: V::str(value: $args[2] ?? null, what: 'dateAdd'));
		if ($unit === 'months') {
			return V::isoDate(seconds: V::addMonths(seconds: $seconds, months: $amount));
		}

		if ($unit === 'years') {
			return V::isoDate(seconds: V::addMonths(seconds: $seconds, months: ($amount * 12)));
		}

		return V::isoDate(seconds: ($seconds + ($amount * V::UNIT_SECONDS[$unit])));
	}//end dateAdd()

	/**
	 * @param array<int, mixed> $args [date, pattern]
	 *
	 * @return string
	 */
	private static function formatDate(array $args): string {
		$iso = V::isoDate(seconds: V::dateSeconds(value: $args[0] ?? null));
		$parts = [
			'yyyy' => substr($iso, 0, 4), 'MM' => substr($iso, 5, 2), 'dd' => substr($iso, 8, 2),
			'HH' => substr($iso, 11, 2), 'mm' => substr($iso, 14, 2), 'ss' => substr($iso, 17, 2),
		];

		return (string)preg_replace_callback(
			self::PATTERN_TOKENS,
			static fn (array $match): string => $parts[$match[0]],
			V::str(value: $args[1] ?? null, what: 'formatDate')
		);
	}//end formatDate()

	/**
	 * @param array<int, mixed> $args [value, pattern?]
	 *
	 * @return string
	 */
	private static function parseDate(array $args): string {
		$value = V::str(value: $args[0] ?? null, what: 'parseDate');
		if (array_key_exists(1, $args) === false) {
			return V::isoDate(seconds: V::dateSeconds(value: $value));
		}

		return V::isoDate(seconds: self::parsePattern(value: $value, pattern: V::str(value: $args[1], what: 'parseDate')));
	}//end parseDate()

	/**
	 * A date read with a yyyy/MM/dd/HH/mm/ss pattern, in UTC.
	 *
	 * @param string $value   The text.
	 * @param string $pattern The pattern.
	 *
	 * @return int
	 */
	private static function parsePattern(string $value, string $pattern): int {
		$order = [];
		$regex = (string)preg_replace_callback(
			self::PATTERN_TOKENS,
			static function (array $match) use (&$order): string {
				$order[] = $match[0];
				if ($match[0] === 'yyyy') {
					return '(\d{4})';
				}

				return '(\d{2})';
			},
			preg_quote($pattern, '/')
		);
		if (preg_match('/^' . $regex . '$/', $value, $match) !== 1) {
			throw new EvaluationError('invalid date');
		}

		$parts = ['yyyy' => 1970, 'MM' => 1, 'dd' => 1, 'HH' => 0, 'mm' => 0, 'ss' => 0];
		foreach ($order as $index => $token) {
			$parts[$token] = (int)$match[$index + 1];
		}

		$seconds = gmmktime($parts['HH'], $parts['mm'], $parts['ss'], $parts['MM'], $parts['dd'], $parts['yyyy']);
		if ((int)gmdate('j', $seconds) !== $parts['dd'] || (int)gmdate('n', $seconds) !== $parts['MM']) {
			throw new EvaluationError('invalid date');
		}

		return $seconds;
	}//end parsePattern()

	/**
	 * @param array<int, mixed> $args [end, start]
	 *
	 * @return int
	 */
	private static function durationBetween(array $args): int {
		return (V::dateSeconds(value: $args[0] ?? null) - V::dateSeconds(value: $args[1] ?? null));
	}//end durationBetween()

	/**
	 * @param array<int, mixed> $args [seconds, pattern = hh:mm:ss]
	 *
	 * @return string
	 */
	private static function durationFormat(array $args): string {
		$seconds = V::num(value: $args[0] ?? null, what: 'durationFormat');
		$pattern = V::str(value: ($args[1] ?? 'hh:mm:ss'), what: 'durationFormat');
		$total = (int)round(abs($seconds));
		$parts = [
			'hh' => str_pad((string)intdiv($total, 3600), 2, '0', STR_PAD_LEFT),
			'mm' => str_pad((string)intdiv(($total % 3600), 60), 2, '0', STR_PAD_LEFT),
			'ss' => str_pad((string)($total % 60), 2, '0', STR_PAD_LEFT),
		];
		$text = (string)preg_replace_callback('/hh|mm|ss/', static fn (array $match): string => $parts[$match[0]], $pattern);

		if ($seconds < 0) {
			return '-' . $text;
		}

		return $text;
	}//end durationFormat()

	/**
	 * @param array<int, mixed> $args Scalars.
	 *
	 * @return string
	 */
	private static function concat(array $args): string {
		return implode('', array_map(static fn (mixed $arg): string => V::text(value: $arg), $args));
	}//end concat()

	/**
	 * @param array<int, mixed> $args [s]
	 *
	 * @return string
	 */
	private static function upper(array $args): string {
		return mb_strtoupper(V::str(value: $args[0] ?? null, what: 'upper'));
	}//end upper()

	/**
	 * @param array<int, mixed> $args [s]
	 *
	 * @return string
	 */
	private static function lower(array $args): string {
		return mb_strtolower(V::str(value: $args[0] ?? null, what: 'lower'));
	}//end lower()

	/**
	 * @param array<int, mixed> $args [s]
	 *
	 * @return string
	 */
	private static function trim(array $args): string {
		return (string)preg_replace('/^[\s\x{FEFF}\x{A0}]+|[\s\x{FEFF}\x{A0}]+$/u', '', V::str(value: $args[0] ?? null, what: 'trim'));
	}//end trim()

	/**
	 * @param array<int, mixed> $args [s, part]
	 *
	 * @return bool
	 */
	private static function contains(array $args): bool {
		return str_contains(V::str(value: $args[0] ?? null, what: 'contains'), V::str(value: $args[1] ?? null, what: 'contains'));
	}//end contains()

	/**
	 * @param array<int, mixed> $args [s, part]
	 *
	 * @return bool
	 */
	private static function startsWith(array $args): bool {
		return str_starts_with(V::str(value: $args[0] ?? null, what: 'startsWith'), V::str(value: $args[1] ?? null, what: 'startsWith'));
	}//end startsWith()

	/**
	 * @param array<int, mixed> $args [s, part]
	 *
	 * @return bool
	 */
	private static function endsWith(array $args): bool {
		return str_ends_with(V::str(value: $args[0] ?? null, what: 'endsWith'), V::str(value: $args[1] ?? null, what: 'endsWith'));
	}//end endsWith()

	/**
	 * @param array<int, mixed> $args [s, search, replacement]
	 *
	 * @return string
	 */
	private static function replace(array $args): string {
		$search = V::str(value: $args[1] ?? null, what: 'replace');
		$subject = V::str(value: $args[0] ?? null, what: 'replace');
		if ($search === '') {
			return $subject;
		}

		return str_replace($search, V::str(value: $args[2] ?? null, what: 'replace'), $subject);
	}//end replace()

	/**
	 * @param array<int, mixed> $args [template, ...values]
	 *
	 * @return string
	 */
	private static function format(array $args): string {
		$template = V::str(value: $args[0] ?? null, what: 'format');
		$values = array_slice($args, 1);

		return (string)preg_replace_callback(
			'/\{(\d+)\}/',
			static function (array $match) use ($values): string {
				$index = (int)$match[1];
				if ($index < count($values)) {
					return V::text(value: $values[$index]);
				}

				return $match[0];
			},
			$template
		);
	}//end format()

	/**
	 * @param array<int, mixed> $args Values.
	 *
	 * @return mixed The first that is not null.
	 */
	private static function coalesce(array $args): mixed {
		foreach ($args as $arg) {
			if ($arg !== null) {
				return $arg;
			}
		}

		return null;
	}//end coalesce()

	/**
	 * @param array<int, mixed> $args [value, fallback]
	 *
	 * @return mixed
	 */
	private static function ifNull(array $args): mixed {
		return ($args[0] ?? null) ?? ($args[1] ?? null);
	}//end ifNull()

	/**
	 * @param array<int, mixed> $args [cond, value, ..., default?]
	 *
	 * @return mixed
	 */
	private static function caseOf(array $args): mixed {
		$count = count($args);
		for ($i = 0; ($i + 1) < $count; $i += 2) {
			if ($args[$i] === true) {
				return $args[$i + 1];
			}
		}

		if (($count % 2) === 1) {
			return $args[$count - 1];
		}

		return null;
	}//end caseOf()

	/**
	 * @param array<int, mixed> $args [value]
	 *
	 * @return int|float
	 */
	private static function toNumber(array $args): int|float {
		$value = ($args[0] ?? null);
		if (V::isNumber(value: $value) === true) {
			return V::num(value: $value, what: 'toNumber');
		}

		if (is_bool($value) === true) {
			if ($value === true) {
				return 1;
			}

			return 0;
		}

		if (is_string($value) === true && trim($value) !== '' && is_numeric(trim($value)) === true) {
			return (trim($value) + 0);
		}

		throw new EvaluationError('cannot convert to a number');
	}//end toNumber()

	/**
	 * @param array<int, mixed> $args [value]
	 *
	 * @return string
	 */
	private static function toText(array $args): string {
		return V::text(value: $args[0] ?? null);
	}//end toText()

	/**
	 * @param array<int, mixed> $args [value]
	 *
	 * @return bool
	 */
	private static function toBool(array $args): bool {
		$value = ($args[0] ?? null);
		if (is_bool($value) === true) {
			return $value;
		}

		if (V::isNumber(value: $value) === true) {
			return V::same(left: $value, right: 0) === false;
		}

		$word = null;
		if (is_string($value) === true) {
			$word = strtolower(trim($value));
		}
		if (in_array($word, ['true', '1', 'yes'], true) === true) {
			return true;
		}

		if (in_array($word, ['false', '0', 'no', ''], true) === true) {
			return false;
		}

		throw new EvaluationError('cannot convert to a boolean');
	}//end toBool()

	/**
	 * @param array<int, mixed> $args [value]
	 *
	 * @return string
	 */
	private static function toDate(array $args): string {
		return V::isoDate(seconds: V::dateSeconds(value: $args[0] ?? null));
	}//end toDate()
}//end class
