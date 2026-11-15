<?php

/**
 * CalculatedFieldValues
 *
 * The value rules both evaluators share: what counts as a number, a string or
 * a list, how a value reads as text, how a dotted path is read off a row, and
 * how dates convert to and from seconds. Each rule mirrors the client-side
 * evaluator (src/utils/calculatedFields/evaluator.js) so a calculated field
 * gives the same answer on either side.
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

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Value rules shared by the evaluator and its functions.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) Each rule is one small public helper, as in the client.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
final class CalculatedFieldValues {

	public const DAY = 86400;

	/**
	 * The ISO 8601 dates and date-times a date string may be.
	 */
	private const ISO_DATE = '/^[+-]?\d{4,6}(-\d{2}(-\d{2})?)?([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/i';

	/**
	 * Seconds per fixed-length unit.
	 */
	public const UNIT_SECONDS = ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400, 'weeks' => 604800];

	/**
	 * Whether a value is a number (an int or a float, never a boolean).
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public static function isNumber(mixed $value): bool {
		return is_int($value) === true || is_float($value) === true;
	}//end isNumber()

	/**
	 * Whether a value is a list (a JSON array, not an object).
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public static function isList(mixed $value): bool {
		return is_array($value) === true && array_is_list($value) === true;
	}//end isList()

	/**
	 * A finite number, or the client's "needs a number" failure.
	 *
	 * @param mixed  $value The value.
	 * @param string $what  The operator or function asking.
	 *
	 * @return int|float
	 *
	 * @throws EvaluationError When the value is no finite number.
	 */
	public static function num(mixed $value, string $what): int|float {
		if (self::isNumber(value: $value) === false || is_finite((float)$value) === false) {
			throw new EvaluationError($what . ' needs a number');
		}

		return $value;
	}//end num()

	/**
	 * A string, or the client's "needs a string" failure.
	 *
	 * @param mixed  $value The value.
	 * @param string $what  The function asking.
	 *
	 * @return string
	 *
	 * @throws EvaluationError When the value is no string.
	 */
	public static function str(mixed $value, string $what): string {
		if (is_string($value) === false) {
			throw new EvaluationError($what . ' needs a string');
		}

		return $value;
	}//end str()

	/**
	 * A list, or the client's "needs a list" failure.
	 *
	 * @param mixed  $value The value.
	 * @param string $what  The function asking.
	 *
	 * @return array<int, mixed>
	 *
	 * @throws EvaluationError When the value is no list.
	 */
	public static function list(mixed $value, string $what): array {
		if (self::isList(value: $value) === false) {
			throw new EvaluationError($what . ' needs a list');
		}

		return $value;
	}//end list()

	/**
	 * A result that must stay finite.
	 *
	 * @param int|float $value The result.
	 *
	 * @return int|float
	 *
	 * @throws EvaluationError On an overflow.
	 */
	public static function finite(int|float $value): int|float {
		if (is_finite((float)$value) === false) {
			throw new EvaluationError('numeric overflow');
		}

		return $value;
	}//end finite()

	/**
	 * Whether two values are the same, as JavaScript's `===` sees JSON values:
	 * an int and a float of the same value are the same number.
	 *
	 * @param mixed $left  One value.
	 * @param mixed $right The other.
	 *
	 * @return bool
	 */
	public static function same(mixed $left, mixed $right): bool {
		if (self::isNumber(value: $left) === true && self::isNumber(value: $right) === true) {
			return (float)$left === (float)$right;
		}

		if (is_array($left) === true || is_array($right) === true) {
			return false;
		}

		return $left === $right;
	}//end same()

	/**
	 * A number as JavaScript's String() writes it: an integral value without
	 * a fraction, otherwise the shortest round-trip digits, with an exponent
	 * written as 1e+21 or 1e-7.
	 *
	 * @param int|float $value The number.
	 *
	 * @return string
	 */
	public static function numberText(int|float $value): string {
		if (is_int($value) === true) {
			return (string)$value;
		}

		if ($value === floor($value) && abs($value) < 1e21) {
			return sprintf('%.0f', $value);
		}

		$text = strtolower((string)json_encode($value));
		if (str_contains($text, 'e') === false) {
			return $text;
		}

		[$mantissa, $exponent] = explode('e', $text, 2);
		if (str_contains($mantissa, '.') === true) {
			$mantissa = rtrim(rtrim($mantissa, '0'), '.');
		}

		if ($exponent[0] !== '-' && $exponent[0] !== '+') {
			$exponent = '+' . $exponent;
		}

		return $mantissa . 'e' . $exponent;
	}//end numberText()

	/**
	 * A scalar as text, the way the client's toString and concat do.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 *
	 * @throws EvaluationError When the value is no scalar.
	 */
	public static function text(mixed $value): string {
		if (is_string($value) === true) {
			return $value;
		}

		if (self::isNumber(value: $value) === true) {
			return self::numberText(value: self::num(value: $value, what: 'toString'));
		}

		if (is_bool($value) === true) {
			if ($value === true) {
				return 'true';
			}

			return 'false';
		}

		throw new EvaluationError('toString needs a scalar');
	}//end text()

	/**
	 * Read a dotted path off a value; across a list, from each item, flattened.
	 *
	 * @param mixed  $value The row or a widget's data.
	 * @param string $path  The dotted path.
	 *
	 * @return mixed The value, or null when the path does not exist.
	 */
	public static function readPath(mixed $value, string $path): mixed {
		$current = $value;
		foreach (explode('.', $path) as $part) {
			if (is_array($current) === false) {
				return null;
			}

			$current = self::readPart(current: $current, part: $part);
		}

		return $current;
	}//end readPath()

	/**
	 * One path part of an object, or of every item of a list.
	 *
	 * @param array<mixed> $current The object or list.
	 * @param string       $part    The path part.
	 *
	 * @return mixed
	 */
	private static function readPart(array $current, string $part): mixed {
		if (self::isList(value: $current) === true) {
			return self::readFromEach(items: $current, part: $part);
		}

		return ($current[$part] ?? null);
	}//end readPart()

	/**
	 * One path part read from every item of a list, flattened one level.
	 *
	 * @param array<int, mixed> $items The list.
	 * @param string            $part  The path part.
	 *
	 * @return array<int, mixed>
	 */
	private static function readFromEach(array $items, string $part): array {
		$out = [];
		foreach ($items as $item) {
			$next = null;
			if (is_array($item) === true && array_key_exists($part, $item) === true) {
				$next = $item[$part];
			}

			if (self::isList(value: $next) === true) {
				array_push($out, ...$next);
				continue;
			}

			$out[] = $next;
		}

		return $out;
	}//end readFromEach()

	/**
	 * Whole seconds since the epoch of a date: epoch milliseconds or an ISO date string.
	 *
	 * @param mixed $value The date.
	 *
	 * @return int
	 *
	 * @throws EvaluationError When the value is no date.
	 */
	public static function dateSeconds(mixed $value): int {
		if (self::isNumber(value: $value) === true && is_finite((float)$value) === true) {
			return (int)floor($value / 1000);
		}

		if (is_string($value) === true && preg_match(self::ISO_DATE, trim($value)) === 1) {
			try {
				$date = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
				return (int)floor((float)$date->format('U.u'));
			} catch (Throwable) {
				throw new EvaluationError('invalid date');
			}
		}

		throw new EvaluationError('invalid date');
	}//end dateSeconds()

	/**
	 * Seconds since the epoch as an ISO date in UTC, without milliseconds.
	 *
	 * @param int $seconds The seconds.
	 *
	 * @return string
	 */
	public static function isoDate(int $seconds): string {
		return gmdate('Y-m-d\TH:i:s\Z', $seconds);
	}//end isoDate()

	/**
	 * A date moved by whole calendar months, clamped to the last day of the month.
	 *
	 * @param int $seconds The date.
	 * @param int $months  The months to add.
	 *
	 * @return int
	 */
	public static function addMonths(int $seconds, int $months): int {
		[$year, $month, $day, $hour, $minute, $second] = array_map('intval', explode(' ', gmdate('Y n j G i s', $seconds)));
		$target = ($month + $months);
		$lastDay = (int)gmdate('j', gmmktime(0, 0, 0, ($target + 1), 0, $year));

		return gmmktime($hour, $minute, $second, $target, min($day, $lastDay), $year);
	}//end addMonths()

	/**
	 * Whole calendar months from one date to another.
	 *
	 * @param int $end   The later date.
	 * @param int $start The earlier date.
	 *
	 * @return int
	 */
	public static function monthsBetween(int $end, int $start): int {
		if ($end < $start) {
			return -self::monthsBetween(end: $start, start: $end);
		}

		[$endYear, $endMonth] = array_map('intval', explode(' ', gmdate('Y n', $end)));
		[$startYear, $startMonth] = array_map('intval', explode(' ', gmdate('Y n', $start)));
		$months = ((($endYear - $startYear) * 12) + ($endMonth - $startMonth));
		if (self::addMonths(seconds: $start, months: $months) > $end) {
			$months--;
		}

		return $months;
	}//end monthsBetween()

	/**
	 * A number truncated toward zero.
	 *
	 * @param int|float $value The number.
	 *
	 * @return int|float
	 */
	public static function trunc(int|float $value): int|float {
		if (is_int($value) === true) {
			return $value;
		}

		$truncated = floor($value);
		if ($value < 0) {
			$truncated = ceil($value);
		}
		if (abs($truncated) < PHP_INT_MAX) {
			return (int)$truncated;
		}

		return $truncated;
	}//end trunc()
}//end class
