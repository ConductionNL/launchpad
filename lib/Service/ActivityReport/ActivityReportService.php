<?php

/**
 * ActivityReportService
 *
 * A person's activity as counts per type and a calendar per day, gated and logged (REQ-DWMS-005, REQ-DWMS-006).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\ActivityReport
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ActivityReport;

use DateTimeImmutable;
use DateTimeZone;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Reports a person's activity over a period.
 *
 * A person always reads their own, whether or not activity reporting is on,
 * and that read is not logged. Anybody else's activity is refused until an
 * administrator turned activity reporting on with a purpose, is read only by
 * an administrator, carries the purpose, and every such read is written to
 * Nextcloud's audit log with the reader, the subject and the period. The
 * figures are counts per activity type and per day (UTC), from the activity
 * events; nothing reports hours. The CSV carries the same numbers.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-005-activity-reporting-names-its-purpose
 */
class ActivityReportService {

	/**
	 * The longest period one report covers, in days.
	 */
	public const MAX_DAYS = 366;

	/**
	 * What the refusal says while activity reporting is off.
	 */
	public const DISABLED_MESSAGE = 'Activity reporting on named people is off. '
		. 'An administrator can turn it on in the Launchpad admin settings, recording the purpose it serves.';

	/**
	 * Constructor.
	 *
	 * @param ActivityReportingPolicy $policy     Whether reporting on others is on, and why.
	 * @param ActivityEventReader     $events     The activity events.
	 * @param IEventDispatcher        $dispatcher Carries the read to the audit log.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.3
	 */
	public function __construct(
		private readonly ActivityReportingPolicy $policy,
		private readonly ActivityEventReader $events,
		private readonly IEventDispatcher $dispatcher,
	) {
	}//end __construct()

	/**
	 * The activity of a person over a period, for a reader.
	 *
	 * @param string $readerId      Who reads.
	 * @param bool   $readerIsAdmin Whether the reader is an administrator.
	 * @param string $subjectId     Whose activity.
	 * @param string $from          The first day (Y-m-d).
	 * @param string $to            The last day (Y-m-d).
	 *
	 * @return array<string,mixed> The report: subject, period, purpose, counts per type, per day and the total.
	 *
	 * @throws ActivityReportRefusedException When the reader may not have it, or the period is not one.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.4
	 */
	public function report(string $readerId, bool $readerIsAdmin, string $subjectId, string $from, string $to): array {
		$subjectId = trim($subjectId);
		if ($subjectId === '') {
			$subjectId = $readerId;
		}

		$self = ($subjectId === $readerId);
		if ($self === false) {
			$this->requireMayReadOthers(readerIsAdmin: $readerIsAdmin);
		}

		[$start, $end] = self::period(from: $from, to: $to);
		if ($self === false) {
			$this->dispatcher->dispatchTyped(
				new CriticalActionPerformedEvent(
					'Launchpad activity report of "%s" for %s to %s read by "%s" (purpose: %s)',
					[$subjectId, $start->format('Y-m-d'), $end->format('Y-m-d'), $readerId, $this->policy->purpose()]
				)
			);
		}

		$events = $this->events->events(
			userId: $subjectId,
			from: $start->getTimestamp(),
			until: $end->modify('+1 day')->getTimestamp()
		);

		$purpose = null;
		if ($self === false || $this->policy->isEnabled() === true) {
			$purpose = $this->policy->purpose();
		}

		return [
			'subject'   => $subjectId,
			'self'      => $self,
			'from'      => $start->format('Y-m-d'),
			'to'        => $end->format('Y-m-d'),
			'timezone'  => 'UTC',
			'purpose'   => $purpose,
			'available' => ($events !== null),
		] + self::figures(events: ($events ?? []));
	}//end report()

