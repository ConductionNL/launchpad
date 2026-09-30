<?php

/**
 * The dashboard read carries breadcrumbs for a child dashboard, and an
 * ancestor the viewer may not see keeps its place but not its name
 * (dashboard-tree-navigation REQ-TREEUI-002).
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
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardApiControllerBreadcrumbsTest extends TestCase {
	private $dashboardService;

	private $treeService;

	protected function setUp(): void {
		parent::setUp();
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->treeService = $this->createMock(DashboardTreeService::class);
	}

	private function controller(): DashboardApiController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('sanne');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(IRequest::class),
			dashboardService: $this->dashboardService,
			permissionService: $this->createMock(PermissionService::class),
			treeService: $this->treeService,
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->createMock(AnalyticsService::class),
			logger: $this->createMock(LoggerInterface::class),
			userSession: $session,
			actionAuth: $this->createMock(ActionAuthService::class),
			userId: 'sanne',
		);
	}

	private function dashboard(string $uuid, string $name, ?string $parent): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		$dashboard->setParentUuid($parent);
		$dashboard->setUserId('owner');
		return $dashboard;
	}

	private function readChild(array $visible): array {
		$child = $this->dashboard('onb', 'Onboarding', 'hr');
		$this->dashboardService->method('getDashboardForUser')->willReturn(
			['dashboard' => $child, 'placements' => [], 'permissionLevel' => 'view_only']
		);
		$this->treeService->method('computeBreadcrumbs')->with(uuid: 'onb')->willReturn(
			[
				['uuid' => 'hr', 'name' => 'HR', 'slug' => 'hr'],
				['uuid' => 'onb', 'name' => 'Onboarding', 'slug' => 'onboarding'],
			]
		);
		$this->dashboardService->method('getVisibleToUser')->willReturn(
			array_map(static fn (Dashboard $d): array => ['dashboard' => $d, 'source' => 'group'], [$child, ...$visible])
		);

		return $this->controller()->show(7)->getData();
	}

	public function testAChildDashboardCarriesItsBreadcrumbs(): void {
		$data = $this->readChild([$this->dashboard('hr', 'HR', null)]);

		$this->assertSame(['HR', 'Onboarding'], array_column($data['breadcrumbs'], 'name'));
	}

	public function testAnAncestorTheViewerMayNotSeeLosesItsName(): void {
		$data = $this->readChild([]);

		$this->assertNull($data['breadcrumbs'][0]['name']);
		$this->assertNull($data['breadcrumbs'][0]['uuid']);
		$this->assertTrue($data['breadcrumbs'][0]['hidden']);
		$this->assertSame('Onboarding', $data['breadcrumbs'][1]['name']);
	}

	public function testATopLevelDashboardHasNoBreadcrumbs(): void {
		$this->dashboardService->method('getDashboardForUser')->willReturn(
			['dashboard' => $this->dashboard('hr', 'HR', null), 'placements' => [], 'permissionLevel' => 'full']
		);
		$this->treeService->expects($this->never())->method('computeBreadcrumbs');

		$this->assertSame([], $this->controller()->show(3)->getData()['breadcrumbs']);
	}
}
