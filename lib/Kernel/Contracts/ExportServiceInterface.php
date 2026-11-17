<?php

/**
 * Contract of ExportService
 *
 * The public surface of ExportService that other components call, so they depend
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

use OCA\LaunchPad\Db\Dashboard;
use OCP\AppFramework\Http\StreamResponse;

/**
 * Contract of ExportService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ExportServiceInterface {
	/**
	 * Export a single dashboard as a streaming ZIP response.
	 *
	 * Caller MUST already have validated admin / owner permissions.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $currentUserId The current user ID (for manifest).
	 *
	 * @return StreamResponse The streaming ZIP response.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the dashboard cannot be found.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function exportDashboard(
		string $dashboardUuid,
		string $currentUserId,
	): StreamResponse;

	/**
	 * Export every dashboard in the instance as a streaming ZIP.
	 *
	 * Caller MUST already have validated Nextcloud-admin permission.
	 *
	 * @param string $currentUserId The current user ID (for manifest).
	 *
	 * @return StreamResponse The streaming ZIP response.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function exportSite(string $currentUserId): StreamResponse;

	/**
	 * Build a manifest array for the archive root (REQ-EXIM-001).
	 *
	 * @param string $scope Either 'dashboard' or 'site'.
	 * @param int $dashboardCount Number of dashboards in the archive.
	 * @param string $currentUserId The exporting user's UID.
	 *
	 * @return array<string, mixed> The manifest data.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function buildManifest(
		string $scope,
		int $dashboardCount,
		string $currentUserId,
	): array;

	/**
	 * Serialize a dashboard with its widget placements (REQ-EXIM-002).
	 *
	 * @param Dashboard $dashboard The dashboard entity.
	 *
	 * @return array<string, mixed> The serialized dashboard payload.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function serializeDashboard(Dashboard $dashboard): array;
}//end interface
