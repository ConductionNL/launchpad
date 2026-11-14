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
class TemplateResyncService {
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
	 * The only accepted `strategy` values (REQ-RESYNC-001 "Invalid
	 * strategy is rejected").
	 *
	 * @var string[]
	 */
	public const VALID_STRATEGIES = [
		self::STRATEGY_OVERWRITE,
		self::STRATEGY_MERGE,
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
	 * @param PersonalLayerService $personalLayers Drops personal-layer entries for removed placements (REQ-DWMS-001).
	 */
	public function __construct(
		private readonly ResyncStores $stores,
		private readonly ActivityPublisher $activityPublisher,
		private readonly INotificationManager $notificationManager,
		private readonly LoggerInterface $logger,
		private readonly PersonalLayerService $personalLayers,
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
	 * @param string $strategy `'overwrite'` or `'merge'`.
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
	 *                              affectedCount, copies: [...]}`.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-002-dry-run-reports-the-plan-without-mutating
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

		foreach ($copies as $copy) {
			$copyPlacements = $this->stores->placementMapper->findByDashboardId(
				dashboardId: $copy->getId()
			);
			$diff = $this->diffCopy(
				templatePlacements: $templatePlacements,
				copyPlacements: $copyPlacements,
				strategy: $strategy
			);

			$hasChanges = $this->diffHasChanges(diff: $diff);
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
		];
	}//end planResync()

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
			strategy: $request->strategy
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
		$diff = $this->diffCopy(
			templatePlacements: $tally->templatePlacements,
			copyPlacements: $this->stores->placementMapper->findByDashboardId(dashboardId: $copy->getId()),
			strategy: $tally->strategy
		);
		$changed = $this->diffHasChanges(diff: $diff);
		$error = null;
		if ($changed === true) {
			$error = $this->applyDiffToCopy(copy: $copy, diff: $diff);
		}

		$tally->reports[] = $this->summarizeCopy(copy: $copy, diff: $diff, applied: ($error === null), error: $error);
		if ($changed === true && $error === null) {
			$tally->affected[] = $copy;
			$this->pruneLayers(copy: $copy, diff: $diff);
		}
	}//end resyncCopy()

	/**
	 * After a re-sync removed placements, drop the personal-layer entries that named them.
	 *
	 * @param Dashboard $copy The copy that was re-synced.
	 * @param array     $diff The applied diff.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-001-a-personal-layer-over-a-shared-dashboard
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
			$this->personalLayers->pruneDashboard(dashboardId: (int)$copy->getId(), liveIds: $liveIds);
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
	 *                                   {@see self::diffCopy()}, whose
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
			foreach ($diff['toAdd'] as $templatePlacement) {
				$this->stores->placementMapper->insert(
					entity: $this->cloneTemplatePlacementInto(
						template: $templatePlacement,
						dashboardId: $copy->getId()
					)
				);
			}

			foreach ($diff['toUpdate'] as $pair) {
				$this->applyTemplateFields(
					copy: $pair['copy'],
					template: $pair['template']
				);
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

	// ---------------------------------------------------------------
	// Diff engine
	// ---------------------------------------------------------------

	/**
	 * Partition one copy's placements against the template's current
	 * placements (REQ-RESYNC-003, REQ-RESYNC-004).
	 *
	 * Matching is by {@see WidgetPlacement::getTemplatePlacementId()}:
	 *
	 * - Matched + fields differ  → `toUpdate` (reconcile onto the copy).
	 * - Matched + fields equal   → `toPreserve` (no-op, both strategies).
	 * - Template-origin but the
	 *   matching template placement
	 *   no longer exists           → `toRemove` (admin deleted it from the
	 *                                template — both strategies).
	 * - No known origin
	 *   (user-added)                → `toPreserve` under `merge`,
	 *                                `toRemove` under `overwrite`.
	 * - A template placement with
	 *   no matching copy placement  → `toAdd` (new template placement, or
	 *                                a compulsory widget the user removed
	 *                                — restored either way).
	 *
	 * Compulsory widgets receive no special-case handling: they are
	 * ordinary template placements, so the `toAdd` (restore) / `toUpdate`
	 * (align position+flags) branches above already reconcile them under
	 * BOTH strategies, satisfying REQ-RESYNC-004 without a separate code
	 * path.
	 *
	 * @param WidgetPlacement[] $templatePlacements The template's current
	 *                                              placements.
	 * @param WidgetPlacement[] $copyPlacements The copy's current
	 *                                          placements.
	 * @param string $strategy `'overwrite'` or
	 *                         `'merge'`.
	 *
	 * @return array{
	 *     toAdd: WidgetPlacement[],
	 *     toUpdate: array<int, array{copy: WidgetPlacement, template: WidgetPlacement}>,
	 *     toRemove: WidgetPlacement[],
	 *     toPreserve: WidgetPlacement[]
	 * }
	 */
	private function diffCopy(
		array $templatePlacements,
		array $copyPlacements,
		string $strategy,
	): array {
		$templateById = [];
		foreach ($templatePlacements as $tp) {
			$templateById[$tp->getId()] = $tp;
		}

		$matchedTemplateIds = [];
		$toUpdate = [];
		$toRemove = [];
		$toPreserve = [];

		foreach ($copyPlacements as $cp) {
			$originId = $cp->getTemplatePlacementId();

			if ($originId !== null && isset($templateById[$originId]) === true) {
				$matchedTemplateIds[$originId] = true;
				$tp = $templateById[$originId];

				if ($this->placementDiffers(copy: $cp, template: $tp) === true) {
					$toUpdate[] = [
						'copy' => $cp,
						'template' => $tp,
					];
					continue;
				}

				$toPreserve[] = $cp;
				continue;
			}

			if ($originId !== null && isset($templateById[$originId]) === false) {
				// Template-origin, but the admin has since deleted this
				// placement from the template — remove it under both
				// strategies (REQ-RESYNC-003 "Template widget removed by
				// admin is removed under merge").
				$toRemove[] = $cp;
				continue;
			}

			// No known origin — a genuinely user-added placement.
			if ($strategy === self::STRATEGY_OVERWRITE) {
				$toRemove[] = $cp;
				continue;
			}

			$toPreserve[] = $cp;
		}//end foreach

		return [
			'toAdd' => $this->unmatchedTemplatePlacements(
				templatePlacements: $templatePlacements,
				matchedTemplateIds: $matchedTemplateIds
			),
			'toUpdate' => $toUpdate,
			'toRemove' => $toRemove,
			'toPreserve' => $toPreserve,
		];
	}//end diffCopy()

	/**
	 * Template placements the copy has no counterpart for — i.e. the
	 * additions a re-sync must make.
	 *
	 * @param WidgetPlacement[] $templatePlacements The template's current placements.
	 * @param array<int, boolean> $matchedTemplateIds Set of template placement IDs
	 *                                                already matched to a copy
	 *                                                placement, keyed by ID.
	 *
	 * @return WidgetPlacement[] The unmatched template placements, in input order.
	 */
	private function unmatchedTemplatePlacements(array $templatePlacements, array $matchedTemplateIds): array {
		$toAdd = [];
		foreach ($templatePlacements as $tp) {
			if (isset($matchedTemplateIds[$tp->getId()]) === false) {
				$toAdd[] = $tp;
			}
		}

		return $toAdd;
	}//end unmatchedTemplatePlacements()

	/**
	 * Whether a diff carries any mutation (add/update/remove). Used to
	 * decide `affectedCount` and to skip untouched copies entirely
	 * (REQ-RESYNC-005 idempotency).
	 *
	 * @param array<string, mixed> $diff The diff computed by
	 *                                   {@see self::diffCopy()}.
	 *
	 * @return bool True when applying the diff would change the copy.
	 */
	private function diffHasChanges(array $diff): bool {
		return count($diff['toAdd']) > 0
			|| count($diff['toUpdate']) > 0
			|| count($diff['toRemove']) > 0;
	}//end diffHasChanges()

	/**
	 * The reconcilable field snapshot compared for equality and applied
	 * on update. Kept as one array so the equality check
	 * ({@see self::placementDiffers()}) and the field-copy
	 * ({@see self::applyTemplateFields()} / {@see self::cloneTemplatePlacementInto()})
	 * can never silently drift apart.
	 *
	 * @param WidgetPlacement $placement The placement to snapshot.
	 *
	 * @return array<string, mixed> The comparable field values.
	 */
	private function templateFieldSnapshot(WidgetPlacement $placement): array {
		return [
			'widgetId' => $placement->getWidgetId(),
			'gridX' => $placement->getGridX(),
			'gridY' => $placement->getGridY(),
			'gridWidth' => $placement->getGridWidth(),
			'gridHeight' => $placement->getGridHeight(),
			'isCompulsory' => $placement->getIsCompulsory(),
			'isVisible' => $placement->getIsVisible(),
			'styleConfig' => $placement->getStyleConfig(),
			'customTitle' => $placement->getCustomTitle(),
			'customIcon' => $placement->getCustomIcon(),
			'showTitle' => $placement->getShowTitle(),
			'sortOrder' => $placement->getSortOrder(),
			'content' => $placement->getContent(),
			'tileType' => $placement->getTileType(),
			'tileTitle' => $placement->getTileTitle(),
			'tileIcon' => $placement->getTileIcon(),
			'tileIconType' => $placement->getTileIconType(),
			'tileBackgroundColor' => $placement->getTileBackgroundColor(),
			'tileTextColor' => $placement->getTileTextColor(),
			'tileLinkType' => $placement->getTileLinkType(),
			'tileLinkValue' => $placement->getTileLinkValue(),
			'requiresAcknowledgement' => $placement->getRequiresAcknowledgement(),
			'acknowledgementPrompt' => $placement->getAcknowledgementPrompt(),
			'acknowledgementDeadline' => $placement->getAcknowledgementDeadline(),
			'reacknowledgeOnChange' => $placement->getReacknowledgeOnChange(),
			'acknowledgementContentVersion' => $placement->getAcknowledgementContentVersion(),
			'announcementKey' => $placement->getAnnouncementKey(),
		];
	}//end templateFieldSnapshot()

	/**
	 * Whether a copy placement's reconcilable fields differ from its
	 * matching template placement's current fields.
	 *
	 * @param WidgetPlacement $copy The copy's placement.
	 * @param WidgetPlacement $template The matching template placement.
	 *
	 * @return bool True when the copy needs to be updated to match.
	 */
	private function placementDiffers(
		WidgetPlacement $copy,
		WidgetPlacement $template,
	): bool {
		return $this->templateFieldSnapshot(placement: $copy) !== $this->templateFieldSnapshot(placement: $template);
	}//end placementDiffers()

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
	 *
	 * @return WidgetPlacement The new (uninserted) placement entity.
	 */
	private function cloneTemplatePlacementInto(
		WidgetPlacement $template,
		int $dashboardId,
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
		$clone->setTemplatePlacementId($template->getId());

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
	 * @throws InvalidArgumentException When not `overwrite` or `merge`.
	 */
	private function assertValidStrategy(string $strategy): void {
		if (in_array(needle: $strategy, haystack: self::VALID_STRATEGIES, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'Invalid strategy: only "overwrite" and "merge" are accepted'
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
	 *                                   self::diffCopy()}.
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
			'hasChanges' => $this->diffHasChanges(diff: $diff),
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
