<?php

/**
 * RecurrenceResolver
 *
 * Computes the next fire moment of a scheduled export from its recurrence
 * rule and its time zone (REQ-SCH-001). Four rule types: `weekly`,
 * `monthly`, `daily` and `cron` (five fields). Times are wall-clock times in
 * the export's time zone: a Monday 09:00 report stays at 09:00 across a DST
 * change. A wall-clock time that falls in a spring-forward gap fires at the
 * same distance past the gap (02:30 becomes 03:30), and one that occurs
 * twice in an autumn fall-back fires once, at its first occurrence. A
 * monthly day past the end of a month is clamped to that month's last day.
 *
 * The next moment is always strictly after the moment asked about, so a
 * runner that was down fires a missed rule once, not once per missed slot.
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
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Next-run computation for scheduled-export recurrence rules.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) A small cron parser and
 *   three calendar rules; splitting them apart would scatter one grammar.
 */
class RecurrenceResolver {

	/**
	 * The recurrence types.
	 *
	 * @var list<string>
	 */
	public const TYPES = ['weekly', 'monthly', 'daily', 'cron'];

	/**
	 * RFC 5545 BYDAY codes by ISO-8601 day number (1 = Monday).
	 *
	 * @var array<string, int>
	 */
	public const DAY_CODES = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

	/**
	 * How many days ahead a rule is searched before it is called unsatisfiable.
	 * Eight years covers a cron rule such as "29 February on a Monday".
	 *
	 * @var int
	 */
	private const SEARCH_DAYS = 2930;

	/**
	 * Cron month and weekday names.
	 *
	 * @var array<string, int>
	 */
	private const CRON_NAMES = [
		'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
		'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
		'SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6,
	];

	/**
	 * The first fire of a recurrence strictly after a moment.
	 *
	 * @param array<string, mixed> $recurrence The rule: `type` plus its fields.
	 * @param string               $timezone   The IANA time zone it runs in.
	 * @param DateTimeInterface    $after      The moment to look past.
	 *
	 * @return DateTimeImmutable The next fire, in the rule's time zone.
	 *
	 * @throws InvalidArgumentException When the rule or the time zone is invalid, or never fires.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
	 */
	public function nextRunAt(array $recurrence, string $timezone, DateTimeInterface $after): DateTimeImmutable {
		$errors = $this->validate(recurrence: $recurrence);
		if ($errors !== []) {
			throw new InvalidArgumentException(implode(' ', $errors));
		}

		$zone  = self::zone(timezone: $timezone);
		$local = DateTimeImmutable::createFromInterface($after)->setTimezone($zone);

		return self::firstAfter(rule: $this->compile(recurrence: $recurrence), local: $local);
	}//end nextRunAt()

	/**
	 * The first moment of a compiled rule after a local time, searched day by
	 * day for SEARCH_DAYS days in that time's zone.
	 *
	 * @param array<string, mixed> $rule  The compiled recurrence.
	 * @param DateTimeImmutable    $local The moment to search after, in the schedule's zone.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws InvalidArgumentException When the rule never fires.
	 */
	private static function firstAfter(array $rule, DateTimeImmutable $local): DateTimeImmutable {
		$zone = $local->getTimezone();
		$day  = $local->setTime(0, 0);

		for ($i = 0; $i < self::SEARCH_DAYS; $i++) {
			$date = $day->modify('+' . $i . ' days');
			if (self::matchesDay(rule: $rule, date: $date) === false) {
				continue;
			}

			foreach ($rule['times'] as [$hour, $minute]) {
				// Built from the wall-clock text, so PHP resolves a gap time
				// forward; an ambiguous one is moved to its first occurrence.
				$candidate = self::firstOccurrence(
					candidate: new DateTimeImmutable($date->format('Y-m-d') . sprintf(' %02d:%02d:00', $hour, $minute), $zone),
					zone: $zone
				);
				if ($candidate > $local) {
					return $candidate;
				}
			}
		}

		throw new InvalidArgumentException('The recurrence never fires.');
	}//end firstAfter()

