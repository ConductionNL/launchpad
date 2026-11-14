<?php

/**
 * CalendarRangeParserTest
 *
 * Unit tests for the calendar widget's from/to range parsing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\CalendarRangeParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CalendarRangeParser.
 *
 * @covers \OCA\LaunchPad\Controller\CalendarRangeParser
 */
class CalendarRangeParserTest extends TestCase {
	public function testAValidRangeIsReturnedAsIs(): void {
		$range = (new CalendarRangeParser())->parse('2026-03-01T00:00:00+00:00', '2026-03-31T23:59:59+00:00');

		$this->assertNull($range['refusal']);
		$this->assertSame('2026-03-01T00:00:00+00:00', $range['start']->format(DATE_ATOM));
		$this->assertSame('2026-03-31T23:59:59+00:00', $range['end']->format(DATE_ATOM));
	}

	public function testAnEmptyRangeIsAllowed(): void {
		$range = (new CalendarRangeParser())->parse('2026-03-01T10:00:00Z', '2026-03-01T10:00:00Z');

		$this->assertNull($range['refusal']);
		$this->assertEquals($range['start'], $range['end']);
	}

	public function testBothBoundsAreRequired(): void {
		$parser = new CalendarRangeParser();

		foreach ([['', '2026-03-01'], ['2026-03-01', '']] as [$from, $to]) {
			$this->assertSame(
				['refusal' => 'Both from and to are required ISO 8601 timestamps', 'start' => null, 'end' => null],
				$parser->parse($from, $to)
			);
		}
	}

	public function testAnUnparseableDateIsRefused(): void {
		$this->assertSame(
			['refusal' => 'Invalid date format', 'start' => null, 'end' => null],
			(new CalendarRangeParser())->parse('2026-03-01', 'next blue moon')
		);
	}

	public function testAnInvertedRangeIsRefused(): void {
		$this->assertSame(
			'`to` must be greater than or equal to `from`',
			(new CalendarRangeParser())->parse('2026-03-02T00:00:00Z', '2026-03-01T23:59:59Z')['refusal']
		);
	}

	public function testTheEndIsCappedAtOneYearAfterTheStart(): void {
		$range = (new CalendarRangeParser())->parse('2026-03-01T00:00:00+00:00', '2030-01-01T00:00:00+00:00');

		$this->assertNull($range['refusal']);
		$this->assertSame('2027-03-01T00:00:00+00:00', $range['end']->format(DATE_ATOM));
	}

	public function testExactlyOneYearIsNotCapped(): void {
		$range = (new CalendarRangeParser())->parse('2026-03-01T00:00:00+00:00', '2027-03-01T00:00:00+00:00');

		$this->assertSame('2027-03-01T00:00:00+00:00', $range['end']->format(DATE_ATOM));
	}
}
