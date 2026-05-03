<?php

/**
 * GroupDeletedListener
 *
 * Listens to OCP\Group\Events\GroupDeletedEvent and removes any MyDash
 * role assignments scoped to the deleted group. REQ-ROLE-011.
 *
 * The listener is intentionally narrow — it only touches the role
 * assignment table. Dashboard-share cleanup for deleted groups is
 * handled by Nextcloud's own group lifecycle (`UserDeletedListener`
 * deals with member fan-out via per-user events) and is therefore not
 * duplicated here.
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

use OCA\MyDash\Service\RoleService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Cascade role-assignment removal on group deletion (REQ-ROLE-011).
 *
 * @implements IEventListener<GroupDeletedEvent>
 */
class GroupDeletedListener implements IEventListener
{
    /**
     * Constructor
     *
     * @param RoleService     $roleService The role service.
     * @param LoggerInterface $logger      PSR-3 logger for cascade failures.
     */
    public function __construct(
        private readonly RoleService $roleService,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Handle the GroupDeletedEvent — remove every role assignment
     * scoped to the deleted group.
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

        $groupId = (string) $event->getGroup()->getGID();

        try {
            $this->roleService->deleteByGroupId(groupId: $groupId);
        } catch (Throwable $t) {
            // Cascade is best-effort — log and continue so the group
            // deletion event chain is not interrupted.
            $this->logger->error(
                message: sprintf(
                    'mydash GroupDeletedListener: failed to cascade role-assignment cleanup for group %s: %s',
                    $groupId,
                    $t->getMessage()
                ),
                context: ['app' => 'mydash']
            );
        }
    }//end handle()
}//end class
