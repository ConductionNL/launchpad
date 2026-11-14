<?php

/**
 * AdminDemoShowcasesControllerDestroyTest
 *
 * Unit tests for AdminDemoShowcasesController::destroy(): the 204 on a
 * successful uninstall, the logged neutral 500 when it fails, and the guard
 * that refuses a non-admin before anything is uninstalled.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AdminDemoShowcasesController;
use OCA\LaunchPad\Controller\ShowcaseTools;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\AdminDemoShowcasesController
 * @uses   \OCA\LaunchPad\Controller\ShowcaseTools
 * @uses   \OCA\LaunchPad\Service\RequestGuard
 */
class AdminDemoShowcasesControllerDestroyTest extends TestCase {
	private DemoShowcasesService&MockObject $service;

	private LoggerInterface&MockObject $logger;

	private IGroupManager&MockObject $groups;

	private AdminDemoShowcasesController $controller;

	/**
	 * Build the controller with a signed-in user.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(DemoShowcasesService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->groups = $this->createMock(IGroupManager::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->controller = new AdminDemoShowcasesController(
			request: $this->createMock(IRequest::class),
			showcases: new ShowcaseTools(showcasesSvc: $this->service, logger: $this->logger),
			guard: new RequestGuard(
				userSession: $session,
				groupManager: $this->groups,
				actionAuth: $this->createMock(ActionAuthService::class)
			)
		);
	}//end setUp()

	public function testASuccessfulUninstallIs204(): void {
		$this->groups->method('isAdmin')->willReturn(true);
		$this->service->expects($this->once())->method('uninstallShowcase')->with('de-bron');

		$response = $this->controller->destroy(id: 'de-bron');

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testAFailedUninstallIsLoggedAndAnswersANeutral500(): void {
		$this->groups->method('isAdmin')->willReturn(true);
		$failure = new RuntimeException('table locked');
		$this->service->method('uninstallShowcase')->willThrowException($failure);
		$this->logger->expects($this->once())
			->method('error')
			->with('Showcase uninstall failed', ['showcaseId' => 'de-bron', 'exception' => $failure]);

		$response = $this->controller->destroy(id: 'de-bron');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Showcase uninstall failed'], $response->getData());
	}

	public function testANonAdminCannotUninstall(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->service->expects($this->never())->method('uninstallShowcase');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller->destroy(id: 'de-bron')->getStatus());
	}
}//end class
