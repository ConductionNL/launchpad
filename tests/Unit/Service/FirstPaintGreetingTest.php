<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Service\FirstPaintGreeting;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * The greeting line of the first-paint shell, as CnHeaderWidget words it.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
 */
class FirstPaintGreetingTest extends TestCase {

	/**
	 * Translations that fill in the placeholders, in English or Dutch.
	 *
	 * @param string $language en or nl.
	 *
	 * @return IL10N
	 */
	private function l10n(string $language='en'): IL10N {
		$nl = [
			'Good morning, {name}'   => 'Goedemorgen, {name}',
			'Good afternoon, {name}' => 'Goedemiddag, {name}',
			'Good evening, {name}'   => 'Goedenavond, {name}',
			'Good afternoon'         => 'Goedemiddag',
			'week {number}'          => 'week {number}',
		];
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnCallback(
			// As Nextcloud's IL10N: `{name}` is not filled in by t() itself.
			callback: static function (string $text, array $parameters=[]) use ($language, $nl): string {
				return vsprintf($language === 'nl' ? ($nl[$text] ?? $text) : $text, $parameters);
			}
		);

		return $l;
	}//end l10n()

	/**
	 * The board's line: greeting with the first name, long date, ISO week, in
	 * the reader's language and time zone (13:10 in Amsterdam is 12:10 UTC).
	 *
	 * @return void
	 */
	public function testTheVandaagLineInDutchAndEnglish(): void {
		$content = ['greeting' => true, 'showDate' => true, 'showWeek' => true];
		$now     = new DateTimeImmutable('2026-10-05T11:10:00Z');

		$this->assertSame(
			expected: 'Goedemiddag, Pieter · maandag 5 oktober 2026 · week 41',
			actual: FirstPaintGreeting::line(l: $this->l10n(language: 'nl'), content: $content, displayName: 'Pieter Jansen', now: $now, timezone: 'Europe/Amsterdam', locale: 'nl_NL')
		);
		$this->assertSame(
			expected: 'Good afternoon, Pieter · Monday, October 5, 2026 · week 41',
			actual: FirstPaintGreeting::line(l: $this->l10n(), content: $content, displayName: 'Pieter Jansen', now: $now, timezone: 'Europe/Amsterdam', locale: 'en_US')
		);
	}//end testTheVandaagLineInDutchAndEnglish()

	/**
	 * The hour decides the greeting in the reader's zone, not the server's.
	 *
	 * @return void
	 */
	public function testTheHourIsTheReadersHour(): void {
		$l = $this->l10n();
		$this->assertSame(expected: 'Good morning, Pieter', actual: FirstPaintGreeting::greeting(l: $l, content: ['greeting' => true], displayName: 'Pieter Jansen', hour: 11));
		$this->assertSame(expected: 'Good afternoon, Pieter', actual: FirstPaintGreeting::greeting(l: $l, content: ['greeting' => true], displayName: 'Pieter Jansen', hour: 12));
		$this->assertSame(expected: 'Good evening, Pieter', actual: FirstPaintGreeting::greeting(l: $l, content: ['greeting' => true], displayName: 'Pieter Jansen', hour: 18));

		// 23:30 UTC is 00:30 in Amsterdam (morning) and 19:30 in New York (evening).
		$line = FirstPaintGreeting::line(l: $l, content: ['greeting' => true], displayName: 'Pieter', now: new DateTimeImmutable('2026-10-05T23:30:00Z'), timezone: 'Europe/Amsterdam', locale: 'en_US');
		$this->assertSame(expected: 'Good morning, Pieter', actual: $line);
		$line = FirstPaintGreeting::line(l: $l, content: ['greeting' => true], displayName: 'Pieter', now: new DateTimeImmutable('2026-10-05T23:30:00Z'), timezone: 'America/New_York', locale: 'en_US');
		$this->assertSame(expected: 'Good evening, Pieter', actual: $line);
	}//end testTheHourIsTheReadersHour()

	/**
	 * The full name when asked, no name without one, nothing without a greeting,
	 * and the date alone when only the date is asked for.
	 *
	 * @return void
	 */
	public function testNameAndParts(): void {
		$l   = $this->l10n();
		$now = new DateTimeImmutable('2026-10-05T11:10:00Z');
		$this->assertSame(expected: 'Good afternoon, Pieter Jansen', actual: FirstPaintGreeting::greeting(l: $l, content: ['greeting' => 'full'], displayName: 'Pieter Jansen', hour: 13));
		$this->assertSame(expected: 'Good afternoon', actual: FirstPaintGreeting::greeting(l: $l, content: ['greeting' => true], displayName: '  ', hour: 13));
		$this->assertSame(expected: '', actual: FirstPaintGreeting::greeting(l: $l, content: [], displayName: 'Pieter', hour: 13));
		$this->assertSame(expected: 'Monday, October 5, 2026', actual: FirstPaintGreeting::line(l: $l, content: ['showDate' => true], displayName: 'Pieter', now: $now, timezone: 'Europe/Amsterdam', locale: 'en_US'));
		$this->assertSame(expected: '', actual: FirstPaintGreeting::line(l: $l, content: [], displayName: 'Pieter', now: $now, timezone: 'Nowhere/Unknown', locale: ''));
	}//end testNameAndParts()
}//end class
