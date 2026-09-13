<?php

/**
 * DashboardSharesListener
 *
 * Deletes the user and group shares of a deleted dashboard, the rows in
 * `oc_launchpad_dashboard_shares`. REQ-CSC-002, REQ-CSC-003.
 *
 * 🔴 WHY THIS LISTENER EXISTS. The cascade registry cleaned placements,
 * reactions, locks, versions, public shares, metadata values, translations
 * and view analytics, and nothing cleaned user and group shares. Deleting a
 * shared dashboard left its share rows behind until the orphan sweep
 * (`OrphanedSharesCategory`) found them. The mapper already had a
 * `deleteByDashboardUuid()` documented as cascade-triggered; nothing called
 * it, and it could not have worked here (see below).
 *
 * 🔴 BY ID, NOT BY UUID. Every dispatcher fires `DashboardDeletedEvent` after
 * the dashboard row is gone, and the share table is keyed on `dashboard_id`.
 * Translating the UUID through `oc_launchpad_dashboards` therefore finds
 * nothing and deletes zero rows without complaint. The event carries the id
 * for exactly this reason. The UUID path is kept only as the fallback for an
 * event dispatched without an id, which works while the row still exists.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/dashboard-cascade-events/spec.md#requirement-req-csc-003-dependent-data-listener-group
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deletes user and group shares for a deleted dashboard.
 *
 * @implements IEventListener<DashboardDeletedEvent>
 *
 * @spec openspec/specs/dashboard-cascade-events/spec.md#requirement-req-csc-003-dependent-data-listener-group
 */
class DashboardSharesListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param DashboardShareMapper $shareMapper User and group share mapper.
	 * @param LoggerInterface      $logger      PSR-3 logger for log-and-continue
	 *                                          failure handling per REQ-CSC-006.
	 */
	public function __construct(
		private readonly DashboardShareMapper $shareMapper,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle the DashboardDeletedEvent by deleting every share of the dashboard.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md#requirement-req-csc-003-dependent-data-listener-group
	 */
	public function handle(Event $event): void {
		if (($event instanceof DashboardDeletedEvent) === false) {
			return;
		}

		$uuid = $event->getDashboardUuid();

		try {
			$deleted = $this->deleteShares(
				uuid: $uuid,
				dashboardId: $event->getDashboardId()
			);

			$this->logger->debug(
				message: sprintf(
					'launchpad DashboardSharesListener: deleted %d share row(s) for dashboard %s',
					$deleted,
					$uuid
				),
				context: ['app' => 'launchpad']
			);
		} catch (Throwable $t) {
			// Failure isolation per REQ-CSC-006: log at WARN, do not rethrow,
			// so peer listeners still execute.
			$this->logger->warning(
				message: sprintf(
					'launchpad DashboardSharesListener: failed for dashboard %s: %s',
					$uuid,
					$t->getMessage()
				),
				context: ['app' => 'launchpad']
			);
		}//end try
	}//end handle()

	/**
	 * Delete the shares by id, or by UUID when the event carries no id.
	 *
	 * The id path is the one that works after the dashboard row is gone, which
	 * is when every dispatcher fires. The UUID path translates through
	 * `oc_launchpad_dashboards` and only works while the row still exists.
	 *
	 * @param string   $uuid        The deleted dashboard's UUID.
	 * @param int|null $dashboardId The deleted row's id, or null.
	 *
	 * @return int The number of share rows deleted.
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md#requirement-req-csc-003-dependent-data-listener-group
	 */
	private function deleteShares(string $uuid, ?int $dashboardId): int {
		if ($dashboardId !== null && $dashboardId > 0) {
			return $this->shareMapper->deleteByDashboardId(dashboardId: $dashboardId);
		}

		return $this->shareMapper->deleteByDashboardUuid(dashboardUuid: $uuid);
	}//end deleteShares()
}//end class
