<?php

/**
 * DashboardDiscussionListener Test
 *
 * A changed audience syncs the discussion object of the dashboard or of an
 * owner's dashboards; a deleted dashboard removes it.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Event\DashboardAudienceChangedEvent;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\DashboardDiscussionListener;
use OCA\LaunchPad\Service\Discussion\DashboardDiscussionMirror;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DashboardDiscussionListener}.
 */
class DashboardDiscussionListenerTest extends TestCase {

	/**
	 * Each event reaches the matching mirror call, and an unrelated event none.
	 *
	 * @return void
	 */
	public function testEachEventReachesItsMirrorCall(): void {
		$mirror = $this->createMock(originalClassName: DashboardDiscussionMirror::class);
		$mirror->expects($this->once())->method('syncDashboard')->with(7);
		$mirror->expects($this->once())->method('syncOwnedBy')->with('alice');
		$mirror->expects($this->once())->method('remove')->with('uuid-7');
		$listener = new DashboardDiscussionListener(mirror: $mirror);

		$listener->handle(event: new DashboardAudienceChangedEvent(dashboardId: 7));
		$listener->handle(event: new DashboardAudienceChangedEvent(ownerId: 'alice'));
		$listener->handle(event: new DashboardDeletedEvent(
			dashboardUuid: 'uuid-7',
			ownerUserId: 'alice',
			type: 'user',
			deletedAt: new DateTimeImmutable(),
			dashboardId: 7
		));
		$listener->handle(event: new Event());
	}//end testEachEventReachesItsMirrorCall()
}//end class
