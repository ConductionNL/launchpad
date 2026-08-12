<?php

/**
 * DashboardApiController Quota Test
 *
 * Unit tests for the dashboard-quota-limits controller surface
 * (REQ-QUOTA-002 / REQ-QUOTA-006):
 *   - fork at the dashboard limit returns HTTP 409 with the structured body
 *   - the visible() listing carries the additive quota envelope
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

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\QuotaService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the controller constructor.
 */
class DashboardApiControllerQuotaTest extends TestCase {

	/** @var DashboardService&MockObject */
	private $dashboardService;

	/** @var IUserSession&MockObject */
	private $userSession;

	/** @var ActionAuthService&MockObject */
	private $actionAuth;

	/** @var AdminSettingMapper&MockObject */
	private $settingMapper;

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	private QuotaService $quotaService;

	protected function setUp(): void {
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);

		$this->quotaService = new QuotaService(
			settingMapper: $this->settingMapper,
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
		);
	}//end setUp()

	private function makeController(?string $userId = 'alice'): DashboardApiController {
		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}

		$this->userSession->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(IRequest::class),
			dashboardService: $this->dashboardService,
			permissionService: $this->createMock(PermissionService::class),
			treeService: $this->createMock(DashboardTreeService::class),
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->createMock(AnalyticsService::class),
			logger: $this->createMock(LoggerInterface::class),
			userSession: $this->userSession,
			actionAuth: $this->actionAuth,
			userId: $userId,
			quotaService: $this->quotaService,
		);
	}//end makeController()

	public function testForkAtQuotaReturns409StructuredBody(): void {
		// REQ-QUOTA-002 — the service throws; the controller maps it to a
		// 409 with the machine-readable body.
		$this->dashboardService
			->method('forkAsPersonal')
			->willThrowException(
				new QuotaExceededException(
					quota: QuotaExceededException::QUOTA_DASHBOARDS,
					limit: 5,
					current: 5
				)
			);

		$response = $this->makeController()->fork(uuid: 'source-uuid');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame(
			[
				'error' => 'quota_exceeded',
				'quota' => 'dashboards',
				'limit' => 5,
				'current' => 5,
			],
			$response->getData()
		);
	}//end testForkAtQuotaReturns409StructuredBody()

	public function testVisibleCarriesQuotaEnvelope(): void {
		// REQ-QUOTA-006 — the unioned listing carries the additive quota
		// envelope so the frontend can disable affordances.
		$this->dashboardService->method('getVisibleToUser')->willReturn([]);
		$this->settingMapper->method('getValue')->willReturnCallback(
			function (string $k, $default = null) {
				return match ($k) {
					AdminSetting::KEY_MAX_DASHBOARDS_PER_USER => 5,
					AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD => 40,
					AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS => true,
					default => $default,
				};
			}
		);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(3);

		$response = $this->makeController()->visible();
		$data = $response->getData();

		$this->assertArrayHasKey('items', $data);
		$this->assertArrayHasKey('quota', $data);
		$this->assertSame(
			[
				'maxDashboards' => 5,
				'dashboardsUsed' => 3,
				'maxWidgetsPerDashboard' => 40,
			],
			$data['quota']
		);
	}//end testVisibleCarriesQuotaEnvelope()
}//end class
