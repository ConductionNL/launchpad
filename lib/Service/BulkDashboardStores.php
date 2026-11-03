<?php

/**
 * BulkDashboardStores
 *
 * What a bulk operation changes dashboards through: the dashboard and placement mappers, the tree
 * and the event dispatcher.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * What a bulk operation changes dashboards through: the dashboard and placement mappers, the tree
 * and the event dispatcher.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class BulkDashboardStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper The dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper The placement mapper (used by the per-uuid hard delete
	 *                                               cascade).
	 * @param DashboardTreeService  $treeService Cycle / cascade service delegated by bulk-move + bulk-delete
	 *                                           (REQ-BULK-001/002).
	 * @param IEventDispatcher|null $eventDispatcher Event dispatcher for DashboardDeletedEvent (SB1 fix,
	 *                                               REQ-CSC-001). Nullable for backwards- compat.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly DashboardTreeService $treeService,
		public readonly ?IEventDispatcher $eventDispatcher=null,
	) {
	}//end __construct()
}//end class
