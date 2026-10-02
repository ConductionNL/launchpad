<?php

/**
 * PersonalLayerApiController Access Test
 *
 * THE LEAST PRIVILEGED PRINCIPAL THAT SHOULD BE REFUSED.
 *
 * The three personal-layer routes are `#[NoAdminRequired]` and take the
 * dashboard from the URL, so the only thing standing between an ordinary user
 * and somebody else's dashboard is `DashboardService::getDashboardForUser()`
 * returning null. `PersonalLayerService` does NOT re-check it: `save()` and
 * `reset()` take a user id and a dashboard id and write.
 *
 * That check had no test. `PersonalLayerServiceTest` exercises the service,
 * which sits below the check, and the api-direct spec drives every
 * authenticated case as `admin`, who can see every dashboard, plus an
 * anonymous caller, who only proves Nextcloud's own auth middleware runs.
 * Neither can fail if the guard is deleted.
 *
 * So these tests use the principal that actually discriminates: a signed-in,
 * non-admin user for whom the dashboard is not visible. They assert 404 on all
 * three verbs AND that nothing was written, because a refusal that still
 * writes is the failure worth catching.
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

use OCA\LaunchPad\Controller\PersonalLayerApiController;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Access control on the personal-layer routes.
 */
class PersonalLayerApiControllerAccessTest extends TestCase {
	private const DASHBOARD = 42;

	/** @var IRequest&MockObject */
	private $request;

	/** @var PersonalLayerService&MockObject */
	private $layers;

	/** @var PersonalLayerMapper&MockObject */
	private $mapper;

	/** @var DashboardService&MockObject */
	private $dashboards;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->layers = $this->createMock(PersonalLayerService::class);
		$this->mapper = $this->createMock(PersonalLayerMapper::class);
		$this->dashboards = $this->createMock(DashboardService::class);
	}//end setUp()

	/**
	 * The controller as one named user.
	 *
	 * @param string|null $userId The signed-in user, or null for anonymous.
	 *
	 * @return PersonalLayerApiController The controller.
	 */
	private function makeController(?string $userId): PersonalLayerApiController {
		return new PersonalLayerApiController(
			$this->request,
			$this->layers,
			$this->mapper,
			$this->dashboards,
			$userId
		);
	}//end makeController()

	/**
	 * Say that this dashboard is not visible to this user.
	 *
	 * This is what `getDashboardForUser()` returns for a user who is neither
	 * the owner nor a share recipient: null, not an exception.
	 *
	 * @return void
	 */
	private function invisibleToEveryone(): void {
		$this->dashboards->method('getDashboardForUser')->willReturn(null);
	}//end invisibleToEveryone()

	/**
	 * A signed-in user who cannot see the dashboard cannot read its layer.
	 *
	 * @return void
	 */
	public function testAUserWhoCannotSeeTheDashboardIsRefusedTheRead(): void {
		$this->invisibleToEveryone();
		$this->mapper->expects($this->never())->method('findForUser');

		$response = $this->makeController('outsider')->show(self::DASHBOARD);

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		self::assertSame('dashboard_not_found', $response->getData()['error']);
	}//end testAUserWhoCannotSeeTheDashboardIsRefusedTheRead()

	/**
	 * And cannot write one. The write is the one that matters: a layer saved
	 * against a dashboard the caller cannot see is a row nobody can reach to
	 * delete, on an object they were never shown.
	 *
	 * @return void
	 */
	public function testAUserWhoCannotSeeTheDashboardWritesNoLayer(): void {
		$this->invisibleToEveryone();
		$this->layers->expects($this->never())->method('save');

		$response = $this->makeController('outsider')->save(
			self::DASHBOARD,
			[1 => ['sortOrder' => 99]],
			[2]
		);

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testAUserWhoCannotSeeTheDashboardWritesNoLayer()

	/**
	 * And cannot reset one. The reset deletes, so an unguarded reset is a
	 * delete on an object the caller was never shown.
	 *
	 * @return void
	 */
	public function testAUserWhoCannotSeeTheDashboardDeletesNoLayer(): void {
		$this->invisibleToEveryone();
		$this->layers->expects($this->never())->method('reset');

		$response = $this->makeController('outsider')->reset(self::DASHBOARD);

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testAUserWhoCannotSeeTheDashboardDeletesNoLayer()

	/**
	 * The refusal must be about VISIBILITY, not about being anonymous.
	 *
	 * Without this, every test above still passes when the guard is reduced to
	 * "is there a session", which is the weaker check the api-direct spec
	 * already covers. Here the dashboard IS visible and the caller IS an
	 * ordinary non-admin, so the write has to land.
	 *
	 * @return void
	 */
	public function testAnOrdinaryUserWhoCanSeeTheDashboardMayArrangeIt(): void {
		$this->dashboards->method('getDashboardForUser')->willReturn(['placements' => []]);
		$this->layers->expects($this->once())
			->method('save')
			->willReturn(['saved' => true]);

		$response = $this->makeController('member')->save(self::DASHBOARD, [], []);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertTrue($response->getData()['saved']);
	}//end testAnOrdinaryUserWhoCanSeeTheDashboardMayArrangeIt()

	/**
	 * A session with no user never reaches the dashboard lookup at all.
	 *
	 * @return void
	 */
	public function testAnAnonymousCallerNeverReachesTheDashboardLookup(): void {
		$this->dashboards->expects($this->never())->method('getDashboardForUser');
		$this->layers->expects($this->never())->method('save');

		$response = $this->makeController(null)->save(self::DASHBOARD, [], []);

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testAnAnonymousCallerNeverReachesTheDashboardLookup()

	/**
	 * An empty user id is a session too, and it must be refused the same way.
	 *
	 * `(string)$this->userId` turns null into '', so a guard that only checked
	 * for null would let '' through and then save a layer owned by nobody.
	 *
	 * @return void
	 */
	public function testAnEmptyUserIdIsRefusedRatherThanSavedAsNobody(): void {
		$this->dashboards->expects($this->never())->method('getDashboardForUser');
		$this->layers->expects($this->never())->method('save');

		$response = $this->makeController('')->save(self::DASHBOARD, [], []);

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testAnEmptyUserIdIsRefusedRatherThanSavedAsNobody()
}//end class
