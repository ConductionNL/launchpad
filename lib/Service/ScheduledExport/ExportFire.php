<?php

/**
 * ExportFire
 *
 * One fire of a scheduled export (REQ-SCH-002/004/008/009), shared by the
 * background runner and "Run now": refuse an overlap and a disabled
 * creator, open the run-row, render once per distinct filter context,
 * deliver each recipient its context's artefacts (a render failure fails
 * only that context's recipients), close the row, notify on permanent
 * failures, and record the outcome on the export. Also the retry side:
 * due retries, and a manual "Retry" of a run's failed deliveries.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
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

namespace OCA\LaunchPad\Service\ScheduledExport;

use DateTimeInterface;
use OCA\LaunchPad\Service\RetryHandler;
use OCP\Files\File;
use Throwable;

/**
 * Fires an export, and retries its deliveries.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-8
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) A fire joins render, dispatch, audit and the export.
 */
class ExportFire {

	public const REASON_OVERLAP = 'The previous run of this export is still running.';

	public const REASON_CREATOR_DISABLED = 'The creator of this export is disabled.';

	/**
	 * Constructor.
	 *
	 * @param ScheduledExportService $exports  The exports.
	 * @param AuditLog               $log      The run-rows.
	 * @param RenderPipeline         $render   Renders the targets.
	 * @param DeliveryDispatcher     $dispatch Delivers the artefacts.
	 * @param FireSupport            $support  Retry policy, notifier, users, Files, clock.
	 */
	public function __construct(
		private readonly ScheduledExportService $exports,
		private readonly AuditLog $log,
		private readonly RenderPipeline $render,
		private readonly DeliveryDispatcher $dispatch,
		private readonly FireSupport $support,
	) {
	}//end __construct()

