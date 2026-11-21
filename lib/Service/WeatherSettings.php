<?php

/**
 * WeatherSettings
 *
 * The app and system config of the weather service.
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
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;
use OCP\IConfig;

/**
 * The app and system config of the weather service.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */
class WeatherSettings {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Admin config: cache TTL, provider URL, API key.
	 * @param IConfig    $config Reads the requesting user's locale/language and the system default.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly IConfig $config,
	) {
	}//end __construct()
}//end class
