<?php

/**
 * TemplateResyncService
 *
 * Service for the `admin-template-resync` capability — pushes an updated
 * admin template to its already-provisioned user copies (REQ-RESYNC-001..005).
 *
 * Copy independence (REQ-TMPL-006) is a shipped, load-bearing invariant —
 * re-sync is an explicit, opt-in override of it. Each user copy's widget
 * placements are partitioned into "template-origin" (traced back to a
 * source template placement via
 * {@see \OCA\LaunchPad\Db\WidgetPlacement::getTemplatePlacementId()}) and
 * "user-added" (no known origin). The `overwrite` strategy replaces the
 * whole layout with the current template; `merge` reconciles only
 * template-origin placements (add/update/remove) while leaving user-added
 * placements untouched. Compulsory widgets are ordinary template-origin
 * placements, so both strategies reconcile them identically to any other
 * template placement — there is no separate compulsory code path.
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

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\BackgroundJob\TemplateResyncJob;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Kernel\Contracts\TemplateResyncServiceInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Diffs an admin template against its provisioned copies and applies the
 * chosen re-sync strategy.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Orchestrates DB mappers,
 *  the transaction boundary, the audit publisher, the notification
 *  manager, and the async job dispatcher for one cohesive re-sync
 *  operation — splitting further would fragment a single atomic concern.
 */
class TemplateResyncService implements TemplateResyncServiceInterface {
	/**
	 * Replace each copy's layout with the current template layout.
	 */
	public const STRATEGY_OVERWRITE = 'overwrite';

	/**
	 * Reconcile template-origin placements while preserving user-added
	 * placements.
	 */
	public const STRATEGY_MERGE = 'merge';

	/**
	 * Add the template placements a copy lacks, at the first free spot, and
	 * change nothing else (REQ-RESYNC-006).
	 */
	public const STRATEGY_ADD_ONLY = 'add_only';

	/**
	 * The only accepted `strategy` values (REQ-RESYNC-001 "Invalid
	 * strategy is rejected").
	 *
	 * @var string[]
	 */
	public const VALID_STRATEGIES = [
		self::STRATEGY_OVERWRITE,
		self::STRATEGY_MERGE,
		self::STRATEGY_ADD_ONLY,
	];

	/**
	 * Above this many provisioned copies, a real (non-dry-run) re-sync is
	 * applied asynchronously via {@see TemplateResyncJob} instead of
	 * inline within the request, so the admin's HTTP request returns
	 * promptly (REQ-RESYNC-005 "Large groups apply asynchronously").
	 *
	 * @var int
	 */
	public const ASYNC_THRESHOLD = 50;

