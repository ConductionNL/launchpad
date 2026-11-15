<?php

/**
 * WidgetPlacementsListener
 *
 * Cleans up `oc_launchpad_widget_placements` rows when a dashboard is
 * soft-deleted. Stub registered as part of the cascade-events
 * scaffolding; the live implementation is owned by the placements
 * subsystem and will be filled in as part of widget-placement
 * follow-up work. REQ-CSC-003.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Deletes widget placements for the deleted dashboard.
 * C4 fix (REQ-CSC-003, REQ-CSC-008 idempotency).
 *
 */
class WidgetPlacementsListener extends DashboardCleanupListener {
	protected const CLEANED = 'deleted %d placement rows';

	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param LoggerInterface $logger PSR-3 logger for
	 *                                log-and-continue failure
	 *                                handling per REQ-CSC-006.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placementMapper,
		LoggerInterface $logger,
	) {
		parent::__construct(logger: $logger);
	}//end __construct()

	/**
	 * Remove the deleted dashboard's rows.
	 *
	 * @param DashboardDeletedEvent $event The event.
	 *
	 * @return int The number of rows removed.
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		return $this->placementMapper->deleteByDashboardUuid(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
