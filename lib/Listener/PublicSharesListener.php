<?php

/**
 * PublicSharesListener
 *
 * Soft-revokes public shares when a dashboard is deleted, preserving the
 * audit trail (revokedAt set to now rather than hard-delete). REQ-CSC-003.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Soft-revokes public shares for a deleted dashboard.
 *
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
 */
class PublicSharesListener extends DashboardCleanupListener {
	protected const CLEANED = 'soft-revoked %d share(s)';

	/**
	 * Constructor.
	 *
	 * @param PublicShareMapper $shareMapper Public share mapper.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly PublicShareMapper $shareMapper,
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
		return $this->shareMapper->revokeByDashboardUuid(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
