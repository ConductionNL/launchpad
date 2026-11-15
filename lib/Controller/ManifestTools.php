<?php

/**
 * ManifestTools
 *
 * The service container and the logger of the manifest API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The service container and the logger of the manifest API.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */
class ManifestTools {
	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container The Nextcloud DI container; used to lazy-load ObjectService so that
	 *                                      launchpad degrades gracefully when OpenRegister is not yet active.
	 * @param LoggerInterface    $logger PSR logger.
	 */
	public function __construct(
		public readonly ContainerInterface $container,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
