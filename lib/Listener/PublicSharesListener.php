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
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Soft-revokes public shares for a deleted dashboard.
 *
 * @implements IEventListener<DashboardDeletedEvent>
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
 */
class PublicSharesListener implements IEventListener
{
    /**
     * Constructor.
     *
     * @param PublicShareMapper $shareMapper Public share mapper.
     * @param LoggerInterface   $logger      PSR-3 logger.
     */
    public function __construct(
        private readonly PublicShareMapper $shareMapper,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Handle the DashboardDeletedEvent by soft-revoking all active shares.
     *
     * @param Event $event The event.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     */
    public function handle(Event $event): void
    {
        if (($event instanceof DashboardDeletedEvent) === false) {
            return;
        }

        $uuid = $event->getDashboardUuid();

        try {
            $count = $this->shareMapper->revokeByDashboardUuid(
                dashboardUuid: $uuid
            );

            $this->logger->debug(
                message: sprintf(
                    'launchpad PublicSharesListener: soft-revoked %d share(s) for dashboard %s',
                    $count,
                    $uuid
                ),
                context: ['app' => 'launchpad']
            );
        } catch (Throwable $t) {
            $this->logger->warning(
                message: sprintf(
                    'launchpad PublicSharesListener: failed for dashboard %s: %s',
                    $uuid,
                    $t->getMessage()
                ),
                context: ['app' => 'launchpad']
            );
        }//end try
    }//end handle()
}//end class
