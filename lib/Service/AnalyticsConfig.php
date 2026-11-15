<?php

/**
 * AnalyticsConfig
 *
 * The app and system config the analytics service reads.
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
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;
use OCP\IConfig;

/**
 * The app and system config the analytics service reads.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
class AnalyticsConfig {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig App config for admin settings.
	 * @param IConfig    $config Nextcloud config for user values.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly IConfig $config,
	) {
	}//end __construct()
}//end class
