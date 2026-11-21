<?php

/**
 * AdminTemplateStores
 *
 * Where admin templates are written: the dashboard and placement mappers, resources and events.
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
 * Where admin templates are written: the dashboard and placement mappers, resources and events.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class AdminTemplateStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param ResourceService|null  $resourceService Resource-uploads pipeline reused for preview-image storage
	 *                                               (REQ-TMPL-017). Optional so existing wiring + tests stay
	 *                                               valid.
	 * @param IEventDispatcher|null $eventDispatcher Event dispatcher for DashboardDeletedEvent (SB1 fix,
	 *                                               REQ-CSC-001). Nullable for backwards- compat.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly ?ResourceService $resourceService=null,
		public readonly ?IEventDispatcher $eventDispatcher=null,
	) {
	}//end __construct()
}//end class
