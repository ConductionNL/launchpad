<?php

/**
 * CalculatedFieldFunctionsTest
 *
 * The function library's edges the shared suite does not reach: unknown
 * names, empty and null inputs, the logarithm base, year units, date patterns,
 * template placeholders without a value, and the conversions.
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

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Edges of the function library.
 */
#[CoversClass(CalculatedFieldFunctions::class)]
#[UsesClass(CalculatedFieldValues::class)]
class CalculatedFieldFunctionsTest extends TestCase {

	/**
	 * Call a function at a fixed now.
	 *
	 * @param string            $name The function.
	 * @param array<int, mixed> $args Its arguments.
	 *
	 * @return mixed
	 */
	private static function call(string $name, array $args): mixed {
		return CalculatedFieldFunctions::call(name: $name, args: $args, nowMs: 0);
	}//end call()

	/**
	 * The message an EvaluationError carries for a call.
	 *
	 * @param string            $name The function.
	 * @param array<int, mixed> $args Its arguments.
	 *
	 * @return string The message, or '' when nothing was thrown.
	 */
	private static function refusal(string $name, array $args): string {
		try {
			self::call(name: $name, args: $args);
		} catch (EvaluationError $e) {
			return $e->getMessage();
		}

		return '';
	}//end refusal()

	/**
	 * An unknown name and a logarithm base of 1 are refused by message.
	 *
	 * @return void
	 */
	public function testUnknownNamesAndBadBasesAreRefused(): void {
		$this->assertSame("unknown function 'nope'", self::refusal(name: 'nope', args: []));
		$this->assertSame('invalid logarithm base', self::refusal(name: 'log', args: [8, 1]));
		$this->assertSame('invalid logarithm base', self::refusal(name: 'log', args: [8, 0]));
		$this->assertEqualsWithDelta(3.0, self::call(name: 'log', args: [8, 2]), 1e-12);
	}//end testUnknownNamesAndBadBasesAreRefused()

	/**
	 * Aggregates over nothing answer null; counts over null answer 0; sumIf over null answers 0.
	 *
	 * @return void
	 */
	public function testAggregatesOverNothing(): void {
		$this->assertNull(self::call(name: 'min', args: [[]]));
		$this->assertNull(self::call(name: 'max', args: [[null]]));
		$this->assertNull(self::call(name: 'avg', args: [[]]));
		$this->assertSame(0, self::call(name: 'count', args: [null]));
		$this->assertSame(0, self::call(name: 'countIf', args: [null, 1]));
		$this->assertSame(0, self::call(name: 'sumIf', args: [null, [1], 1]));
		$this->assertSame(6, self::call(name: 'sumIf', args: [[5, 6], ['a', 'b'], 'b']));
	}//end testAggregatesOverNothing()

	/**
	 * Years count whole years between dates, and adding years keeps the day.
	 *
	 * @return void
	 */
	public function testYearUnits(): void {
		$this->assertSame(2, self::call(name: 'dateDiff', args: ['2028-03-01T00:00:00Z', '2026-01-01T00:00:00Z', 'years']));
		$this->assertSame('2028-01-31T00:00:00Z', self::call(name: 'dateAdd', args: ['2026-01-31T00:00:00Z', 2, 'years']));
	}//end testYearUnits()

	/**
	 * A date pattern reads matching text and refuses text that does not match.
	 *
	 * @return void
	 */
	public function testParseDateWithAPattern(): void {
		$this->assertSame('2026-12-31T00:00:00Z', self::call(name: 'parseDate', args: ['31/12/2026', 'dd/MM/yyyy']));
		$this->assertSame('invalid date', self::refusal(name: 'parseDate', args: ['2026-12-31', 'dd/MM/yyyy']));
	}//end testParseDateWithAPattern()

	/**
	 * Replacing an empty search leaves the text; a placeholder without a value stays as written.
	 *
	 * @return void
	 */
	public function testReplaceAndFormatEdges(): void {
		$this->assertSame('abc', self::call(name: 'replace', args: ['abc', '', 'x']));
		$this->assertSame('a en {2}', self::call(name: 'format', args: ['{0} en {2}', 'a', 'b']));
	}//end testReplaceAndFormatEdges()

	/**
	 * Conversions: numbers pass, booleans become 1 and 0, and words become booleans.
	 *
	 * @return void
	 */
	public function testConversions(): void {
		$this->assertSame(2.5, self::call(name: 'toNumber', args: [2.5]));
		$this->assertSame(1, self::call(name: 'toNumber', args: [true]));
		$this->assertSame(0, self::call(name: 'toNumber', args: [false]));
		$this->assertTrue(self::call(name: 'toBool', args: [true]));
		$this->assertFalse(self::call(name: 'toBool', args: ['No']));
		$this->assertFalse(self::call(name: 'toBool', args: ['']));
	}//end testConversions()
}//end class
