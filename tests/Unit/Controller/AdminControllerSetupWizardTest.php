<?php

/**
 * AdminController Setup-Wizard Test
 *
 * Covers the `getWizardState` and `completeWizard` endpoints added by the
 * `setup-wizard` change (REQ-WIZ-008, REQ-WIZ-009), and pins that the
 * retired storage-step endpoint stays gone (decision 131).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AdminController;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\SetupWizardService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminControllerSetupWizardTest extends TestCase {
	private AdminController $controller;

	/** @var IRequest&MockObject */
	private $request;
	/** @var IGroupManager&MockObject */
	private $groupManager;
	/** @var IUserSession&MockObject */
	private $userSession;
	/** @var SetupWizardService&MockObject */
	private $wizardService;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->wizardService = $this->createMock(SetupWizardService::class);

		$this->controller = new AdminController(
			request: $this->request,
			templateService: $this->createMock(AdminTemplateService::class),
			settingsService: $this->createMock(AdminSettingsService::class),
			groupManager: $this->groupManager,
			userSession: $this->userSession,
			exportService: $this->createMock(ExportService::class),
			importService: $this->createMock(ImportService::class),
			roleService: $this->createMock(\OCA\LaunchPad\Service\RoleService::class),
			feedRefresh: $this->createMock(\OCA\LaunchPad\Service\FeedRefreshService::class),
			footerService: $this->createMock(\OCA\LaunchPad\Service\FooterService::class),
			setupWizardService: $this->wizardService,
			actionAuth: $this->createMock(\OCA\LaunchPad\Service\ActionAuthService::class),
			resyncService: $this->createMock(\OCA\LaunchPad\Service\TemplateResyncService::class),
		);
	}

	private function loginAsAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('alice')->willReturn(true);
	}

	private function loginAsNonAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('bob')->willReturn(false);
	}

	public function testGetWizardStateReturnsServicePayload(): void {
		$this->loginAsAdmin();
		$payload = [
			'complete' => false,
			'currentRecommendedStep' => 2,
			'stepStatuses' => ['1' => 'done'],
		];
		$this->wizardService->expects($this->once())
			->method('getWizardState')
			->willReturn($payload);

		$response = $this->controller->getWizardState();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($payload, $response->getData());
	}

	public function testGetWizardStateRejectsNonAdminWith403(): void {
		$this->loginAsNonAdmin();
		$this->wizardService->expects($this->never())->method('getWizardState');

		$response = $this->controller->getWizardState();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testCompleteWizardCallsServiceAndReturnsState(): void {
		$this->loginAsAdmin();
		$payload = [
			'complete' => true,
			'currentRecommendedStep' => 1,
			'stepStatuses' => ['7' => 'done'],
		];
		$this->wizardService->expects($this->once())
			->method('markWizardComplete')
			->willReturn($payload);

		$response = $this->controller->completeWizard();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($payload, $response->getData());
	}

	public function testCompleteWizardRejectsNonAdminWith403(): void {
		$this->loginAsNonAdmin();
		$this->wizardService->expects($this->never())->method('markWizardComplete');

		$response = $this->controller->completeWizard();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testTheStorageStepEndpointIsRetired(): void {
		// Decision 131: the wizard no longer asks where content is stored.
		$this->assertFalse(method_exists(AdminController::class, 'setWizardStorage'));

		$routes = file_get_contents(__DIR__ . '/../../../appinfo/routes.php');
		$this->assertIsString($routes);
		$this->assertStringNotContainsString('setup-wizard/storage', $routes);
		$this->assertStringNotContainsString('setWizardStorage', $routes);
	}
}
