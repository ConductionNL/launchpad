<?php

/**
 * DeleteCommandStores
 *
 * The dashboard and placement mappers and the tree service of the dashboard delete command.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/cli-commands/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardTreeService;

/**
 * The dashboard and placement mappers and the tree service of the dashboard delete command.
 *
 * @spec openspec/specs/cli-commands/spec.md
 */
class DeleteCommandStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget mapper.
	 * @param DashboardTreeService  $treeService Tree service for cascading delete.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly DashboardTreeService $treeService,
	) {
	}//end __construct()
}//end class
