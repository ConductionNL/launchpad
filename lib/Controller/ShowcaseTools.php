<?php

/**
 * ShowcaseTools
 *
 * The demo showcase service and the logger of the showcase admin API.
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
 * @spec openspec/specs/demo-data-showcases/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\DemoShowcasesService;
use Psr\Log\LoggerInterface;

/**
 * The demo showcase service and the logger of the showcase admin API.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class ShowcaseTools {
	/**
	 * Constructor.
	 *
	 * @param DemoShowcasesService $showcasesSvc Showcase service.
	 * @param LoggerInterface      $logger PSR-3 logger.
	 */
	public function __construct(
		public readonly DemoShowcasesService $showcasesSvc,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
