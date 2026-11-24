<?php

/**
 * DashboardPayloads Test
 *
 * The visible list and the active envelope, built once for the API and for
 * the main page's initial state, and the first load the page carries.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\DashboardPayloads;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Kernel\Contracts\QuotaServiceInterface;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DashboardPayloads.
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-4
 */
class DashboardPayloadsTest extends TestCase {
	/** @var DashboardService&MockObject */
	private $dashboardService;

	/** @var ActionAuthService&MockObject */
	private $actionAuth;

	/** @var IUserSession&MockObject */
	private $userSession;

	protected function setUp(): void {
		$this->dashboardService = $this->createMock(originalClassName: DashboardService::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->userSession->method('getUser')->willReturn($this->createMock(originalClassName: IUser::class));
	}//end setUp()

	/**
	 * A dashboard entity with the given uuid and owner.
	 *
	 * @param string      $uuid   The UUID.
	 * @param string|null $userId The owner, null for a group dashboard.
	 *
	 * @return Dashboard The entity.
	 */
	private function dashboard(string $uuid, ?string $userId): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName('Team');
		$dashboard->setUserId($userId);
		return $dashboard;
	}//end dashboard()

	/**
	 * The payloads under test.
	 *
	 * @param QuotaServiceInterface|null $quota The quota service, if any.
	 * @param bool                       $withActor Whether the action check is there.
	 *
	 * @return DashboardPayloads The builder.
	 */
	private function payloads(?QuotaServiceInterface $quota=null, bool $withActor=true): DashboardPayloads {
		$actor = null;
		if ($withActor === true) {
			$actor = new RequestActor(userSession: $this->userSession, actionAuth: $this->actionAuth);
		}

		return new DashboardPayloads(
			services: new DashboardApiServices(dashboardService: $this->dashboardService, quotaService: $quota),
			actor: $actor
		);
	}//end payloads()

	/**
	 * Without the quota service the list is bare, each row tagged with its
	 * source and the caller's ownership.
	 *
	 * @return void
	 */
	public function testVisibleListWithoutQuotaIsTheBareList(): void {
		$list = $this->payloads()->visibleList(
			userId: 'alice',
			items: [
				['dashboard' => $this->dashboard(uuid: 'a', userId: 'alice'), 'source' => 'user'],
				['dashboard' => $this->dashboard(uuid: 'b', userId: null), 'source' => 'group'],
			]
		);

		self::assertCount(2, $list);
		self::assertSame('user', $list[0]['source']);
		self::assertTrue($list[0]['isOwner']);
		self::assertSame('group', $list[1]['source']);
		self::assertFalse($list[1]['isOwner']);
	}//end testVisibleListWithoutQuotaIsTheBareList()

	/**
	 * With the quota service the list sits in `{items, quota}`.
	 *
	 * @return void
	 */
	public function testVisibleListWithQuotaCarriesTheEnvelope(): void {
		$quota = $this->createMock(originalClassName: QuotaServiceInterface::class);
		$quota->method('getQuotaStatus')->with('alice')->willReturn(['maxDashboards' => 5]);

		$list = $this->payloads(quota: $quota)->visibleList(
			userId: 'alice',
			items: [['dashboard' => $this->dashboard(uuid: 'a', userId: 'alice'), 'source' => 'user']]
		);

		self::assertSame(['maxDashboards' => 5], $list['quota']);
		self::assertCount(1, $list['items']);
	}//end testVisibleListWithQuotaCarriesTheEnvelope()

	/**
	 * Without an effective dashboard there is no envelope.
	 *
	 * @return void
	 */
	public function testActiveIsNullWithoutADashboard(): void {
		$this->dashboardService->method('getEffectiveDashboard')->willReturn(null);

		self::assertNull($this->payloads()->active(userId: 'alice'));
	}//end testActiveIsNullWithoutADashboard()

	/**
	 * A group dashboard the caller does not own is tagged as such, with its
	 * serialised placements and permission level.
	 *
	 * @return void
	 */
	public function testActiveEnvelopeTagsOwnershipAndPlacements(): void {
		$placement = $this->createMock(originalClassName: WidgetPlacement::class);
		$placement->method('jsonSerialize')->willReturn(['id' => 7]);
		$this->dashboardService->method('getEffectiveDashboard')->with('alice')->willReturn([
			'dashboard' => $this->dashboard(uuid: 'g', userId: 'bob'),
			'placements' => [$placement],
			'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
		]);

		$envelope = $this->payloads()->active(userId: 'alice');

		self::assertSame('g', $envelope['dashboard']['uuid']);
		self::assertSame([['id' => 7]], $envelope['placements']);
		self::assertSame(Dashboard::PERMISSION_VIEW_ONLY, $envelope['permissionLevel']);
		self::assertFalse($envelope['isOwner']);
		self::assertSame('bob', $envelope['sharedBy']);
		self::assertFalse($envelope['hasVariants']);
	}//end testActiveEnvelopeTagsOwnershipAndPlacements()

	/**
	 * The first load carries both payloads when the caller may read them.
	 *
	 * @return void
	 */
	public function testFirstLoadCarriesBothPayloads(): void {
		$this->dashboardService->method('getEffectiveDashboard')->willReturn(null);

		$firstLoad = $this->payloads()->firstLoad(
			userId: 'alice',
			visible: [['dashboard' => $this->dashboard(uuid: 'a', userId: 'alice'), 'source' => 'user']]
		);

		self::assertCount(1, $firstLoad['visible']);
		self::assertNull($firstLoad['active']);
	}//end testFirstLoadCarriesBothPayloads()

	/**
	 * A caller the action matrix refuses gets no first load, so the store
	 * asks the API and meets the same refusal there.
	 *
	 * @return void
	 */
	public function testFirstLoadIsNullWhenTheActionIsRefused(): void {
		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		$this->dashboardService->expects(self::never())->method('getEffectiveDashboard');

		self::assertNull($this->payloads()->firstLoad(userId: 'alice', visible: []));
	}//end testFirstLoadIsNullWhenTheActionIsRefused()

	/**
	 * Without the action check, or without a user, there is no first load.
	 *
	 * @return void
	 */
	public function testFirstLoadIsNullWithoutActorOrUser(): void {
		self::assertNull($this->payloads(withActor: false)->firstLoad(userId: 'alice', visible: []));
		self::assertNull($this->payloads()->firstLoad(userId: '', visible: []));
	}//end testFirstLoadIsNullWithoutActorOrUser()
}//end class
