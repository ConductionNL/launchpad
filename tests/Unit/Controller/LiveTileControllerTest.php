<?php

/**
 * LiveTileControllerTest
 *
 * Covers REQ-LIVETILE-003 controller behaviour:
 *  - 401 when the caller is anonymous.
 *  - 403 on an unauthorized placement, and the resolver is NEVER called
 *    in that case (the fetch must not happen).
 *  - 200 with the resolved reading, and the response shape excludes any
 *    source URL / header / credential fields (only the 5 documented
 *    keys are present).
 *  - `connectorStatus()` / `validateSource()` auxiliary endpoints.
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

use OCA\LaunchPad\Controller\LiveTileController;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Small]
class LiveTileControllerTest extends TestCase {

	/**
	 * @var IRequest&MockObject
	 */
	private $request;

	/**
	 * @var LiveTileService&MockObject
	 */
	private $liveTileService;

	/**
	 * @var PermissionService&MockObject
	 */
	private $permissionService;

	/**
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	private LiveTileController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->liveTileService = $this->createMock(originalClassName: LiveTileService::class);
		$this->permissionService = $this->createMock(originalClassName: PermissionService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->controller = new LiveTileController(
			request: $this->request,
			liveTileService: $this->liveTileService,
			permissionService: $this->permissionService,
			userSession: $this->userSession,
			logger: $this->logger,
		);
	}//end setUp()

	private function authAsUser(string $uid): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}//end authAsUser()

	// -------------------------------------------------------------
	// show() — REQ-LIVETILE-003.
	// -------------------------------------------------------------

	public function testShowReturns401WhenAnonymous(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->liveTileService->expects($this->never())->method('resolveForPlacement');

		$response = $this->controller->show(placementId: 1);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testShowReturns401WhenAnonymous()

	public function testShowReturns403OnUnauthorizedPlacementAndNeverFetches(): void {
		$this->authAsUser(uid: 'alice');
		$this->permissionService->method('canViewPlacement')
			->with('alice', 42)
			->willReturn(false);
		// REQ-LIVETILE-003 "Caller authorization" — the fetch MUST NOT be
		// performed when the caller may not view the placement.
		$this->liveTileService->expects($this->never())->method('resolveForPlacement');

		$response = $this->controller->show(placementId: 42);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}//end testShowReturns403OnUnauthorizedPlacementAndNeverFetches()

	public function testShowReturns200WithReadingWhenAuthorized(): void {
		$this->authAsUser(uid: 'alice');
		$this->permissionService->method('canViewPlacement')->willReturn(true);
		$this->liveTileService->method('resolveForPlacement')->willReturn([
			'value' => 42,
			'formatted' => '42',
			'badge' => null,
			'fetchedAt' => '2026-07-23T10:00:00+00:00',
			'stale' => false,
		]);

		$response = $this->controller->show(placementId: 5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(42, $response->getData()['value']);
	}//end testShowReturns200WithReadingWhenAuthorized()

	public function testShowResponseNeverContainsSourceUrlOrCredentials(): void {
		$this->authAsUser(uid: 'alice');
		$this->permissionService->method('canViewPlacement')->willReturn(true);
		$this->liveTileService->method('resolveForPlacement')->willReturn([
			'value' => 1234,
			'formatted' => '€1,234',
			'badge' => ['state' => 'ok', 'label' => 'Healthy'],
			'fetchedAt' => '2026-07-23T10:00:00+00:00',
			'stale' => false,
		]);

		$response = $this->controller->show(placementId: 6);
		$data = $response->getData();

		$this->assertSame(
			['value', 'formatted', 'badge', 'fetchedAt', 'stale'],
			array_keys($data),
			'response MUST be exactly {value, formatted, badge, fetchedAt, stale} — no url/headers/credentials'
		);
		foreach ($data as $key => $value) {
			if (is_string($value) === false) {
				continue;
			}

			$this->assertStringNotContainsStringIgnoringCase('http://', $value);
			$this->assertStringNotContainsStringIgnoringCase('https://', $value);
			$this->assertStringNotContainsStringIgnoringCase('authorization', $value);
			$this->assertStringNotContainsStringIgnoringCase('api_key', $value);
			$this->assertStringNotContainsStringIgnoringCase('apikey', $value);
		}
	}//end testShowResponseNeverContainsSourceUrlOrCredentials()

	public function testShowReturns404WhenPlacementUnresolvable(): void {
		$this->authAsUser(uid: 'alice');
		$this->permissionService->method('canViewPlacement')->willReturn(true);
		$this->liveTileService->method('resolveForPlacement')->willReturn(['error' => 'placement_not_found']);

		$response = $this->controller->show(placementId: 999);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testShowReturns404WhenPlacementUnresolvable()

	// -------------------------------------------------------------
	// connectorStatus() — REQ-LIVETILE-005.
	// -------------------------------------------------------------

	public function testConnectorStatusReturns401WhenAnonymous(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->connectorStatus();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testConnectorStatusReturns401WhenAnonymous()

	public function testConnectorStatusReportsAvailability(): void {
		$this->authAsUser(uid: 'alice');
		$this->liveTileService->method('isConnectorAvailable')->willReturn(true);

		$response = $this->controller->connectorStatus();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['available']);
	}//end testConnectorStatusReportsAvailability()

	// -------------------------------------------------------------
	// validateSource() — REQ-LIVETILE-002.
	// -------------------------------------------------------------

	public function testValidateSourceReturns401WhenAnonymous(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->validateSource();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testValidateSourceReturns401WhenAnonymous()

	public function testValidateSourceReturnsErrorsFromService(): void {
		$this->authAsUser(uid: 'alice');
		$this->request->method('getParam')->with('config')->willReturn(['sourceMode' => 'url', 'url' => 'https://blocked.example.com']);
		$this->liveTileService->method('validateSourceConfig')->willReturn(['host_not_allowed']);

		$response = $this->controller->validateSource();
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($data['valid']);
		$this->assertSame(['host_not_allowed'], $data['errors']);
	}//end testValidateSourceReturnsErrorsFromService()
}//end class
