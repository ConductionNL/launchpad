<?php

/**
 * StoreControllerTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Controller\StoreController} covering the
 * `dashboard-store` capability — REQ-STORE-004 (auth posture) and the outcome
 * and report pass-through the store page reads.
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

use OCA\LaunchPad\Controller\StoreController;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 */
class StoreControllerTest extends TestCase {
	/** @var IRequest&MockObject */
	private $request;

	/** @var StoreService&MockObject */
	private $storeService;

	/** @var IUserSession&MockObject */
	private $userSession;

	private StoreController $controller;

	/**
	 * Build the controller under test with an anonymous session by default.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->storeService = $this->createMock(StoreService::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession
		);
	}//end setUp()

	/**
	 * Put a signed-in user on the session.
	 *
	 * @param string $uid The user id.
	 *
	 * @return void
	 */
	private function signIn(string $uid): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}//end signIn()

	/**
	 * REQ-STORE-004: search is signed-in, install and config are admin.
	 *
	 * A route whose method declares NO Nextcloud auth attribute is rejected by
	 * the middleware before the controller runs, so the absence of an attribute
	 * is a dead endpoint rather than an open one. Asserted by reflection so a
	 * removed attribute reddens here rather than at dispatch time.
	 *
	 * @return void
	 */
	public function testEveryRouteDeclaresItsAuthPosture(): void {
		$search = new ReflectionMethod(StoreController::class, 'search');
		$this->assertCount(1, $search->getAttributes(NoAdminRequired::class));

		foreach (['install', 'getConfig', 'updateConfig'] as $name) {
			$method = new ReflectionMethod(StoreController::class, $name);
			$attributes = $method->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $name . ' must be admin-gated');
			$this->assertSame(
				[LaunchPadAdmin::class],
				$attributes[0]->getArguments(),
				$name . ' must name the LaunchPad admin setting'
			);
		}
	}//end testEveryRouteDeclaresItsAuthPosture()

	/**
	 * REQ-STORE-004: install is NOT reachable without the admin attribute.
	 *
	 * Stated separately from the sweep above because this is the one that costs
	 * something if it regresses: search discloses a remote registry's cards,
	 * install writes into the instance.
	 *
	 * @return void
	 */
	public function testInstallIsNotMerelyLoginRequired(): void {
		$method = new ReflectionMethod(StoreController::class, 'install');

		$this->assertCount(0, $method->getAttributes(NoAdminRequired::class));
	}//end testInstallIsNotMerelyLoginRequired()

	/**
	 * An anonymous search gets an explicit 401, not a login redirect.
	 *
	 * @return void
	 */
	public function testAnonymousSearchIsUnauthorised(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->storeService->expects($this->never())->method('search');

		$response = $this->controller->search();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testAnonymousSearchIsUnauthorised()

	/**
	 * REQ-STORE-003: the engine's outcome reaches the page unchanged.
	 *
	 * @return void
	 */
	public function testSearchReturnsTheServiceOutcome(): void {
		$this->signIn(uid: 'alice');
		$this->request->method('getParam')->willReturn(null);
		$this->storeService->method('search')->willReturn(
			['outcome' => 'store_unreachable', 'cards' => []]
		);

		$response = $this->controller->search();
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('store_unreachable', $data['outcome']);
		$this->assertArrayHasKey('builtIn', $data, 'CnStorePage reads builtIn unconditionally');
		$this->assertArrayHasKey('kinds', $data, 'CnStorePage reads kinds unconditionally');
	}//end testSearchReturnsTheServiceOutcome()

	/**
	 * A refused install is a 400 so the page shows the message.
	 *
	 * `CnStorePage` reads `body.message` only when the response is not ok. A
	 * refusal returned as 200 would render as a silent success.
	 *
	 * @return void
	 */
	public function testARefusedInstallIsNotReportedAsSuccess(): void {
		$this->signIn(uid: 'alice');
		$this->storeService->method('install')->willReturn(
			['success' => false, 'message' => 'That template carries no dashboard.', 'components' => []]
		);

		$response = $this->controller->install(slug: 'empty');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
	}//end testARefusedInstallIsNotReportedAsSuccess()

	/**
	 * A partial install is a 200 carrying the per-component report.
	 *
	 * @return void
	 */
	public function testAPartialInstallReturnsTheComponentReport(): void {
		$this->signIn(uid: 'alice');
		$components = [
			['schema' => 'Sales overview', 'status' => 'installed'],
			['schema' => 'Refused board', 'status' => 'refused'],
		];
		$this->storeService->method('install')->willReturn(
			['success' => true, 'message' => 'Installed 1 of 2.', 'components' => $components]
		);

		$response = $this->controller->install(slug: 'sales-overview');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($components, $response->getData()['components']);
	}//end testAPartialInstallReturnsTheComponentReport()

	/**
	 * The install runs as the calling administrator, not as a fixed identity.
	 *
	 * @return void
	 */
	public function testInstallRunsAsTheCallingAdministrator(): void {
		$this->signIn(uid: 'alice');
		$this->storeService->expects($this->once())->method('install')->with('sales-overview', 'alice')
			->willReturn(['success' => true, 'message' => '', 'components' => []]);

		$this->controller->install(slug: 'sales-overview');
	}//end testInstallRunsAsTheCallingAdministrator()

	/**
	 * REQ-STORE-007: the config response never carries a token value.
	 *
	 * @return void
	 */
	public function testTheConfigResponseCarriesNoToken(): void {
		$this->storeService->method('getRegistryConfig')->willReturn(
			['registryUrl' => 'https://registry.example.org/', 'registryRegister' => 'launchpad', 'tokenConfigured' => true]
		);
		$this->storeService->method('isAvailable')->willReturn(true);

		$data = $this->controller->getConfig()->getData();

		$this->assertArrayNotHasKey('registryToken', $data);
		$this->assertTrue($data['tokenConfigured']);
		$this->assertTrue($data['available']);
	}//end testTheConfigResponseCarriesNoToken()

	/**
	 * REQ-STORE-007: an absent parameter stays null so the key is left alone.
	 *
	 * @return void
	 */
	public function testAnAbsentParameterIsPassedAsNull(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name) {
				return ($name === 'registryUrl') ? 'https://registry.example.org/' : null;
			}
		);
		$this->storeService->method('getRegistryConfig')->willReturn(
			['registryUrl' => '', 'registryRegister' => '', 'tokenConfigured' => false]
		);
		$this->storeService->expects($this->once())->method('updateRegistryConfig')
			->with('https://registry.example.org/', null, null);

		$this->controller->updateConfig();
	}//end testAnAbsentParameterIsPassedAsNull()
}//end class
