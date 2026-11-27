<?php

/**
 * ScreenLinksListener
 *
 * Revokes the screen link of a deleted dashboard (REQ-KIOSK-006), keeping
 * the row for the audit trail like the public shares (revokedAt set to now).
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Revokes the screen link of a deleted dashboard.
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-3
 */
class ScreenLinksListener extends DashboardCleanupListener {
	protected const CLEANED = 'revoked %d screen link(s)';

	/**
	 * Constructor.
	 *
	 * @param KioskPlaylistMapper $playlistMapper Kiosk playlist mapper.
	 * @param LoggerInterface     $logger         PSR-3 logger.
	 */
	public function __construct(
		private readonly KioskPlaylistMapper $playlistMapper,
		LoggerInterface $logger,
	) {
		parent::__construct(logger: $logger);
	}//end __construct()

	/**
	 * Revoke the deleted dashboard's screen link.
	 *
	 * @param DashboardDeletedEvent $event The event.
	 *
	 * @return int The number of screen links revoked.
	 *
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-3
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		return $this->playlistMapper->revokeScreenLinksOf(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