	/**
	 * Constructor.
	 *
	 * @param ResyncStores         $stores The dashboard and placement mappers, the database and the job list.
	 * @param ActivityPublisher    $activityPublisher Audit-trail publisher (REQ-RESYNC-005).
	 * @param INotificationManager $notificationManager Nextcloud notification manager — used for the per-user
	 *                                                  "your dashboard was updated" notification. Canonical
	 *                                                  fallback per REQ-RESYNC-005; this app has no
	 *                                                  OpenRegister dependency (see the admin-templates
	 *                                                  storage policy), so the `x-openregister-
	 *                                                  notifications` branch is not wired in.
	 * @param LoggerInterface      $logger PSR-3 logger.
	 * @param ResyncFollowUps      $followUps What happens to a changed copy: its personal layers are pruned
	 *                                        (REQ-DWMS-001) and it gets a version by the template (REQ-VERSUI-005).
	 * @param ResyncDiffer         $differ    Diffs a copy against the template (REQ-RESYNC-003, 006, 007).
	 * @param PlacementLayout      $layout    Reads and stamps a placement's layout (REQ-RESYNC-007).
	 *
	 * @spec openspec/changes/archive/2026-11-24-version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	public function __construct(
		private readonly ResyncStores $stores,
		private readonly ActivityPublisher $activityPublisher,
		private readonly INotificationManager $notificationManager,
		private readonly LoggerInterface $logger,
		private readonly ResyncFollowUps $followUps,
		private readonly ResyncDiffer $differ = new ResyncDiffer(),
		private readonly PlacementLayout $layout = new PlacementLayout(),
	) {
	}//end __construct()

	/**
	 * Controller-facing entry point (REQ-RESYNC-001).
	 *
	 * Validates the template + strategy up front (before any dry-run or
	 * mutation work), then dispatches to the dry-run report, the
	 * synchronous apply, or the async job enqueue depending on `$dryRun`
	 * and the provisioned-copy count.
	 *
	 * @param int $templateId The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'`, `'merge'` or `'add_only'`.
	 * @param bool $dryRun When true, compute and return the plan
	 *                     without mutating anything.
	 * @param string $actingAdminId The acting admin's NC user ID (for the
	 *                              audit record).
	 *
	 * @return array<string, mixed> The dry-run report, the applied result,
	 *                              or the async-accepted envelope.
	 *
	 * @throws InvalidArgumentException When the strategy is invalid or the
	 *                                  dashboard is not an admin template.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
	 */
	public function resync(
		int $templateId,
		string $strategy,
		bool $dryRun,
		string $actingAdminId,
	): array {
		$this->assertValidStrategy(strategy: $strategy);
		$this->getValidatedTemplate(templateId: $templateId);
		$request = new TemplateResyncRequest(templateId: $templateId, strategy: $strategy);

		if ($dryRun === true) {
			return $this->dryRunPlan(request: $request);
		}

		return $this->resyncNow(request: $request, actingAdminId: $actingAdminId);
	}//end resync()

	/**
	 * The plan of a dry run, marked as such.
	 *
	 * @param TemplateResyncRequest $request The resync.
	 *
	 * @return array<string, mixed> The plan.
	 */
	private function dryRunPlan(TemplateResyncRequest $request): array {
		$plan = $this->planResync(
			templateId: $request->templateId,
			strategy: $request->strategy
		);
		$plan['dryRun'] = true;
		$plan['async'] = false;

		return $plan;
	}//end dryRunPlan()

	/**
	 * Apply a resync inline, or queue it when the template has more copies
	 * than {@see self::ASYNC_THRESHOLD} (REQ-RESYNC-005).
	 *
	 * @param TemplateResyncRequest $request       The resync.
	 * @param string                $actingAdminId The admin triggering it.
	 *
	 * @return array<string, mixed> The applied or the accepted report.
	 */
	private function resyncNow(TemplateResyncRequest $request, string $actingAdminId): array {
		$totalCopies = count(
			$this->stores->dashboardMapper->findByBasedOnTemplate(
				templateId: $request->templateId
			)
		);

		if ($totalCopies > self::ASYNC_THRESHOLD) {
			$affectedCount = $this->queueResync(request: $request, actingAdminId: $actingAdminId);
			return $this->acceptedReport(request: $request, totalCopies: $totalCopies) + ['affectedCount' => $affectedCount];
		}

		return $this->applyResync(templateId: $request->templateId, strategy: $request->strategy, actingAdminId: $actingAdminId);
	}//end resyncNow()

	/**
	 * Plan a large resync and queue the background job that applies it.
	 *
	 * @param TemplateResyncRequest $request       The resync.
	 * @param string                $actingAdminId The admin triggering it.
	 *
	 * @return mixed The planned affected-copy count.
	 */
	private function queueResync(TemplateResyncRequest $request, string $actingAdminId): mixed {
		$plan = $this->planResync(
			templateId: $request->templateId,
			strategy: $request->strategy
		);

		$this->stores->jobList->add(
			TemplateResyncJob::class,
			[
				'templateId' => $request->templateId,
				'strategy' => $request->strategy,
				'actingAdminId' => $actingAdminId,
			]
		);

		return $plan['affectedCount'];
	}//end queueResync()

