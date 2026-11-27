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
	 * The Dutch words of the strings these tests use.
	 */
	private const NL = [
		'Good morning, {name}'   => 'Goedemorgen, {name}',
		'Good afternoon, {name}' => 'Goedemiddag, {name}',
		'Good evening, {name}'   => 'Goedenavond, {name}',
		'Good afternoon'         => 'Goedemiddag',
		'week {number}'          => 'week {number}',
	];

	/**
	 * Translations as Nextcloud's IL10N gives them: `{name}` is not filled in by t().
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
		$l->method('t')->willReturnCallback(
			callback: static fn (string $text, array $parameters=[]): string => vsprintf(
				format: ($words[$text] ?? $text),
				values: $parameters
			)
		);

		return $l;
	}//end l10n()

	/**
	 * A reader.
	 *
	 * @param string $name     The display name.
	 * @param string $timezone The zone.
	 * @param string $locale   The locale.
	 *
	 * @return array<string, string>
	 */
	private static function reader(string $name='Pieter Jansen', string $timezone='Europe/Amsterdam', string $locale='en_US'): array {
		return ['name' => $name, 'timezone' => $timezone, 'locale' => $locale];
	}//end reader()

	/**
	 * The board's line: greeting with the first name, long date, ISO week, in
	 * the reader's language and time zone (13:10 in Amsterdam is 11:10 UTC).
	 *
	 * @return void
	 */
	public function testTheVandaagLineInDutchAndEnglish(): void {
		$content = ['greeting' => true, 'showDate' => true, 'showWeek' => true];
		$now     = new DateTimeImmutable('2026-10-05T11:10:00Z');

		$this->assertSame(
			expected: 'Goedemiddag, Pieter · maandag 5 oktober 2026 · week 41',
			actual: FirstPaintGreeting::line(l: $this->l10n(language: 'nl'), input: self::reader(locale: 'nl_NL') + ['content' => $content, 'now' => $now])
		);
		$this->assertSame(
			expected: 'Good afternoon, Pieter · Monday, October 5, 2026 · week 41',
			actual: FirstPaintGreeting::line(l: $this->l10n(), input: self::reader() + ['content' => $content, 'now' => $now])
		);
	}//end testTheVandaagLineInDutchAndEnglish()

	/**
	 * The hour decides the greeting, in the reader's zone, not the server's.
	 *
	 * @return void
	 */
	public function testTheHourIsTheReadersHour(): void {
		$l       = $this->l10n();
		$content = ['greeting' => true];
		$this->assertSame(
			expected: 'Good morning, Pieter',
			actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => $content, 'name' => 'Pieter Jansen', 'hour' => 11])
		);
		$this->assertSame(
			expected: 'Good afternoon, Pieter',
			actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => $content, 'name' => 'Pieter Jansen', 'hour' => 12])
		);
		$this->assertSame(
			expected: 'Good evening, Pieter',
			actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => $content, 'name' => 'Pieter Jansen', 'hour' => 18])
		);

		// 23:30 UTC is 00:30 in Amsterdam (morning) and 19:30 in New York (evening).
		$late = new DateTimeImmutable('2026-10-05T23:30:00Z');
		$this->assertSame(
			expected: 'Good morning, Pieter',
			actual: FirstPaintGreeting::line(l: $l, input: self::reader() + ['content' => $content, 'now' => $late])
		);
		$this->assertSame(
			expected: 'Good evening, Pieter',
			actual: FirstPaintGreeting::line(l: $l, input: self::reader(timezone: 'America/New_York') + ['content' => $content, 'now' => $late])
		);
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
		$this->assertSame(
			expected: 'Good afternoon, Pieter Jansen',
			actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => ['greeting' => 'full'], 'name' => 'Pieter Jansen', 'hour' => 13])
		);
		$this->assertSame(
			expected: 'Good afternoon',
			actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => ['greeting' => true], 'name' => '  ', 'hour' => 13])
		);
		$this->assertSame(expected: '', actual: FirstPaintGreeting::greeting(l: $l, input: ['content' => [], 'name' => 'Pieter', 'hour' => 13]));
		$this->assertSame(
			expected: 'Monday, October 5, 2026',
			actual: FirstPaintGreeting::line(l: $l, input: self::reader() + ['content' => ['showDate' => true], 'now' => $now])
		);
		$this->assertSame(
			expected: '',
			actual: FirstPaintGreeting::line(l: $l, input: self::reader(timezone: 'Nowhere/Unknown', locale: '') + ['content' => [], 'now' => $now])
		);
	}//end testNameAndParts()
}//end class
