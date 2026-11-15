<?php

/**
 * StatusReportService
 *
 * A dated status report on a body of work: prefilled by the product, finished by a person (REQ-DWMS-004).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StatusReport
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

namespace OCA\LaunchPad\Service\StatusReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Service\Reporting\ObjectRows;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;

/**
 * Builds and keeps status reports.
 *
 * A body of work is the objects of one schema in one register, optionally
 * filtered, each with a status and optionally a due date, plus the deadline
 * of the whole. The product computes the figures and prefills the rating
 * and the summary; a person may rewrite the rating and the summary before
 * keeping it. The kept report records, per value, whether it was computed
 * or written by a person. The figures are always computed again when the
 * report is kept, so a person cannot write them.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-004-a-dated-status-report-with-a-health-rating
 */
class StatusReportService {

	/**
	 * The health ratings.
	 */
	public const HEALTH = ['on-track', 'at-risk', 'off-track'];

	/**
	 * The status values that mean an item is closed, unless the request names its own.
	 */
	public const CLOSED_VALUES = ['closed', 'done', 'completed', 'resolved', 'gesloten', 'afgehandeld'];

	/**
	 * Within this many days of the deadline, less than NEAR_DONE percent closed is a risk.
	 */
	public const NEAR_DAYS = 14;

	/**
	 * The share closed, in percent, a body of work near its deadline needs to be on track.
	 */
	public const NEAR_DONE = 75;

	/**
	 * The longest summary kept, in characters.
	 */
	public const MAX_SUMMARY = 5000;

	/**
	 * Constructor.
	 *
	 * @param ObjectRows        $rows  The objects of the body of work.
	 * @param StatusReportStore $store Keeps a report.
	 * @param ITimeFactory      $time  Today.
	 * @param IL10N             $l10n  The prefilled summary's language.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public function __construct(
		private readonly ObjectRows $rows,
		private readonly StatusReportStore $store,
		private readonly ITimeFactory $time,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * The prefilled report, not kept: rating, summary and figures all computed.
	 *
	 * @param array<string,mixed> $subject The body of work: register, schema, title, deadline, filters, statusProperty, closedValues, dueProperty.
	 *
	 * @return array<string,mixed> The draft.
	 *
	 * @throws StatusReportRefusedException When the subject is incomplete or its objects cannot be read.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public function draft(array $subject): array {
		$subject = self::subject(input: $subject);
		$today   = $this->today();
		$rows    = $this->rows->rows(register: $subject['register'], schema: $subject['schema'], filters: $subject['filters']);
		if ($rows === null) {
			throw new StatusReportRefusedException(
				message: 'The objects of ' . $subject['register'] . '/' . $subject['schema'] . ' cannot be read.',
				statusCode: 409
			);
		}

		$figures = self::figures(rows: $rows, subject: $subject, today: $today);
		$health  = self::health(figures: $figures);

		return [
			'date'       => $today->format('Y-m-d'),
			'subject'    => $subject,
			'health'     => $health,
			'summary'    => $this->summary(figures: $figures, subject: $subject),
			'figures'    => $figures,
			'provenance' => self::provenance(figures: $figures, healthWritten: false, summaryWritten: false),
		];
	}//end draft()

	/**
	 * Keep a report: computed again, with the person's rating and summary where they wrote one.
	 *
	 * @param string              $userId  Who keeps it.
	 * @param array<string,mixed> $subject The body of work, as for draft().
	 * @param string|null         $health  The rating the person chose; null keeps the computed one.
	 * @param string|null         $summary The summary the person wrote; null keeps the computed one.
	 *
	 * @return array<string,mixed> The kept report, as stored.
	 *
	 * @throws StatusReportRefusedException When the input is not usable or the report cannot be stored.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
	 */
	public function keep(string $userId, array $subject, ?string $health=null, ?string $summary=null): array {
		if ($health !== null && in_array($health, self::HEALTH, true) === false) {
			throw new StatusReportRefusedException(message: 'The health rating must be one of: ' . implode(', ', self::HEALTH) . '.');
		}

		if ($summary !== null) {
			$summary = trim($summary);
			if ($summary === '' || mb_strlen($summary) > self::MAX_SUMMARY) {
				throw new StatusReportRefusedException(message: 'The summary must have between 1 and ' . self::MAX_SUMMARY . ' characters.');
			}
		}

		$report         = $this->draft(subject: $subject);
		$healthWritten  = ($health !== null && $health !== $report['health']);
		$summaryWritten = ($summary !== null && $summary !== $report['summary']);
		if ($healthWritten === true) {
			$report['health'] = $health;
		}

		if ($summaryWritten === true) {
			$report['summary'] = $summary;
		}

		$report['provenance'] = self::provenance(figures: $report['figures'], healthWritten: $healthWritten, summaryWritten: $summaryWritten);
		$report['author']     = $userId;
		$report['keptAt']     = (new DateTimeImmutable('@' . $this->time->getTime()))->format(DATE_ATOM);

		$stored = $this->store->save(report: $report);
		if ($stored === null) {
			throw new StatusReportRefusedException(message: 'The status report could not be stored; OpenRegister is needed to keep it.', statusCode: 503);
		}

		return $stored;
	}//end keep()