	/**
	 * The report as CSV: one row per activity type, one per active day, and the total.
	 *
	 * @param array<string,mixed> $report A report from report().
	 *
	 * @return string The CSV.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.6
	 */
	public static function csv(array $report): string {
		$out = fopen('php://temp', 'r+');
		fputcsv($out, ['kind', 'key', 'count'], ',', '"', '');
		if (is_string($report['purpose'] ?? null) === true && $report['purpose'] !== '') {
			fputcsv($out, ['purpose', $report['purpose'], ''], ',', '"', '');
		}

		fputcsv($out, ['subject', (string) ($report['subject'] ?? ''), ''], ',', '"', '');
		fputcsv($out, ['period', ($report['from'] ?? '') . '/' . ($report['to'] ?? ''), ''], ',', '"', '');
		foreach ((array) ($report['counts'] ?? []) as $type => $count) {
			fputcsv($out, ['type', (string) $type, (string) $count], ',', '"', '');
		}

		foreach ((array) ($report['days'] ?? []) as $day => $count) {
			fputcsv($out, ['day', (string) $day, (string) $count], ',', '"', '');
		}

		fputcsv($out, ['total', '', (string) ($report['total'] ?? 0)], ',', '"', '');
		rewind($out);
		$csv = (string) stream_get_contents($out);
		fclose($out);

		return $csv;
	}//end csv()

	/**
	 * Counts per type, per day and in total; a day without activity is absent.
	 *
	 * @param list<array{type:string,timestamp:int}> $events The events.
	 *
	 * @return array{counts:array<string,int>,days:array<string,int>,total:int} The figures.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.5
	 */
	public static function figures(array $events): array {
		$counts = [];
		$days   = [];
		foreach ($events as $event) {
			$counts[$event['type']] = (($counts[$event['type']] ?? 0) + 1);
			$day        = gmdate('Y-m-d', $event['timestamp']);
			$days[$day] = (($days[$day] ?? 0) + 1);
		}

		ksort($counts);
		ksort($days);

		return ['counts' => $counts, 'days' => $days, 'total' => count($events)];
	}//end figures()

	/**
	 * Refuse a read of somebody else's activity unless reporting is on and the reader is an administrator.
	 *
	 * @param bool $readerIsAdmin Whether the reader is an administrator.
	 *
	 * @return void
	 *
	 * @throws ActivityReportRefusedException When either is not so.
	 */
	private function requireMayReadOthers(bool $readerIsAdmin): void {
		if ($this->policy->isEnabled() === false) {
			throw new ActivityReportRefusedException(message: self::DISABLED_MESSAGE, statusCode: 403, disabled: true);
		}

		if ($readerIsAdmin === false) {
			throw new ActivityReportRefusedException(message: 'Only an administrator can read another person\'s activity.', statusCode: 403);
		}
	}//end requireMayReadOthers()

	/**
	 * The first and last day of a period, in UTC.
	 *
	 * @param string $from The first day (Y-m-d).
	 * @param string $to   The last day (Y-m-d).
	 *
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable} The two days at midnight.
	 *
	 * @throws ActivityReportRefusedException When either is not a date, they are reversed, or the period is too long.
	 */
	private static function period(string $from, string $to): array {
		$utc   = new DateTimeZone('UTC');
		$start = DateTimeImmutable::createFromFormat('!Y-m-d', trim($from), $utc);
		$end   = DateTimeImmutable::createFromFormat('!Y-m-d', trim($to), $utc);
		if ($start === false || $end === false || $start->format('Y-m-d') !== trim($from) || $end->format('Y-m-d') !== trim($to)) {
			throw new ActivityReportRefusedException(message: 'The period needs a first and a last day, as YYYY-MM-DD.', statusCode: 400);
		}

		if ($end < $start || (int) $start->diff($end)->days >= self::MAX_DAYS) {
			throw new ActivityReportRefusedException(
				message: 'The period must run forwards and cover at most ' . self::MAX_DAYS . ' days.',
				statusCode: 400
			);
		}

		return [$start, $end];
	}//end period()
}//end class
