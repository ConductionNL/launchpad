<?php

/**
 * Contract of AnalyticsService
 *
 * The public surface of AnalyticsService that other components call, so they depend
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
 * Contract of AnalyticsService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AnalyticsServiceInterface {
	/**
	 * Report whether analytics are enabled instance-wide
	 * (REQ-ANLT-005). Default `true` when the setting is absent.
	 *
	 * @return bool `true` when analytics is globally enabled.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function isGloballyEnabled(): bool;

	/**
	 * Report whether the supplied user has opted out of analytics
	 * tracking (REQ-ANLT-004). Default `false`.
	 *
	 * @param string $userId The user identifier.
	 *
	 * @return bool `true` when the user has opted out.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function isUserOptedOut(string $userId): bool;

	/**
	 * Store whether the supplied user is opted out of analytics tracking
	 * (REQ-ANLT-004, set from the person's own settings, REQ-ANLT-UI-001).
	 *
	 * @param string $userId   The user identifier.
	 * @param bool   $optedOut `true` to stop counting this user's visits.
	 *
	 * @return bool The stored value.
	 *
	 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
	 */
	public function setUserOptedOut(string $userId, bool $optedOut): bool;

	/**
	 * Resolve the retention window in days, clamped to the
	 * `[MIN_RETENTION_DAYS, MAX_RETENTION_DAYS]` range
	 * (REQ-ANLT-009).
	 *
	 * @return int The effective retention window.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getRetentionDays(): int;

	/**
	 * Record a view event for `$dashboardUuid` by `$userId`
	 * (REQ-ANLT-002, REQ-ANLT-003, REQ-ANLT-004, REQ-ANLT-005).
	 *
	 * Short-circuits to `false` (no-op) when:
	 *   - global analytics is disabled (REQ-ANLT-005);
	 *   - the user has opted out (REQ-ANLT-004).
	 *
	 * Always increments `viewCount` by 1 when the call proceeds;
	 * `uniqueViewerCount` is incremented only when the dedup layer
	 * reports the viewer as new for today.
	 *
	 * @param string $dashboardUuid The dashboard UUID being viewed.
	 * @param string $userId The viewing user identifier.
	 *
	 * @return bool `true` when an event was recorded, `false` when
	 *              the call was short-circuited.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function recordViewEvent(
		string $dashboardUuid,
		string $userId,
	): bool;

	/**
	 * Resolve the top-N dashboards by total view count for the
	 * supplied period (REQ-ANLT-006). The dashboard `name` is
	 * looked up from `DashboardMapper` per row; rows whose dashboard
	 * has been deleted since the aggregate was recorded are kept
	 * with `name = null` so admins still see the orphan counter.
	 *
	 * @param string $period The period string (`7d`, `30d`, `90d`).
	 * @param int $limit Maximum rows.
	 *
	 * @return array<int, array{dashboardUuid: string, name: string|null,
	 *   viewCount: int, uniqueViewerCount: int}>
	 *   Top-N dashboards sorted by `viewCount` descending.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getTopDashboards(string $period, int $limit): array;

	/**
	 * Return the daily breakdown for one dashboard (REQ-ANLT-007).
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $period The period string.
	 *
	 * @return array<int, array{viewBucket: string, viewCount: int,
	 *   uniqueViewerCount: int}>
	 *   Daily breakdown sorted by `viewBucket` ascending. Missing
	 *   days are omitted (REQ-ANLT-007 scenario).
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the dashboard does not
	 *                               exist.
	 * @throws \InvalidArgumentException When the period string is
	 *                                  not one of `7d`, `30d`,
	 *                                  `90d`.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getDashboardDetail(
		string $dashboardUuid,
		string $period,
	): array;

	/**
	 * Compute instance-wide totals + the top-5 dashboards
	 * (REQ-ANLT-008).
	 *
	 * @param string $period The period string.
	 *
	 * @return array{totalViewCount: int, totalUniqueViewers: int,
	 *   dashboardCount: int, period: string,
	 *   top5: array<int, array{dashboardUuid: string, name: string|null,
	 *     viewCount: int, uniqueViewerCount: int}>}
	 *   The summary payload.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getInstanceSummary(string $period): array;

	/**
	 * Generate a CSV export of every aggregate row in the supplied
	 * period (REQ-ANLT-010). Header row first, sorted by
	 * `(dashboardUuid, viewBucket)` ascending. Dashboard names are
	 * resolved lazily and cached in-method to avoid N+1 lookups.
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
	 * (REQ-ANLT-010 scenario "CSV filename includes export date").
	 *
	 * @return string The filename in the form
	 *                `dashboard-analytics-YYYY-MM-DD.csv`.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function csvExportFilename(): string;

	/**
	 * Compute the cutoff date for the retention purge job — every
	 * row with `view_bucket < cutoff` is eligible for deletion
	 * (REQ-ANLT-009).
	 *
	 * @return string The cutoff date in `YYYY-MM-DD` format.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function getPurgeCutoffDate(): string;
}//end interface
