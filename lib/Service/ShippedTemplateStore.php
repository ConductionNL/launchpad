<?php

/**
 * ShippedTemplateStore
 *
 * The dashboard mapper and the app config of the shipped template service.
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
 * @spec openspec/specs/admin-templates/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCP\IAppConfig;

/**
 * The dashboard mapper and the app config of the shipped template service.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
class ShippedTemplateStore {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper $dashboardMapper Looks up an earlier install.
	 * @param IAppConfig      $appConfig Remembers what was installed.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly IAppConfig $appConfig,
	) {
	}//end __construct()
}//end class
