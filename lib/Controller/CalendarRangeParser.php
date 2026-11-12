<?php

/**
 * CalendarRangeParser
 *
 * Parses the `from` / `to` window of a calendar-widget request: both
 * required, both dates, in order, and capped at one year to bound RRULE
 * expansion (design D1).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use DateTimeImmutable;
use Throwable;

/**
 * Parses a calendar-widget request window.
 *
 * Built by {@see WidgetApiController} per request; holds no state.
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */
final class CalendarRangeParser {
	/**
	 * Parse the window, or say why it is refused.
	 *
	 * @param string $from The requested start (ISO 8601).
	 * @param string $to   The requested end (ISO 8601).
	 *
	 * @return array{refusal: string|null, start: DateTimeImmutable|null, end: DateTimeImmutable|null}
	 *
	 * @spec openspec/specs/calendar-widget/spec.md
	 */
	public function parse(string $from, string $to): array {
		if ($from === '' || $to === '') {
			return self::refused(message: 'Both from and to are required ISO 8601 timestamps');
		}

		try {
			$start = new DateTimeImmutable(datetime: $from);
			$end = new DateTimeImmutable(datetime: $to);
		} catch (Throwable $exception) {
			unset($exception);
			return self::refused(message: 'Invalid date format');
		}

		if ($end < $start) {
			return self::refused(message: '`to` must be greater than or equal to `from`');
		}

		return ['refusal' => null, 'start' => $start, 'end' => self::cappedEnd(start: $start, end: $end)];
	}//end parse()

	/**
	 * A refused window.
	 *
	 * @param string $message Why it is refused.
	 *
	 * @return array{refusal: string, start: null, end: null}
	 */
	private static function refused(string $message): array {
		return ['refusal' => $message, 'start' => null, 'end' => null];
	}//end refused()

	/**
	 * Defensive 1-year cap per design D1 to bound RRULE expansion.
	 *
	 * @param DateTimeImmutable $start The range start.
	 * @param DateTimeImmutable $end   The requested range end.
	 *
	 * @return DateTimeImmutable The end, at most one year after the start.
	 */
	private static function cappedEnd(DateTimeImmutable $start, DateTimeImmutable $end): DateTimeImmutable {
		$maxEnd = $start->modify(modifier: '+1 year');
		if ($end > $maxEnd) {
			return $maxEnd;
		}

		return $end;
	}//end cappedEnd()
}//end class
