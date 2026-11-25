<?php

/**
 * StoredDate
 *
 * Reads a date as people and directories store it (`DD-MM-YYYY`,
 * `DD/MM/YYYY`, `DD.MM.YYYY` or ISO) as an ISO-8601 calendar date, for the
 * people widget's birthdates.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * A stored date read as `YYYY-MM-DD`.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
final class StoredDate {
	/**
	 * Best-effort conversion of a stored birthdate string to ISO-8601
	 * (`YYYY-MM-DD`). Accepts common locale formats (`DD-MM-YYYY`,
	 * `DD/MM/YYYY`, `DD.MM.YYYY`, `YYYY-MM-DD`). Returns null on failure
	 * so callers can omit the field cleanly.
	 *
	 * @param string|null $raw Stored birthdate value.
	 *
	 * @return string|null ISO date string or null when the input cannot
	 *                     be parsed.
	 */
	public static function toIso(?string $raw): ?string {
		$trimmed = trim(string: (string)$raw);
		if ($trimmed === '') {
			return null;
		}

		// Already ISO?
		if (preg_match(pattern: '/^\d{4}-\d{2}-\d{2}$/', subject: $trimmed) === 1) {
			return $trimmed;
		}

		return self::isoFromSeparatedDate(date: $trimmed);
	}//end toIso()

	/**
	 * Try each of the `-`, `/` and `.` separators on a date string and
	 * answer the first split into three segments that forms a real date.
	 *
	 * @param string $date The trimmed stored date.
	 *
	 * @return string|null `YYYY-MM-DD`, or null when no separator fits.
	 */
	private static function isoFromSeparatedDate(string $date): ?string {
		foreach (['-', '/', '.'] as $sep) {
			$parts = explode(separator: $sep, string: $date);
			if (count(value: $parts) !== 3) {
				continue;
			}

			$iso = self::isoFromDateParts(parts: $parts);
			if ($iso !== null) {
				return $iso;
			}
		}

		return null;
	}//end isoFromSeparatedDate()

	/**
	 * Assemble an ISO-8601 date from three already-split date segments.
	 *
	 * Heuristic: the 4-digit segment is the year, and its position decides
	 * whether the input is DMY (`DD-MM-YYYY`) or YMD (`YYYY-MM-DD`). Any
	 * triple that carries no 4-digit year, holds a non-numeric segment, or
	 * does not denote a real calendar date yields null so the caller can
	 * try the next separator.
	 *
	 * @param array $parts Exactly three segments from a single `explode()`.
	 *
	 * @return string|null `YYYY-MM-DD`, or null when the triple is not a
	 *                     valid date.
	 */
	private static function isoFromDateParts(array $parts): ?string {
		$yearMonthDay = self::yearMonthDayOrder(parts: $parts);
		if ($yearMonthDay === null || self::allDigits(segments: $yearMonthDay) === false) {
			return null;
		}

		[$year, $month, $day] = $yearMonthDay;
		if (checkdate(month: (int)$month, day: (int)$day, year: (int)$year) === false) {
			return null;
		}

		return sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
	}//end isoFromDateParts()

	/**
	 * The segments in year, month, day order: YMD when the first segment
	 * has four characters, else DMY when the last one has; null when
	 * neither does.
	 *
	 * @param array $parts Exactly three date segments.
	 *
	 * @return array<int,string>|null The year, month and day segments, or null.
	 */
	private static function yearMonthDayOrder(array $parts): ?array {
		if (strlen(string: $parts[0]) === 4) {
			return [$parts[0], $parts[1], $parts[2]];
		}

		if (strlen(string: $parts[2]) === 4) {
			return [$parts[2], $parts[1], $parts[0]];
		}

		return null;
	}//end yearMonthDayOrder()

	/**
	 * Whether every segment consists of digits only.
	 *
	 * @param array<int,string> $segments The date segments.
	 *
	 * @return bool True when all are digit strings.
	 */
	private static function allDigits(array $segments): bool {
		foreach ($segments as $segment) {
			if (ctype_digit(text: $segment) === false) {
				return false;
			}
		}

		return true;
	}//end allDigits()
}//end class
