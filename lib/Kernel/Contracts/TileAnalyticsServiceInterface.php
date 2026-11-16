<?php

/**
 * Contract of TileAnalyticsService
 *
 * The public surface of TileAnalyticsService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
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
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of TileAnalyticsService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TileAnalyticsServiceInterface {
	/**
	 * Report whether tile-click tracking is currently active for
	 * `$userId` — i.e. analytics is globally enabled AND the user has
	 * not opted out. Exposed so the frontend config endpoint can
	 * suppress the client-side hook without duplicating the gate
	 * logic (REQ-TANLT-003).
	 *
	 * @param string $userId The user identifier.
	 *
	 * @return bool `true` when clicks by this user would be recorded.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function isTrackingActiveFor(string $userId): bool;

	/**
	 * Record a click on the tile at widget-placement `$placementId`
	 * by `$userId` (REQ-TANLT-001, REQ-TANLT-002, REQ-TANLT-003).
	 *
	 * Short-circuits to `false` (no-op — no counter change, no cache
	 * write, no per-event row) when:
	 *   - global analytics is disabled (reused REQ-ANLT-005 gate);
	 *   - the user has opted out (reused REQ-ANLT-004 gate).
	 *
	 * Always increments `clickCount` by 1 when the call proceeds;
	 * `uniqueActorCount` is incremented only when the dedup layer
	 * reports the actor as new for today on this placement.
	 *
	 * @param int $placementId The widget-placement (tile) ID.
	 * @param string $userId The clicking user identifier.
	 *
	 * @return bool `true` when a click was recorded, `false` when the
	 *              call was short-circuited.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the placement does not exist.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function recordClick(int $placementId, string $userId): bool;

	/**
	 * Resolve the top-N tiles by total click count for the supplied
	 * period (REQ-TANLT-004).
	 *
	 * @param string $period The period string (`7d`, `30d`, `90d`).
	 * @param int $limit Maximum rows.
	 *
	 * @return array<int, array{placementUuid: string, dashboardUuid: string,
	 *   clickCount: int, uniqueActorCount: int}>
	 *   Top-N tiles sorted by `clickCount` descending.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getTopTiles(string $period, int $limit): array;

	/**
	 * Return the per-tile breakdown for one dashboard
	 * (REQ-TANLT-004 — "per-dashboard tile breakdown").
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $period The period string.
	 *
	 * @return array<int, array{placementUuid: string, clickCount: int,
	 *   uniqueActorCount: int}>
	 *   Per-tile totals sorted by `clickCount` descending.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getDashboardBreakdown(
		string $dashboardUuid,
		string $period,
	): array;

	/**
	 * Generate a CSV export of every aggregate row in the supplied
	 * period (REQ-TANLT-005). Header row first, sorted by
	 * `(placementUuid, clickBucket)` ascending.
	 *
	 * @param string $period The period string.
	 *
	 * @return string The CSV body (CRLF line endings).
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function generateCsvExport(string $period): string;

	/**
	 * Compute a filename suitable for the CSV export attachment
	 * (REQ-TANLT-005 scenario "CSV export contains tile statistics").
	 *
	 * @return string The filename in the form
	 *                `tile-analytics-YYYY-MM-DD.csv`.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function csvExportFilename(): string;
}//end interface
