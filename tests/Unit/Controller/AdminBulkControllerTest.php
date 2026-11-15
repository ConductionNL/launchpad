<?php

/**
 * AdminBulkControllerTest
 *
 * Controller-level tests for `POST /api/admin/dashboards/bulk-*`
 * (REQ-BULK-001..011). Verifies admin-only guard, request shape
 * validation, and propagation of `PermissionDeniedException` and
 * `InvalidArgumentException` from the bulk service.
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

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AdminBulkController;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Service\PermissionDeniedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use OCA\LaunchPad\Service\ActionAuthService;

class AdminBulkControllerTest extends TestCase {

	/**
	 * @var IRequest&MockObject
	 */
	private $request;

	/**
	 * @var BulkOperationService&MockObject
	 */
	private $bulkService;

	/**
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * @var IGroupManager&MockObject
	 */
	private $groupManager;

	private AdminBulkController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->bulkService = $this->createMock(BulkOperationService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->controller = new AdminBulkController(
			request: $this->request,
			bulkService: $this->bulkService,
			guard: new RequestGuard(userSession: $this->userSession, groupManager: $this->groupManager, actionAuth: $this->createMock(ActionAuthService::class)),
		);
	}//end setUp()

	private function loginAsAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('alice')->willReturn(true);
	}//end loginAsAdmin()

	private function loginAsNonAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('bob')->willReturn(false);
	}//end loginAsNonAdmin()

	public function testBulkDeleteNonAdminForbidden(): void {
		$this->loginAsNonAdmin();

		$response = $this->controller->bulkDelete(dashboardUuids: ['uuid-1']);

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testBulkDeleteNonAdminForbidden()

	public function testBulkDeleteRejectsNonArrayBody(): void {
		$this->loginAsAdmin();

		$response = $this->controller->bulkDelete(dashboardUuids: 'not-an-array');

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}//end testBulkDeleteRejectsNonArrayBody()

	public function testBulkDeleteValidRequestReturns200(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkDelete')->willReturn(
			[
				'deletedCount' => 2,
				'skippedCount' => 0,
				'errors' => [],
				'dryRun' => false,
			]
		);

		$response = $this->controller->bulkDelete(
			dashboardUuids: ['uuid-1', 'uuid-2']
		);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$payload = $response->getData();
		self::assertSame(2, $payload['deletedCount']);
	}//end testBulkDeleteValidRequestReturns200()

	public function testBulkDeletePermissionDeniedMappedTo403(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkDelete')->willThrowException(
			new PermissionDeniedException(
				message: 'denied',
				deniedUuids: ['uuid-3']
			)
		);

		$response = $this->controller->bulkDelete(dashboardUuids: ['uuid-3']);

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertSame(['uuid-3'], $response->getData()['deniedUuids']);
	}//end testBulkDeletePermissionDeniedMappedTo403()

	public function testBulkDeleteSizeCapMappedTo400(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkDelete')->willThrowException(
			new InvalidArgumentException(message: 'maximum is 500')
		);

		$response = $this->controller->bulkDelete(dashboardUuids: ['x']);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}//end testBulkDeleteSizeCapMappedTo400()

	public function testBulkMoveValidRequest(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkMove')->willReturn(
			[
				'movedCount' => 1,
				'skippedCount' => 0,
				'errors' => [],
				'dryRun' => false,
			]
		);

		$response = $this->controller->bulkMove(
			dashboardUuids: ['child'],
			parentUuid: 'parent'
		);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testBulkMoveValidRequest()

	public function testBulkStatusRequiresPublicationStatus(): void {
		$this->loginAsAdmin();

		$response = $this->controller->bulkStatus(
			dashboardUuids: ['d1'],
			publicationStatus: null
		);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}//end testBulkStatusRequiresPublicationStatus()

	public function testBulkStatusValidRequest(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkStatus')->willReturn(
			[
				'updatedCount' => 1,
				'skippedCount' => 0,
				'errors' => [],
				'dryRun' => false,
			]
		);

		$response = $this->controller->bulkStatus(
			dashboardUuids: ['d1'],
			publicationStatus: 'published'
		);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testBulkStatusValidRequest()

	public function testBulkReindexValidRequest(): void {
		$this->loginAsAdmin();

		$this->bulkService->method('bulkReindex')->willReturn(
			[
				'reindexedCount' => 1,
				'errors' => [],
				'dryRun' => false,
			]
		);

		$response = $this->controller->bulkReindex(dashboardUuids: ['d1']);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testBulkReindexValidRequest()

	public function testBulkReindexNonAdminForbidden(): void {
		$this->loginAsNonAdmin();

		$response = $this->controller->bulkReindex(dashboardUuids: ['d1']);

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testBulkReindexNonAdminForbidden()

	/**
	 * Every endpoint maps invalid UUIDs, a denied dashboard and a refused request the same way.
	 *
	 * @return void
	 */
	public function testEveryEndpointMapsItsFailuresTheSameWay(): void {
		$this->loginAsAdmin();
		$failures = [
			'denied' => new PermissionDeniedException(message: 'denied', deniedUuids: ['uuid-9']),
			'refused' => new InvalidArgumentException(message: 'maximum is 500'),
		];
		$this->bulkService->method('bulkDelete')->willReturnCallback(function () use (&$failure) { throw $failure; });
		$this->bulkService->method('bulkMove')->willReturnCallback(function () use (&$failure) { throw $failure; });
		$this->bulkService->method('bulkStatus')->willReturnCallback(function () use (&$failure) { throw $failure; });
		$this->bulkService->method('bulkReindex')->willReturnCallback(function () use (&$failure) { throw $failure; });

		$calls = [
			'delete' => fn (mixed $uuids): JSONResponse => $this->controller->bulkDelete(dashboardUuids: $uuids),
			'move' => fn (mixed $uuids): JSONResponse => $this->controller->bulkMove(dashboardUuids: $uuids, parentUuid: 'p'),
			'status' => fn (mixed $uuids): JSONResponse => $this->controller->bulkStatus(dashboardUuids: $uuids, publicationStatus: 'published'),
			'reindex' => fn (mixed $uuids): JSONResponse => $this->controller->bulkReindex(dashboardUuids: $uuids),
		];

		$actual = [];
		foreach ($calls as $name => $call) {
			foreach ([['a', 5], ['a', ' '], 'x'] as $i => $bad) {
				$response = $call($bad);
				$actual[$name.' bad '.$i] = [$response->getStatus(), $response->getData()];
			}
			foreach ($failures as $label => $failure) {
				$response = $call([' uuid-9 ']);
				$actual[$name.' '.$label] = [$response->getStatus(), $response->getData()];
			}
		}

		$expected = [];
		foreach (array_keys($calls) as $name) {
			for ($i = 0; $i < 3; $i++) {
				$expected[$name.' bad '.$i] = [400, ['error' => 'dashboardUuids must be an array of strings']];
			}
			$expected[$name.' denied'] = [403, ['error' => 'denied', 'deniedUuids' => ['uuid-9']]];
			$expected[$name.' refused'] = [400, ['error' => 'maximum is 500']];
		}

		self::assertSame($expected, $actual);
	}//end testEveryEndpointMapsItsFailuresTheSameWay()
}