	/**
	 * The report of a queued resync, without its affected count.
	 *
	 * @param TemplateResyncRequest $request     The resync.
	 * @param integer               $totalCopies The number of copies.
	 *
	 * @return array<string, mixed> The report.
	 */
	private function acceptedReport(TemplateResyncRequest $request, int $totalCopies): array {
		return [
			'templateId' => $request->templateId,
			'strategy' => $request->strategy,
			'dryRun' => false,
			'async' => true,
			'accepted' => true,
			'totalCopies' => $totalCopies,
		];
	}//end acceptedReport()

	/**
	 * Compute the re-sync plan WITHOUT mutating anything (REQ-RESYNC-002).
	 *
	 * Safe to call directly (bypassing {@see self::resync()}) when the
	 * caller has already validated the template/strategy — used by
	 * {@see self::resync()} itself both for the dry-run response and to
	 * report `affectedCount` in the async-accepted envelope.
	 *
	 * @param int $templateId The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'` or `'merge'`.
	 *
	 * @return array<string, mixed> `{templateId, strategy, totalCopies,
	 *                              affectedCount, copies: [...], gained,
	 *                              lost, changedByOwnerCount}`.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-002-dry-run-reports-the-plan-without-mutating
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
	 */
	public function planResync(int $templateId, string $strategy): array {
		$template = $this->getValidatedTemplate(templateId: $templateId);
		$templatePlacements = $this->stores->placementMapper->findByDashboardId(
			dashboardId: $template->getId()
		);
		$copies = $this->stores->dashboardMapper->findByBasedOnTemplate(
			templateId: $templateId
		);

		$copyReports = [];
		$affectedCount = 0;
		$copyLayouts = [];

		foreach ($copies as $copy) {
			$copyPlacements = $this->stores->placementMapper->findByDashboardId(
				dashboardId: $copy->getId()
			);
			$copyLayouts[] = $copyPlacements;
			$diff = $this->differ->diff(
				templatePlacements: $templatePlacements,
				copyPlacements: $copyPlacements,
				strategy: $strategy,
				columns: $this->columnsOf(copy: $copy)
			);

			$hasChanges = $this->differ->hasChanges(diff: $diff);
			if ($hasChanges === true) {
				$affectedCount++;
			}

			$copyReports[] = $this->summarizeCopy(
				copy: $copy,
				diff: $diff,
				applied: false,
				error: null
			);
		}//end foreach

		return [
			'templateId' => $templateId,
			'strategy' => $strategy,
			'totalCopies' => count($copies),
			'affectedCount' => $affectedCount,
			'copies' => $copyReports,
		] + (new TemplateRolloutSummary(layout: $this->layout))->summarize(
			templatePlacements: $templatePlacements,
			copies: $copyLayouts
		);
	}//end planResync()