	/**
	 * Fire an export now.
	 *
	 * @param array<string, mixed> $export The export as stored.
	 * @param FireTrigger          $trigger     The schedule or a user's "Run now".
	 *
	 * @return array<string, mixed> The run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function fire(array $export, FireTrigger $trigger): array {
		$now = $this->support->time->now();
		if ($this->inProgress(exportId: (string)($export['id'] ?? '')) === true) {
			$run = $this->log->skipped(export: $export, trigger: $trigger, status: 'partial', reason: self::REASON_OVERLAP);
			$this->finish(export: $export, run: $run, trigger: $trigger);
			return $run;
		}

		$creator = $this->support->users->get((string)($export['createdBy'] ?? ''));
		if ($creator === null || $creator->isEnabled() === false) {
			$run = $this->log->skipped(export: $export, trigger: $trigger, status: 'failed', reason: self::REASON_CREATOR_DISABLED);
			$this->support->notifier->failed(export: $export + ['creatorEnabled' => false], reason: self::REASON_CREATOR_DISABLED);
			$this->finish(export: $export, run: $run, trigger: $trigger);
			return $run;
		}

		$run    = $this->log->start(export: $export, trigger: $trigger);
		$secret = $this->exports->forDispatch(id: (string)($export['id'] ?? '')) ?? $export;

		$artefacts  = [];
		$deliveries = [];
		foreach ($this->groups(export: $secret) as $group) {
			try {
				$rendered = $this->render->render(export: $export, filterContext: $group['filterContext']);
			} catch (RenderException $e) {
				foreach (array_keys($group['recipients']) as $id) {
					$deliveries[] = $this->failedDelivery(id: (string)$id, recipient: $group['recipients'][$id], error: $e->getMessage());
				}

				continue;
			}

			$indexes   = range(count($artefacts), count($artefacts) + count($rendered) - 1);
			$artefacts = array_merge($artefacts, array_map(static fn (RenderResult $r): array => $r->toAudit(), $rendered));
			$done      = $deliveries;
			$context   = new DeliveryContext(export: $secret, artefacts: $rendered, now: $now);
			$progress  = function (array $sent) use ($run, $done, $artefacts): void {
				$this->log->update(run: $run + ['renderArtefacts' => $artefacts, 'deliveries' => array_merge($done, $sent)]);
			};
			$sent      = $this->dispatch->dispatch(context: $context, recipients: $group['recipients'], afterEach: $progress);
			foreach ($sent as $delivery) {
				$deliveries[] = $delivery + ['artefactIndexes' => $indexes];
			}
		}//end foreach

		$run = $this->log->record(run: $run, artefacts: $artefacts, deliveries: $deliveries);
		$this->notifyPermanent(export: $export, deliveries: $deliveries);
		$this->finish(export: $export, run: $run, trigger: $trigger);

		return $run;
	}//end fire()

	/**
	 * Attempt every delivery whose retry is due, and close the rows that settle.
	 *
	 * @return int The number of deliveries attempted.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function retryDue(): int {
		$now      = $this->support->time->now();
		$attempts = 0;
		foreach ($this->log->allRuns() as $run) {
			$isDue = fn ($delivery): bool => is_array($delivery) === true && $this->support->retry->isDue(delivery: $delivery, now: $now);
			$due   = array_filter((array)($run['deliveries'] ?? []), $isDue);
			if ($due === []) {
				continue;
			}

			$attempts += count($due);
			$this->redeliver(run: $run, indexes: array_keys($due), now: $now);
		}

		return $attempts;
	}//end retryDue()

	/**
	 * A manual "Retry" of every delivery of a run that failed for good, attempted now.
	 *
	 * @param array<string, mixed> $run The run-row.
	 *
	 * @return array<string, mixed> The updated run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function retryRun(array $run): array {
		$now     = $this->support->time->now();
		$indexes = [];
		foreach ((array)($run['deliveries'] ?? []) as $i => $delivery) {
			if (($delivery['status'] ?? null) === RetryHandler::STATUS_FAILED_PERMANENT) {
				$run['deliveries'][$i] = $this->support->retry->rearm(delivery: $delivery, now: $now);
				$indexes[]             = $i;
			}
		}

		if ($indexes === []) {
			return $run;
		}

		return $this->redeliver(run: $run, indexes: $indexes, now: $now);
	}//end retryRun()

	/**
	 * Attempt the given deliveries of a run again with its stored artefacts, then close the row.
	 *
	 * @param array<string, mixed> $run     The run-row.
	 * @param list<int|string>     $indexes The deliveries to attempt.
	 * @param DateTimeInterface    $now     The moment.
	 *
	 * @return array<string, mixed> The saved run-row.
	 */
	private function redeliver(array $run, array $indexes, DateTimeInterface $now): array {
		$export = $this->exports->forDispatch(id: (string)($run['exportId'] ?? ''));
		if ($export === null) {
			return $run;
		}

		$recipients = $this->recipients(export: $export);
		foreach ($indexes as $i) {
			$delivery  = $run['deliveries'][$i];
			$recipient = $recipients[(string)($delivery['recipientId'] ?? '')] ?? ['channel' => (string)($delivery['channel'] ?? '')];
			$artefacts = $this->storedArtefacts(run: $run, indexes: (array)($delivery['artefactIndexes'] ?? []));
			$context   = new DeliveryContext(export: $export, artefacts: $artefacts, now: $now);

			$run['deliveries'][$i] = $this->dispatch->deliver(delivery: $delivery, recipient: $recipient, context: $context);
		}

		$run = $this->log->record(run: $run, artefacts: (array)($run['renderArtefacts'] ?? []), deliveries: (array)$run['deliveries']);
		$this->notifyPermanent(export: $export, deliveries: array_intersect_key((array)$run['deliveries'], array_flip($indexes)));
		if ($run['status'] !== 'running') {
			$this->exports->recordFire(export: $export, fields: ['lastRunStatus' => $run['status']]);
		}

		return $run;
	}//end redeliver()

	/**
	 * The artefacts of a run read back from Files; a purged one is skipped.
	 *
	 * @param array<string, mixed> $run     The run-row.
	 * @param list<int>            $indexes Which of its artefacts.
	 *
	 * @return list<RenderResult>
	 */
	private function storedArtefacts(array $run, array $indexes): array {
		$results = [];
		foreach ($indexes as $index) {
			$meta = $run['renderArtefacts'][$index] ?? null;
			$path = null;
			if (is_array($meta) === true) {
				$path = $meta['storagePath'] ?? null;
			}

			if (is_string($path) === false || $path === '') {
				continue;
			}

			try {
				$file = $this->support->rootFolder->get($path);
			} catch (Throwable) {
				continue;
			}

			if ($file instanceof File) {
				$results[] = new RenderResult(format: (string)$meta['format'], filename: $file->getName(), content: $file->getContent(), storagePath: $path);
			}
		}

		return $results;
	}//end storedArtefacts()

