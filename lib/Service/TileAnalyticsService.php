<?php

/**
 * TileAnalyticsService
 *
 * Aggregation + reporting service for the tile usage-analytics
 * capability (REQ-TANLT-001..005). This is a strict downward
 * extension of the dashboard view-analytics capability
 * ({@see AnalyticsService}) at the tile/widget-placement grain — it
 * deliberately reuses that capability's privacy machinery wholesale
 * instead of reinventing it:
 *
 *   - {@see AnalyticsService::isGloballyEnabled()} and
 *     {@see AnalyticsService::isUserOptedOut()} gate recording — no
 *     new setting or opt-out surface is introduced (REQ-TANLT-003).
 *   - {@see UniqueViewerDedup} provides the SAME salted-daily-hash
 *     unique-actor dedup used for dashboard views (REQ-TANLT-002).
 *     The placement UUID is passed as the dedup service's scoping key
 *     (its `dashboardUuid` parameter name is generic — a cache-key
 *     namespace component — so reusing it for tile-scoped dedup does
 *     not duplicate any logic).
 *   - {@see AnalyticsService::getPurgeCutoffDate()} and
 *     {@see AnalyticsService::getRetentionDays()} are reused unchanged
 *     by the shared `PurgeViewsJob` (REQ-TANLT-005) — no new
 *     retention knob.
 *
 * Privacy guarantee: this service NEVER reads or stores raw user IDs
 * in the analytics database. No per-event rows are ever persisted —
 * every write increments the single aggregate row for
 * `(placementUuid, clickBucket)`.
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

use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\TileClickMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Reporting service for the tile usage-analytics capability.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) Calls only pure, stateless helpers —
 *  {@see AnalyticsService::periodToDateRange()} and
 *  {@see UniqueViewerDedup::utcDateFor()}. Both are dependency-free functions
 *  declared `public static`; injecting their owning classes purely to reach
 *  them would add two collaborators that are never otherwise used here.
 */
