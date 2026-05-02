<?php

/**
 * GroupDeletedListener
 *
 * Cleans up group-scoped MyDash data when a Nextcloud group is
 * deleted: cascade-deletes group-shared dashboards (which in turn
 * fire `DashboardDeletedEvent` for each), removes group role
 * assignments, and prunes the group from `mydash.org_navigation_tree`
 * and `mydash.group_order` IConfig settings. Stub registered as part
 * of the cascade-events scaffolding; the live implementation is
 * owned by the dashboard-sharing / navigation-editor follow-ups.
 * REQ-CSC-005.
 *
 * @category  Listener
 * @package   OCA\MyDash\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MyDash\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Cleans up group-scoped data when a Nextcloud group is deleted.
 *
 * @implements IEventListener<GroupDeletedEvent>
 */
class GroupDeletedListener implements IEventListener
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger PSR-3 logger for log-and-continue
     *                                failure handling per REQ-CSC-006.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Handle the GroupDeletedEvent.
     *
     * @param Event $event The event.
     *
     * @return void
     */
    public function handle(Event $event): void
    {
        if (($event instanceof GroupDeletedEvent) === false) {
            return;
        }

        try {
            // TODO(dashboard-sharing/navigation-editor-org): wire the
            // four actions below in the live implementation.
            // 1. Enumerate group-shared dashboards owned by the
            // deleted group and call DashboardService::delete() for
            // each (which dispatches DashboardDeletedEvent and fires
            // the full cascade).
            // 2. DELETE FROM oc_mydash_role_assignments WHERE
            // groupId = $event->getGroup()->getGID().
            // 3. Read mydash.org_navigation_tree JSON, drop the group
            // from every groupVisibility array, write back.
            // 4. Read mydash.group_order JSON array, drop the group,
            // write back.
            // Stub registered for cascade scaffolding — see REQ-CSC-005
            // scenarios.
            $this->logger->debug(
                message: sprintf(
                    'mydash GroupDeletedListener: stub invoked for group %s',
                    $event->getGroup()->getGID()
                ),
                context: ['app' => 'mydash']
            );
        } catch (Throwable $t) {
            $this->logger->warning(
                message: sprintf(
                    'mydash GroupDeletedListener: failed for group %s: %s',
                    $event->getGroup()->getGID(),
                    $t->getMessage()
                ),
                context: ['app' => 'mydash']
            );
        }//end try
    }//end handle()
}//end class
