<?php

/**
 * TileSources
 *
 * The tile service, translator and service discovery of the tile API.
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
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ServiceDiscoveryService;
use OCA\LaunchPad\Service\TileService;
use OCP\IL10N;

/**
 * The tile service, translator and service discovery of the tile API.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class TileSources {
	/**
	 * Constructor.
	 *
	 * @param TileService             $tileService The tile service.
	 * @param IL10N                   $l10n The localisation helper.
	 * @param ServiceDiscoveryService $discovery The apps in the user's app menu.
	 */
	public function __construct(
		public readonly TileService $tileService,
		public readonly IL10N $l10n,
		public readonly ServiceDiscoveryService $discovery,
	) {
	}//end __construct()
}//end class