class TileAnalyticsService {
	/**
	 * Constructor.
	 *
	 * @param TileClickMapper $tileClickMapper Aggregate-row mapper.
	 * @param WidgetPlacementMapper $placementMapper Resolves the placement
	 *                                               (tile) that was
	 *                                               clicked to its owning
	 *                                               dashboard.
	 * @param DashboardMapper $dashboardMapper Resolves the owning
	 *                                         dashboard's UUID.
	 * @param UniqueViewerDedup $dedup Reused unique-actor
	 *                                 dedup service
	 *                                 (REQ-TANLT-002).
	 * @param AnalyticsService $analyticsService Reused for
	 *                                           `analytics_enabled`
	 *                                           /
	 *                                           `analytics_optout`
	 *                                           /
	 *                                           retention
	 *                                           (REQ-TANLT-003,
	 *                                           REQ-TANLT-005)
	 *                                           — this
	 *                                           service
	 *                                           never
	 *                                           reads
	 *                                           `IAppConfig`/`IConfig`
	 *                                           directly
	 *                                           so there
	 *                                           is exactly
	 *                                           one place
	 *                                           those
	 *                                           gates
	 *                                           live.
	 */
	public function __construct(
		private readonly TileClickMapper $tileClickMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly DashboardMapper $dashboardMapper,
		private readonly UniqueViewerDedup $dedup,
		private readonly AnalyticsService $analyticsService,
	) {
	}//end __construct()

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
	public function isTrackingActiveFor(string $userId): bool {
		if ($this->analyticsService->isGloballyEnabled() === false) {
			return false;
		}

		return ($this->analyticsService->isUserOptedOut(userId: $userId) === false);
	}//end isTrackingActiveFor()

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
	 * @throws DoesNotExistException When the placement does not exist.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function recordClick(int $placementId, string $userId): bool {
		// Existence guard surfaces a DoesNotExistException to the
		// caller so the controller can return a clean 404, mirroring
		// AnalyticsService::recordViewEvent()'s dashboard guard.
		$placement = $this->placementMapper->find(id: $placementId);
		$placementUuid = (string)$placementId;

		if ($this->analyticsService->isGloballyEnabled() === false) {
			return false;
		}

		if ($this->analyticsService->isUserOptedOut(userId: $userId) === true) {
			return false;
		}

		$dashboardUuid = '';
		try {
			$dashboard = $this->dashboardMapper->find(id: $placement->getDashboardId());
			$dashboardUuid = (string)$dashboard->getUuid();
		} catch (DoesNotExistException) {
			// Orphan placement — dashboard already deleted. Still record the
			// click against the placement so no data is silently dropped;
			// the row simply carries an empty dashboardUuid.
			$dashboardUuid = '';
		}

		$today = UniqueViewerDedup::utcDateFor();
		$isNewActor = $this->dedup->isNewUniqueViewer(
			userId: $userId,
			viewBucketDate: $today,
			dashboardUuid: $placementUuid
		);
		$uniqueDelta = 0;
		if ($isNewActor === true) {
			$uniqueDelta = 1;
		}

		$this->tileClickMapper->upsertClick(
			placementUuid: $placementUuid,
			dashboardUuid: $dashboardUuid,
			clickBucket: $today,
			clickCountDelta: 1,
			uniqueCountDelta: $uniqueDelta
		);

		return true;
	}//end recordClick()

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
	public function getTopTiles(string $period, int $limit): array {
		[$startDate, $endDate] = AnalyticsService::periodToDateRange(period: $period);

		return $this->tileClickMapper->findTopTilesInRange(
			startDate: $startDate,
			endDate: $endDate,
			limit: $limit
		);
	}//end getTopTiles()

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
	): array {
		[$startDate, $endDate] = AnalyticsService::periodToDateRange(period: $period);

		return $this->tileClickMapper->findByDashboardInRange(
			dashboardUuid: $dashboardUuid,
			startDate: $startDate,
			endDate: $endDate
		);
	}//end getDashboardBreakdown()

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
	public function generateCsvExport(string $period): string {
		[$startDate, $endDate] = AnalyticsService::periodToDateRange(period: $period);

		$rows = $this->tileClickMapper->findAllInRange(
			startDate: $startDate,
			endDate: $endDate
		);

		$output = [];
		$output[] = self::csvLine(
			cells: [
				'placementUuid',
				'dashboardUuid',
				'clickBucket',
				'clickCount',
				'uniqueActorCount',
			]
		);

		foreach ($rows as $row) {
			$output[] = self::csvLine(
				cells: [
					(string)$row->getPlacementUuid(),
					(string)$row->getDashboardUuid(),
					(string)$row->getClickBucket(),
					(string)$row->getClickCount(),
					(string)$row->getUniqueActorCount(),
				]
			);
		}

		return implode(separator: "\r\n", array: $output) . "\r\n";
	}//end generateCsvExport()

	/**
	 * Compute a filename suitable for the CSV export attachment
	 * (REQ-TANLT-005 scenario "CSV export contains tile statistics").
	 *
	 * @return string The filename in the form
	 *                `tile-analytics-YYYY-MM-DD.csv`.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function csvExportFilename(): string {
		$today = (new DateTimeImmutable('now'))
			->setTimezone(timezone: new DateTimeZone(timezone: 'UTC'))
			->format(format: 'Y-m-d');

		return 'tile-analytics-' . $today . '.csv';
	}//end csvExportFilename()

	/**
	 * Render one CSV line from the supplied cells. Quotes every
	 * cell, escaping embedded quotes per RFC 4180 and prefixing
	 * spreadsheet-formula trigger characters (mirrors
	 * `AnalyticsService::csvLine()` — M1 CSV injection guard).
	 *
	 * @param string[] $cells The raw cell values.
	 *
	 * @return string The CSV-encoded line (no trailing newline).
	 */
	private static function csvLine(array $cells): string {
		$escaped = array_map(
			callback: static function (string $cell): string {
				$formula = ['=', '+', '-', '@', "\t", "\r"];
				if ($cell !== '' && in_array(needle: $cell[0], haystack: $formula, strict: true) === true) {
					$cell = "'" . $cell;
				}

				return '"' . str_replace(
					search: '"',
					replace: '""',
					subject: $cell
				) . '"';
			},
			array: $cells
		);

		return implode(separator: ',', array: $escaped);
	}//end csvLine()
}//end class
