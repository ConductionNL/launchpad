<?php

/**
 * GroupDeletedListenerTest
 *
 * Pins the group-deletion cascade: role assignments scoped to the deleted
 * group are removed, a failing cascade is logged and never thrown, and
 * other events are ignored.
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

use OCA\LaunchPad\Listener\GroupDeletedListener;
use OCA\LaunchPad\Service\RoleService;
use OCP\EventDispatcher\Event;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for GroupDeletedListener.
 *
 * @covers \OCA\LaunchPad\Listener\GroupDeletedListener
 */
class GroupDeletedListenerTest extends TestCase {

	/** @var RoleService&MockObject */
	private $roleService;

	/** @var LoggerInterface&MockObject */
	private $logger;

	private GroupDeletedListener $listener;

	/**
	 * Fresh mocks per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->roleService = $this->createMock(RoleService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new GroupDeletedListener(
			roleService: $this->roleService,
			logger: $this->logger,
		);
	}//end setUp()

	/**
	 * Build a GroupDeletedEvent for the given group id.
	 *
	 * @param string $gid The group id.
	 *
	 * @return GroupDeletedEvent
	 */
	private function deletedGroup(string $gid): GroupDeletedEvent {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($gid);
		return new GroupDeletedEvent($group);
	}//end deletedGroup()

	/**
	 * Role assignments of exactly the deleted group are removed, nothing is logged.
	 *
	 * @return void
	 */
	public function testDeletesRoleAssignmentsOfTheDeletedGroup(): void {
		$this->roleService->expects($this->once())
			->method('deleteByGroupId')
			->with('editors')
			->willReturn(3);
		$this->logger->expects($this->never())->method('error');

		$this->listener->handle($this->deletedGroup('editors'));
	}//end testDeletesRoleAssignmentsOfTheDeletedGroup()

	/**
	 * A failing cascade is logged with group and cause, and does not propagate.
	 *
	 * @return void
	 */
	public function testAFailingCascadeIsLoggedAndSwallowed(): void {
		$this->roleService->method('deleteByGroupId')
			->willThrowException(new RuntimeException('db down'));
		$this->logger->expects($this->once())
			->method('error')
			->with(
				'launchpad GroupDeletedListener: failed to cascade role-assignment cleanup for group editors: db down',
				['app' => 'launchpad']
			);

		$this->listener->handle($this->deletedGroup('editors'));
	}//end testAFailingCascadeIsLoggedAndSwallowed()

	/**
	 * Any other event is ignored without touching the role service.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$this->roleService->expects($this->never())->method('deleteByGroupId');
		$this->logger->expects($this->never())->method('error');

		$this->listener->handle(new Event());
	}//end testOtherEventsAreIgnored()
}//end class
