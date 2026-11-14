<?php

/**
 * CatalogueControllerTest
 *
 * The app catalogue endpoints (launcher-app-catalogue, REQ-ACAT-001..004):
 * the user side lists and requests behind its actions, the admin side
 * carries #[AuthorizedAdminSetting] on every method, refuses non-admins and
 * maps the service's refusals to their status codes.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\AdminCatalogueController;
use OCA\LaunchPad\Controller\CatalogueController;
use OCA\LaunchPad\Db\AppRequest;
use OCA\LaunchPad\Db\CatalogueApp;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CatalogueService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for CatalogueController and AdminCatalogueController.
 */
class CatalogueControllerTest extends TestCase {

	/**
	 * The mocked service.
	 *
	 * @var CatalogueService&MockObject
	 */
	private CatalogueService $service;

	/**
	 * Actions the caller is refused.
	 *
	 * @var array<int, string>
	 */
	private array $refused = [];

	/**
	 * Actions checked, in order.
	 *
	 * @var array<int, string>
	 */
	private array $checked = [];

	protected function setUp(): void {
		$this->service = $this->createMock(CatalogueService::class);
	}//end setUp()

	/**
	 * The session and action check for a caller.
	 *
	 * @param string|null $uid The caller, or null when signed out.
	 *
	 * @return ActionCaller
	 */
	private function caller(?string $uid): ActionCaller {
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('requireAction')->willReturnCallback(
			function (IUser $user, string $action): void {
				$this->checked[] = $action;
				if (in_array($action, $this->refused, true) === true) {
					throw new OCSForbiddenException('no');
				}
			}
		);

		return new ActionCaller(actionAuth: $auth, userSession: $session);
	}//end caller()

	/**
	 * The user controller.
	 *
	 * @param string|null $uid The caller.
	 *
	 * @return CatalogueController
	 */
	private function userController(?string $uid = 'pieter'): CatalogueController {
		return new CatalogueController(request: $this->createMock(IRequest::class), catalogue: $this->service, caller: $this->caller(uid: $uid));
	}//end userController()

