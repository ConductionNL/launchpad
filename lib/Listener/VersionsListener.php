<?php

/**
 * VersionsListener
 *
 * Cleans up `oc_launchpad_dash_versions` rows and the JSON version
 * file (in GroupFolder mode) when a dashboard is soft-deleted. Stub
 * registered as part of the cascade-events scaffolding; the live
 * implementation is owned by the dashboard-versioning follow-up.
 * REQ-CSC-003.
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

use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Deletes version rows for the deleted dashboard. C4 fix (REQ-CSC-003).
 *
 */
class VersionsListener extends DashboardCleanupListener {
	protected const CLEANED = 'deleted %d version rows';

	/**
	 * Constructor.
	 *
	 * @param DashboardVersionMapper $versionMapper Version row mapper.
	 * @param LoggerInterface $logger PSR-3 logger for
	 *                                log-and-continue failure
	 *                                handling per REQ-CSC-006.
	 */
	public function __construct(
		private readonly DashboardVersionMapper $versionMapper,
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
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		return $this->versionMapper->deleteByDashboardUuid(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
