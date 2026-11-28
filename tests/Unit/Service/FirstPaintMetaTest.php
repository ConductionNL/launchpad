<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Service\FirstPaintMeta;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * The rights and last change of the shell's meta line, as
 * DashboardPageHeader.vue words them (rightsText, lastChangedText).
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintMeta
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
 */
class FirstPaintMetaTest extends TestCase {

	/**
	 * The Dutch words of the strings these tests use.
	 */
	private const NL = [
		'view only'                 => 'alleen bekijken',
		'you may add widgets'       => 'je mag widgets toevoegen',
		'Last changed today {time}' => 'Laatst gewijzigd vandaag {time}',
		'Last changed {when}'       => 'Laatst gewijzigd {when}',
	];

	/**
	 * Translations as Nextcloud's IL10N gives them: `{time}` is not filled in by t().
	 *
	 * @param string $language en or nl.
	 *
	 * @return IL10N
	 */
	private function l10n(string $language='en'): IL10N {
		$words = [];
		if ($language === 'nl') {
			$words = self::NL;
		}

		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(callback: static fn (string $text): string => ($words[$text] ?? $text));

		return $l;
	}//end l10n()

	/**
	 * The input for a dashboard changed at a wall-clock time, read on Saturday 28 November 2026 15:00 in Amsterdam.
	 *
	 * @param string $updatedAt The stored change time.
	 * @param string $level     The reader's level.
	 * @param string $locale    The locale.
	 *
	 * @return array<string, mixed>
	 */
	private static function input(string $updatedAt, string $level='full', string $locale='en_US'): array {
		return [
			'permissionLevel' => $level,
			'updatedAt'       => $updatedAt,
			'timezone'        => 'Europe/Amsterdam',
			'locale'          => $locale,
			'now'             => new DateTimeImmutable(datetime: '2026-11-28 15:00:00', timezone: new DateTimeZone(timezone: 'Europe/Amsterdam')),
		];
	}//end input()

	/**
	 * A change today shows its time with a 2-digit hour, as the browser prints it, in Dutch and in English.
	 *
	 * @return void
	 */
	public function testAChangeTodayShowsItsTime(): void {
		$this->assertSame(
			expected: 'Last changed today 11:43 AM',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-28 11:43:00'))
		);
		$this->assertSame(
			expected: 'Last changed today 09:05 AM',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-28 09:05:00'))
		);
		$this->assertSame(
			expected: 'Laatst gewijzigd vandaag 09:05',
			actual: FirstPaintMeta::rest(l: $this->l10n(language: 'nl'), input: self::input(updatedAt: '2026-11-28 09:05:00', locale: 'nl_NL'))
		);
	}//end testAChangeTodayShowsItsTime()

	/**
	 * Within the last week the weekday, before that the day and month.
	 *
	 * @return void
	 */
	public function testAnOlderChangeShowsTheWeekdayOrTheDate(): void {
		$this->assertSame(
			expected: 'Last changed Wednesday',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-25 10:00:00'))
		);
		$this->assertSame(
			expected: 'Last changed Sunday',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-22 00:00:00'))
		);
		$this->assertSame(
			expected: 'Last changed November 21',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-21 23:59:00'))
		);
		$this->assertSame(
			expected: 'Laatst gewijzigd 18 november',
			actual: FirstPaintMeta::rest(l: $this->l10n(language: 'nl'), input: self::input(updatedAt: '2026-11-18 12:00:00', locale: 'nl_NL'))
		);
	}//end testAnOlderChangeShowsTheWeekdayOrTheDate()

	/**
	 * The reader's rights come first; full rights add nothing; without a usable time only the rights.
	 *
	 * @return void
	 */
	public function testRightsAndAMissingTime(): void {
		$this->assertSame(
			expected: 'view only · Last changed today 11:43 AM',
			actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: '2026-11-28 11:43:00', level: 'view_only'))
		);
		$this->assertSame(
			expected: 'je mag widgets toevoegen',
			actual: FirstPaintMeta::rest(l: $this->l10n(language: 'nl'), input: self::input(updatedAt: '', level: 'add_only', locale: 'nl_NL'))
		);
		$this->assertSame(expected: '', actual: FirstPaintMeta::rest(l: $this->l10n(), input: self::input(updatedAt: 'not a date')));
		$this->assertSame(expected: '', actual: FirstPaintMeta::rest(l: $this->l10n(), input: ['permissionLevel' => 'full']));
	}//end testRightsAndAMissingTime()
}//end class
