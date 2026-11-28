<?php

/**
 * FirstPaintGreeting
 *
 * The greeting line of a greeting header (LpVandaag) as the first-paint shell
 * prints it: "Goedemiddag, Pieter · vrijdag 27 november 2026 · week 48". It
 * follows nextcloud-vue's CnHeaderWidget (greetingText, dateText,
 * greetingLineText): morning before 12, afternoon before 18, evening after;
 * the first name unless the header asks for the full name; the long date in
 * the reader's locale; the ISO week when `showWeek` is set. The hour and the
 * date are taken in the reader's time zone.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use OCP\IL10N;

/**
 * Formats the greeting line of the first-paint shell.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
 */
class FirstPaintGreeting {

	/**
	 * The greeting line, or '' when the header shows none.
	 *
	 * @param IL10N                $l     The app's translations, in the reader's language.
	 * @param array<string, mixed> $input `content` (the header placement's: greeting, showDate, showWeek), `name` (the
	 *                                    reader's display name), `timezone` ('' for the server's), `locale` (nl_NL,
	 *                                    en_US) and `now` (DateTimeImmutable, the moment of the request).
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
	 */
	public static function line(IL10N $l, array $input): string {
		$content = (array) ($input['content'] ?? []);
		$now     = $input['now'] ?? null;
		if (($now instanceof DateTimeImmutable) === false) {
			$now = new DateTimeImmutable();
		}

		$local = $now->setTimezone(self::zone(timezone: (string) ($input['timezone'] ?? '')));
		$parts = [
			self::greeting(l: $l, input: ['content' => $content, 'name' => (string) ($input['name'] ?? ''), 'hour' => (int) $local->format('G')]),
			self::date(moment: $local, input: ['content' => $content, 'locale' => (string) ($input['locale'] ?? '')]),
		];
		if (($content['showWeek'] ?? false) === true) {
			$parts[] = self::fill(text: $l->t('week {number}'), values: ['number' => (int) $local->format('W')]);
		}

		return implode(' · ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
	}//end line()

	/**
	 * "Good afternoon, Pieter", by the hour, or '' when the header greets nobody.
	 *
	 * @param IL10N                $l     The translations.
	 * @param array<string, mixed> $input `content` (the header content), `name` (the reader's display name) and `hour`
	 *                                    (in the reader's time zone, 0 to 23).
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
	 */
	public static function greeting(IL10N $l, array $input): string {
		$mode = $input['content']['greeting'] ?? false;
		if ($mode !== true && $mode !== 'full' && $mode !== 'first') {
			return '';
		}

		$name = trim((string) ($input['name'] ?? ''));
		if ($name !== '' && $mode !== 'full') {
			$name = (string) preg_split('/\s+/', $name)[0];
		}

		$hour = (int) ($input['hour'] ?? 12);
		$part = 'evening';
		if ($hour < 12) {
			$part = 'morning';
		} else if ($hour < 18) {
			$part = 'afternoon';
		}

		$words = [
			'morning'   => [$l->t('Good morning, {name}'), $l->t('Good morning')],
			'afternoon' => [$l->t('Good afternoon, {name}'), $l->t('Good afternoon')],
			'evening'   => [$l->t('Good evening, {name}'), $l->t('Good evening')],
		][$part];

		if ($name === '') {
			return $words[1];
		}

		return self::fill(text: $words[0], values: ['name' => $name]);
	}//end greeting()

	/**
	 * Fill `{key}` placeholders. Nextcloud's IL10N::t() fills `%s` only;
	 * these strings keep the browser's `{name}` form so the two sides share
	 * one wording.
	 *
	 * @param string                    $text   The translated text.
	 * @param array<string, int|string> $values The values by key.
	 *
	 * @return string
	 */
	private static function fill(string $text, array $values): string {
		$pairs = [];
		foreach ($values as $key => $value) {
			$pairs['{'.$key.'}'] = (string) $value;
		}

		return strtr($text, $pairs);
	}//end fill()

	/**
	 * The long date, "vrijdag 27 november 2026", or '' without `showDate`.
	 *
	 * @param DateTimeImmutable    $moment The moment, in the reader's time zone.
	 * @param array<string, mixed> $input  `content` (the header content) and `locale`.
	 *
	 * @return string
	 */
	private static function date(DateTimeImmutable $moment, array $input): string {
		if (($input['content']['showDate'] ?? false) !== true) {
			return '';
		}

		$formatter = new IntlDateFormatter(self::locale(input: $input), IntlDateFormatter::FULL, IntlDateFormatter::NONE, $moment->getTimezone());
		$text      = $formatter->format($moment);
		if (is_string($text) === false) {
			return '';
		}

		return $text;
	}//end date()

	/**
	 * The reader's locale from the input, en_US when it is unknown.
	 *
	 * @param array<string, mixed> $input With `locale`.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
	 */
	public static function locale(array $input): string {
		$locale = (string) ($input['locale'] ?? '');
		if ($locale === '') {
			return 'en_US';
		}

		return $locale;
	}//end locale()

	/**
	 * The reader's zone, the server's when it is unknown.
	 *
	 * @param string $timezone The zone name.
	 *
	 * @return DateTimeZone
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
	 */
	public static function zone(string $timezone): DateTimeZone {
		if ($timezone !== '') {
			try {
				return new DateTimeZone($timezone);
			} catch (Exception) {
				// An unknown zone name: the server's zone below.
			}
		}

		return new DateTimeZone(date_default_timezone_get());
	}//end zone()
}//end class
