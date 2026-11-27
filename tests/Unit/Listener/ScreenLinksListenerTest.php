<?php

/**
 * ScreenLinksListenerTest
 *
 * Deleting a dashboard revokes its screen link (REQ-KIOSK-006).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\ScreenLinksListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\LaunchPad\Listener\ScreenLinksListener
 */
class ScreenLinksListenerTest extends TestCase {

	public function testDeletingADashboardRevokesItsScreenLink(): void {
		$mapper = $this->createMock(KioskPlaylistMapper::class);
		$mapper->expects($this->once())->method('revokeScreenLinksOf')->with('dash-kantine')->willReturn(1);

		$listener = new ScreenLinksListener(playlistMapper: $mapper, logger: $this->createMock(LoggerInterface::class));
		$listener->handle(new DashboardDeletedEvent(
			dashboardUuid: 'dash-kantine',
			ownerUserId: 'pieter',
			type: 'user',
			deletedAt: new DateTimeImmutable()
		));
	}
}
