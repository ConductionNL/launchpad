<?php

/**
 * RecurrenceResolverTest
 *
 * Next-run computation for scheduled exports (REQ-SCH-001): the four rule
 * types, month-end clamping, leap days, and wall-clock behaviour across the
 * 2026 spring and autumn DST changes in Europe/Amsterdam.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use OCA\LaunchPad\Service\RecurrenceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\RecurrenceResolver
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
 */
class RecurrenceResolverTest extends TestCase {

	private const ZONE = 'Europe/Amsterdam';

	/**
	 * The next run, as ATOM, for a rule asked at a local wall-clock moment.
	 *
	 * @param array<string, mixed> $recurrence The rule.
	 * @param string               $after      Local `Y-m-d H:i`.
	 *
	 * @return string
	 */
	private static function next(array $recurrence, string $after): string {
		return (new RecurrenceResolver())
			->nextRunAt($recurrence, self::ZONE, new DateTimeImmutable($after, new DateTimeZone(self::ZONE)))
			->format(DateTimeInterface::ATOM);
	}//end next()

	/**
	 * Rules and the moment they next fire.
	 *
	 * @return array<string, array{array<string, mixed>, string, string}>
	 */
	public static function rules(): array {
		return [
			'weekly Monday 09:00 asked on a Friday noon' => [['type' => 'weekly', 'daysOfWeek' => ['Monday'], 'timeOfDay' => '09:00'], '2026-11-13 12:00', '2026-11-16T09:00:00+01:00'],
			'weekly with BYDAY codes picks the nearest day' => [['type' => 'weekly', 'daysOfWeek' => ['FR', 'MO'], 'timeOfDay' => '17:00'], '2026-11-13 12:00', '2026-11-13T17:00:00+01:00'],
			'weekly on the day, after the time, waits a week' => [['type' => 'weekly', 'daysOfWeek' => [5], 'timeOfDay' => '09:00'], '2026-11-13 12:00', '2026-11-20T09:00:00+01:00'],
			'daily before the time is today' => [['type' => 'daily', 'timeOfDay' => '18:30'], '2026-11-13 12:00', '2026-11-13T18:30:00+01:00'],
			'daily exactly at the time is tomorrow' => [['type' => 'daily', 'timeOfDay' => '12:00'], '2026-11-13 12:00', '2026-11-14T12:00:00+01:00'],
			'monthly 31 clamps to the last of February' => [['type' => 'monthly', 'dayOfMonth' => 31, 'timeOfDay' => '08:00'], '2026-02-01 00:00', '2026-02-28T08:00:00+01:00'],
			'monthly 31 in a leap year is the 29th' => [['type' => 'monthly', 'dayOfMonth' => 31, 'timeOfDay' => '08:00'], '2028-02-01 00:00', '2028-02-29T08:00:00+01:00'],
			'monthly 30 in April is the 30th' => [['type' => 'monthly', 'dayOfMonth' => 30, 'timeOfDay' => '08:00'], '2026-04-01 00:00', '2026-04-30T08:00:00+02:00'],
			'monthly last day after it passed is next month' => [['type' => 'monthly', 'lastDay' => true, 'timeOfDay' => '08:00'], '2026-02-28 09:00', '2026-03-31T08:00:00+02:00'],
			'monthly first of the month' => [['type' => 'monthly', 'dayOfMonth' => 1, 'timeOfDay' => '08:00'], '2026-11-13 12:00', '2026-12-01T08:00:00+01:00'],
			'cron Monday 09:00 across the spring change stays 09:00' => [['type' => 'cron', 'expression' => '0 9 * * 1'], '2026-03-28 12:00', '2026-03-30T09:00:00+02:00'],
			'cron Monday 09:00 across the autumn change stays 09:00' => [['type' => 'cron', 'expression' => '0 9 * * 1'], '2026-10-24 12:00', '2026-10-26T09:00:00+01:00'],
			'cron step and weekday range' => [['type' => 'cron', 'expression' => '*/15 2-3 * * MON-FRI'], '2026-11-13 03:50', '2026-11-16T02:00:00+01:00'],
			'cron list of minutes' => [['type' => 'cron', 'expression' => '5,35 * * * *'], '2026-11-13 12:10', '2026-11-13T12:35:00+01:00'],
			'cron Sunday as 7' => [['type' => 'cron', 'expression' => '0 6 * * 7'], '2026-11-13 12:00', '2026-11-15T06:00:00+01:00'],
			'cron 29 February waits for a leap year' => [['type' => 'cron', 'expression' => '0 0 29 2 *'], '2026-03-01 00:00', '2028-02-29T00:00:00+01:00'],
			'cron with both day fields fires on either' => [['type' => 'cron', 'expression' => '0 8 1 * 3'], '2026-11-13 12:00', '2026-11-18T08:00:00+01:00'],
			'cron month name' => [['type' => 'cron', 'expression' => '0 7 1 JAN *'], '2026-11-13 12:00', '2027-01-01T07:00:00+01:00'],
		];
	}//end rules()

	/**
	 * Each rule fires at the expected next moment.
	 *
	 * @param array<string, mixed> $recurrence The rule.
	 * @param string               $after      Local moment asked.
	 * @param string               $expected   The expected next run.
	 *
	 * @return void
	 */
	#[DataProvider('rules')]
	public function testNextRun(array $recurrence, string $after, string $expected): void {
		$this->assertSame($expected, self::next($recurrence, $after));
	}//end testNextRun()

	/**
	 * A time in the spring-forward gap fires the same distance past it.
	 *
	 * @return void
	 */
	public function testATimeInTheSpringGapFiresAfterIt(): void {
		$this->assertSame('2026-03-29T03:30:00+02:00', self::next(['type' => 'daily', 'timeOfDay' => '02:30'], '2026-03-29 00:00'));
		$this->assertSame('2026-03-30T02:30:00+02:00', self::next(['type' => 'daily', 'timeOfDay' => '02:30'], '2026-03-29 04:00'));
	}//end testATimeInTheSpringGapFiresAfterIt()

