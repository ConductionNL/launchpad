<?php

/**
 * CalculatedFieldValuesTest
 *
 * The value helpers the evaluator and its functions share: type checks that
 * refuse with the evaluator's own messages, number formatting that matches the
 * client evaluator's JavaScript text, path reads over lists, and the date
 * arithmetic.
 *
 * @category  Test
 * @package   Unit\Service\CalculatedFields
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

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The shared value helpers.
 */
#[CoversClass(CalculatedFieldValues::class)]
class CalculatedFieldValuesTest extends TestCase {

	/**
	 * The message an EvaluationError carries for a call.
	 *
	 * @param callable $call The call.
	 *
	 * @return string The message, or '' when nothing was thrown.
	 */
	private static function refusal(callable $call): string {
		try {
			$call();
		} catch (EvaluationError $e) {
			return $e->getMessage();
		}

		return '';
	}//end refusal()

	/**
	 * The type checks refuse with "<what> needs a ..." and pass a good value through.
	 *
	 * @return void
	 */
	public function testTheTypeChecksRefuseWithTheirMessage(): void {
		$this->assertSame('sum needs a number', self::refusal(static fn () => CalculatedFieldValues::num(value: INF, what: 'sum')));
		$this->assertSame('sum needs a number', self::refusal(static fn () => CalculatedFieldValues::num(value: '3', what: 'sum')));
		$this->assertSame('upper needs a string', self::refusal(static fn () => CalculatedFieldValues::str(value: 3, what: 'upper')));
		$this->assertSame('count needs a list', self::refusal(static fn () => CalculatedFieldValues::list(value: ['a' => 1], what: 'count')));
		$this->assertSame('numeric overflow', self::refusal(static fn () => CalculatedFieldValues::finite(value: INF)));

		$this->assertSame('x', CalculatedFieldValues::str(value: 'x', what: 'upper'));
		$this->assertSame([1, 2], CalculatedFieldValues::list(value: [1, 2], what: 'count'));
		$this->assertSame(2.5, CalculatedFieldValues::finite(value: 2.5));
	}//end testTheTypeChecksRefuseWithTheirMessage()

	/**
	 * Numbers compare by value across int and float; arrays never equal; the rest strictly.
	 *
	 * @return void
	 */
	public function testSameComparesNumbersByValue(): void {
		$this->assertTrue(CalculatedFieldValues::same(left: 2, right: 2.0));
		$this->assertFalse(CalculatedFieldValues::same(left: [1], right: [1]));
		$this->assertFalse(CalculatedFieldValues::same(left: '2', right: 2));
		$this->assertTrue(CalculatedFieldValues::same(left: 'a', right: 'a'));
	}//end testSameComparesNumbersByValue()

	/**
	 * Numbers print as JavaScript prints them: whole floats without a point, large and tiny ones as e+/e-.
	 *
	 * @return void
	 */
	public function testNumberTextMatchesJavaScript(): void {
		$this->assertSame('7', CalculatedFieldValues::numberText(value: 7));
		$this->assertSame('3', CalculatedFieldValues::numberText(value: 3.0));
		$this->assertSame('2.5', CalculatedFieldValues::numberText(value: 2.5));
		$this->assertSame('1e+25', CalculatedFieldValues::numberText(value: 1e25));
		$this->assertSame('-1.25e+22', CalculatedFieldValues::numberText(value: -1.25e22));
		$this->assertSame('1.5e-7', CalculatedFieldValues::numberText(value: 1.5e-7));
	}//end testNumberTextMatchesJavaScript()

	/**
	 * toString takes strings, numbers and booleans, and refuses anything else.
	 *
	 * @return void
	 */
	public function testTextTakesScalarsOnly(): void {
		$this->assertSame('abc', CalculatedFieldValues::text(value: 'abc'));
		$this->assertSame('2.5', CalculatedFieldValues::text(value: 2.5));
		$this->assertSame('true', CalculatedFieldValues::text(value: true));
		$this->assertSame('false', CalculatedFieldValues::text(value: false));
		$this->assertSame('toString needs a scalar', self::refusal(static fn () => CalculatedFieldValues::text(value: ['a'])));
		$this->assertSame('toString needs a scalar', self::refusal(static fn () => CalculatedFieldValues::text(value: null)));
	}//end testTextTakesScalarsOnly()

	/**
	 * A path read over a list reads each item and flattens listed values; a non-array has no path.
	 *
	 * @return void
	 */
	public function testReadPathOverLists(): void {
		$this->assertSame([1, 2, 3, null], CalculatedFieldValues::readPath(value: [['a' => [1, 2]], ['a' => 3], 'x'], path: 'a'));
		$this->assertSame('deep', CalculatedFieldValues::readPath(value: ['a' => ['b' => 'deep']], path: 'a.b'));
		$this->assertNull(CalculatedFieldValues::readPath(value: 'text', path: 'a.b'));
		$this->assertNull(CalculatedFieldValues::readPath(value: ['a' => 'text'], path: 'a.b'));
	}//end testReadPathOverLists()

	/**
	 * Dates read from milliseconds or an ISO string; anything else is an invalid date.
	 *
	 * @return void
	 */
	public function testDateSeconds(): void {
		$this->assertSame(1772366400, CalculatedFieldValues::dateSeconds(value: 1772366400999));
		$this->assertSame(1772366400, CalculatedFieldValues::dateSeconds(value: ' 2026-03-01T12:00:00Z '));
		$this->assertSame('2026-03-01T12:00:00Z', CalculatedFieldValues::isoDate(seconds: 1772366400));
		$this->assertSame('invalid date', self::refusal(static fn () => CalculatedFieldValues::dateSeconds(value: '2026-99-99')));
		$this->assertSame('invalid date', self::refusal(static fn () => CalculatedFieldValues::dateSeconds(value: 'next tuesday')));
		$this->assertSame('invalid date', self::refusal(static fn () => CalculatedFieldValues::dateSeconds(value: INF)));
	}//end testDateSeconds()

	/**
	 * Adding a month clamps to the month's last day; whole months between count down when reversed.
	 *
	 * @return void
	 */
	public function testMonthArithmetic(): void {
		$jan31 = gmmktime(10, 0, 0, 1, 31, 2026);
		$this->assertSame('2026-02-28T10:00:00Z', CalculatedFieldValues::isoDate(seconds: CalculatedFieldValues::addMonths(seconds: $jan31, months: 1)));

		$jan15 = gmmktime(0, 0, 0, 1, 15, 2026);
		$mar10 = gmmktime(0, 0, 0, 3, 10, 2026);
		$this->assertSame(1, CalculatedFieldValues::monthsBetween(end: $mar10, start: $jan15));
		$this->assertSame(-1, CalculatedFieldValues::monthsBetween(end: $jan15, start: $mar10));
	}//end testMonthArithmetic()

	/**
	 * Truncation goes toward zero and stays a float beyond the integer range.
	 *
	 * @return void
	 */
	public function testTruncGoesTowardZero(): void {
		$this->assertSame(5, CalculatedFieldValues::trunc(value: 5));
		$this->assertSame(-2, CalculatedFieldValues::trunc(value: -2.7));
		$this->assertSame(2, CalculatedFieldValues::trunc(value: 2.7));
		$this->assertSame(1e30, CalculatedFieldValues::trunc(value: 1e30));
	}//end testTruncGoesTowardZero()
}//end class
