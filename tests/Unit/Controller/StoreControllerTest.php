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
use OCA\LaunchPad\Service\StoreInstallFlow;
use OCA\LaunchPad\Service\StoreInstalls;
use OCA\LaunchPad\Service\StorePublishService;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Service\StoreUpdateService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http;
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

		foreach (['install', 'update', 'getConfig', 'updateConfig', 'publishable', 'publish'] as $name) {
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
	 * REQ-STORE-012 scenario "A member cannot update": the update is admin-gated
	 * (the attribute answers a member with 403) and never merely login-required.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	public function testUpdateIsAdminOnly(): void {
		$method = new ReflectionMethod(objectOrMethod: StoreController::class, method: 'update');

		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(name: NoAdminRequired::class));
		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(name: AuthorizedAdminSetting::class));
	}//end testUpdateIsAdminOnly()

	/**
	 * The update's report maps to 200, 404 when nothing is installed, 400 on a refusal.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	public function testUpdateStatusFollowsTheReport(): void {
		$this->signIn(uid: 'admin');
		$updates = $this->createMock(originalClassName: StoreUpdateService::class);
		$updates->method('update')->willReturnOnConsecutiveCalls(
			['success' => true, 'version' => '1.1.0'],
			['success' => false, 'notInstalled' => true, 'message' => 'not installed'],
			['success' => false, 'message' => 'refused']
		);
		$updates->expects($this->exactly(count: 3))->method('update')->with('sociaal-domein', 'admin');
		$controller = new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession,
			flow: new StoreInstallFlow(updates: $updates)
		);

		$this->assertSame(expected: Http::STATUS_OK, actual: $controller->update(slug: 'sociaal-domein')->getStatus());
		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $controller->update(slug: 'sociaal-domein')->getStatus());
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $controller->update(slug: 'sociaal-domein')->getStatus());
	}//end testUpdateStatusFollowsTheReport()

	/**
	 * The store page's Update posts to install: an installed item is updated
	 * in place, never installed a second time (REQ-STORE-012, REQ-STORE-006).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	public function testInstallOfAnInstalledItemUpdatesInPlace(): void {
		$this->signIn(uid: 'admin');
		$installs = $this->createMock(originalClassName: StoreInstalls::class);
		$record = ['version' => '1.0.0', 'dashboardId' => 41, 'dashboardUuid' => 'u', 'placementIds' => [1]];
		$installs->method('all')->willReturn(['sociaal-domein' => $record]);
		$installs->expects($this->never())->method('record');
		$updates = $this->createMock(originalClassName: StoreUpdateService::class);
		$updates->expects($this->once())->method('update')->with('sociaal-domein', 'admin')
			->willReturn(['success' => true, 'components' => [], 'version' => '1.1.0']);
		$this->storeService->expects($this->never())->method('install');
		$controller = new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession,
			flow: new StoreInstallFlow(installs: $installs, updates: $updates)
		);

		$response = $controller->install(slug: 'sociaal-domein');

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: '1.1.0', actual: $response->getData()['version']);
	}//end testInstallOfAnInstalledItemUpdatesInPlace()

	/**
	 * A first install still creates (REQ-STORE-006 unchanged).
	 *
	 * @return void
	 */
	public function testAFirstInstallStillCreates(): void {
		$this->signIn(uid: 'admin');
		$installs = $this->createMock(originalClassName: StoreInstalls::class);
		$installs->method('all')->willReturn([]);
		$updates = $this->createMock(originalClassName: StoreUpdateService::class);
		$updates->expects($this->never())->method('update');
		$this->storeService->expects($this->once())->method('install')
			->willReturn(['success' => true, 'message' => 'Installed 1 of 1.', 'components' => []]);
		$controller = new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession,
			flow: new StoreInstallFlow(installs: $installs, updates: $updates)
		);

		$this->assertSame(expected: Http::STATUS_OK, actual: $controller->install(slug: 'sociaal-domein')->getStatus());
	}//end testAFirstInstallStillCreates()

	/**
	 * An anonymous update is refused before anything runs.
	 *
	 * @return void
	 */
	public function testUpdateWithoutAUserIsUnauthorized(): void {
		$updates = $this->createMock(originalClassName: StoreUpdateService::class);
		$updates->expects($this->never())->method('update');
		$controller = new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession,
			flow: new StoreInstallFlow(updates: $updates)
		);

		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $controller->update(slug: 'sociaal-domein')->getStatus());
	}//end testUpdateWithoutAUserIsUnauthorized()

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
	 * REQ-STORE-010: the search answer carries the register banner and the check time.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-010-the-marketplace-shows-dashboard-templates-as-cards
	 *
	 * @return void
	 */
	public function testSearchCarriesTheRegisterBanner(): void {
		$this->signIn(uid: 'pieter');
		$this->request->method('getParam')->willReturn(null);
		$this->storeService->method('search')->willReturn(
			['outcome' => 'ok', 'cards' => [], 'checkedAt' => '2026-11-25T06:00:00Z']
		);
		$this->storeService->method('registryBanner')->willReturn(
			['name' => 'VNG dashboardregister', 'url' => 'https://register.vng.nl/dashboards']
		);

		$data = $this->controller->search()->getData();

		$this->assertSame(expected: ['name' => 'VNG dashboardregister', 'url' => 'https://register.vng.nl/dashboards'], actual: $data['registry']);
		$this->assertSame(expected: '2026-11-25T06:00:00Z', actual: $data['checkedAt']);
	}//end testSearchCarriesTheRegisterBanner()

	/**
	 * REQ-STORE-010: no register means no banner data and no check time.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-010-the-marketplace-shows-dashboard-templates-as-cards
	 *
	 * @return void
	 */
	public function testSearchWithoutARegisterHasNoBanner(): void {
		$this->signIn(uid: 'admin');
		$this->request->method('getParam')->willReturn(null);
		$this->storeService->method('search')->willReturn(['outcome' => 'not_configured', 'cards' => []]);
		$this->storeService->method('registryBanner')->willReturn(null);

		$data = $this->controller->search()->getData();

		$this->assertNull(actual: $data['registry']);
		$this->assertNull(actual: $data['checkedAt']);
	}//end testSearchWithoutARegisterHasNoBanner()

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

	/**
	 * Without a signed-in user nothing is installed and the answer is a 401
	 * the page can show.
	 *
	 * @return void
	 */
	public function testInstallWithoutAUserIsUnauthorized(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->storeService->expects($this->never())->method('install');

		$response = $this->controller->install(slug: 'sales-overview');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(
			['success' => false, 'message' => 'Sign in to install a template.', 'components' => []],
			$response->getData()
		);
	}//end testInstallWithoutAUserIsUnauthorized()
	/**
	 * A controller with a publish service.
	 *
	 * @param StorePublishService $publications The publish service.
	 *
	 * @return StoreController
	 */
	private function publishingController(StorePublishService $publications): StoreController {
		return new StoreController(
			request: $this->request,
			storeService: $this->storeService,
			userSession: $this->userSession,
			flow: new StoreInstallFlow(publications: $publications)
		);
	}//end publishingController()

	/**
	 * REQ-STORE-013: publish is admin-only, not merely signed-in, so a member's request is refused with 403.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 *
	 * @return void
	 */
	public function testPublishIsAdminOnly(): void {
		foreach (['publish', 'publishable'] as $name) {
			$method = new ReflectionMethod(objectOrMethod: StoreController::class, method: $name);
			$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(name: NoAdminRequired::class), message: $name);
			$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(name: AuthorizedAdminSetting::class), message: $name);
		}
	}//end testPublishIsAdminOnly()

	/**
	 * REQ-STORE-013: the dialog's item and objects reach the service, and an ok passes through.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 *
	 * @return void
	 */
	public function testPublishPassesTheDialogBodyAndTheOutcome(): void {
		$this->signIn(uid: 'admin');
		$item = ['slug' => 'raads-en-collegewerk', 'title' => 'Raads- en collegewerk', 'version' => '0.9.0'];
		$objects = [['schema' => 'dashboard', 'id' => 'uuid-1']];
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key): ?array => ['item' => $item, 'objects' => $objects][$key] ?? null
		);

		$seen = [];
		$publications = $this->createMock(originalClassName: StorePublishService::class);
		$publications->method('publish')->willReturnCallback(
			static function (array $item, array $objects, string $userId) use (&$seen): array {
				$seen = [$item, $objects, $userId];
				return ['outcome' => 'ok', 'slug' => 'raads-en-collegewerk'];
			}
		);

		$response = $this->publishingController(publications: $publications)->publish();

		$this->assertSame(expected: [$item, $objects, 'admin'], actual: $seen);
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: ['outcome' => 'ok', 'slug' => 'raads-en-collegewerk'], actual: $response->getData());
	}//end testPublishPassesTheDialogBodyAndTheOutcome()

	/**
	 * REQ-STORE-013: the same version twice is a 409, a refused request a 400, a register refusal a 200 with its outcome.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 *
	 * @return void
	 */
	public function testPublishStatusFollowsTheOutcome(): void {
		$this->signIn(uid: 'admin');
		$this->request->method('getParam')->willReturn(null);
		$cases = [
			StorePublishService::OUTCOME_VERSION_NOT_HIGHER => Http::STATUS_CONFLICT,
			StorePublishService::OUTCOME_INVALID_REQUEST => Http::STATUS_BAD_REQUEST,
			'store_rejected' => Http::STATUS_OK,
		];
		foreach ($cases as $outcome => $status) {
			$publications = $this->createMock(originalClassName: StorePublishService::class);
			$publications->method('publish')->willReturn(['outcome' => $outcome, 'slug' => '', 'message' => 'x']);

			$response = $this->publishingController(publications: $publications)->publish();

			$this->assertSame(expected: $status, actual: $response->getStatus(), message: $outcome);
			$this->assertSame(expected: $outcome, actual: $response->getData()['outcome']);
		}
	}//end testPublishStatusFollowsTheOutcome()

	/**
	 * REQ-STORE-013: the dialog lists the administrator's publishable dashboards.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 *
	 * @return void
	 */
	public function testPublishableListsTheAdministratorsDashboards(): void {
		$this->signIn(uid: 'admin');
		$objects = [['schema' => 'dashboard', 'id' => 'uuid-1', 'title' => 'Raads- en collegewerk']];
		$publications = $this->createMock(originalClassName: StorePublishService::class);
		$publications->method('publishable')->willReturnCallback(
			static fn (string $userId): array => array_filter($objects, static fn (): bool => $userId === 'admin')
		);

		$data = $this->publishingController(publications: $publications)->publishable()->getData();

		$this->assertSame(expected: ['objects' => $objects], actual: $data);
	}//end testPublishableListsTheAdministratorsDashboards()

	/**
	 * REQ-STORE-013: the search marks this instance's own publications.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 *
	 * @return void
	 */
	public function testSearchMarksOwnPublications(): void {
		$this->signIn(uid: 'admin');
		$this->request->method('getParam')->willReturn(null);
		$this->storeService->method('search')->willReturn(['outcome' => 'ok', 'cards' => [['slug' => 'a']]]);
		$publications = $this->createMock(originalClassName: StorePublishService::class);
		$publications->method('annotate')->willReturn([['slug' => 'a', 'typeName' => 'Own publication']]);

		$data = $this->publishingController(publications: $publications)->search()->getData();

		$this->assertSame(expected: 'Own publication', actual: $data['cards'][0]['typeName']);
	}//end testSearchMarksOwnPublications()
}//end class