	/**
	 * The first occurrence of a wall-clock time that a fall-back makes occur
	 * twice. PHP resolves such a time to its second occurrence; the moment one
	 * offset-change earlier shows the same wall clock exactly when the time
	 * was ambiguous.
	 *
	 * @param DateTimeImmutable $candidate The time as PHP resolved it.
	 * @param DateTimeZone      $zone      The rule's time zone.
	 *
	 * @return DateTimeImmutable
	 */
	private static function firstOccurrence(DateTimeImmutable $candidate, DateTimeZone $zone): DateTimeImmutable {
		$timestamp = $candidate->getTimestamp();
		$dayBefore = (new DateTimeImmutable('@' . ($timestamp - 86400)))->setTimezone($zone);
		$shift     = $dayBefore->getOffset() - $candidate->getOffset();
		if ($shift <= 0) {
			return $candidate;
		}

		$earlier = (new DateTimeImmutable('@' . ($timestamp - $shift)))->setTimezone($zone);
		if ($earlier->format('Y-m-d H:i') === $candidate->format('Y-m-d H:i')) {
			return $earlier;
		}

		return $candidate;
	}//end firstOccurrence()

	/**
	 * What is wrong with a recurrence rule, or nothing.
	 *
	 * @param array<string, mixed> $recurrence The rule.
	 *
	 * @return list<string> One sentence per problem.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
	 */
	public function validate(array $recurrence): array {
		$type = $recurrence['type'] ?? null;
		if (in_array($type, self::TYPES, true) === false) {
			return ['The recurrence type must be weekly, monthly, daily or cron.'];
		}

		if ($type === 'cron') {
			try {
				self::parseCron(expression: (string)($recurrence['expression'] ?? ''));
			} catch (InvalidArgumentException $e) {
				return [$e->getMessage()];
			}

			return [];
		}

		$errors = [];
		if (self::timeOf(value: $recurrence['timeOfDay'] ?? null) === null) {
			$errors[] = 'The time of day must be HH:MM.';
		}

		if ($type === 'weekly' && self::weekdays(value: $recurrence['daysOfWeek'] ?? null) === null) {
			$errors[] = 'A weekly recurrence needs one or more days of the week (MO to SU).';
		}

		if ($type === 'monthly' && ($recurrence['lastDay'] ?? false) !== true) {
			$day = $recurrence['dayOfMonth'] ?? null;
			if (is_int($day) === false || $day < 1 || $day > 31) {
				$errors[] = 'A monthly recurrence needs a day of the month from 1 to 31, or the last day.';
			}
		}

		return $errors;
	}//end validate()

	/**
	 * A rule as day filters and sorted wall-clock times.
	 *
	 * @param array<string, mixed> $recurrence A valid rule.
	 *
	 * @return array{type: string, times: list<array{int, int}>, weekdays?: list<int>, dayOfMonth?: int, lastDay?: bool, cron?: array<string, mixed>}
	 */
	private function compile(array $recurrence): array {
		$type = (string)$recurrence['type'];
		if ($type === 'cron') {
			$cron  = self::parseCron(expression: (string)$recurrence['expression']);
			$times = [];
			foreach ($cron['hours'] as $hour) {
				foreach ($cron['minutes'] as $minute) {
					$times[] = [$hour, $minute];
				}
			}

			return ['type' => 'cron', 'times' => $times, 'cron' => $cron];
		}

		$rule = ['type' => $type, 'times' => [self::timeOf(value: $recurrence['timeOfDay'])]];
		if ($type === 'weekly') {
			$rule['weekdays'] = self::weekdays(value: $recurrence['daysOfWeek']);
		}

		if ($type === 'monthly') {
			$rule['lastDay']    = (($recurrence['lastDay'] ?? false) === true);
			$rule['dayOfMonth'] = (int)($recurrence['dayOfMonth'] ?? 31);
		}

		return $rule;
	}//end compile()

	/**
	 * Whether a rule fires on a local date.
	 *
	 * @param array<string, mixed> $rule The compiled rule.
	 * @param DateTimeImmutable    $date The local date (at midnight).
	 *
	 * @return bool
	 */
	private static function matchesDay(array $rule, DateTimeImmutable $date): bool {
		return match ($rule['type']) {
			'weekly' => in_array((int)$date->format('N'), $rule['weekdays'], true),
			'monthly' => (int)$date->format('j') === self::monthlyDay(rule: $rule, date: $date),
			'cron' => self::cronMatchesDay(cron: $rule['cron'], date: $date),
			default => true,
		};
	}//end matchesDay()

