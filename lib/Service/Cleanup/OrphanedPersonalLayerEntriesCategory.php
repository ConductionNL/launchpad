<?php

/**
 * OrphanedPersonalLayerEntriesCategory
 *
 * Cleanup category for personal layer entries that point at widget
 * placements which no longer exist (REQ-DWMS-001, task 1.5 of
 * dashboards-and-who-may-see-them). A re-sync, a version restore or an
 * owner removing a widget can take a placement away from a shared
 * dashboard; the entries a reader kept for it then sit in their layer.
 *
 * Tier-A (`safeToPurgeAutomatically=true`): `PersonalLayerService::applyTo()`
 * walks the live placements and looks overrides up by id, so a stale entry
 * is never rendered, and placement ids are never reused. Removing it has no
 * user-visible effect. A layer the sweep empties is deleted whole, which
 * puts the reader back on the owner's arrangement.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Cleanup
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

namespace OCA\LaunchPad\Service\Cleanup;

use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PersonalLayerService;

/**
 * Sweeps personal layer entries whose placement is gone.
 */
class OrphanedPersonalLayerEntriesCategory implements CleanupCategoryInterface {
	/**
	 * Stable category identifier.
	 *
	 * @var string
	 */
	public const NAME = 'orphaned_personal_layer_entries';

	/**
	 * Constructor.
	 *
	 * @param PersonalLayerMapper $layerMapper Lists every stored layer.
	 * @param WidgetPlacementMapper $placementMapper Reads the live placements.
	 * @param PersonalLayerService $personalLayers Counts and prunes one layer.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function __construct(
		private readonly PersonalLayerMapper $layerMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly PersonalLayerService $personalLayers,
	) {
	}//end __construct()

	/**
	 * Stable identifier.
	 *
	 * @return string The identifier.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function getName(): string {
		return self::NAME;
	}//end getName()

	/**
	 * Human-readable label for the admin UI.
	 *
	 * @return string The label.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function getDisplayName(): string {
		return 'Personal layout entries for removed widgets';
	}//end getDisplayName()

	/**
	 * Tier-A: a stale entry is never rendered.
	 *
	 * @return bool True.
	 *
	 * @SuppressWarnings(PHPMD.BooleanGetMethodName) The name is CleanupCategoryInterface's.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function getSafeToPurgeAutomatically(): bool {
		return true;
	}//end getSafeToPurgeAutomatically()

	/**
	 * Always available: the layer table ships in the core schema.
	 *
	 * @return bool True.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function isAvailable(): bool {
		return true;
	}//end isAvailable()

	/**
	 * Count stale entries across every layer, writing nothing.
	 *
	 * @return int The stale entry count.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function scan(): int {
		return $this->walk(write: false);
	}//end scan()

	/**
	 * Drop stale entries across every layer.
	 *
	 * Dry-run safety comes from the orchestrator's transaction and rollback,
	 * as for every other category.
	 *
	 * @param bool $dryRun True for dry-run.
	 *
	 * @return int The number of entries dropped.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) The signature is CleanupCategoryInterface's.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Dry-run is the orchestrator's transaction, as for every category.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function purge(bool $dryRun = false): int {
		return $this->walk(write: true);
	}//end purge()

	/**
	 * Walk every layer against its dashboard's live placements.
	 *
	 * @param bool $write Prune when true, count only when false.
	 *
	 * @return int Stale entries found or dropped.
	 */
	private function walk(bool $write): int {
		$liveByDashboard = [];
		$total = 0;
		foreach ($this->layerMapper->findAllLayers() as $layer) {
			$dashboardId = (int)$layer->getDashboardId();
			if (isset($liveByDashboard[$dashboardId]) === false) {
				$liveByDashboard[$dashboardId] = array_map(
					static fn ($placement): int => (int)$placement->getId(),
					$this->placementMapper->findByDashboardId(dashboardId: $dashboardId)
				);
			}

			$userId = (string)$layer->getUserId();
			if ($write === true) {
				$total += $this->personalLayers->pruneOrphans(
					userId: $userId,
					dashboardId: $dashboardId,
					liveIds: $liveByDashboard[$dashboardId]
				);
				continue;
			}

			$total += $this->personalLayers->countOrphans(
				userId: $userId,
				dashboardId: $dashboardId,
				liveIds: $liveByDashboard[$dashboardId]
			);
		}//end foreach

		return $total;
	}//end walk()
}//end class
