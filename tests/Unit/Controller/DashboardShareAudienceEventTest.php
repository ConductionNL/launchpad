<?php

/**
 * DashboardShareApiController audience event Test
 *
 * Every share change announces the changed audience, so the dashboard's
 * discussion object follows it; a refused change announces nothing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
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

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardShareApiController;
use OCA\LaunchPad\Controller\ShareApiTools;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Event\DashboardAudienceChangedEvent;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\DashboardShareService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the audience events of {@see DashboardShareApiController}.
 */
class DashboardShareAudienceEventTest extends TestCase {

	/**
	 * The share service.
	 *
	 * @var DashboardShareService&MockObject
	 */
	private DashboardShareService&MockObject $service;

	/**
	 * The dispatched events.
	 *
	 * @var list<object>
	 */
	private array $events = [];

	/**
	 * The controller under test, called by alice.
	 *
	 * @var DashboardShareApiController
	 */
	private DashboardShareApiController $controller;

	/**
	 * Wire the controller to a recording dispatcher.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(originalClassName: DashboardShareService::class);
		$dispatcher    = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(function (object $event): void {
			$this->events[] = $event;
		});
		$this->controller = new DashboardShareApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			shareService: $this->service,
			tools: new ShareApiTools(
				userManager: $this->createMock(originalClassName: IUserManager::class),
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				logger: $this->createMock(originalClassName: LoggerInterface::class),
				events: $dispatcher
			),
			userId: 'alice'
		);
	}//end setUp()

	/**
	 * Adding and replacing shares names the dashboard.
	 *
	 * @return void
	 */
	public function testAddingAndReplacingNameTheDashboard(): void {
		$this->service->method('addShare')->willReturn(new DashboardShare());
		$this->service->method('replaceShares')->willReturn([]);

		$this->controller->create(id: 7, shareType: 'user', shareWith: 'bob', permissionLevel: 'view_only');
		$this->controller->replace(id: 7, shares: []);

		$expected = new DashboardAudienceChangedEvent(dashboardId: 7);
		$this->assertEquals(expected: [$expected, $expected], actual: $this->events);
	}//end testAddingAndReplacingNameTheDashboard()

	/**
	 * Revoking names the owner, whose dashboards it can reach.
	 *
	 * @return void
	 */
	public function testRevokingNamesTheOwner(): void {
		$this->service->method('revokeAllForRecipient')->willReturn(2);

		$this->controller->destroy(shareId: 3);
		$this->controller->revokeForRecipient(shareType: 'user', shareWith: 'bob');

		$expected = new DashboardAudienceChangedEvent(ownerId: 'alice');
		$this->assertEquals(expected: [$expected, $expected], actual: $this->events);
	}//end testRevokingNamesTheOwner()

	/**
	 * A refused change announces nothing.
	 *
	 * @return void
	 */
	public function testARefusedChangeAnnouncesNothing(): void {
		$this->service->method('removeShare')->willThrowException(new ForbiddenException(message: 'Access denied'));

		$this->controller->destroy(shareId: 3);

		$this->assertSame(expected: [], actual: $this->events);
	}//end testARefusedChangeAnnouncesNothing()
}//end class
