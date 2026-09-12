<?php

/**
 * AdminControllerExportImportTest
 *
 * Controller-level tests for the dashboard-export-import endpoints
 * (`POST /api/admin/export`, `POST /api/admin/import`). Verifies the
 * admin-only guard, parameter validation, and 409 collision wiring.
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
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\ImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminControllerExportImportTest extends TestCase {
	/** @var IRequest&MockObject */
	private $request;

	/** @var AdminTemplateService&MockObject */
	private $templateService;

	/** @var AdminSettingsService&MockObject */
	private $settingsService;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	/** @var IUserSession&MockObject */
	private $userSession;

	/** @var ExportService&MockObject */
	private $exportService;

	/** @var ImportService&MockObject */
	private $importService;

	/** @var FooterService&MockObject */
	private $footerService;

	private AdminController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->templateService = $this->createMock(originalClassName: AdminTemplateService::class);
		$this->settingsService = $this->createMock(originalClassName: AdminSettingsService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->exportService = $this->createMock(originalClassName: ExportService::class);
		$this->importService = $this->createMock(originalClassName: ImportService::class);
		$this->footerService = $this->createMock(originalClassName: FooterService::class);

		$this->controller = new AdminController(
			request: $this->request,
			templateService: $this->templateService,
			settingsService: $this->settingsService,
			groupManager: $this->groupManager,
			userSession: $this->userSession,
			exportService: $this->exportService,
			importService: $this->importService,
			roleService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\RoleService::class),
			feedRefresh: $this->createMock(originalClassName: \OCA\LaunchPad\Service\FeedRefreshService::class),
			footerService: $this->footerService,
			setupWizardService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\SetupWizardService::class),
			actionAuth: $this->createMock(originalClassName: \OCA\LaunchPad\Service\ActionAuthService::class),
			resyncService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\TemplateResyncService::class),
		);
	}

	private function loginAsNonAdmin(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('bob')->willReturn(false);
	}

	private function loginAsAdmin(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('alice')->willReturn(true);
	}

	/**
	 * REQ-EXIM-002 "Export non-existent dashboard": a UUID nothing answers to
	 * is a 404, not a 500 and not an empty archive.
	 *
	 * @return void
	 */
	public function testExportDashboardNotFoundReturns404(): void {
		$this->loginAsAdmin();
		$this->exportService->method('exportDashboard')->willThrowException(
			exception: new DoesNotExistException(msg: 'no such dashboard')
		);

		$response = $this->controller->export(
			scope: 'dashboard',
			dashboardUuid: '00000000-0000-0000-0000-000000000000'
		);

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
	}//end testExportDashboardNotFoundReturns404()

	/**
	 * REQ-EXIM-005 "preserveUuids parameter default is false": omitting the
	 * parameter imports with fresh UUIDs.
	 *
	 * The admin page always sends the switch's state, so only a direct caller
	 * can leave it out; this is where that default lives.
	 *
	 * @return void
	 */
	public function testImportDefaultsToFreshUuids(): void {
		$this->loginAsAdmin();

		$seen = null;
		$this->importService->method('import')->willReturnCallback(
			static function (string $zipPath, bool $preserveUuids, string $userId) use (&$seen): array {
				$seen = $preserveUuids;
				return [
					'status' => 'ok',
					'importedDashboardCount' => 0,
					'skippedDashboardCount' => 0,
					'errors' => [],
					'manifest' => [],
				];
			}
		);

		$tmp = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-default-');
		$_FILES['file'] = ['tmp_name' => $tmp, 'name' => 'archive.zip', 'error' => 0, 'size' => 1];

		try {
			$this->controller->import();
		} finally {
			unset($_FILES['file']);
			@unlink(filename: $tmp);
		}

		$this->assertFalse(
			condition: $seen,
			message: 'omitting preserveUuids must import with fresh UUIDs'
		);
	}//end testImportDefaultsToFreshUuids()

	public function testExportNonAdminForbidden(): void {
		$this->loginAsNonAdmin();

		$response = $this->controller->export(scope: 'site');

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}

	public function testImportNonAdminForbidden(): void {
		$this->loginAsNonAdmin();

		$response = $this->controller->import();

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}

	public function testExportRejectsUnknownScope(): void {
		$this->loginAsAdmin();

		$response = $this->controller->export(scope: 'galaxy');

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
	}

	public function testExportDashboardRequiresUuid(): void {
		$this->loginAsAdmin();

		$response = $this->controller->export(scope: 'dashboard', dashboardUuid: null);

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		$this->assertSame(
			expected: 'dashboardUuid parameter is required when scope=dashboard',
			actual: $response->getData()['error'] ?? null
		);
	}

	public function testExportDashboardRejectsInvalidUuidFormat(): void {
		$this->loginAsAdmin();

		$response = $this->controller->export(
			scope: 'dashboard',
			dashboardUuid: 'not!valid'
		);

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		// REQ-EXIM-002 pins the message, not just the status.
		$this->assertSame(
			expected: 'Invalid dashboard UUID format',
			actual: $response->getData()['error'] ?? null
		);
	}

	public function testImportMissingFileReturns400(): void {
		$this->loginAsAdmin();
		unset($_FILES['file']);

		$response = $this->controller->import();

		$this->assertInstanceOf(expected: JSONResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
	}

	public function testImportUuidCollisionReturns409(): void {
		$this->loginAsAdmin();

		// Fake a multipart upload via $_FILES — the controller reads the
		// path straight off it without touching the filesystem (the
		// import service is mocked).
		$tmpFile = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-imp-');
		file_put_contents(filename: $tmpFile, data: 'placeholder');
		$_FILES['file'] = [
			'name' => 'archive.zip',
			'type' => 'application/zip',
			'tmp_name' => $tmpFile,
			'error' => 0,
			'size' => 11,
		];

		$this->importService
			->expects($this->once())
			->method('import')
			->willReturn([
				'status' => ImportService::ERR_UUID_COLLISION,
				'manifest' => ['schemaVersion' => 1],
				'importedDashboardCount' => 0,
				'skippedDashboardCount' => 0,
				'errors' => [
					['type' => ImportService::ERR_UUID_COLLISION, 'dashboard' => 'abc'],
				],
			]);

		$response = $this->controller->import(preserveUuids: true);

		$this->assertSame(expected: Http::STATUS_CONFLICT, actual: $response->getStatus());

		@unlink(filename: $tmpFile);
		unset($_FILES['file']);
	}
}
