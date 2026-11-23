<?php

/**
 * The dashboard reads hand their placements through the widget visibility
 * rules (REQ-VIS-013): an editor may see hidden widgets, a viewer may not.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\DashboardApiAccess;
use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\UserResponses;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Which permission level counts as an editor for the visibility rules.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 */
class DashboardApiControllerVisibilityTest extends TestCase {

	/**
	 * The dashboard service double.
	 *
	 * @var DashboardService&MockObject
	 */
	private DashboardService $dashboards;

	/**
	 * The visibility double.
	 *
	 * @var ConditionalServiceInterface&MockObject
	 */
	private ConditionalServiceInterface $visibility;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
	}//end setUp()

	/**
	 * The controller with alice signed in.
	 *
	 * @return DashboardApiController The controller.
	 */
	private function controller(?RoleFeaturePermissionService $roleWidgets=null): DashboardApiController {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			services: new DashboardApiServices(dashboardService: $this->dashboards, visibility: $this->visibility, roleWidgets: $roleWidgets),
			access: new DashboardApiAccess(
				permissionService: $this->createMock(originalClassName: PermissionService::class),
				history: new DashboardApiHistory(
					treeService: $this->createMock(originalClassName: DashboardTreeService::class),
					versionService: $this->createMock(originalClassName: DashboardVersionService::class),
					analyticsService: $this->createMock(originalClassName: AnalyticsService::class),
					logger: $this->createMock(originalClassName: LoggerInterface::class)
				)
			),
			context: new ActorResponses(
				actor: new RequestActor(userSession: $session, actionAuth: $this->createMock(originalClassName: ActionAuthService::class)),
				responses: new UserResponses(userId: 'alice', responseHelper: new ResponseHelper(logger: new NullLogger()))
			)
		);
	}//end controller()

	/**
	 * A dashboard with one placement, at a permission level.
	 *
	 * @param string $level The caller's permission level.
	 *
	 * @return array<string, mixed> The service result.
	 */
	private static function readResult(string $level, string $owner='bob'): array {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('dash-7');
		$dashboard->setUserId($owner);
		$placement = new WidgetPlacement();
		$placement->setId(3);

		return ['dashboard' => $dashboard, 'placements' => [$placement], 'permissionLevel' => $level];
	}//end readResult()

	/**
	 * Permission levels and whether they edit.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function levels(): array {
		return [
			'view only' => [Dashboard::PERMISSION_VIEW_ONLY, false],
			'add only'  => [Dashboard::PERMISSION_ADD_ONLY, true],
			'full'      => [Dashboard::PERMISSION_FULL, true],
		];
	}//end levels()

	/**
	 * show() hands the placements to the visibility rules and answers what they return.
	 *
	 * @param string $level   The caller's permission level.
	 * @param bool   $canEdit Whether that level edits.
	 *
	 * @return void
	 */
	#[DataProvider('levels')]
	public function testShowAppliesTheRules(string $level, bool $canEdit): void {
		$this->dashboards->method('getDashboardForUser')->willReturn(self::readResult(level: $level));
		$this->visibility->expects($this->once())
			->method('visiblePlacements')
			->with($this->callback(callback: static fn (array $placements): bool => ($placements[0]['id'] ?? null) === 3), 'alice', $canEdit)
			->willReturn([['id' => 3, 'hiddenForViewers' => true]]);

		$data = $this->controller()->show(id: 7)->getData();

		$this->assertSame(expected: [['id' => 3, 'hiddenForViewers' => true]], actual: $data['placements']);
	}//end testShowAppliesTheRules()

	/**
	 * getActive() applies the rules the same way.
	 *
	 * @param string $level   The caller's permission level.
	 * @param bool   $canEdit Whether that level edits.
	 *
	 * @return void
	 */
	#[DataProvider('levels')]
	public function testGetActiveAppliesTheRules(string $level, bool $canEdit): void {
		$this->dashboards->method('getEffectiveDashboard')->willReturn(self::readResult(level: $level));
		$this->visibility->expects($this->once())
			->method('visiblePlacements')
			->with($this->anything(), 'alice', $canEdit)
			->willReturn([]);

		$data = $this->controller()->getActive()->getData();

		$this->assertSame(expected: [], actual: $data['placements']);
	}//end testGetActiveAppliesTheRules()

	/**
	 * On the caller's own dashboard the placements go through the role's
	 * widget allow-list before the visibility rules (REQ-RFP-003).
	 *
	 * @return void
	 */
	public function testShowHidesUnpermittedWidgetsOnOwnDashboard(): void {
		$this->dashboards->method('getDashboardForUser')
			->willReturn(self::readResult(level: Dashboard::PERMISSION_FULL, owner: 'alice'));
		$roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roleWidgets->expects($this->once())
			->method('hideUnpermittedPlacements')
			->with($this->anything(), 'alice')
			->willReturn([['id' => 3, 'isVisible' => 0]]);
		$this->visibility->expects($this->once())
			->method('visiblePlacements')
			->with([['id' => 3, 'isVisible' => 0]], 'alice', true)
			->willReturnArgument(0);

		$data = $this->controller(roleWidgets: $roleWidgets)->show(id: 7)->getData();

		$this->assertSame(expected: [['id' => 3, 'isVisible' => 0]], actual: $data['placements']);
	}//end testShowHidesUnpermittedWidgetsOnOwnDashboard()

	/**
	 * A dashboard someone else owns, shared with or published to the caller,
	 * is narrowed by the caller's role too, so a view-only viewer never gets
	 * the unpermitted widget (REQ-RFP-001).
	 *
	 * @return void
	 */
	public function testShowHidesUnpermittedWidgetsOnAnotherOwnersDashboard(): void {
		$this->dashboards->method('getDashboardForUser')
			->willReturn(self::readResult(level: Dashboard::PERMISSION_VIEW_ONLY));
		$roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roleWidgets->expects($this->once())
			->method('hideUnpermittedPlacements')
			->with($this->anything(), 'alice')
			->willReturn([['id' => 3, 'isVisible' => 0]]);
		$this->visibility->expects($this->once())
			->method('visiblePlacements')
			->with([['id' => 3, 'isVisible' => 0]], 'alice', false)
			->willReturn([]);

		$data = $this->controller(roleWidgets: $roleWidgets)->show(id: 7)->getData();

		$this->assertSame(expected: [], actual: $data['placements']);
	}//end testShowHidesUnpermittedWidgetsOnAnotherOwnersDashboard()

	/**
	 * getActive() narrows a group dashboard the caller does not own.
	 *
	 * @return void
	 */
	public function testGetActiveHidesUnpermittedWidgetsOnAGroupDashboard(): void {
		$this->dashboards->method('getEffectiveDashboard')
			->willReturn(self::readResult(level: Dashboard::PERMISSION_VIEW_ONLY, owner: ''));
		$roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roleWidgets->expects($this->once())
			->method('hideUnpermittedPlacements')
			->with($this->anything(), 'alice')
			->willReturn([['id' => 3, 'isVisible' => 0]]);
		$this->visibility->method('visiblePlacements')->willReturnArgument(0);

		$data = $this->controller(roleWidgets: $roleWidgets)->getActive()->getData();

		$this->assertSame(expected: [['id' => 3, 'isVisible' => 0]], actual: $data['placements']);
	}//end testGetActiveHidesUnpermittedWidgetsOnAGroupDashboard()

	/**
	 * getActive() narrows the caller's own dashboard the same way.
	 *
	 * @return void
	 */
	public function testGetActiveHidesUnpermittedWidgetsOnOwnDashboard(): void {
		$this->dashboards->method('getEffectiveDashboard')
			->willReturn(self::readResult(level: Dashboard::PERMISSION_FULL, owner: 'alice'));
		$roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roleWidgets->method('hideUnpermittedPlacements')->willReturn([['id' => 3, 'isVisible' => 0]]);
		$this->visibility->method('visiblePlacements')->willReturnArgument(0);

		$data = $this->controller(roleWidgets: $roleWidgets)->getActive()->getData();

		$this->assertSame(expected: [['id' => 3, 'isVisible' => 0]], actual: $data['placements']);
	}//end testGetActiveHidesUnpermittedWidgetsOnOwnDashboard()
}//end class
