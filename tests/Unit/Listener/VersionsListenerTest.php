<?php

/**
 * Tests for VersionsListener — the live cascade that removes a dashboard's
 * version snapshots when the dashboard is deleted.
 *
 * This listener IS registered on DashboardDeletedEvent in Application.php and
 * has always done the real work, but it had no test at all. The coverage that
 * existed sat on DashboardVersionService::deleteVersionsForDashboard(), a
 * wrapper around the same single mapper call that nothing in production ever
 * reached — so the tested path was the dead one and the live one was unproven.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\VersionsListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Listener\VersionsListener
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class VersionsListenerTest extends TestCase {
	/**
	 * The version row mapper.
	 *
	 * @var DashboardVersionMapper|MockObject
	 */
	private DashboardVersionMapper|MockObject $versionMapper;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface|MockObject
	 */
	private LoggerInterface|MockObject $logger;

	/**
	 * The listener under test.
	 *
	 * @var VersionsListener
	 */
	private VersionsListener $listener;

	/**
	 * Build the listener with mocked collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->versionMapper = $this->createMock(DashboardVersionMapper::class);
		$this->logger        = $this->createMock(LoggerInterface::class);
		$this->listener      = new VersionsListener(
			versionMapper: $this->versionMapper,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * Build a DashboardDeletedEvent for the given uuid.
	 *
	 * @param string $uuid The deleted dashboard's uuid.
	 *
	 * @return DashboardDeletedEvent
	 */
	private function event(string $uuid): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: $uuid,
			ownerUserId: 'alice',
			type: 'personal',
			deletedAt: new DateTimeImmutable('2026-08-16T12:00:00+00:00')
		);
	}//end event()

	/**
	 * The snapshots of a deleted dashboard are removed, scoped to that
	 * dashboard's uuid.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function testDeletedDashboardHasItsVersionRowsRemoved(): void {
		$this->versionMapper->expects($this->once())
			->method('deleteByDashboardUuid')
			->with(dashboardUuid: 'dash-1')
			->willReturn(7);

		$this->listener->handle($this->event('dash-1'));
	}//end testDeletedDashboardHasItsVersionRowsRemoved()

	/**
	 * An unrelated event leaves the version rows alone.
	 *
	 * Without this the listener could delete on ANY dispatched event and the
	 * test above would still pass — the instanceof guard is the only thing
	 * standing between this and a cascade that fires on the wrong signal.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function testAnUnrelatedEventDeletesNothing(): void {
		$this->versionMapper->expects($this->never())
			->method('deleteByDashboardUuid');

		$this->listener->handle(new class extends Event {
		});
	}//end testAnUnrelatedEventDeletesNothing()

	/**
	 * A mapper failure is logged and swallowed.
	 *
	 * REQ-CSC-006 is log-and-continue: this listener is one of several on
	 * DashboardDeletedEvent, and a throw here would abort the siblings that
	 * have not run yet, leaving a half-cascaded delete behind.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function testAMapperFailureIsLoggedAndNotRethrown(): void {
		$this->versionMapper->method('deleteByDashboardUuid')
			->willThrowException(new RuntimeException('database is gone'));

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('database is gone'), $this->anything());

		$this->listener->handle($this->event('dash-1'));
	}//end testAMapperFailureIsLoggedAndNotRethrown()
}//end class