	/**
	 * The copy's grid column count; 12 when unset.
	 *
	 * @param Dashboard $copy The copy.
	 *
	 * @return int The column count, at least 1.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	private function columnsOf(Dashboard $copy): int {
		$columns = (int)$copy->getGridColumns();
		if ($columns < 1) {
			return 12;
		}

		return $columns;
	}//end columnsOf()

	/**
	 * Recompute the plan and apply it, per copy, inside its own
	 * transaction (REQ-RESYNC-005). Writes exactly one audit record for
	 * the whole run and notifies every user whose copy actually changed.
	 *
	 * Idempotent: when a copy's diff has no add/update/remove entries the
	 * copy is left untouched (no transaction opened, no notification
	 * queued) — re-running against an unchanged template is a no-op for
	 * every copy (REQ-RESYNC-005 "Re-sync is idempotent").
	 *
	 * Called both inline (small target groups, from {@see self::resync()})
	 * and from {@see TemplateResyncJob::run()} (large target groups).
	 *
	 * @param int $templateId The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'` or `'merge'`.
	 * @param string $actingAdminId The acting admin's NC user ID.
	 *
	 * @return array<string, mixed> `{templateId, strategy, dryRun: false,
	 *                              async: false, totalCopies,
	 *                              affectedCount, copies: [...]}`.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-005-re-sync-is-idempotent-audited-async-capable-and-notifies-users
	 */
	public function applyResync(
		int $templateId,
		string $strategy,
		string $actingAdminId,
	): array {
		$request = new TemplateResyncRequest(templateId: $templateId, strategy: $strategy);
		$template = $this->getValidatedTemplate(templateId: $templateId);
		$tally = $this->resyncCopiesOf(template: $template, request: $request);

		$this->emitAuditEvent(
			template: $template,
			run: ['templateId' => $templateId, 'strategy' => $strategy, 'affectedCount' => count($tally->affected), 'actingAdminId' => $actingAdminId]
		);

		$this->notifyAffected(tally: $tally);

		return $this->appliedReport(request: $request, tally: $tally);
	}//end applyResync()

	/**
	 * Diff and update every copy of a template.
	 *
	 * @param Dashboard             $template The validated template.
	 * @param TemplateResyncRequest $request  The resync.
	 *
	 * @return TemplateResyncTally The per-copy outcome.
	 */
	private function resyncCopiesOf(Dashboard $template, TemplateResyncRequest $request): TemplateResyncTally {
		$tally = new TemplateResyncTally(
			templatePlacements: $this->stores->placementMapper->findByDashboardId(
				dashboardId: $template->getId()
			),
			strategy: $request->strategy,
			templateName: (string)$template->getName()
		);

		foreach ($this->stores->dashboardMapper->findByBasedOnTemplate(templateId: $request->templateId) as $copy) {
			$this->resyncCopy(tally: $tally, copy: $copy);
		}

		return $tally;
	}//end resyncCopiesOf()

	/**
	 * Diff one copy, apply the diff when it has changes, and record the
	 * copy's report; a copy whose change was written is affected.
	 *
	 * @param TemplateResyncTally $tally The run.
	 * @param Dashboard           $copy  The copy.
	 *
	 * @return void
	 */
	private function resyncCopy(TemplateResyncTally $tally, Dashboard $copy): void {
		$diff = $this->differ->diff(
			templatePlacements: $tally->templatePlacements,
			copyPlacements: $this->stores->placementMapper->findByDashboardId(dashboardId: $copy->getId()),
			strategy: $tally->strategy,
			columns: $this->columnsOf(copy: $copy)
		);
		$changed = $this->differ->hasChanges(diff: $diff);
		$error = null;
		if ($changed === true) {
			$error = $this->applyDiffToCopy(copy: $copy, diff: $diff);
		}

		$tally->reports[] = $this->summarizeCopy(copy: $copy, diff: $diff, applied: ($error === null), error: $error);
		if ($changed === true && $error === null) {
			$tally->affected[] = $copy;
			$this->pruneLayers(copy: $copy, diff: $diff);
			$this->recordTemplateVersion(copy: $copy, templateName: $tally->templateName);
		}
	}//end resyncCopy()

