<?php

/**
 * The schedule endpoint takes a take-down time, alone or with a go-live
 * time (sharing-dashboard-schedule-screen REQ-SCHEDUI-001, REQ-SCHEDUI-002).
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardApiControllerScheduleTest extends TestCase {
	private $dashboardService;

	private function controller(): DashboardApiController {
		$this->dashboardService = $this->dashboardService ?? $this->createMock(DashboardService::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('sanne');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(IRequest::class),
			dashboardService: $this->dashboardService,
			permissionService: $this->createMock(PermissionService::class),
			treeService: $this->createMock(DashboardTreeService::class),
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->createMock(AnalyticsService::class),
			logger: $this->createMock(LoggerInterface::class),
			userSession: $session,
			actionAuth: $this->createMock(ActionAuthService::class),
			userId: 'sanne',
		);
	}

	public function testATakeDownTimeAloneIsPassedOn(): void {
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->dashboardService->expects($this->once())
			->method('schedule')
			->with(uuid: 'open-day', publishAt: null, userId: 'sanne', unpublishAt: '2026-10-02T17:00:00+02:00')
			->willReturn(new Dashboard());

		$response = $this->controller()->schedule(uuid: 'open-day', publishAt: null, unpublishAt: '2026-10-02T17:00:00+02:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testNeitherTimeIsRefused(): void {
		$response = $this->controller()->schedule(uuid: 'open-day');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
