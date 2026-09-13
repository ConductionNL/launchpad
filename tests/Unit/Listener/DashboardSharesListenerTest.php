<?php

/**
 * DashboardSharesListenerTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Listener\DashboardSharesListener}, the
 * cascade listener that deletes a deleted dashboard's user and group shares
 * (dashboard-cascade-events REQ-CSC-002, REQ-CSC-003, REQ-CSC-006).
 *
 * The row-level proof, a real dashboard with a real user share and group
 * share deleted through DashboardService, is
 * DashboardSharesCascadeDatabaseTest, which needs a live database. This file
 * pins the two things a database test cannot isolate: that the listener keys
 * on the event's ID, and that it is actually registered.
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
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\DashboardSharesListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the collaborators.
 */
class DashboardSharesListenerTest extends TestCase {
	/** @var DashboardShareMapper&MockObject */
	private $shareMapper;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/**
	 * Build the collaborators every test shares.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->shareMapper = $this->createMock(originalClassName: DashboardShareMapper::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}//end setUp()

	/**
	 * Build a deletion event.
	 *
	 * @param int|null $dashboardId The deleted row's id, or null.
	 *
	 * @return DashboardDeletedEvent
	 */
	private function event(?int $dashboardId): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: 'abc-123',
			ownerUserId: 'alice',
			type: 'user',
			deletedAt: new DateTimeImmutable(),
			dashboardId: $dashboardId,
		);
	}//end event()

	/**
	 * The listener under test.
	 *
	 * @return DashboardSharesListener
	 */
	private function listener(): DashboardSharesListener {
		return new DashboardSharesListener(
			shareMapper: $this->shareMapper,
			logger: $this->logger
		);
	}//end listener()

	/**
	 * With an id on the event, shares are deleted BY ID and never by UUID.
	 *
	 * The UUID path translates through `oc_launchpad_dashboards`, and every
	 * dispatcher fires after that row is gone, so taking it would delete zero
	 * rows. This is the assertion that fails if the listener regresses to it.
	 *
	 * @return void
	 */
	public function testDeletesByTheIdTheEventCarries(): void {
		$this->shareMapper->expects($this->once())
			->method('deleteByDashboardId')
			->with(42)
			->willReturn(2);
		$this->shareMapper->expects($this->never())->method('deleteByDashboardUuid');

		$this->listener()->handle(event: $this->event(dashboardId: 42));
	}//end testDeletesByTheIdTheEventCarries()

	/**
	 * Without an id, it falls back to the UUID, which works while the row exists.
	 *
	 * @return void
	 */
	public function testFallsBackToTheUuidWhenTheEventCarriesNoId(): void {
		$this->shareMapper->expects($this->never())->method('deleteByDashboardId');
		$this->shareMapper->expects($this->once())
			->method('deleteByDashboardUuid')
			->with('abc-123')
			->willReturn(1);

		$this->listener()->handle(event: $this->event(dashboardId: null));
	}//end testFallsBackToTheUuidWhenTheEventCarriesNoId()

	/**
	 * REQ-CSC-006: a failing delete is logged and swallowed, never rethrown.
	 *
	 * @return void
	 */
	public function testAFailingDeleteIsLoggedAndDoesNotStopPeerListeners(): void {
		$this->shareMapper->method('deleteByDashboardId')
			->willThrowException(new RuntimeException(message: 'db gone'));
		$this->logger->expects($this->once())->method('warning');

		$this->listener()->handle(event: $this->event(dashboardId: 42));
	}//end testAFailingDeleteIsLoggedAndDoesNotStopPeerListeners()

	/**
	 * Any other event is ignored.
	 *
	 * @return void
	 */
	public function testIgnoresOtherEvents(): void {
		$this->shareMapper->expects($this->never())->method('deleteByDashboardId');
		$this->shareMapper->expects($this->never())->method('deleteByDashboardUuid');

		$this->listener()->handle(event: new Event());
	}//end testIgnoresOtherEvents()

	/**
	 * REQ-CSC-002: the listener is registered for DashboardDeletedEvent.
	 *
	 * A listener class nobody registers deletes nothing, and every test above
	 * would still pass. So this runs the real `Application::register()`
	 * against a recording context. The constructor is skipped because
	 * `App::__construct()` needs a live server, and `register()` touches only
	 * the context it is given.
	 *
	 * @return void
	 */
	public function testIsRegisteredForDashboardDeletedEvent(): void {
		$registered = [];
		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		$context->method('registerEventListener')->willReturnCallback(
			static function (string $event, string $listener) use (&$registered): void {
				$registered[] = [$event, $listener];
			}
		);

		$application = (new ReflectionClass(objectOrClass: Application::class))->newInstanceWithoutConstructor();
		$application->register(context: $context);

		$this->assertContains(
			needle: [DashboardDeletedEvent::class, DashboardSharesListener::class],
			haystack: $registered,
			message: 'DashboardSharesListener is not registered for DashboardDeletedEvent'
		);
	}//end testIsRegisteredForDashboardDeletedEvent()
}//end class