	/**
	 * The admin controller.
	 *
	 * @param string               $uid    The caller.
	 * @param bool                 $admin  Whether the caller is an administrator.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return AdminCatalogueController
	 */
	private function adminController(string $uid = 'noor', bool $admin = true, array $params = []): AdminCatalogueController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key): mixed => $params[$key] ?? null);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);

		return new AdminCatalogueController(request: $request, catalogue: $this->service, caller: $this->caller(uid: $uid), groupManager: $groups);
	}//end adminController()

	/**
	 * The user methods are open to signed-in users; every admin method carries
	 * #[AuthorizedAdminSetting(LaunchPadAdmin::class)] and not #[NoAdminRequired].
	 *
	 * @return void
	 */
	public function testRouteAttributes(): void {
		foreach (['index', 'request'] as $method) {
			$this->assertCount(1, (new ReflectionMethod(CatalogueController::class, $method))->getAttributes(NoAdminRequired::class), $method);
		}

		$methods = array_filter(
			(new ReflectionClass(AdminCatalogueController::class))->getMethods(ReflectionMethod::IS_PUBLIC),
			static fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === AdminCatalogueController::class && $m->getName() !== '__construct'
		);
		$this->assertSame(
			['index', 'create', 'update', 'destroy', 'approve', 'decline', 'setManagerGroup'],
			array_values(array_map(static fn (ReflectionMethod $m): string => $m->getName(), $methods))
		);
		foreach ($methods as $method) {
			$attributes = $method->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method->getName());
			$this->assertSame([LaunchPadAdmin::class], $attributes[0]->getArguments(), $method->getName());
			$this->assertSame([], $method->getAttributes(NoAdminRequired::class), $method->getName());
		}
	}//end testRouteAttributes()

	/**
	 * The list is the service's list for the caller, behind catalogue.list.
	 *
	 * @return void
	 */
	public function testTheListIsTheCallersOwn(): void {
		$app = new CatalogueApp();
		$app->setName('Zaaksysteem');
		$this->service->expects($this->once())->method('listFor')->with('pieter')->willReturn([$app]);

		$response = $this->userController()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['apps' => [$app]], $response->getData());
		$this->assertSame(['catalogue.list'], $this->checked);
	}//end testTheListIsTheCallersOwn()

	/**
	 * Signed out is 401, a refused action 403; the service is not reached.
	 *
	 * @return void
	 */
	public function testTheUserSideIsGuarded(): void {
		$this->service->expects($this->never())->method('listFor');
		$this->service->expects($this->never())->method('request');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->userController(uid: null)->index()->getStatus());
		$this->refused = ['catalogue.list', 'catalogue.request'];
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->userController()->index()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->userController()->request(appName: 'X')->getStatus());
	}//end testTheUserSideIsGuarded()

	/**
	 * A request is stored for the caller (201); the limit is 429 with its code
	 * and message; a missing name is 400.
	 *
	 * @return void
	 */
	public function testRequestOutcomes(): void {
		$stored = new AppRequest();
		$this->service->method('request')->willReturnCallback(
			static function (string $userId, string $appName, string $purpose) use ($stored): AppRequest {
				if ($appName === 'limit') {
					throw new DomainException(CatalogueService::ERROR_REQUEST_LIMIT);
				}

				if ($appName === '') {
					throw new InvalidArgumentException('Give the name of the app, at most 255 characters.');
				}

				$stored->setRequestedBy($userId);
				$stored->setAppName($appName);
				$stored->setPurpose($purpose);
				return $stored;
			}
		);

		$created = $this->userController()->request(appName: 'Kaartviewer', purpose: 'Percelen');
		$this->assertSame(Http::STATUS_CREATED, $created->getStatus());
		$this->assertSame(['pieter', 'Kaartviewer', 'Percelen'], [$stored->getRequestedBy(), $stored->getAppName(), $stored->getPurpose()]);

		$limited = $this->userController()->request(appName: 'limit');
		$this->assertSame(Http::STATUS_TOO_MANY_REQUESTS, $limited->getStatus());
		$this->assertSame(
			['error' => 'You have 10 open requests. Wait for an answer first.', 'code' => 'request_limit'],
			$limited->getData()
		);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->userController()->request()->getStatus());
	}//end testRequestOutcomes()

	/**
	 * A non-admin is refused every admin method before the service is reached.
	 *
	 * @return void
	 */
	public function testTheAdminSideRefusesNonAdmins(): void {
		$this->service->expects($this->never())->method($this->anything());
		$controller = $this->adminController(uid: 'pieter', admin: false);

		foreach ([
			$controller->index(),
			$controller->create(),
			$controller->update(uuid: 'a'),
			$controller->destroy(uuid: 'a'),
			$controller->approve(uuid: 'r'),
			$controller->decline(uuid: 'r', note: 'n'),
			$controller->setManagerGroup(managerGroup: 'g'),
		] as $response) {
			$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		}
	}//end testTheAdminSideRefusesNonAdmins()

	/**
	 * Each admin method checks its own action.
	 *
	 * @return void
	 */
	public function testEachAdminMethodChecksItsAction(): void {
		$this->service->method('listAll')->willReturn([]);
		$this->service->method('openRequests')->willReturn([]);
		$this->service->method('getManagerGroup')->willReturn('appbeheer');
		$this->service->method('create')->willReturn(new CatalogueApp());
		$this->service->method('decline')->willReturn(new AppRequest());

		$index = $this->adminController()->index();
		$this->assertSame(['apps' => [], 'requests' => [], 'managerGroup' => 'appbeheer'], $index->getData());
		$this->adminController(params: ['name' => 'A'])->create();
		$this->adminController()->decline(uuid: 'r', note: 'n');

		$this->assertSame(['catalogue.manage', 'catalogue.manage', 'catalogue.decide'], $this->checked);
	}//end testEachAdminMethodChecksItsAction()

	/**
	 * Create passes only the entry fields of the body.
	 *
	 * @return void
	 */
	public function testCreatePassesTheEntryFields(): void {
		$this->service->expects($this->once())->method('create')
			->with(['name' => 'Zaaksysteem', 'linkType' => 'url', 'linkValue' => 'https://zaken.example.nl', 'targetGroups' => ['Burgerzaken']])
			->willReturn(new CatalogueApp());

		$response = $this->adminController(
			params: ['name' => 'Zaaksysteem', 'linkType' => 'url', 'linkValue' => 'https://zaken.example.nl', 'targetGroups' => ['Burgerzaken'], 'id' => 99]
		)->create();

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}//end testCreatePassesTheEntryFields()

	/**
	 * Decisions map a missing request to 404, a decided one to 409 and a bad
	 * input to 400; approve passes the caller as the decider.
	 *
	 * @return void
	 */
	public function testDecisionOutcomes(): void {
		$this->service->method('approve')->willReturnCallback(
			static function (string $requestUuid, string $adminId, array $entry): array {
				return match ($requestUuid) {
					'gone' => throw new DoesNotExistException('gone'),
					'decided' => throw new DomainException(CatalogueService::ERROR_NOT_OPEN),
					'bad' => throw new InvalidArgumentException('bad'),
					default => ['request' => $adminId, 'app' => $entry],
				};
			}
		);
		$this->service->method('decline')->willThrowException(new InvalidArgumentException('Say why the request is declined.'));

		$controller = $this->adminController(params: ['linkValue' => 'https://kaart.example.nl']);
		$this->assertSame(['request' => 'noor', 'app' => ['linkValue' => 'https://kaart.example.nl']], $controller->approve(uuid: 'r')->getData());
		$this->assertSame(Http::STATUS_NOT_FOUND, $controller->approve(uuid: 'gone')->getStatus());
		$conflict = $controller->approve(uuid: 'decided');
		$this->assertSame(Http::STATUS_CONFLICT, $conflict->getStatus());
		$this->assertSame('request_not_open', $conflict->getData()['code']);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $controller->approve(uuid: 'bad')->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $controller->decline(uuid: 'r')->getStatus());
	}//end testDecisionOutcomes()

	/**
	 * Update and delete answer 404 for a missing entry.
	 *
	 * @return void
	 */
	public function testMissingEntriesAre404(): void {
		$this->service->method('update')->willThrowException(new DoesNotExistException('gone'));
		$this->service->method('delete')->willThrowException(new DoesNotExistException('gone'));

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->adminController()->update(uuid: 'x')->getStatus());
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->adminController()->destroy(uuid: 'x')->getStatus());
	}//end testMissingEntriesAre404()
}//end class