	/**
	 * The day of this date's month a monthly rule fires on: its day,
	 * clamped to the month's last day.
	 *
	 * @param array<string, mixed> $rule The compiled monthly rule.
	 * @param DateTimeImmutable    $date A date in the month.
	 *
	 * @return int
	 */
	private static function monthlyDay(array $rule, DateTimeImmutable $date): int {
		$last = (int)$date->format('t');
		if ($rule['lastDay'] === true) {
			return $last;
		}

		return min($rule['dayOfMonth'], $last);
	}//end monthlyDay()

	/**
	 * Whether a cron rule's month, day-of-month and weekday fields match a
	 * date. As in cron, when both day fields are restricted either may match.
	 *
	 * @param array<string, mixed> $cron The parsed cron rule.
	 * @param DateTimeImmutable    $date The local date.
	 *
	 * @return bool
	 */
	private static function cronMatchesDay(array $cron, DateTimeImmutable $date): bool {
		if (in_array((int)$date->format('n'), $cron['months'], true) === false) {
			return false;
		}

		$domMatch = in_array((int)$date->format('j'), $cron['daysOfMonth'], true);
		$dowMatch = in_array((int)$date->format('w'), $cron['daysOfWeek'], true);
		if ($cron['domRestricted'] === true && $cron['dowRestricted'] === true) {
			return $domMatch || $dowMatch;
		}

		return $domMatch && $dowMatch;
	}//end cronMatchesDay()

	/**
	 * A five-field cron expression as sets of allowed values.
	 *
	 * @param string $expression The expression, e.g. `0 9 * * 1`.
	 *
	 * @return array{
	 *     minutes: list<int>, hours: list<int>, daysOfMonth: list<int>, months: list<int>,
	 *     daysOfWeek: list<int>, domRestricted: bool, dowRestricted: bool
	 * }
	 *
	 * @throws InvalidArgumentException When it is not a valid five-field expression.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
	 */
	public static function parseCron(string $expression): array {
		$fields = preg_split('/\s+/', trim($expression));
		if ($fields === false || count($fields) !== 5) {
			throw new InvalidArgumentException('A cron expression has five fields: minute hour day-of-month month day-of-week.');
		}

		$daysOfWeek = array_values(array_unique(array_map(
			static fn (int $d): int => $d % 7,
			self::cronField(field: $fields[4], bounds: [0, 7])
		)));
		sort($daysOfWeek);

		return [
			'minutes' => self::cronField(field: $fields[0], bounds: [0, 59]),
			'hours' => self::cronField(field: $fields[1], bounds: [0, 23]),
			'daysOfMonth' => self::cronField(field: $fields[2], bounds: [1, 31]),
			'months' => self::cronField(field: $fields[3], bounds: [1, 12]),
			'daysOfWeek' => $daysOfWeek,
			'domRestricted' => $fields[2] !== '*',
			'dowRestricted' => $fields[4] !== '*',
		];
	}//end parseCron()

	/**
	 * One cron field as its sorted allowed values: `*`, numbers or names,
	 * ranges `a-b`, steps `/n`, and comma lists of those.
	 *
	 * @param string          $field  The field.
	 * @param array{int, int} $bounds The lowest and the highest value allowed.
	 *
	 * @return list<int>
	 *
	 * @throws InvalidArgumentException When the field is malformed or out of range.
	 */
	private static function cronField(string $field, array $bounds): array {
		[$min, $max] = $bounds;
		$values = [];
		foreach (explode(',', strtoupper($field)) as $part) {
			if (preg_match('/^(\*|[0-9A-Z]+(?:-[0-9A-Z]+)?)(?:\/([0-9]+))?$/', $part, $m) !== 1) {
				throw new InvalidArgumentException(sprintf('The cron field "%s" is not valid.', $field));
			}

			$step = (int)($m[2] ?? 1);
			[$from, $to] = self::cronRange(range: $m[1], bounds: $bounds, stepped: isset($m[2]));
			if ($step < 1 || $from < $min || $to > $max || $from > $to) {
				throw new InvalidArgumentException(sprintf('The cron field "%s" is out of range.', $field));
			}

			for ($value = $from; $value <= $to; $value += $step) {
				$values[$value] = $value;
			}
		}

		sort($values);
		return $values;
	}//end cronField()

