<?php

/**
 * LiveTileSettings
 *
 * The app config and the logger of the live tile service.
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
use Psr\Log\LoggerInterface;

/**
 * The app config and the logger of the live tile service.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */
class LiveTileSettings {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig      $appConfig Admin config: allow-listed hosts.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