	/**
	 * Write the version a re-synced copy gets, by the template. A failure is
	 * logged; the resync itself already landed.
	 *
	 * @param Dashboard $copy         The copy that took the change.
	 * @param string    $templateName The template's name.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
	 */
	private function recordTemplateVersion(Dashboard $copy, string $templateName): void {
		try {
			$this->followUps->recordTemplateVersion(copy: $copy, templateName: $templateName);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad.template_resync.version_failed',
				context: ['dashboardId' => $copy->getId(), 'exception' => $t]
			);
		}
	}//end recordTemplateVersion()

	/**
	 * After a re-sync removed placements, drop the personal-layer entries that named them.
	 *
	 * @param Dashboard $copy The copy that was re-synced.
	 * @param array     $diff The applied diff.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-001-a-personal-layer-over-a-shared-dashboard
	 */
	private function pruneLayers(Dashboard $copy, array $diff): void {
		if ($diff['toRemove'] === []) {
			return;
		}

		$liveIds = array_map(
			static fn (WidgetPlacement $placement): int => (int)$placement->getId(),
			$this->stores->placementMapper->findByDashboardId(dashboardId: $copy->getId())
		);

		try {
			$this->followUps->personalLayers->pruneDashboard(dashboardId: (int)$copy->getId(), liveIds: $liveIds);
		} catch (Throwable $t) {
			// The re-sync itself landed; a stale layer entry is ignored at render time.
			$this->logger->warning(
				message: 'launchpad.template_resync.layer_prune_failed',
				context: ['dashboardId' => $copy->getId(), 'exception' => $t]
			);
		}
	}//end pruneLayers()

	/**
	 * Notify the owner of every affected copy.
	 *
	 * @param TemplateResyncTally $tally The run.
	 *
	 * @return void
	 */
	private function notifyAffected(TemplateResyncTally $tally): void {
		foreach ($tally->affected as $dashboard) {
			$this->notifyUserResync(dashboard: $dashboard);
		}
	}//end notifyAffected()

	/**
	 * The report of an applied resync.
	 *
	 * @param TemplateResyncRequest $request The resync.
	 * @param TemplateResyncTally   $tally   The per-copy outcome.
	 *
	 * @return array<string, mixed> The report.
	 */
	private function appliedReport(TemplateResyncRequest $request, TemplateResyncTally $tally): array {
		return [
			'templateId' => $request->templateId,
			'strategy' => $request->strategy,
			'dryRun' => false,
			'async' => false,
			'totalCopies' => count($tally->reports),
			'affectedCount' => count($tally->affected),
			'copies' => $tally->reports,
		];
	}//end appliedReport()

	/**
	 * Apply one copy's diff inside its own DB transaction. Partial
	 * placement failure rolls that single copy back — the run continues
	 * to the next copy rather than aborting the whole batch
	 * (REQ-RESYNC-005 "Each per-copy apply MUST be transactional").
	 *
	 * @param Dashboard $copy The provisioned copy.
	 * @param array<string, mixed> $diff The diff computed by
	 *                                   {@see ResyncDiffer::diff()}, whose
	 *                                   `@return` documents the exact
	 *                                   shape: `toAdd` and `toRemove` hold
	 *                                   {@see WidgetPlacement} lists and
	 *                                   `toUpdate` holds copy/template
	 *                                   pairs.
	 *
	 * @return string|null The failure message, or null on success.
	 */
	private function applyDiffToCopy(Dashboard $copy, array $diff): ?string {
		$this->stores->db->beginTransaction();

		try {
			foreach ($diff['toAdd'] as $index => $templatePlacement) {
				$this->stores->placementMapper->insert(
					entity: $this->cloneTemplatePlacementInto(
						template: $templatePlacement,
						dashboardId: $copy->getId(),
						spot: ($diff['addSpots'][$index] ?? null)
					)
				);
			}

			foreach ($diff['toUpdate'] as $pair) {
				$ownLayout = $this->layout->rectOf(placement: $pair['copy']);
				$this->applyTemplateFields(copy: $pair['copy'], template: $pair['template']);
				$this->layout->stamp(placement: $pair['copy']);
				if ($pair['keepLayout'] === true) {
					// REQ-RESYNC-007: the owner's position and size stay.
					$this->layout->apply(placement: $pair['copy'], rect: $ownLayout);
				}
				$this->stores->placementMapper->update(entity: $pair['copy']);
			}

			foreach ($diff['toRemove'] as $copyPlacement) {
				$this->stores->placementMapper->delete(entity: $copyPlacement);
			}

			$this->stores->db->commit();
		} catch (Throwable $t) {
			$this->stores->db->rollBack();
			$this->logger->error(
				message: 'launchpad.template_resync.copy_apply_failed',
				context: [
					'dashboardId' => $copy->getId(),
					'exception' => $t,
				]
			);

			return $t->getMessage();
		}//end try

		return null;
	}//end applyDiffToCopy()


	/**
	 * Mutate a copy placement in place so its reconcilable fields match
	 * the template placement's current fields (REQ-RESYNC-004 "position
	 * and flags aligned").
	 *
	 * @param WidgetPlacement $copy The copy's placement (mutated).
	 * @param WidgetPlacement $template The template placement to copy
	 *                                  from.
	 *
	 * @return void
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	private function applyTemplateFields(
		WidgetPlacement $copy,
		WidgetPlacement $template,
	): void {
		$copy->setWidgetId($template->getWidgetId());
		$copy->setGridX($template->getGridX());
		$copy->setGridY($template->getGridY());
		$copy->setGridWidth($template->getGridWidth());
		$copy->setGridHeight($template->getGridHeight());
		$copy->setIsCompulsory($template->getIsCompulsory());
		$copy->setIsVisible($template->getIsVisible());
		$copy->setStyleConfig($template->getStyleConfig());
		$copy->setCustomTitle($template->getCustomTitle());
		$copy->setCustomIcon($template->getCustomIcon());
		$copy->setShowTitle($template->getShowTitle());
		$copy->setSortOrder($template->getSortOrder());
		$copy->setContent($template->getContent());
		$copy->setTileType($template->getTileType());
		$copy->setTileTitle($template->getTileTitle());
		$copy->setTileIcon($template->getTileIcon());
		$copy->setTileIconType($template->getTileIconType());
		$copy->setTileBackgroundColor($template->getTileBackgroundColor());
		$copy->setTileTextColor($template->getTileTextColor());
		$copy->setTileLinkType($template->getTileLinkType());
		$copy->setTileLinkValue($template->getTileLinkValue());
		$copy->setRequiresAcknowledgement($template->getRequiresAcknowledgement());
		$copy->setAcknowledgementPrompt($template->getAcknowledgementPrompt());
		$copy->setAcknowledgementDeadline($template->getAcknowledgementDeadline());
		$copy->setReacknowledgeOnChange($template->getReacknowledgeOnChange());
		$copy->setAcknowledgementContentVersion($template->getAcknowledgementContentVersion());
		$copy->setAnnouncementKey($template->getAnnouncementKey());
		$copy->setRuleMatch($template->getRuleMatch());
		$copy->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);
	}//end applyTemplateFields()

	/**
	 * Build a fresh placement for `$dashboardId` cloned from a template
	 * placement, stamping the origin key so future re-syncs recognise it
	 * as template-origin.
	 *
	 * @param WidgetPlacement $template The template placement to clone.
	 * @param int $dashboardId The target copy's dashboard ID.
	 * @param array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}|null $spot Where it goes
	 *                                                                          (`add_only`); null keeps
	 *                                                                          the template's spot.
	 *
	 * @return WidgetPlacement The new (uninserted) placement entity.
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	private function cloneTemplatePlacementInto(
		WidgetPlacement $template,
		int $dashboardId,
		?array $spot=null,
	): WidgetPlacement {
		$clone = new WidgetPlacement();
		$clone->setDashboardId($dashboardId);
		$clone->setWidgetId($template->getWidgetId());
		$clone->setGridX($template->getGridX());
		$clone->setGridY($template->getGridY());
		$clone->setGridWidth($template->getGridWidth());
		$clone->setGridHeight($template->getGridHeight());
		$clone->setIsCompulsory($template->getIsCompulsory());
		$clone->setIsVisible($template->getIsVisible());
		$clone->setStyleConfig($template->getStyleConfig());
		$clone->setCustomTitle($template->getCustomTitle());
		$clone->setCustomIcon($template->getCustomIcon());
		$clone->setShowTitle($template->getShowTitle());
		$clone->setSortOrder($template->getSortOrder());
		$clone->setContent($template->getContent());
		$clone->setTileType($template->getTileType());
		$clone->setTileTitle($template->getTileTitle());
		$clone->setTileIcon($template->getTileIcon());
		$clone->setTileIconType($template->getTileIconType());
		$clone->setTileBackgroundColor($template->getTileBackgroundColor());
		$clone->setTileTextColor($template->getTileTextColor());
		$clone->setTileLinkType($template->getTileLinkType());
		$clone->setTileLinkValue($template->getTileLinkValue());
		$clone->setRequiresAcknowledgement($template->getRequiresAcknowledgement());
		$clone->setAcknowledgementPrompt($template->getAcknowledgementPrompt());
		$clone->setAcknowledgementDeadline($template->getAcknowledgementDeadline());
		$clone->setReacknowledgeOnChange($template->getReacknowledgeOnChange());
		$clone->setAcknowledgementContentVersion($template->getAcknowledgementContentVersion());
		$clone->setAnnouncementKey($template->getAnnouncementKey());
		$clone->setRuleMatch($template->getRuleMatch());
		$clone->setTemplatePlacementId($template->getId());
		if ($spot !== null) {
			$this->layout->apply(placement: $clone, rect: $spot);
		}

		$this->layout->stamp(placement: $clone);

		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$clone->setCreatedAt($now);
		$clone->setUpdatedAt($now);

		return $clone;
	}//end cloneTemplatePlacementInto()

	// ---------------------------------------------------------------
	// Validation
	// ---------------------------------------------------------------

	/**
	 * Validate the `strategy` param (REQ-RESYNC-001 "Invalid strategy is
	 * rejected").
	 *
	 * @param string $strategy The candidate strategy.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When not `overwrite`, `merge` or `add_only`.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	private function assertValidStrategy(string $strategy): void {
		if (in_array(needle: $strategy, haystack: self::VALID_STRATEGIES, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'Invalid strategy: only "overwrite", "merge" and "add_only" are accepted'
			);
		}
	}//end assertValidStrategy()

	/**
	 * Load a dashboard by ID and assert it is an admin template
	 * (REQ-RESYNC-001 "Re-sync rejects a non-template dashboard").
	 *
	 * @param int $templateId The candidate template's dashboard ID.
	 *
	 * @return Dashboard The validated template.
	 *
	 * @throws InvalidArgumentException When the ID is unknown or the
	 *                                  dashboard is not an admin
	 *                                  template.
	 */
	private function getValidatedTemplate(int $templateId): Dashboard {
		try {
			$template = $this->stores->dashboardMapper->find(id: $templateId);
		} catch (DoesNotExistException $e) {
			throw new InvalidArgumentException(
				message: 'Not an admin template',
				previous: $e
			);
		}

		if ($template->getType() !== Dashboard::TYPE_ADMIN_TEMPLATE) {
			throw new InvalidArgumentException(
				message: 'Not an admin template'
			);
		}

		return $template;
	}//end getValidatedTemplate()

	// ---------------------------------------------------------------
	// Reporting / audit / notification
	// ---------------------------------------------------------------

	/**
	 * Summarise one copy's diff for the report returned to the admin.
	 *
	 * @param Dashboard $copy The provisioned copy.
	 * @param array<string, mixed> $diff The diff computed by
	 *                                   {@see
	 *                                   ResyncDiffer::diff()}.
	 * @param bool $applied Whether the diff was applied
	 *                      (false for dry-run reports).
	 * @param string|null $error The failure message, or null.
	 *
	 * @return array<string, mixed> The per-copy report row.
	 */
	private function summarizeCopy(
		Dashboard $copy,
		array $diff,
		bool $applied,
		?string $error,
	): array {
		return [
			'dashboardId' => $copy->getId(),
			'dashboardUuid' => $copy->getUuid(),
			'userId' => $copy->getUserId(),
			'toAdd' => count($diff['toAdd']),
			'toUpdate' => count($diff['toUpdate']),
			'toRemove' => count($diff['toRemove']),
			'toPreserve' => count($diff['toPreserve']),
			'hasChanges' => $this->differ->hasChanges(diff: $diff),
			'applied' => $applied,
			'error' => $error,
		];
	}//end summarizeCopy()

	/**
	 * Emit the single audit Activity event summarising the whole re-sync
	 * run (REQ-RESYNC-005 "Audit record is written on a real run").
	 *
	 * Mirrors {@see BulkOperationService}'s audit pattern: one row via
	 * {@see ActivityPublisher::publish()} with a synthetic object identity
	 * and the operation's structured payload in `extraParams`. Any
	 * Activity failure is swallowed by the publisher so an audit-log
	 * problem never rolls back the re-sync itself.
	 *
	 * @param Dashboard            $template The template that was re-synced.
	 * @param array<string, mixed> $run      The run: `templateId` (the template's dashboard ID),
	 *                                       `strategy` (`'overwrite'` or `'merge'`), `affectedCount`
	 *                                       (copies actually changed) and `actingAdminId`.
	 *
	 * @return void
	 */
	private function emitAuditEvent(Dashboard $template, array $run): void {
		$templateId    = $run['templateId'];
		$templateName  = (string)$template->getName();
		$strategy      = $run['strategy'];
		$affectedCount = $run['affectedCount'];
		$actingAdminId = $run['actingAdminId'];
		try {
			$row = new ActivityRow(
				type: Extension::EVENT_UPDATED,
				actorUserId: $actingAdminId,
				dashboard: new ActivitySubject(
					uuid: 'template-resync-' . $templateId,
					name: 'Template resync: ' . $templateName . ' (' . $strategy . ', ' . $affectedCount . ' copies)'
				),
				extraParams: [
					'templateResync' => true,
					'templateId' => $templateId,
					'strategy' => $strategy,
					'affectedCount' => $affectedCount,
				]
			);
			$this->activityPublisher->publish(row: $row, recipientUserId: $actingAdminId);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad.template_resync.audit_event_failed',
				context: [
					'templateId' => $templateId,
					'exception' => $t,
				]
			);
		}//end try
	}//end emitAuditEvent()

	/**
	 * Notify the copy's owner that an administrator updated their
	 * dashboard (REQ-RESYNC-005 "Affected users are notified").
	 *
	 * Dispatched via the canonical Nextcloud `INotification` manager —
	 * the same pattern {@see DashboardShareService} uses for
	 * `dashboard_shared` / `dashboard_ownership_transferred`. LaunchPad
	 * has no OpenRegister install-time dependency (see the
	 * `admin-templates` storage policy), so the `x-openregister-
	 * notifications` dialect branch described in REQ-RESYNC-005 is not
	 * applicable here — this IS the "otherwise" branch.
	 *
	 * @param Dashboard $dashboard The user's provisioned copy.
	 *
	 * @return void
	 */
	private function notifyUserResync(Dashboard $dashboard): void {
		$userId = (string)$dashboard->getUserId();
		if ($userId === '') {
			return;
		}

		try {
			$notification = $this->notificationManager->createNotification();
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$notification->setApp('launchpad')
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				->setUser($userId)
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				->setDateTime(new DateTime())
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				->setObject('dashboard', (string)$dashboard->getId())
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				->setSubject('dashboard_template_resynced', [(string)$dashboard->getName()]);

			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->notificationManager->notify($notification);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad.template_resync.notify_failed',
				context: [
					'dashboardId' => $dashboard->getId(),
					'userId' => $userId,
					'exception' => $t,
				]
			);
		}//end try
	}//end notifyUserResync()
}//end class
