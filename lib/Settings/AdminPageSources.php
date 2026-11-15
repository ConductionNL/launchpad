<?php

/**
 * AdminPageSources
 *
 * What the admin settings page reads: widgets, admin settings, dashboards and files.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\FileService;
use OCA\LaunchPad\Service\WidgetService;

/**
 * What the admin settings page reads: widgets, admin settings, dashboards and files.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class AdminPageSources {
	/**
	 * Constructor.
	 *
	 * @param WidgetService      $widgetService Available-widgets descriptor formatter.
	 * @param AdminSettingMapper $settingMapper Admin settings store (legacy configured-groups list).
	 * @param DashboardService   $dashboardService Dashboard service exposing the `allow_user_dashboards` flag
	 *                                             (REQ-ASET-003).
	 * @param FileService        $fileService link-button-widget extension allow-list reader.
	 */
	public function __construct(
		public readonly WidgetService $widgetService,
		public readonly AdminSettingMapper $settingMapper,
		public readonly DashboardService $dashboardService,
		public readonly FileService $fileService,
	) {
	}//end __construct()
}//end class
