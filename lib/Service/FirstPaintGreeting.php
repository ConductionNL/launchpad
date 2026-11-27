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
	 * @param IL10N                $l           The app's translations, in the reader's language.
	 * @param array<string, mixed> $content     The header placement's content (greeting, showDate, showWeek).
	 * @param string               $displayName The reader's display name.
	 * @param DateTimeImmutable    $now         The moment of the request.
	 * @param string               $timezone    The reader's time zone, '' for the server's.
	 * @param string               $locale      The reader's locale, such as nl_NL or en_US.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
	 */
	public static function line(
		IL10N $l,
		array $content,
		string $displayName,
		DateTimeImmutable $now,
		string $timezone,
		string $locale
	): string {
		$local = $now->setTimezone(self::zone(timezone: $timezone));
		$parts = [
			self::greeting(l: $l, content: $content, displayName: $displayName, hour: (int) $local->format('G')),
			self::date(content: $content, moment: $local, locale: $locale),
		];
		if (($content['showWeek'] ?? false) === true) {
			$parts[] = self::fill(text: $l->t('week {number}'), values: ['number' => (int) $local->format('W')]);
		}

		return implode(' · ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
	}//end line()

	/**
	 * "Good afternoon, Pieter", by the hour, or '' when the header greets nobody.
	 *
	 * @param IL10N                $l           The translations.
	 * @param array<string, mixed> $content     The header content.
	 * @param string               $displayName The reader's display name.
	 * @param int                  $hour        The hour in the reader's time zone, 0 to 23.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
	 */
	public static function greeting(IL10N $l, array $content, string $displayName, int $hour): string {
		$mode = $content['greeting'] ?? false;
		if ($mode !== true && $mode !== 'full' && $mode !== 'first') {
			return '';
		}

		$name = trim($displayName);
		if ($name !== '' && $mode !== 'full') {
			$name = (string) preg_split('/\s+/', $name)[0];
		}

		if ($hour < 12) {
			return $name !== '' ? self::fill(text: $l->t('Good morning, {name}'), values: ['name' => $name]) : $l->t('Good morning');
		}

		if ($hour < 18) {
			return $name !== '' ? self::fill(text: $l->t('Good afternoon, {name}'), values: ['name' => $name]) : $l->t('Good afternoon');
		}

		return $name !== '' ? self::fill(text: $l->t('Good evening, {name}'), values: ['name' => $name]) : $l->t('Good evening');
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
	 * @param array<string, mixed> $content The header content.
	 * @param DateTimeImmutable    $moment  The moment, in the reader's time zone.
	 * @param string               $locale  The reader's locale.
	 *
	 * @return string
	 */
	private static function date(array $content, DateTimeImmutable $moment, string $locale): string {
		if (($content['showDate'] ?? false) !== true) {
			return '';
		}

		$formatter = new IntlDateFormatter(
			$locale !== '' ? $locale : 'en_US',
			IntlDateFormatter::FULL,
			IntlDateFormatter::NONE,
			$moment->getTimezone()
		);
		$text = $formatter->format($moment);

		return is_string($text) === true ? $text : '';
	}//end date()

	/**
	 * The reader's zone, the server's when it is unknown.
	 *
	 * @param string $timezone The zone name.
	 *
	 * @return DateTimeZone
	 */
	private static function zone(string $timezone): DateTimeZone {
		if ($timezone !== '' && in_array($timezone, DateTimeZone::listIdentifiers(), true) === true) {
			return new DateTimeZone($timezone);
		}

		return new DateTimeZone(date_default_timezone_get());
	}//end zone()
}//end class
