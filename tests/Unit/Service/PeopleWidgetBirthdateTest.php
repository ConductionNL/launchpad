<?php

/**
 * PeopleWidgetService birthdate characterisation test
 *
 * Pins how stored birthdates are normalised (ISO, DMY and YMD with `-`,
 * `/` or `.` separators, rejected shapes) and how the days to the next
 * birthday are counted, so the date helpers can be split into named
 * steps without changing their answers. Expectations are computed
 * relative to today, never pinned to a calendar date.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Service\PeopleWidgetService;
use PHPUnit\Framework\TestCase;

class PeopleWidgetBirthdateTest extends TestCase {
	/**
	 * Stored shapes that denote the same date as an ISO date.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function equivalentShapes(): array {
		return [
			'iso padded'      => [' 1990-06-10 ', '1990-06-10'],
			'dmy dash'        => ['10-06-1990', '1990-06-10'],
			'dmy slash'       => ['10/06/1990', '1990-06-10'],
			'dmy dot'         => ['10.06.1990', '1990-06-10'],
			'ymd slash'       => ['1990/06/10', '1990-06-10'],
			'ymd dot short'   => ['1990.6.1', '1990-06-01'],
			'dmy short'       => ['1-6-1990', '1990-06-01'],
			'iso overflow'    => ['2001-02-30', '2001-03-02'],
			'iso month zero'  => ['2001-00-10', '2000-12-10'],
			'feb 29'          => ['29.02.2000', '2000-02-29'],
		];
	}

	/**
	 * @dataProvider equivalentShapes
	 */
	public function testShapeCountsLikeItsIsoDate(string $stored, string $iso): void {
		$expected = PeopleWidgetService::computeDaysToBirthday(birthdate: $iso);

		$this->assertNotNull($expected);
		$this->assertSame($expected, PeopleWidgetService::computeDaysToBirthday(birthdate: $stored));
	}

	/**
	 * Stored shapes that are not a date.
	 *
	 * @return array<string,array{0:string|null}>
	 */
	public static function rejectedShapes(): array {
		return [
			'null'             => [null],
			'blank'            => ['   '],
			'two digit year'   => ['10-06-90'],
			'no year'          => ['10-06'],
			'four parts'       => ['10-06-1990-1'],
			'letters'          => ['aa-06-1990'],
			'signed'           => ['+1-06-1990'],
			'not a real date'  => ['31-02-2001'],
			'month thirteen'   => ['1990/13/01'],
			'both four digit'  => ['1990-06-2000'],
			'mixed seps'       => ['10-06/1990'],
			'spaces inside'    => ['10 - 06 - 1990'],
			'year in middle'   => ['10-1990-06'],
		];
	}

	/**
	 * @dataProvider rejectedShapes
	 */
	public function testRejectedShapeHasNoCount(?string $stored): void {
		$this->assertNull(PeopleWidgetService::computeDaysToBirthday(birthdate: $stored));
	}

	public function testBirthdayTodayIsZeroDays(): void {
		$today = new DateTimeImmutable('today');

		$this->assertSame(0, PeopleWidgetService::computeDaysToBirthday(birthdate: '1980-' . $today->format('m-d')));
	}

	public function testBirthdayTomorrowIsOneDay(): void {
		$tomorrow = new DateTimeImmutable('tomorrow');

		$this->assertSame(1, PeopleWidgetService::computeDaysToBirthday(birthdate: '1980-' . $tomorrow->format('m-d')));
	}

	public function testBirthdayYesterdayWrapsToNextYear(): void {
		$yesterday = new DateTimeImmutable('yesterday');
		$next = $yesterday->modify('+1 year');
		$expected = (int)(new DateTimeImmutable('today'))->diff($next)->days;
		if ($yesterday->format('m-d') === '02-29') {
			$this->markTestSkipped('Feb-29 wrap is covered by the leap-year test.');
		}

		$this->assertSame($expected, PeopleWidgetService::computeDaysToBirthday(birthdate: '1980-' . $yesterday->format('m-d')));
	}
}//end class
