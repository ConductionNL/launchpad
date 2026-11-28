<?php

/**
 * DashboardDiscussionListener
 *
 * Keeps the discussion object of a dashboard in OpenRegister in step with
 * who may view the dashboard: a changed audience syncs it, a deleted
 * dashboard removes it.
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
 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-003-discussion-access-follows-the-existing-view-guard
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Event\DashboardAudienceChangedEvent;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\Discussion\DashboardDiscussionMirror;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Syncs or removes a dashboard's discussion object.
 *
 * @implements IEventListener<Event>
 */
class DashboardDiscussionListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param DashboardDiscussionMirror $mirror Writes the discussion objects.
	 */
	public function __construct(
		private readonly DashboardDiscussionMirror $mirror,
	) {
	}//end __construct()

	/**
	 * Handle a changed audience or a deleted dashboard.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
	 */
	public function handle(Event $event): void {
		if ($event instanceof DashboardDeletedEvent) {
			$this->mirror->remove(dashboardUuid: $event->getDashboardUuid());
			return;
		}

		if ($event instanceof DashboardAudienceChangedEvent === false) {
			return;
		}

		if ($event->dashboardId !== null) {
			$this->mirror->syncDashboard(dashboardId: $event->dashboardId);
		}

		if ($event->ownerId !== null && $event->ownerId !== '') {
			$this->mirror->syncOwnedBy(ownerId: $event->ownerId);
		}
	}//end handle()
}//end class
