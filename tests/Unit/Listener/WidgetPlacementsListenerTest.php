<?php

/**
 * WidgetPlacementsListenerTest
 *
 * Unit tests for WidgetPlacementsListener covering REQ-CSC-003 stub
 * behaviour: handle() accepts DashboardDeletedEvent silently and
 * ignores foreign event types without throwing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\WidgetPlacementsListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for WidgetPlacementsListener stub.
 */
class WidgetPlacementsListenerTest extends TestCase
{
    /**
     * The stub handles a DashboardDeletedEvent without throwing.
     *
     * REQ-CSC-003 stub registration; live cleanup is owned by the
     * placements subsystem (downstream proposal).
     *
     * @return void
     */
    public function testHandlesDashboardDeletedEventWithoutThrowing(): void
    {
        $logger         = $this->createMock(originalClassName: LoggerInterface::class);
        $placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
        $placementMapper->method('deleteByDashboardUuid')->willReturn(0);
        $listener = new WidgetPlacementsListener(
            placementMapper: $placementMapper,
            logger: $logger
        );

        $event = new DashboardDeletedEvent(
            dashboardUuid: 'abc-123',
            ownerUserId: 'alice',
            type: 'user',
            deletedAt: new DateTimeImmutable(),
        );

        $listener->handle(event: $event);

        $this->expectNotToPerformAssertions();
    }//end testHandlesDashboardDeletedEventWithoutThrowing()

    /**
     * The stub silently ignores events of a foreign type — the
     * `instanceof` guard short-circuits.
     *
     * @return void
     */
    public function testIgnoresForeignEventTypes(): void
    {
        $logger          = $this->createMock(originalClassName: LoggerInterface::class);
        $placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
        $listener        = new WidgetPlacementsListener(
            placementMapper: $placementMapper,
            logger: $logger
        );

        $foreignEvent = new class extends Event {
        };

        $logger->expects($this->never())->method(constraint: 'debug');
        $logger->expects($this->never())->method(constraint: 'warning');

        $listener->handle(event: $foreignEvent);
    }//end testIgnoresForeignEventTypes()
}//end class
