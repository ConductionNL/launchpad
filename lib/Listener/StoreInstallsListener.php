<?php

/**
 * StoreInstallsListener
 *
 * Drops the marketplace install record of a deleted dashboard, so its
 * register item shows Available again instead of an Open that leads nowhere
 * (REQ-STORE-011).
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\StoreInstalls;
use Psr\Log\LoggerInterface;

/**
 * Forgets the install of a deleted dashboard.
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
 */
class StoreInstallsListener extends DashboardCleanupListener {
	protected const CLEANED = 'dropped %d marketplace install records';

	/**
	 * Constructor.
	 *
	 * @param StoreInstalls   $installs The install record.
	 * @param LoggerInterface $logger   PSR-3 logger for log-and-continue (REQ-CSC-006).
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function __construct(
		private readonly StoreInstalls $installs,
		LoggerInterface $logger,
	) {
		parent::__construct(logger: $logger);
	}//end __construct()

	/**
	 * Drop the entries that point at the deleted dashboard.
	 *
	 * @param DashboardDeletedEvent $event The event.
	 *
	 * @return int The number of entries dropped.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		$dashboardId = $event->getDashboardId();
		if ($dashboardId === null) {
			return 0;
		}

		return $this->installs->forget(dashboardId: $dashboardId);
	}//end cleanUp()
}//end class
