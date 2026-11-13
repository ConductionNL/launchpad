<?php

/**
 * Tests for TreeListener — the cascade that re-announces a deleted
 * dashboard's children as deleted themselves
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

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\TreeListener;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Listener\TreeListener
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class TreeListenerTest extends TestCase {

	/**
	 * Dashboard mapper.
	 *
	 * @var DashboardMapper|MockObject
	 */
	private DashboardMapper|MockObject $mapper;

	/**
	 * Event dispatcher.
	 *
	 * @var IEventDispatcher|MockObject
	 */
	private IEventDispatcher|MockObject $dispatcher;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface|MockObject
	 */
	private LoggerInterface|MockObject $logger;

	/**
	 * Listener under test.
	 *
	 * @var TreeListener
	 */
	private TreeListener $listener;

	/**
	 * Build the listener with doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper     = $this->createMock(DashboardMapper::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger     = $this->createMock(LoggerInterface::class);
		$this->listener   = new TreeListener(
			dashboardMapper: $this->mapper,
			dispatcher: $this->dispatcher,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * The parent deletion event.
	 *
	 * @return DashboardDeletedEvent
	 */
	private function parent(): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: 'parent-1',
			ownerUserId: 'alice',
			type: 'admin_template',
			deletedAt: new DateTimeImmutable('2026-05-06T07:08:09+00:00'),
			dashboardId: 1
		);
	}//end parent()

	/**
	 * Build a child dashboard.
	 *
	 * @param int         $id     Id.
	 * @param string|null $uuid   Uuid.
	 * @param string|null $userId Owner.
	 * @param string|null $type   Type.
	 *
	 * @return Dashboard
	 */
	private function child(int $id, ?string $uuid, ?string $userId = null, ?string $type = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		if ($uuid !== null) {
			$dashboard->setUuid($uuid);
		}
		if ($userId !== null) {
			$dashboard->setUserId($userId);
		}
		if ($type !== null) {
			$dashboard->setType($type);
		}

		return $dashboard;
	}//end child()

	/**
	 * A child with its own owner and type is announced with those values,
	 * the parent's deletion time and the child's id.
	 *
	 * @return void
	 */
	public function testAChildIsAnnouncedWithItsOwnOwnerAndType(): void {
		$this->mapper->expects($this->once())
			->method('findByParent')
			->with(parentUuid: 'parent-1')
			->willReturn([$this->child(5, 'child-5', 'carol', 'group_shared')]);

		$dispatched = [];
		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (DashboardDeletedEvent $event) use (&$dispatched): void {
				$dispatched[] = $event;
			});

		$this->listener->handle($this->parent());

		$event = $dispatched[0];
		$this->assertSame('child-5', $event->getDashboardUuid());
		$this->assertSame('carol', $event->getOwnerUserId());
		$this->assertSame('group_shared', $event->getType());
		$this->assertSame('2026-05-06T07:08:09+00:00', $event->getDeletedAt()->format(DATE_ATOM));
		$this->assertSame(5, $event->getDashboardId());
	}//end testAChildIsAnnouncedWithItsOwnOwnerAndType()

	/**
	 * A child without owner or type inherits the parent's.
	 *
	 * @return void
	 */
	public function testAChildWithoutOwnerOrTypeInheritsTheParents(): void {
		// A new Dashboard defaults to the user type; only an explicit null
		// type (a legacy row) falls back to the parent's.
		$orphan = $this->child(6, 'child-6');
		$orphan->setType(null);
		$this->mapper->method('findByParent')->willReturn([$orphan]);

		$dispatched = [];
		$this->dispatcher->method('dispatchTyped')
			->willReturnCallback(function (DashboardDeletedEvent $event) use (&$dispatched): void {
				$dispatched[] = $event;
			});

		$this->listener->handle($this->parent());

		$this->assertCount(1, $dispatched);
		$this->assertSame('alice', $dispatched[0]->getOwnerUserId());
		$this->assertSame('admin_template', $dispatched[0]->getType());
	}//end testAChildWithoutOwnerOrTypeInheritsTheParents()

	/**
	 * A child without a uuid is skipped but still counted in the debug line.
	 *
	 * @return void
	 */
	public function testAUuidlessChildIsSkippedButCounted(): void {
		$this->mapper->method('findByParent')->willReturn([
			$this->child(7, null),
			$this->child(8, 'child-8'),
		]);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(static fn (DashboardDeletedEvent $e): bool => $e->getDashboardUuid() === 'child-8'));

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'launchpad TreeListener: dispatched delete for 2 children of dashboard parent-1',
				['app' => 'launchpad']
			);

		$this->listener->handle($this->parent());
	}//end testAUuidlessChildIsSkippedButCounted()

	/**
	 * No children: nothing dispatched, a zero count logged.
	 *
	 * @return void
	 */
	public function testNoChildrenDispatchesNothing(): void {
		$this->mapper->method('findByParent')->willReturn([]);
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'launchpad TreeListener: dispatched delete for 0 children of dashboard parent-1',
				['app' => 'launchpad']
			);

		$this->listener->handle($this->parent());
	}//end testNoChildrenDispatchesNothing()

	/**
	 * A failing dispatch is logged as a warning and not rethrown; no
	 * success line is written.
	 *
	 * @return void
	 */
	public function testAFailingDispatchIsLoggedAndSwallowed(): void {
		$this->mapper->method('findByParent')->willReturn([$this->child(9, 'child-9')]);
		$this->dispatcher->method('dispatchTyped')
			->willThrowException(new RuntimeException('listener exploded'));

		$this->logger->expects($this->never())->method('debug');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'launchpad TreeListener: failed for dashboard parent-1: listener exploded',
				['app' => 'launchpad']
			);

		$this->listener->handle($this->parent());
	}//end testAFailingDispatchIsLoggedAndSwallowed()

	/**
	 * Other events never query the mapper.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$this->mapper->expects($this->never())->method('findByParent');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->never())->method($this->anything());

		$this->listener->handle(new Event());
	}//end testOtherEventsAreIgnored()
}//end class