	/**
	 * Counts of the body of work: total, open, closed, overdue, the share closed and the days to the deadline.
	 *
	 * @param list<array<string,mixed>> $rows    The objects.
	 * @param array<string,mixed>       $subject The normalised subject.
	 * @param DateTimeImmutable         $today   Today, at midnight UTC.
	 *
	 * @return array{total:int,open:int,closed:int,overdue:int,percentClosed:int,daysToDeadline:int|null}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public static function figures(array $rows, array $subject, DateTimeImmutable $today): array {
		$closedValues = array_map('mb_strtolower', $subject['closedValues']);
		$total        = 0;
		$closed       = 0;
		$overdue      = 0;
		foreach ($rows as $row) {
			$total++;
			$status = mb_strtolower(trim((string) self::value(row: $row, property: $subject['statusProperty'])));
			if (in_array($status, $closedValues, true) === true) {
				$closed++;
				continue;
			}

			$due = self::day(value: self::value(row: $row, property: $subject['dueProperty']));
			if ($due !== null && $due < $today) {
				$overdue++;
			}
		}

		$days = null;
		if ($subject['deadline'] !== null) {
			$deadline = self::day(value: $subject['deadline']);
			$days     = (int) $today->diff($deadline)->format('%r%a');
		}

		$percent = 0;
		if ($total > 0) {
			$percent = (int) floor(($closed * 100) / $total);
		}

		return [
			'total'          => $total,
			'open'           => ($total - $closed),
			'closed'         => $closed,
			'overdue'        => $overdue,
			'percentClosed'  => $percent,
			'daysToDeadline' => $days,
		];
	}//end figures()

	/**
	 * The rating the figures give.
	 *
	 * Off track: the deadline passed with items open, or more than a quarter of the open items are overdue.
	 * At risk: any item is overdue, or the deadline is within NEAR_DAYS days with less than NEAR_DONE percent closed.
	 * On track otherwise.
	 *
	 * @param array<string,int|null> $figures The figures.
	 *
	 * @return string One of HEALTH.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public static function health(array $figures): string {
		$days = $figures['daysToDeadline'];
		if (($days !== null && $days < 0 && $figures['open'] > 0) || ($figures['overdue'] * 4) > $figures['open']) {
			return 'off-track';
		}

		$nearAndBehind = ($days !== null && $days <= self::NEAR_DAYS && $figures['percentClosed'] < self::NEAR_DONE && $figures['open'] > 0);
		if ($figures['overdue'] > 0 || $nearAndBehind === true) {
			return 'at-risk';
		}

		return 'on-track';
	}//end health()

	/**
	 * Which values were computed and which a person wrote.
	 *
	 * @param array<string,mixed> $figures        The figures (always computed).
	 * @param bool                $healthWritten  Whether a person chose the rating.
	 * @param bool                $summaryWritten Whether a person wrote the summary.
	 *
	 * @return array{date:string,health:string,summary:string,figures:array<string,string>}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.1
	 */
	public static function provenance(array $figures, bool $healthWritten, bool $summaryWritten): array {
		$origin = ['computed', 'written'];

		return [
			'date'    => 'computed',
			'health'  => $origin[(int) $healthWritten],
			'summary' => $origin[(int) $summaryWritten],
			'figures' => array_fill_keys(array_keys($figures), 'computed'),
		];
	}//end provenance()

