<?php

/**
 * LeaverHandoverListener
 *
 * Before an account is deleted, hands the person's dashboards to their
 * manager or to the person the administrator named (REQ-SHARE-016). It runs
 * while the account still exists, so the manager can be read; the dashboards
 * keep their shares and the new owner is notified (REQ-SHARE-013). What it
 * leaves (no setting, nobody to hand to, copies of an admin template) the
 * UserDeletedListener handles after the deletion, as REQ-SHARE-012 says.
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
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\LeaverHandover;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Hands a leaver's dashboards over before the account is deleted.
 *
 * @implements IEventListener<BeforeUserDeletedEvent>
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
 */
class LeaverHandoverListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param LeaverHandover        $handover Who the dashboards go to.
	 * @param DashboardShareService $shares   Finds the leaver's dashboards, transfers them and notifies the new owner.
	 * @param LoggerInterface       $logger   Logs a dashboard that could not be handed over.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	public function __construct(
		private readonly LeaverHandover $handover,
		private readonly DashboardShareService $shares,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Hand every dashboard the leaver owns to the handover target.
	 *
	 * A copy of an admin template is left to REQ-SHARE-012: it is the
	 * leaver's copy of the organisation's dashboard, not their own work.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	public function handle(Event $event): void {
		if (($event instanceof BeforeUserDeletedEvent) === false) {
			return;
		}

		$leaver = $event->getUser();
		$target = $this->handover->targetFor(leaver: $leaver);
		if ($target === null) {
			return;
		}

		foreach ($this->shares->ownedDashboards(userId: $leaver->getUID()) as $dashboard) {
			if ($dashboard->getBasedOnTemplate() !== null) {
				continue;
			}

			$this->handOver(dashboard: $dashboard, target: $target);
		}
	}//end handle()

	/**
	 * Transfer one dashboard and notify the new owner; a failure is logged and
	 * leaves the dashboard to REQ-SHARE-012.
	 *
	 * @param Dashboard $dashboard The leaver's dashboard.
	 * @param string    $target    The new owner.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	private function handOver(Dashboard $dashboard, string $target): void {
		$dashboardId = (int)$dashboard->getId();
		try {
			$this->shares->transferOwnership(dashboardId: $dashboardId, newUserId: $target);
			$this->shares->notifyOwnershipTransferred(
				newOwnerId: $target,
				dashboardId: $dashboardId,
				dashboardName: (string)$dashboard->getName()
			);
		} catch (Throwable $t) {
			$this->logger->error(
				message: sprintf('launchpad LeaverHandoverListener: could not hand over dashboard %d: %s', $dashboardId, $t->getMessage()),
				context: ['app' => 'launchpad']
			);
		}
	}//end handOver()
}//end class