	/**
	 * A time that occurs twice in the autumn fires once, at its first occurrence.
	 *
	 * @return void
	 */
	public function testATimeThatOccursTwiceFiresOnce(): void {
		$resolver = new RecurrenceResolver();
		$rule     = ['type' => 'daily', 'timeOfDay' => '02:30'];
		$first    = $resolver->nextRunAt($rule, self::ZONE, new DateTimeImmutable('2026-10-25 00:00', new DateTimeZone(self::ZONE)));
		$this->assertSame('2026-10-25T02:30:00+02:00', $first->format(DateTimeInterface::ATOM));

		$second = $resolver->nextRunAt($rule, self::ZONE, $first);
		$this->assertSame('2026-10-26T02:30:00+01:00', $second->format(DateTimeInterface::ATOM));
	}//end testATimeThatOccursTwiceFiresOnce()

	/**
	 * The moment asked about may be in any zone; the answer is in the rule's zone,
	 * and a runner that was down fires once, not once per missed slot.
	 *
	 * @return void
	 */
	public function testAnUtcMomentIsReadInTheRulesZoneAndMissedSlotsCollapse(): void {
		$next = (new RecurrenceResolver())->nextRunAt(
			['type' => 'daily', 'timeOfDay' => '09:00'],
			self::ZONE,
			new DateTimeImmutable('2026-11-20T07:59:00+00:00')
		);
		$this->assertSame('2026-11-20T09:00:00+01:00', $next->format(DateTimeInterface::ATOM));
		$this->assertSame(self::ZONE, $next->getTimezone()->getName());
	}//end testAnUtcMomentIsReadInTheRulesZoneAndMissedSlotsCollapse()

	/**
	 * Invalid rules, and what is said about them.
	 *
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function invalidRules(): array {
		return [
			'unknown type' => [['type' => 'yearly'], 'The recurrence type must be weekly, monthly, daily or cron.'],
			'time not HH:MM' => [['type' => 'daily', 'timeOfDay' => '9:00'], 'The time of day must be HH:MM.'],
			'hour 24' => [['type' => 'daily', 'timeOfDay' => '24:00'], 'The time of day must be HH:MM.'],
			'weekly without days' => [['type' => 'weekly', 'daysOfWeek' => [], 'timeOfDay' => '09:00'], 'A weekly recurrence needs one or more days of the week (MO to SU).'],
			'weekly with a bad day' => [['type' => 'weekly', 'daysOfWeek' => ['MO', 'XX'], 'timeOfDay' => '09:00'], 'A weekly recurrence needs one or more days of the week (MO to SU).'],
			'monthly day 32' => [['type' => 'monthly', 'dayOfMonth' => 32, 'timeOfDay' => '09:00'], 'A monthly recurrence needs a day of the month from 1 to 31, or the last day.'],
			'cron with four fields' => [['type' => 'cron', 'expression' => '0 9 * *'], 'A cron expression has five fields: minute hour day-of-month month day-of-week.'],
			'cron minute 60' => [['type' => 'cron', 'expression' => '60 9 * * *'], 'The cron field "60" is out of range.'],
			'cron reversed range' => [['type' => 'cron', 'expression' => '0 9 * * 5-1'], 'The cron field "5-1" is out of range.'],
			'cron junk' => [['type' => 'cron', 'expression' => '0 9 * * FUNDAY'], 'The cron value "FUNDAY" is not valid.'],
			'cron step zero' => [['type' => 'cron', 'expression' => '*/0 9 * * *'], 'The cron field "*/0" is out of range.'],
		];
	}//end invalidRules()

	/**
	 * An invalid rule is reported, and refused when asked for its next run.
	 *
	 * @param array<string, mixed> $recurrence The rule.
	 * @param string               $message    The reported problem.
	 *
	 * @return void
	 */
	#[DataProvider('invalidRules')]
	public function testInvalidRulesAreRefused(array $recurrence, string $message): void {
		$resolver = new RecurrenceResolver();
		$this->assertSame([$message], $resolver->validate($recurrence));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$resolver->nextRunAt($recurrence, self::ZONE, new DateTimeImmutable('2026-11-13 12:00'));
	}//end testInvalidRulesAreRefused()

	/**
	 * An unknown time zone and a rule that can never fire are refused.
	 *
	 * @return void
	 */
	public function testUnknownZoneAndImpossibleRuleAreRefused(): void {
		$resolver = new RecurrenceResolver();
		try {
			$resolver->nextRunAt(['type' => 'daily', 'timeOfDay' => '09:00'], 'Europe/Atlantis', new DateTimeImmutable());
			$this->fail('An unknown zone was accepted.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('Unknown time zone "Europe/Atlantis".', $e->getMessage());
		}

		$this->expectExceptionMessage('The recurrence never fires.');
		$resolver->nextRunAt(['type' => 'cron', 'expression' => '0 0 31 2 *'], self::ZONE, new DateTimeImmutable('2026-01-01'));
	}//end testUnknownZoneAndImpossibleRuleAreRefused()

	/**
	 * Valid rules have nothing to report.
	 *
	 * @return void
	 */
	public function testValidRulesReportNothing(): void {
		$resolver = new RecurrenceResolver();
		$this->assertSame([], $resolver->validate(['type' => 'monthly', 'lastDay' => true, 'timeOfDay' => '08:00']));
		$this->assertSame([], $resolver->validate(['type' => 'cron', 'expression' => '0 2 * * 1-5']));
	}//end testValidRulesReportNothing()
}//end class
