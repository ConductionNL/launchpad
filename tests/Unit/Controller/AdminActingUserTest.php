<?php

/**
 * AdminActingUserTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\AdminBulkController;
use OCA\LaunchPad\Controller\AdminCleanupController;
use OCA\LaunchPad\Controller\AdminOrgNavigationController;
use OCA\LaunchPad\Controller\CleanupSources;
use OCA\LaunchPad\Controller\OrgNavigationSources;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrgNavigationService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The endpoints that act on the session user's behalf hand the services that
 * user, never a value from the request. The request answers `mallory` for
 * every user-shaped parameter.
 *
 * @covers \OCA\LaunchPad\Controller\AdminBulkController
 * @covers \OCA\LaunchPad\Controller\AdminCleanupController
 * @covers \OCA\LaunchPad\Controller\AdminOrgNavigationController
 * @uses \OCA\LaunchPad\Controller\CleanupSources
 * @uses \OCA\LaunchPad\Controller\OrgNavigationSources
 * @uses \OCA\LaunchPad\Controller\ResponseHelper
 * @uses \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses \OCA\LaunchPad\Service\RequestGuard
 */
class AdminActingUserTest extends TestCase {
	/**
	 * A request whose user-shaped parameters are forged.
	 *
	 * @return IRequest The request.
	 */
	private function forgedRequest(): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static function (string $key, mixed $default = null): mixed {
			return in_array($key, ['userId', 'user', 'uid', 'requestedBy'], true) === true ? 'mallory' : $default;
		});
		return $request;
	}

	/**
	 * A guard over the given session user, who is an administrator and holds every action.
	 *
	 * @param string|null $uid The session user.
	 *
	 * @return RequestGuard The guard.
	 */
	private function guard(?string $uid): RequestGuard {
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => $userId === $uid);
		return new RequestGuard(userSession: $session, groupManager: $groups, actionAuth: $this->createMock(ActionAuthService::class));
	}

	/**
	 * Bulk delete runs for the session user.
	 *
	 * @return void
	 */
	public function testBulkDeleteActsAsTheSessionUser(): void {
		$service = $this->createMock(BulkOperationService::class);
		$service->expects($this->once())->method('bulkDelete')->with(['uuid-1'], 'alice', false, false)->willReturn([]);
		$controller = new AdminBulkController(request: $this->forgedRequest(), bulkService: $service, guard: $this->guard(uid: 'alice'));

		$this->assertSame(200, $controller->bulkDelete(dashboardUuids: ['uuid-1'])->getStatus());
	}

	/**
	 * Bulk move runs for the session user.
	 *
	 * @return void
	 */
	public function testBulkMoveActsAsTheSessionUser(): void {
		$service = $this->createMock(BulkOperationService::class);
		$service->expects($this->once())->method('bulkMove')->with(['uuid-1'], 'parent-1', 'alice', false)->willReturn([]);
		$controller = new AdminBulkController(request: $this->forgedRequest(), bulkService: $service, guard: $this->guard(uid: 'alice'));

		$this->assertSame(200, $controller->bulkMove(dashboardUuids: ['uuid-1'], parentUuid: 'parent-1')->getStatus());
	}

	/**
	 * Bulk reindex runs for the session user.
	 *
	 * @return void
	 */
	public function testBulkReindexActsAsTheSessionUser(): void {
		$service = $this->createMock(BulkOperationService::class);
		$service->expects($this->once())->method('bulkReindex')->with(['uuid-1'], 'alice', false)->willReturn([]);
		$controller = new AdminBulkController(request: $this->forgedRequest(), bulkService: $service, guard: $this->guard(uid: 'alice'));

		$this->assertSame(200, $controller->bulkReindex(dashboardUuids: ['uuid-1'])->getStatus());
	}

	/**
	 * A cleanup purge is recorded against the session user.
	 *
	 * @return void
	 */
	public function testCleanupPurgeActsAsTheSessionUser(): void {
		$service = $this->createMock(OrphanedDataCleanupService::class);
		$service->expects($this->once())->method('purge')->with([], false, 'alice', 'api')->willReturn($this->createMock(CleanupResult::class));
		$controller = new AdminCleanupController(
			request: $this->forgedRequest(),
			cleanup: new CleanupSources(cleanupService: $service, registry: $this->createMock(CategoryRegistryService::class)),
			guard: $this->guard(uid: 'alice')
		);

		$this->assertSame(200, $controller->purge()->getStatus());
	}

	/**
	 * The org navigation is filtered by the session user's groups.
	 *
	 * @return void
	 */
	public function testOrgNavigationFiltersForTheSessionUser(): void {
		$service = $this->createMock(OrgNavigationService::class);
		$service->method('getTree')->willReturn([['id' => 'n1']]);
		$service->expects($this->once())->method('filterTreeByUserGroups')->with([['id' => 'n1']], 'alice')->willReturn([]);

		$response = $this->orgNavigation(service: $service, uid: 'alice')->getOrgNavigation();

		$this->assertSame(200, $response->getStatus());
	}

	/**
	 * An anonymous caller is refused before the language is looked at.
	 *
	 * @return void
	 */
	public function testAnonymousWithAnInvalidLanguageIsRefusedFirst(): void {
		$service = $this->createMock(OrgNavigationService::class);
		$service->expects($this->never())->method('getTree');

		$response = $this->orgNavigation(service: $service, uid: null)->getOrgNavigation(lang: 'not-a-language');

		$this->assertSame(401, $response->getStatus());
		$this->assertSame(['error' => 'Not authenticated'], $response->getData());
	}

	/**
	 * The org navigation controller over the given service.
	 *
	 * @param OrgNavigationService $service The navigation service.
	 * @param string|null          $uid     The session user.
	 *
	 * @return AdminOrgNavigationController The controller.
	 */
	private function orgNavigation(OrgNavigationService $service, ?string $uid): AdminOrgNavigationController {
		return new AdminOrgNavigationController(
			request: $this->forgedRequest(),
			sources: new OrgNavigationSources(service: $service, settings: $this->createMock(AdminSettingMapper::class)),
			guard: $this->guard(uid: $uid)
		);
	}
}
