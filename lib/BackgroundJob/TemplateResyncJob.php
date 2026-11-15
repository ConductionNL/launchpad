<?php

/**
 * TemplateResyncJob
 *
 * Applies an admin template re-sync asynchronously for large target
 * groups (REQ-RESYNC-005 "Large groups apply asynchronously"). Enqueued
 * by {@see \OCA\LaunchPad\Service\TemplateResyncService::resync()} — a
 * one-off {@see QueuedJob}, not registered at app boot; NC's background
 * job runner removes it from the queue once {@see self::run()} returns.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One-off async apply for a large-target-group template re-sync.
 *
 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-005-re-sync-is-idempotent-audited-async-capable-and-notifies-users
 */
class TemplateResyncJob extends QueuedJob {
	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time Time factory (parent
	 *                           requirement).
	 * @param TemplateResyncService $resyncService The re-sync orchestrator
	 *                                             — {@see
	 *                                             TemplateResyncService::applyResync()}
	 *                                             recomputes the plan fresh
	 *                                             at run time (rather than
	 *                                             deserialising a stale
	 *                                             one), so the apply
	 *                                             reflects the template's
	 *                                             state at the moment the
	 *                                             job actually runs.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly TemplateResyncService $resyncService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
	}//end __construct()

	/**
	 * Apply the re-sync plan for `$argument['templateId']` /
	 * `$argument['strategy']`, writing the audit record and notifying
	 * every affected user on completion.
	 *
	 * Malformed arguments are logged and skipped rather than throwing —
	 * a throw here would make NC's job runner retry indefinitely with the
	 * same bad payload.
	 *
	 * @param mixed $argument `{templateId: int, strategy: string,
	 *                        actingAdminId: string}`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	protected function run($argument): void {
		$request = new TemplateResyncRequest(argument: $argument);

		if ($request->isComplete() === false) {
			$this->logger->warning(
				message: 'launchpad.template_resync.job_skipped reason=invalid_arguments',
				context: ['argument' => $argument]
			);
			return;
		}

		try {
			$result = $this->resyncService->applyResync(
				templateId: $request->templateId,
				strategy: $request->strategy,
				actingAdminId: $request->actingAdminId
			);
			$this->logCompleted(request: $request, result: $result);
		} catch (Throwable $t) {
			$this->logger->error(
				message: 'launchpad.template_resync.job_failed',
				context: [
					'templateId' => $request->templateId,
					'strategy' => $request->strategy,
					'exception' => $t,
				]
			);
		}//end try
	}//end run()

	/**
	 * Log a completed resync with its affected and total copy counts.
	 *
	 * @param TemplateResyncRequest $request The resync arguments.
	 * @param array<string, mixed>  $result  The resync result.
	 *
	 * @return void
	 */
	private function logCompleted(TemplateResyncRequest $request, array $result): void {
		$this->logger->info(
			message: sprintf(
				'launchpad.template_resync.job_completed template=%d strategy=%s affected=%d total=%d',
				$request->templateId,
				$request->strategy,
				$result['affectedCount'],
				$result['totalCopies']
			)
		);
	}//end logCompleted()
}//end class
