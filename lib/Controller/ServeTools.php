<?php

/**
 * ServeTools
 *
 * The resource serve service and the logger of the resource serve endpoint.
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
 * @spec openspec/changes/resource-serving/tasks.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ResourceServeService;
use Psr\Log\LoggerInterface;

/**
 * The resource serve service and the logger of the resource serve endpoint.
 *
 * @spec openspec/changes/resource-serving/tasks.md
 */
class ServeTools {
	/**
	 * Constructor.
	 *
	 * @param ResourceServeService $serve Filesystem + formatting helper.
	 * @param LoggerInterface      $logger PSR logger.
	 */
	public function __construct(
		public readonly ResourceServeService $serve,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
