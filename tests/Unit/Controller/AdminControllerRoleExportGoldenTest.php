<?php

/**
 * AdminControllerRoleExportGoldenTest
 *
 * Pins the answers of `AdminController::createRole`, `export` and `import`
 * on every branch (allowed and refused), so the split of those endpoints
 * into named steps keeps them byte-for-byte.
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

use OCA\LaunchPad\Controller\AdminCallerAndRoles;
use OCA\LaunchPad\Controller\AdminController;
use OCA\LaunchPad\Controller\AdminSettingsTools;
use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Exception\DuplicateRoleAssignmentException;
use OCA\LaunchPad\Exception\InvalidRoleAssignmentException;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminControllerRoleExportGoldenTest extends TestCase {
	/** @var RoleService&MockObject */
	private $roleService;

	/** @var ExportService&MockObject */
	private $exportService;

	/** @var ImportService&MockObject */
	private $importService;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	private AdminController $controller;

	private bool $callerIsAdmin = true;

	protected function setUp(): void {
		$this->roleService = $this->createMock(originalClassName: RoleService::class);
		$this->exportService = $this->createMock(originalClassName: ExportService::class);
		$this->importService = $this->createMock(originalClassName: ImportService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(originalClassName: IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->willReturnCallback(
			fn (string $uid): bool => ($uid === 'alice' && $this->callerIsAdmin === true)
		);

		$this->controller = new AdminController(
			request: $this->createMock(originalClassName: IRequest::class),
			templates: new AdminTemplateTools(
				templateService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\AdminTemplateService::class),
				exportService: $this->exportService,
				importService: $this->importService,
				resyncService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\TemplateResyncService::class)
			),
			settings: new AdminSettingsTools(
				settingsService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\AdminSettingsService::class),
				groupManager: $this->groupManager,
				footerService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\FooterService::class),
				setupWizardService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\SetupWizardService::class)
			),
			caller: new AdminCallerAndRoles(
				roleService: $this->roleService,
				feedRefresh: $this->createMock(originalClassName: \OCA\LaunchPad\Service\FeedRefreshService::class),
				userSession: $userSession,
				actionAuth: $this->createMock(originalClassName: \OCA\LaunchPad\Service\ActionAuthService::class)
			)
		);
	}

	protected function tearDown(): void {
		unset($_FILES['file']);
	}

	/**
	 * Status and body of a JSON answer.
	 *
	 * @param JSONResponse $response The answer.
	 *
	 * @return array{0: int, 1: mixed}
	 */
	private function answer(JSONResponse $response): array {
		return [$response->getStatus(), $response->getData()];
	}

	public function testCreateRoleAnswers(): void {
		$assignment = $this->createMock(originalClassName: RoleAssignment::class);
		$assignment->method('jsonSerialize')->willReturn(['id' => 3, 'role' => 'editor']);
		$this->roleService->expects($this->exactly(3))->method('assignRole')
			->willReturnCallback(
				static function (?string $userId, ?string $groupId, string $role, string $assignedBy) use ($assignment) {
					self::assertSame('alice', $assignedBy);
					if ($role === 'dup') {
						throw new DuplicateRoleAssignmentException();
					}

					if ($role === '') {
						throw new InvalidRoleAssignmentException();
					}

					return $assignment;
				}
			);

		$this->assertSame(
			[201, ['id' => 3, 'role' => 'editor']],
			$this->answer($this->controller->createRole(userId: 'bob', groupId: null, role: 'editor'))
		);
		$this->assertSame(
			[409, ['error' => 'That target already has the requested role assigned', 'errorCode' => 'duplicate_role_assignment']],
			$this->answer($this->controller->createRole(userId: 'bob', groupId: null, role: 'dup'))
		);
		$this->assertSame(
			[400, ['error' => 'Role assignment payload is invalid', 'errorCode' => 'invalid_role_assignment']],
			$this->answer($this->controller->createRole(userId: null, groupId: null, role: null))
		);
	}

	public function testExportAnswers(): void {
		$site = $this->createMock(originalClassName: StreamResponse::class);
		$one = $this->createMock(originalClassName: StreamResponse::class);
		$this->exportService->method('exportSite')->with('alice')->willReturn($site);
		$this->exportService->method('exportDashboard')->willReturnCallback(
			static function (string $dashboardUuid, string $currentUserId) use ($one) {
				self::assertSame('alice', $currentUserId);
				if ($dashboardUuid === 'missing-uuid') {
					throw new DoesNotExistException('nope');
				}

				return $one;
			}
		);

		$this->assertSame($site, $this->controller->export(scope: 'site', dashboardUuid: null));
		$this->assertSame($one, $this->controller->export(scope: 'dashboard', dashboardUuid: 'abcd-1234'));
		$this->assertSame(
			[404, ['error' => 'Dashboard not found']],
			$this->answer($this->controller->export(scope: 'dashboard', dashboardUuid: 'missing-uuid'))
		);
		$this->assertSame(
			[400, ['error' => 'dashboardUuid parameter is required when scope=dashboard']],
			$this->answer($this->controller->export(scope: 'dashboard', dashboardUuid: ''))
		);
		$this->assertSame(
			[400, ['error' => 'Invalid dashboard UUID format']],
			$this->answer($this->controller->export(scope: 'dashboard', dashboardUuid: 'bad uuid!'))
		);
		$this->assertSame(
			[400, ['error' => 'Unsupported scope: x']],
			$this->answer($this->controller->export(scope: 'x', dashboardUuid: null))
		);

		$this->callerIsAdmin = false;
		$this->assertSame(403, $this->controller->export(scope: 'site', dashboardUuid: null)->getStatus());
	}

	public function testImportAnswers(): void {
		$results = [
			['status' => 'ok', 'importedDashboardCount' => 2, 'skippedDashboardCount' => 1, 'errors' => ['e']],
			['status' => ImportService::ERR_UUID_COLLISION, 'errors' => ['clash']],
		];
		$this->importService->method('import')->willReturnCallback(
			static function (string $zipPath, bool $preserveUuids, string $currentUserId) use (&$results) {
				self::assertSame('/tmp/upload.zip', $zipPath);
				self::assertSame('alice', $currentUserId);
				if ($preserveUuids === false && $results === []) {
					throw new \InvalidArgumentException('Not a zip');
				}

				return array_shift($results);
			}
		);

		$_FILES['file'] = ['tmp_name' => '/tmp/upload.zip'];
		$this->assertSame(
			[200, ['importedDashboardCount' => 2, 'skippedDashboardCount' => 1, 'errors' => ['e']]],
			$this->answer($this->controller->import(preserveUuids: true))
		);
		$this->assertSame(
			[409, ['importedDashboardCount' => 0, 'skippedDashboardCount' => 0, 'errors' => ['clash']]],
			$this->answer($this->controller->import(preserveUuids: true))
		);
		$this->assertSame(
			[400, ['error' => 'Not a zip']],
			$this->answer($this->controller->import(preserveUuids: false))
		);

		$_FILES['file'] = ['tmp_name' => ''];
		$this->assertSame(
			[400, ['error' => 'No file uploaded under field "file".']],
			$this->answer($this->controller->import(preserveUuids: false))
		);
		$_FILES['file'] = 'not-an-array';
		$this->assertSame(400, $this->controller->import(preserveUuids: false)->getStatus());

		$this->callerIsAdmin = false;
		$this->assertSame(403, $this->controller->import(preserveUuids: false)->getStatus());
	}
}
