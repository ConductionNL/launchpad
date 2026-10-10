<?php

/**
 * ActivityReportService
 *
 * Activity reporting with a declared purpose (REQ-DWMS-005, REQ-DWMS-006).
 *
 * Reporting on a named person is surveillance-shaped, so the shape is made
 * explicit here rather than left to a policy document. It is off until an
 * administrator turns it on and writes down why; the purpose travels with
 * every report on somebody else; every such read goes to the platform audit
 * trail (`CriticalActionPerformedEvent`, written by the admin_audit app,
 * whose log retention is the instance's configuration); and a person can
 * always read their own, without the switch and without a log entry.
 *
 * The figures count events, never hours, from the existing activity stream,
 * bucketed per day.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\ActivityEventReader;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Activity per person, gated by a purpose and logged.
 */
class ActivityReportService {
	/**
	 * The longest period one report covers, in days.
	 *
	 * @var int
	 */
	public const MAX_DAYS = 366;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings The admin settings store.
	 * @param ActivityEventReader $events The activity stream.
	 * @param IGroupManager $groups Answers who is an administrator.
	 * @param IEventDispatcher $dispatcher Carries the audit event.
	 * @param IDateTimeZone $timeZone The reader's time zone, for day buckets.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly ActivityEventReader $events,
		private readonly IGroupManager $groups,
		private readonly IEventDispatcher $dispatcher,
		private readonly IDateTimeZone $timeZone,
	) {
	}//end __construct()

	/**
	 * Whether reporting on others is on, and the purpose it was turned on for.
	 *
	 * @return array{enabled: bool, purpose: string}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function getPolicy(): array {
		$purpose = trim((string)$this->settings->getValue(key: AdminSettingKey::ACTIVITY_REPORTING_PURPOSE->value, default: ''));
		$enabled = ($this->settings->getValue(key: AdminSettingKey::ACTIVITY_REPORTING_ENABLED->value, default: false) === true);

		// A switch that is on without a purpose is read as off: the purpose
		// is the condition, not a decoration.
		return ['enabled' => ($enabled === true && $purpose !== ''), 'purpose' => $purpose];
	}//end getPolicy()

	/**
	 * Turn reporting on others on or off.
	 *
	 * @param bool $enabled Whether to turn it on.
	 * @param string $purpose Why; required to turn it on.
	 *
	 * @return array{enabled: bool, purpose: string}
	 *
	 * @throws InvalidArgumentException When it is turned on without a purpose.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function setPolicy(bool $enabled, string $purpose): array {
		$purpose = trim($purpose);
		if ($enabled === true && $purpose === '') {
			throw new InvalidArgumentException(message: 'purpose_required');
		}

		$this->settings->setSetting(key: AdminSettingKey::ACTIVITY_REPORTING_PURPOSE->value, value: $purpose);
		$this->settings->setSetting(key: AdminSettingKey::ACTIVITY_REPORTING_ENABLED->value, value: $enabled);

		return $this->getPolicy();
	}//end setPolicy()

	/**
	 * One person's activity for a period, as counts per type and per day.
	 *
	 * @param string $readerId Who asks.
	 * @param string $subjectId Whose activity.
	 * @param string $from First day, Y-m-d.
	 * @param string $until Last day, Y-m-d, inclusive.
	 *
	 * @return array<string, mixed> The report, or `['error' => ...]` when refused.
	 *
	 * @throws InvalidArgumentException On a malformed or too long period.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function report(string $readerId, string $subjectId, string $from, string $until): array {
		$refusal = $this->refusal(readerId: $readerId, subjectId: $subjectId);
		if ($refusal !== null) {
			return $refusal;
		}

		$report = $this->build(subjectId: $subjectId, from: $from, until: $until);
		$this->audit(readerId: $readerId, subjectId: $subjectId, from: $report['from'], until: $report['until'], what: 'read');

		// The purpose travels with every report on somebody else, so the
		// screen can show it; reading your own carries none.
		$report['purpose'] = null;
		if ($readerId !== $subjectId) {
			$report['purpose'] = $this->getPolicy()['purpose'];
		}

		unset($report['cells']);

		return $report;
	}//end report()

	/**
	 * The same report as CSV, one line per day and activity type.
	 *
	 * The lines sum to the per type counts and to the per day counts of
	 * {@see self::report()}, and the last line carries the same total.
	 *
	 * @param string $readerId Who asks.
	 * @param string $subjectId Whose activity.
	 * @param string $from First day, Y-m-d.
	 * @param string $until Last day, Y-m-d, inclusive.
	 *
	 * @return array{csv?: string, filename?: string, error?: string}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function export(string $readerId, string $subjectId, string $from, string $until): array {
		$refusal = $this->refusal(readerId: $readerId, subjectId: $subjectId);
		if ($refusal !== null) {
			return $refusal;
		}

		$report = $this->build(subjectId: $subjectId, from: $from, until: $until);
		$this->audit(readerId: $readerId, subjectId: $subjectId, from: $report['from'], until: $report['until'], what: 'export');

		$lines = [['date', 'activity_type', 'events']];
		foreach ($report['cells'] as $date => $types) {
			foreach ($types as $type => $count) {
				$lines[] = [$date, $type, (string)$count];
			}
		}

		$lines[] = ['total', '', (string)$report['total']];

		$csv = '';
		foreach ($lines as $line) {
			$csv .= implode(',', array_map(fn (string $field): string => $this->csvField(value: $field), $line))."\n";
		}

		return [
			'csv' => $csv,
			'filename' => 'activity-'.$subjectId.'-'.$report['from'].'-'.$report['until'].'.csv',
		];
	}//end export()

	/**
	 * Why a read is refused, or null when it may go ahead.
	 *
	 * @param string $readerId Who asks.
	 * @param string $subjectId Whose activity.
	 *
	 * @return array{error: string}|null
	 */
	private function refusal(string $readerId, string $subjectId): ?array {
		if ($readerId === '' || $subjectId === '') {
			return ['error' => 'forbidden'];
		}

		if ($readerId === $subjectId) {
			// A person can always read their own activity.
			return null;
		}

		if ($this->getPolicy()['enabled'] === false) {
			return ['error' => 'reporting_disabled'];
		}

		if ($this->groups->isAdmin($readerId) === false) {
			return ['error' => 'forbidden'];
		}

		return null;
	}//end refusal()

	/**
	 * Count the person's events for the period.
	 *
	 * @param string $subjectId Whose activity.
	 * @param string $from First day, Y-m-d.
	 * @param string $until Last day, Y-m-d, inclusive.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException On a malformed or too long period.
	 */
	private function build(string $subjectId, string $from, string $until): array {
		$zone = $this->timeZone->getTimeZone();
		$start = $this->day(value: $from, zone: $zone);
		$end = $this->day(value: $until, zone: $zone);
		if ($end < $start) {
			throw new InvalidArgumentException(message: 'period_reversed');
		}

		$days = ((int)$start->diff($end)->days + 1);
		if ($days > self::MAX_DAYS) {
			throw new InvalidArgumentException(message: 'period_too_long');
		}

		$stop = $end->add(new DateInterval('P1D'));
		$perDay = [];
		for ($cursor = $start; $cursor < $stop; $cursor = $cursor->add(new DateInterval('P1D'))) {
			$perDay[$cursor->format('Y-m-d')] = 0;
		}

		$byType = [];
		$cells = [];
		foreach ($this->events->eventsBy(userId: $subjectId, from: $start->getTimestamp(), until: $stop->getTimestamp()) as $event) {
			$date = (new DateTimeImmutable('@'.$event['timestamp']))->setTimezone($zone)->format('Y-m-d');
			if (isset($perDay[$date]) === false) {
				continue;
			}

			$type = $event['type'];
			$perDay[$date]++;
			$byType[$type] = (($byType[$type] ?? 0) + 1);
			$cells[$date][$type] = (($cells[$date][$type] ?? 0) + 1);
		}

		arsort($byType);
		$peak = max(1, max($perDay));
		$calendar = [];
		foreach ($perDay as $date => $count) {
			// Intensity 0 (nothing) to 4 (the busiest day of the period).
			$level = 0;
			if ($count > 0) {
				$level = (int)ceil(($count / $peak) * 4);
			}

			$calendar[] = ['date' => $date, 'count' => $count, 'level' => $level];
		}

		ksort($cells);

		return [
			'subject' => $subjectId,
			'from' => $start->format('Y-m-d'),
			'until' => $end->format('Y-m-d'),
			'byType' => array_map(
				static fn (string $type, int $count): array => ['type' => $type, 'count' => $count],
				array_keys($byType),
				array_values($byType)
			),
			'days' => $calendar,
			'cells' => $cells,
			'total' => array_sum($perDay),
			'available' => $this->events->isAvailable(),
		];
	}//end build()

	/**
	 * Parse a Y-m-d day at midnight in the given zone.
	 *
	 * @param string $value The day.
	 * @param DateTimeZone $zone The zone.
	 *
	 * @return DateTimeImmutable
	 *
	 * @throws InvalidArgumentException On anything but a real Y-m-d day.
	 */
	private function day(string $value, DateTimeZone $zone): DateTimeImmutable {
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
			throw new InvalidArgumentException(message: 'period_invalid');
		}

		try {
			$parsed = new DateTimeImmutable($value.' 00:00:00', $zone);
		} catch (Exception) {
			throw new InvalidArgumentException(message: 'period_invalid');
		}

		// A day that does not exist (30 February) rolls over; refuse it.
		if ($parsed->format('Y-m-d') !== $value) {
			throw new InvalidArgumentException(message: 'period_invalid');
		}

		return $parsed;
	}//end day()

	/**
	 * Write a read of somebody else's activity to the audit trail.
	 *
	 * @param string $readerId Who read.
	 * @param string $subjectId Whose activity.
	 * @param string $from First day.
	 * @param string $until Last day.
	 * @param string $what `read` or `export`.
	 *
	 * @return void
	 */
	private function audit(string $readerId, string $subjectId, string $from, string $until, string $what): void {
		if ($readerId === $subjectId) {
			return;
		}

		$this->dispatcher->dispatchTyped(
			new CriticalActionPerformedEvent(
				'LaunchPad activity report %s: %s read the activity of %s from %s to %s (purpose: %s)',
				[
					'action' => $what,
					'reader' => $readerId,
					'subject' => $subjectId,
					'from' => $from,
					'until' => $until,
					'purpose' => $this->getPolicy()['purpose'],
				]
			)
		);
	}//end audit()

	/**
	 * Quote one CSV field, and defuse spreadsheet formulas.
	 *
	 * @param string $value The field.
	 *
	 * @return string
	 */
	private function csvField(string $value): string {
		if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) === true) {
			$value = "'".$value;
		}

		return '"'.str_replace('"', '""', $value).'"';
	}//end csvField()
}//end class
