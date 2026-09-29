<?php

/**
 * The dashboard read says whether a dashboard has more than one language,
 * so the page asks for the resolved variant only then; and the visible
 * list passes `metadata[<key>]` filters to MetadataService::filterDashboards
 * (dashboard-language-and-details-tabs REQ-LANGUI-002, REQ-MDUI-003).
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
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardApiControllerLanguagesAndDetailsTest extends TestCase {
	private $dashboardService;

	private $translations;

	private $metadata;

	private $request;

	protected function setUp(): void {
		parent::setUp();
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->translations = $this->createMock(DashboardTranslationService::class);
		$this->metadata = $this->createMock(MetadataService::class);
		$this->request = $this->createMock(IRequest::class);
	}

	private function controller(): DashboardApiController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('jan');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->request,
			dashboardService: $this->dashboardService,
			permissionService: $this->createMock(PermissionService::class),
			treeService: $this->createMock(DashboardTreeService::class),
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->createMock(AnalyticsService::class),
			logger: $this->createMock(LoggerInterface::class),
			userSession: $session,
			actionAuth: $this->createMock(ActionAuthService::class),
			userId: 'jan',
			translationService: $this->translations,
			metadataService: $this->metadata,
		);
	}

	private function dashboard(string $uuid, string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		$dashboard->setUserId('owner');
		return $dashboard;
	}

	public function testTheReadSaysWhenThereIsMoreThanOneLanguage(): void {
		$this->dashboardService->method('getDashboardForUser')->willReturn(
			['dashboard' => $this->dashboard('intra', 'Intranet'), 'placements' => [], 'permissionLevel' => 'view_only']
		);
		$this->translations->method('listVariants')->with(dashboardUuid: 'intra')->willReturn(
			[new DashboardTranslation(), new DashboardTranslation()]
		);

		$this->assertTrue($this->controller()->show(1)->getData()['hasVariants']);
	}

	public function testOneLanguageIsNoVariants(): void {
		$this->dashboardService->method('getDashboardForUser')->willReturn(
			['dashboard' => $this->dashboard('intra', 'Intranet'), 'placements' => [], 'permissionLevel' => 'view_only']
		);
		$this->translations->method('listVariants')->willReturn([new DashboardTranslation()]);

		$this->assertFalse($this->controller()->show(1)->getData()['hasVariants']);
	}

	public function testTheVisibleListPassesTheMetadataFilter(): void {
		$payroll = $this->dashboard('payroll', 'Payroll');
		$onboarding = $this->dashboard('onb', 'Onboarding');
		$this->dashboardService->method('getVisibleToUser')->willReturn(
			[['dashboard' => $payroll, 'source' => 'group'], ['dashboard' => $onboarding, 'source' => 'group']]
		);
		$this->request->method('getParam')->willReturnMap([['metadata', null, ['department' => 'Finance']]]);
		$this->metadata->expects($this->once())
			->method('filterDashboards')
			->with([$payroll, $onboarding], ['department' => 'Finance'])
			->willReturn([$payroll]);

		$data = $this->controller()->visible()->getData();

		$this->assertSame(['Payroll'], array_column($data, 'name'));
	}

	public function testNoFilterLeavesTheListAlone(): void {
		$this->dashboardService->method('getVisibleToUser')->willReturn(
			[['dashboard' => $this->dashboard('payroll', 'Payroll'), 'source' => 'group']]
		);
		$this->metadata->expects($this->never())->method('filterDashboards');

		$this->assertCount(1, $this->controller()->visible()->getData());
	}
}
