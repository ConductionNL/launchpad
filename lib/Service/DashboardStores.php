<?php

/**
 * DashboardStores
 *
 * The tables DashboardService reads and writes: dashboards, placements, admin settings and locks.
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
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;

/**
 * The tables DashboardService reads and writes: dashboards, placements, admin settings and locks.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper          $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper    $placementMapper Widget placement mapper.
	 * @param AdminSettingMapper       $settingMapper Admin setting mapper.
	 * @param DashboardLockMapper|null $lockMapper Optional lock mapper. When provided the delete path cascades
	 *                                             the row removal to the editing-lock table per REQ-LOCK-008.
	 *                                             Nullable to keep the constructor backwards- compatible with
	 *                                             existing unit tests.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly AdminSettingMapper $settingMapper,
		public readonly ?DashboardLockMapper $lockMapper=null,
	) {
	}//end __construct()
}//end class
