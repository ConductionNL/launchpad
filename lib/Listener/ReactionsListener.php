<?php

/**
 * ReactionsListener
 *
 * Cleans up `oc_launchpad_dash_reactions` rows when a dashboard is
 * soft-deleted. Subscribes to {@see DashboardDeletedEvent} via the
 * cascade-events listener registry (REQ-CSC-002, REQ-CSC-003) and
 * delegates the actual delete to {@see ReactionService} so the same
 * code path is reused by every cascade source.
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

use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\ReactionService;
use Psr\Log\LoggerInterface;

/**
 * Deletes reactions for the deleted dashboard. REQ-RXN-009.
 *
 */
class ReactionsListener extends DashboardCleanupListener {
	protected const CLEANED = 'deleted %d reactions';

	/**
	 * Constructor.
	 *
	 * @param ReactionService $reactionService Service that owns the
	 *                                         `deleteReactionsByDashboard`
	 *                                         cascade path.
	 * @param LoggerInterface $logger PSR-3 logger for
	 *                                log-and-continue failure
	 *                                handling per REQ-CSC-006.
	 */
	public function __construct(
		private readonly ReactionService $reactionService,
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
		return $this->reactionService->deleteReactionsByDashboard(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