	/**
	 * Enabled recipients grouped by identical filter context (REQ-SCH-004), so each context renders once.
	 *
	 * @param array<string, mixed> $export The export, secrets revealed.
	 *
	 * @return list<array{filterContext: array<string, mixed>, recipients: array<string, array<string, mixed>>}>
	 */
	private function groups(array $export): array {
		$groups = [];
		foreach ($this->recipients(export: $export) as $id => $recipient) {
			if (($recipient['enabled'] ?? true) === false) {
				continue;
			}

			$context = (array)($recipient['filterContext'] ?? []);
			ksort($context);
			$key = (string)json_encode($context);
			$groups[$key] ??= ['filterContext' => $context, 'recipients' => []];
			$groups[$key]['recipients'][$id] = $recipient;
		}

		return array_values($groups);
	}//end groups()

	/**
	 * The export's recipients by id: their own `id`, else their position.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function recipients(array $export): array {
		$byId = [];
		foreach ((array)($export['recipients'] ?? []) as $index => $recipient) {
			if (is_array($recipient) === true) {
				$byId[(string)($recipient['id'] ?? $index)] = $recipient;
			}
		}

		return $byId;
	}//end recipients()

	/**
	 * A delivery that failed before any attempt (its render failed).
	 *
	 * @param string               $id        The recipient id.
	 * @param array<string, mixed> $recipient The recipient.
	 * @param string               $error     Why.
	 *
	 * @return array<string, mixed>
	 */
	private function failedDelivery(string $id, array $recipient, string $error): array {
		return [
			'recipientId' => $id,
			'channel' => (string)($recipient['channel'] ?? ''),
			'attemptCount' => 0,
			'status' => RetryHandler::STATUS_FAILED_PERMANENT,
			'error' => $error,
		];
	}//end failedDelivery()

	/**
	 * Whether a previous run of the export is still rendering or dispatching
	 * (a run that only waits for retries does not block the next fire).
	 *
	 * @param string $exportId The export.
	 *
	 * @return bool
	 */
	private function inProgress(string $exportId): bool {
		foreach ($this->log->runsOf(exportId: $exportId) as $run) {
			if (($run['status'] ?? null) !== 'running') {
				continue;
			}

			$statuses = array_column((array)($run['deliveries'] ?? []), 'status');
			if (in_array(RetryHandler::STATUS_RETRYING, $statuses, true) === false) {
				return true;
			}
		}

		return false;
	}//end inProgress()

	/**
	 * Notify once when any of the deliveries failed for good.
	 *
	 * @param array<string, mixed>               $export     The export.
	 * @param array<int|string, array<string, mixed>> $deliveries The deliveries.
	 *
	 * @return void
	 */
	private function notifyPermanent(array $export, array $deliveries): void {
		$errors = [];
		foreach ($deliveries as $delivery) {
			if (($delivery['status'] ?? null) === RetryHandler::STATUS_FAILED_PERMANENT) {
				$errors[] = (string)($delivery['channel'] ?? '') . ': ' . (string)($delivery['error'] ?? '');
			}
		}

		if ($errors !== []) {
			$this->support->notifier->failed(export: $export, reason: implode('; ', $errors));
		}
	}//end notifyPermanent()

	/**
	 * Record the fire on the export; a scheduled fire also moves `nextRunAt` on.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param array<string, mixed> $run    The run-row.
	 * @param FireTrigger          $trigger     The trigger.
	 *
	 * @return void
	 */
	private function finish(array $export, array $run, FireTrigger $trigger): void {
		$fields = ['lastRunAt' => (string)($run['startedAt'] ?? ''), 'lastRunStatus' => (string)($run['status'] ?? 'failed')];
		if ($trigger->isScheduled() === true) {
			try {
				$fields['nextRunAt'] = $this->exports->nextRunAfter(export: $export, after: $this->support->time->now());
			} catch (Throwable) {
				// A rule that no longer resolves keeps its old nextRunAt; the export fails validation on its next save.
				$fields['lastRunStatus'] = 'failed';
			}
		}

		$this->exports->recordFire(export: $export, fields: $fields);
	}//end finish()
}//end class