	/**
	 * The request's body of work, checked and with its defaults.
	 *
	 * @param array<string,mixed> $input The request.
	 *
	 * @return array{register:string,schema:string,title:string,deadline:string|null,filters:array<string,mixed>,statusProperty:string,closedValues:list<string>,dueProperty:string}
	 *
	 * @throws StatusReportRefusedException When the register, the schema or the deadline is not usable.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public static function subject(array $input): array {
		$register = trim((string) ($input['register'] ?? ''));
		$schema   = trim((string) ($input['schema'] ?? ''));
		if ($register === '' || $schema === '') {
			throw new StatusReportRefusedException(message: 'A status report needs the register and the schema of the body of work.');
		}

		$deadline = trim((string) ($input['deadline'] ?? ''));
		if ($deadline === '') {
			$deadline = null;
		}

		if ($deadline !== null && self::day(value: $deadline) === null) {
			throw new StatusReportRefusedException(message: 'The deadline must be a day, as YYYY-MM-DD.');
		}

		$closed = array_values(
			array_filter(array_map('strval', (array) ($input['closedValues'] ?? [])), static fn (string $value): bool => trim($value) !== '')
		);
		if ($closed === []) {
			$closed = self::CLOSED_VALUES;
		}

		$filters = $input['filters'] ?? [];
		if (is_array($filters) === false) {
			$filters = [];
		}

		return [
			'register'       => $register,
			'schema'         => $schema,
			'title'          => trim((string) ($input['title'] ?? '')),
			'deadline'       => $deadline,
			'filters'        => $filters,
			'statusProperty' => self::nameOr(value: $input['statusProperty'] ?? '', default: 'status'),
			'closedValues'   => $closed,
			'dueProperty'    => self::nameOr(value: $input['dueProperty'] ?? '', default: 'deadline'),
		];
	}//end subject()

	/**
	 * A property name from the request, or the default when it names none.
	 *
	 * @param mixed  $value   The request's value.
	 * @param string $default The default.
	 *
	 * @return string
	 */
	private static function nameOr(mixed $value, string $default): string {
		$name = trim((string) $value);
		if ($name === '') {
			return $default;
		}

		return $name;
	}//end nameOr()

	/**
	 * The prefilled summary.
	 *
	 * @param array<string,int|null> $figures The figures.
	 * @param array<string,mixed>    $subject The subject.
	 *
	 * @return string
	 */
	private function summary(array $figures, array $subject): string {
		$sentences = [
			$this->l10n->t('%1$s of %2$s items closed (%3$s%%).', [$figures['closed'], $figures['total'], $figures['percentClosed']]),
			$this->l10n->t('%1$s open, of which %2$s overdue.', [$figures['open'], $figures['overdue']]),
		];
		if ($subject['deadline'] !== null) {
			$sentences[] = $this->l10n->t('Deadline %1$s, %2$s days from today.', [$subject['deadline'], $figures['daysToDeadline']]);
		}

		return implode(' ', $sentences);
	}//end summary()

	/**
	 * Today at midnight UTC.
	 *
	 * @return DateTimeImmutable
	 */
	private function today(): DateTimeImmutable {
		return (new DateTimeImmutable('@' . $this->time->getTime()))->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
	}//end today()

	/**
	 * A property of an object, also when nested one level under `object`.
	 *
	 * @param array<string,mixed> $row      The object.
	 * @param string              $property The property.
	 *
	 * @return mixed
	 */
	private static function value(array $row, string $property): mixed {
		if (array_key_exists($property, $row) === true) {
			return $row[$property];
		}

		if (is_array($row['object'] ?? null) === true) {
			return ($row['object'][$property] ?? null);
		}

		return null;
	}//end value()

	/**
	 * The day a value names, at midnight UTC, or null when it names none.
	 *
	 * @param mixed $value A date (Y-m-d) or a date-time.
	 *
	 * @return DateTimeImmutable|null
	 */
	private static function day(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
			return null;
		}

		$day = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('UTC'));
		if ($day === false || $day->format('Y-m-d') !== substr($value, 0, 10)) {
			return null;
		}

		return $day;
	}//end day()
}//end class
