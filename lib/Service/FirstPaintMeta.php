<?php

/**
 * FirstPaintMeta
 *
 * The rest of a page header's meta line as the first-paint shell prints it:
 * what the reader may do and when the dashboard last changed, "view only ·
 * Last changed today 11:43". It follows DashboardPageHeader.vue (rightsText,
 * lastChangedText): nothing extra with full rights; the time for a change
 * today, the weekday within the last week, the day and month before that,
 * in the reader's locale. The stored change time is read as the reader's
 * wall clock, as the browser reads it, so the shell and the page say the
 * same.
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
use Exception;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use OCP\IL10N;

/**
 * Formats the rights and last-change part of the shell's meta line.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
 */
class FirstPaintMeta {

	/**
	 * Days before today that still name the weekday.
	 */
	private const WEEKDAY_DAYS = 6;

	/**
	 * The rights and the last change joined by " · ", or '' when neither applies.
	 *
	 * @param IL10N                $l     The app's translations, in the reader's language.
	 * @param array<string, mixed> $input `permissionLevel` (full, add_only, view_only or ''), `updatedAt` (the stored
	 *                                    change time), `timezone` ('' for the server's), `locale` (nl_NL, en_US, ...)
	 *                                    and `now` (a DateTimeImmutable, for tests; the current time otherwise).
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
	 */
	public static function rest(IL10N $l, array $input): string {
		$parts = [
			self::rights(l: $l, level: (string) ($input['permissionLevel'] ?? '')),
			self::changed(l: $l, input: $input),
		];

		return implode(' · ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
	}//end rest()

	/**
	 * What the reader may do: "view only", "you may add widgets", or ''.
	 *
	 * @param IL10N  $l     The translations.
	 * @param string $level The reader's level.
	 *
	 * @return string
	 */
	private static function rights(IL10N $l, string $level): string {
		return match ($level) {
			'view_only' => $l->t('view only'),
			'add_only' => $l->t('you may add widgets'),
			default => '',
		};
	}//end rights()

	/**
	 * When the dashboard last changed, or '' without a usable time.
	 *
	 * @param IL10N                $l     The translations.
	 * @param array<string, mixed> $input See rest().
	 *
	 * @return string
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) FirstPaintGreeting holds the reader's zone and locale as pure functions.
	 */
	private static function changed(IL10N $l, array $input): string {
		$zone = FirstPaintGreeting::zone(timezone: (string) ($input['timezone'] ?? ''));
		$now  = ($input['now'] ?? null);
		if ($now instanceof DateTimeImmutable === false) {
			$now = new DateTimeImmutable();
		}

		$stored = trim((string) ($input['updatedAt'] ?? ''));
		if ($stored === '') {
			return '';
		}

		$now = $now->setTimezone($zone);
		try {
			$changed = new DateTimeImmutable($stored, $zone);
		} catch (Exception) {
			return '';
		}

		$locale = FirstPaintGreeting::locale(input: $input);
		$today = $now->setTime(0, 0);
		if ($changed >= $today) {
			$time = self::format(moment: $changed, locale: $locale, skeleton: 'jjmm');
			return self::fill(text: $l->t('Last changed today {time}'), key: 'time', value: $time);
		}

		$skeleton = 'MMMMd';
		if ($changed >= $today->modify('-'.self::WEEKDAY_DAYS.' days')) {
			$skeleton = 'EEEE';
		}

		$when = self::format(moment: $changed, locale: $locale, skeleton: $skeleton);
		return self::fill(text: $l->t('Last changed {when}'), key: 'when', value: $when);
	}//end changed()

	/**
	 * A moment in the locale's own pattern for a skeleton, as the browser's
	 * Intl formats the same fields.
	 *
	 * @param DateTimeImmutable $moment   The moment, in the reader's zone.
	 * @param string            $locale   The locale.
	 * @param string            $skeleton The fields, e.g. `jjmm` (2-digit hour and minute).
	 *
	 * @return string
	 */
	private static function format(DateTimeImmutable $moment, string $locale, string $skeleton): string {
		$pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);
		if (is_string($pattern) === false) {
			return '';
		}

		// The browser asks for a 2-digit hour ("09:43 AM"); the generator
		// gives the locale's own width ("h:mm a").
		$pattern   = (string) preg_replace('/(?<![hHkK])([hHkK])(?![hHkK])/', '$1$1', $pattern);
		$formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $moment->getTimezone(), null, $pattern);
		$text      = $formatter->format($moment);
		if (is_string($text) === false) {
			return '';
		}

		// ICU puts a narrow no-break space before AM/PM; browsers print a plain one.
		return str_replace("\u{202F}", ' ', $text);
	}//end format()

	/**
	 * Fill one `{key}` placeholder; IL10N::t() fills `%s` only and these
	 * strings keep the browser's form so both sides share one wording.
	 *
	 * @param string $text  The translated text.
	 * @param string $key   The placeholder name.
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private static function fill(string $text, string $key, string $value): string {
		return str_replace('{'.$key.'}', $value, $text);
	}//end fill()
}//end class
