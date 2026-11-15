<?php

/**
 * AdminController roles and templates test
 *
 * Pins the role-assignment endpoints (list, create with its 409 and 400
 * refusals, delete with its 404, the caller's own role behind the action
 * matrix), the template read and delete endpoints, and the inline admin
 * guard's refusal of an anonymous caller.
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

use OCA\LaunchPad\Controller\AdminCallerAndRoles;
use OCA\LaunchPad\Controller\AdminController;
use OCA\LaunchPad\Controller\AdminSettingsTools;
use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Exception\DuplicateRoleAssignmentException;
use OCA\LaunchPad\Exception\InvalidRoleAssignmentException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\RoleService;
use OCA\LaunchPad\Service\SetupWizardService;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdminControllerRolesAndTemplatesTest extends TestCase {
	/**
	 * Controller.
	 *
	 * @var AdminController
	 */
	private AdminController $controller;

	/**
	 * Template service.
	 *
	 * @var AdminTemplateService
	 */
	private AdminTemplateService&MockObject $templateService;

	/**
	 * Group manager.
	 *
	 * @var IGroupManager
	 */
	private IGroupManager&MockObject $groupManager;

	/**
	 * User session.
	 *
	 * @var IUserSession
	 */
	private IUserSession&MockObject $userSession;

	/**
	 * Role service.
	 *
	 * @var RoleService
	 */
	private RoleService&MockObject $roleService;

	/**
	 * Action auth.
	 *
	 * @var ActionAuthService
	 */
	private ActionAuthService&MockObject $actionAuth;

	/**
	 * Feed refresh.
	 *
	 * @var FeedRefreshService
	 */
	private FeedRefreshService&MockObject $feedRefresh;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->templateService = $this->createMock(originalClassName: AdminTemplateService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->roleService = $this->createMock(originalClassName: RoleService::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->feedRefresh = $this->createMock(originalClassName: FeedRefreshService::class);

		$this->controller = new AdminController(
			request: $this->createMock(originalClassName: IRequest::class),
			templates: new AdminTemplateTools(
				templateService: $this->templateService,
				exportService: $this->createMock(originalClassName: ExportService::class),
				importService: $this->createMock(originalClassName: ImportService::class),
				resyncService: $this->createMock(originalClassName: TemplateResyncService::class)
			),
			settings: new AdminSettingsTools(
				settingsService: $this->createMock(originalClassName: AdminSettingsService::class),
				groupManager: $this->groupManager,
				footerService: $this->createMock(originalClassName: FooterService::class),
				setupWizardService: $this->createMock(originalClassName: SetupWizardService::class)
			),
			caller: new AdminCallerAndRoles(
				roleService: $this->roleService,
				feedRefresh: $this->feedRefresh,
				userSession: $this->userSession,
				actionAuth: $this->actionAuth
			)
		);
	}//end setUp()

	/**
	 * Sign in.
	 *
	 * @param string $uid Uid.
	 * @return IUser
	 */
	private function signIn(string $uid): IUser {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn(value: $uid);
		$this->userSession->method('getUser')->willReturn(value: $user);
		return $user;
	}//end signIn()

	/**
	 * Admin guard refuses an anonymous caller.
	 *
	 * @return void
	 */
	public function testAdminGuardRefusesAnAnonymousCaller(): void {
		$this->userSession->method('getUser')->willReturn(value: null);
		$this->feedRefresh->expects($this->never())->method('refreshAll');

		$response = $this->controller->refreshFeedsNow();

		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Not authenticated'], actual: $response->getData());
	}//end testAdminGuardRefusesAnAnonymousCaller()

	/**
	 * List roles serialises every assignment.
	 *
	 * @return void
	 */
	public function testListRolesSerialisesEveryAssignment(): void {
		$row = new RoleAssignment();
		$row->setUserId('alice');
		$row->setRole('editor');
		$this->roleService->method('listAssignments')->willReturn(value: [$row]);

		$response = $this->controller->listRoles();

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'alice', actual: $response->getData()[0]['userId']);
		$this->assertSame(expected: 'editor', actual: $response->getData()[0]['role']);
	}//end testListRolesSerialisesEveryAssignment()

	/**
	 * Create role records the acting admin and answers201.
	 *
	 * @return void
	 */
	public function testCreateRoleRecordsTheActingAdminAndAnswers201(): void {
		$this->signIn(uid: 'root');
		$created = new RoleAssignment();
		$created->setGroupId('staff');
		$created->setRole('viewer');
		$created->setAssignedBy('root');
		$this->roleService->expects($this->once())->method('assignRole')
			->with(null, 'staff', 'viewer', 'root')
			->willReturn(value: $created);

		$response = $this->controller->createRole(groupId: 'staff', role: 'viewer');

		$this->assertSame(expected: Http::STATUS_CREATED, actual: $response->getStatus());
		$this->assertSame(expected: 'root', actual: $response->getData()['assignedBy']);
	}//end testCreateRoleRecordsTheActingAdminAndAnswers201()

	/**
	 * Create role answers aduplicate with409.
	 *
	 * @return void
	 */
	public function testCreateRoleAnswersADuplicateWith409(): void {
		$this->signIn(uid: 'root');
		$this->roleService->method('assignRole')
			->willThrowException(exception: new DuplicateRoleAssignmentException());

		$response = $this->controller->createRole(userId: 'alice', role: 'editor');

		$this->assertSame(expected: Http::STATUS_CONFLICT, actual: $response->getStatus());
		$this->assertSame(
			expected: 'duplicate_role_assignment',
			actual: $response->getData()['errorCode']
		);
	}//end testCreateRoleAnswersADuplicateWith409()

	/**
	 * Create role answers an invalid payload with400.
	 *
	 * @return void
	 */
	public function testCreateRoleAnswersAnInvalidPayloadWith400(): void {
		$this->signIn(uid: 'root');
		$this->roleService->method('assignRole')
			->willThrowException(exception: new InvalidRoleAssignmentException(message: 'Unknown role'));

		$response = $this->controller->createRole(userId: 'alice', role: 'overlord');

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		$this->assertSame(
			expected: ['error' => 'Unknown role', 'errorCode' => 'invalid_role_assignment'],
			actual: $response->getData()
		);
	}//end testCreateRoleAnswersAnInvalidPayloadWith400()

	/**
	 * Delete role answers204.
	 *
	 * @return void
	 */
	public function testDeleteRoleAnswers204(): void {
		$this->roleService->expects($this->once())->method('removeRole')->with(12);

		$this->assertSame(
			expected: Http::STATUS_NO_CONTENT,
			actual: $this->controller->deleteRole(id: 12)->getStatus()
		);
	}//end testDeleteRoleAnswers204()

	/**
	 * Delete role answers amissing row with404.
	 *
	 * @return void
	 */
	public function testDeleteRoleAnswersAMissingRowWith404(): void {
		$this->roleService->method('removeRole')
			->willThrowException(exception: new DoesNotExistException(msg: 'gone'));

		$this->assertSame(
			expected: Http::STATUS_NOT_FOUND,
			actual: $this->controller->deleteRole(id: 12)->getStatus()
		);
	}//end testDeleteRoleAnswersAMissingRowWith404()

	/**
	 * Get my role refuses an anonymous caller.
	 *
	 * @return void
	 */
	public function testGetMyRoleRefusesAnAnonymousCaller(): void {
		$this->userSession->method('getUser')->willReturn(value: null);
		$this->roleService->expects($this->never())->method('getEffectiveRole');

		$this->assertSame(
			expected: Http::STATUS_UNAUTHORIZED,
			actual: $this->controller->getMyRole()->getStatus()
		);
	}//end testGetMyRoleRefusesAnAnonymousCaller()

	/**
	 * Get my role refuses acaller the matrix denies.
	 *
	 * @return void
	 */
	public function testGetMyRoleRefusesACallerTheMatrixDenies(): void {
		$this->signIn(uid: 'alice');
		$this->actionAuth->method('requireAction')
			->willThrowException(exception: new OCSForbiddenException());
		$this->roleService->expects($this->never())->method('getEffectiveRole');

		$response = $this->controller->getMyRole();

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Forbidden'], actual: $response->getData());
	}//end testGetMyRoleRefusesACallerTheMatrixDenies()

	/**
	 * Get my role answers the callers own role and source.
	 *
	 * @return void
	 */
	public function testGetMyRoleAnswersTheCallersOwnRoleAndSource(): void {
		$user = $this->signIn(uid: 'alice');
		$this->actionAuth->expects($this->once())->method('requireAction')
			->with($user, 'admin.get-my-role');
		$this->roleService->method('getEffectiveRole')->with('alice')->willReturn(value: 'editor');
		$this->roleService->method('getRoleSource')->with('alice')->willReturn(value: 'group');

		$this->assertSame(
			expected: ['role' => 'editor', 'source' => 'group'],
			actual: $this->controller->getMyRole()->getData()
		);
	}//end testGetMyRoleAnswersTheCallersOwnRoleAndSource()

	/**
	 * List templates serialises every template.
	 *
	 * @return void
	 */
	public function testListTemplatesSerialisesEveryTemplate(): void {
		$template = new Dashboard();
		$template->setName('Onboarding');
		$this->templateService->method('listTemplates')->willReturn(value: [$template]);

		$data = $this->controller->listTemplates()->getData();

		$this->assertCount(expectedCount: 1, haystack: $data);
		$this->assertSame(expected: 'Onboarding', actual: $data[0]['name']);
	}//end testListTemplatesSerialisesEveryTemplate()

	/**
	 * Get template answers the template with its placements.
	 *
	 * @return void
	 */
	public function testGetTemplateAnswersTheTemplateWithItsPlacements(): void {
		$template = new Dashboard();
		$template->setName('Onboarding');
		$this->templateService->method('getTemplateWithPlacements')->with(3)
			->willReturn(value: ['template' => $template, 'placements' => []]);

		$response = $this->controller->getTemplate(id: 3);

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'Onboarding', actual: $response->getData()['template']['name']);
		$this->assertSame(expected: [], actual: $response->getData()['placements']);
	}//end testGetTemplateAnswersTheTemplateWithItsPlacements()

	/**
	 * Get template answers amissing template with404.
	 *
	 * @return void
	 */
	public function testGetTemplateAnswersAMissingTemplateWith404(): void {
		$this->templateService->method('getTemplateWithPlacements')
			->willThrowException(exception: new DoesNotExistException(msg: 'gone'));

		$response = $this->controller->getTemplate(id: 3);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
		// The internal message is not echoed to the client.
		$this->assertSame(expected: ['error' => 'Operation failed'], actual: $response->getData());
	}//end testGetTemplateAnswersAMissingTemplateWith404()

	/**
	 * Delete template confirms the deletion.
	 *
	 * @return void
	 */
	public function testDeleteTemplateConfirmsTheDeletion(): void {
		$this->templateService->expects($this->once())->method('deleteTemplate')->with(3);

		$this->assertSame(
			expected: ['status' => 'ok'],
			actual: $this->controller->deleteTemplate(id: 3)->getData()
		);
	}//end testDeleteTemplateConfirmsTheDeletion()

	/**
	 * Delete template answers afailure with400.
	 *
	 * @return void
	 */
	public function testDeleteTemplateAnswersAFailureWith400(): void {
		$this->templateService->method('deleteTemplate')
			->willThrowException(exception: new RuntimeException(message: 'db down'));

		$response = $this->controller->deleteTemplate(id: 3);

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Operation failed'], actual: $response->getData());
	}//end testDeleteTemplateAnswersAFailureWith400()
}//end class