	/**
	 * The bounds of one cron range: `*` is the whole field, `a` alone is a
	 * single value (or `a` to the end when stepped), `a-b` is a range.
	 *
	 * @param string          $range   The range part.
	 * @param array{int, int} $bounds  The field's lowest and highest value.
	 * @param bool            $stepped Whether a step follows.
	 *
	 * @return array{int, int}
	 */
	private static function cronRange(string $range, array $bounds, bool $stepped): array {
		[$min, $max] = $bounds;
		if ($range === '*') {
			return [$min, $max];
		}

		$bounds = array_map(static fn (string $v): int => self::cronValue(value: $v), explode('-', $range));
		if (count($bounds) === 2) {
			return [$bounds[0], $bounds[1]];
		}

		if ($stepped === true) {
			return [$bounds[0], $max];
		}

		return [$bounds[0], $bounds[0]];
	}//end cronRange()

	/**
	 * One cron value: a number or a month or weekday name.
	 *
	 * @param string $value The value.
	 *
	 * @return int
	 *
	 * @throws InvalidArgumentException When it is neither.
	 */
	private static function cronValue(string $value): int {
		if (ctype_digit($value) === true) {
			return (int)$value;
		}

		if (isset(self::CRON_NAMES[$value]) === true) {
			return self::CRON_NAMES[$value];
		}

		throw new InvalidArgumentException(sprintf('The cron value "%s" is not valid.', $value));
	}//end cronValue()

	/**
	 * `HH:MM` as hour and minute, or null.
	 *
	 * @param mixed $value The time of day.
	 *
	 * @return array{int, int}|null
	 */
	private static function timeOf(mixed $value): ?array {
		if (is_string($value) === false || preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value, $m) !== 1) {
			return null;
		}

		return [(int)$m[1], (int)$m[2]];
	}//end timeOf()

	/**
	 * Days of the week as ISO day numbers, or null when none is valid. Takes
	 * BYDAY codes (`MO`), English names (`Monday`) or ISO numbers (1-7).
	 *
	 * @param mixed $value The days.
	 *
	 * @return list<int>|null
	 */
	private static function weekdays(mixed $value): ?array {
		if (is_array($value) === false || $value === []) {
			return null;
		}

		$days = [];
		foreach ($value as $day) {
			$number = self::weekday(day: $day);
			if ($number === null) {
				return null;
			}

			$days[$number] = $number;
		}

		sort($days);
		return $days;
	}//end weekdays()

	/**
	 * One day of the week as its ISO number, or null.
	 *
	 * @param mixed $day A BYDAY code, an English name or an ISO number.
	 *
	 * @return int|null
	 */
	private static function weekday(mixed $day): ?int {
		if (is_int($day) === true) {
			if ($day >= 1 && $day <= 7) {
				return $day;
			}

			return null;
		}

		if (is_string($day) === false || strlen($day) < 2) {
			return null;
		}

		$code = strtoupper(substr($day, 0, 2));
		$full = ['MO' => 'MONDAY', 'TU' => 'TUESDAY', 'WE' => 'WEDNESDAY', 'TH' => 'THURSDAY', 'FR' => 'FRIDAY', 'SA' => 'SATURDAY', 'SU' => 'SUNDAY'];
		if (isset(self::DAY_CODES[$code]) === false) {
			return null;
		}

		if (strlen($day) > 2 && strtoupper($day) !== $full[$code]) {
			return null;
		}

		return self::DAY_CODES[$code];
	}//end weekday()

	/**
	 * A time zone by IANA name.
	 *
	 * @param string $timezone The name.
	 *
	 * @return DateTimeZone
	 *
	 * @throws InvalidArgumentException When it is not a time zone.
	 */
	private static function zone(string $timezone): DateTimeZone {
		try {
			return new DateTimeZone($timezone);
		} catch (Exception) {
			throw new InvalidArgumentException(sprintf('Unknown time zone "%s".', $timezone));
		}
	}//end zone()
}//end class
