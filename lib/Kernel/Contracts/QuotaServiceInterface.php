<?php

/**
 * Contract of QuotaService
 *
 * The public surface of QuotaService that other components call, so they depend
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
 * Contract of QuotaService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface QuotaServiceInterface {
	/**
	 * Build the additive quota-status envelope for the dashboards list
	 * response (REQ-QUOTA-006).
	 *
	 * `maxDashboards` reflects the EFFECTIVE dashboard limit (so
	 * `allow_multiple_dashboards = false` surfaces as `1`), `dashboardsUsed`
	 * is the user's live personal-dashboard count, and
	 * `maxWidgetsPerDashboard` is the configured per-dashboard widget limit.
	 * `0` means unlimited for both `max*` fields.
	 *
	 * @param string $userId The acting user ID.
	 *
	 * @return array{maxDashboards: int, dashboardsUsed: int, maxWidgetsPerDashboard: int}
	 *                                                                                     The quota-status envelope.
	 *
	 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-006-quota-status-surfacing-in-ui
	 */
	public function getQuotaStatus(string $userId): array;
}//end interface
