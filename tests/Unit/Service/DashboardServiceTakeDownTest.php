<?php

/**
 * DashboardService take-down time test (REQ-SCHEDUI-002).
 *
 * A published dashboard whose `unpublishAt` has passed is treated as
 * unpublished when read: colleagues no longer find it in their list, while
 * the owner still does.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Read-time take-down of a published dashboard.
 */
class DashboardServiceTakeDownTest extends TestCase {

	/**
	 * Dashboard mapper mock.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private $dashboardMapper;

	/**
	 * Resolver mock (share path).
	 *
	 * @var DashboardResolver&MockObject
	 */
	private $dashResolver;

	/**
	 * Service under test.
	 *
	 * @var DashboardService
	 */
	private DashboardService $service;

	/**
	 * Set up fresh mocks per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->dashResolver = $this->createMock(DashboardResolver::class);
		$adminTemplateService = $this->createMock(AdminTemplateService::class);
		$adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);

		$this->service = new DashboardService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			settingMapper: $this->createMock(AdminSettingMapper::class),
			templateService: $this->createMock(TemplateService::class),
			dashboardFactory: new DashboardFactory(),
			dashResolver: $this->dashResolver,
			treeService: $this->createMock(DashboardTreeService::class),
			groupManager: $groupManager,
			adminTemplateService: $adminTemplateService,
			db: $this->createMock(IDBConnection::class),
			config: $this->createMock(IConfig::class),
			l10nFactory: $this->createMock(IFactory::class),
			logger: $this->createMock(LoggerInterface::class),
			personalLayers: $this->createMock(PersonalLayerService::class),
		);
	}//end setUp()

	/**
	 * Build a published dashboard with a take-down time.
	 *
	 * @param string $uuid The dashboard uuid.
	 * @param string $owner The owner.
	 * @param string $unpublishAt Relative time for the take-down.
	 *
	 * @return Dashboard
	 */
	private function makePublished(string $uuid, string $owner, string $unpublishAt): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName('Open day');
		$dashboard->setUserId($owner);
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
		$dashboard->setUnpublishAt((new DateTime($unpublishAt))->format('Y-m-d H:i:s'));

		return $dashboard;
	}//end makePublished()

	/**
	 * Scenario "Take-down passes": a colleague no longer lists it.
	 *
	 * @return void
	 */
	public function testAPassedTakeDownHidesTheDashboardFromColleagues(): void {
		$past = $this->makePublished('uuid-past', 'sanne', '-1 day');
		$future = $this->makePublished('uuid-future', 'sanne', '+1 day');
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				['dashboard' => $past, 'source' => Dashboard::SOURCE_GROUP],
				['dashboard' => $future, 'source' => Dashboard::SOURCE_GROUP],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);

		$visible = $this->service->getVisibleToUser(userId: 'colleague');

		$uuids = array_map(fn (array $e): string => (string) $e['dashboard']->getUuid(), $visible);
		$this->assertSame(['uuid-future'], $uuids);
	}//end testAPassedTakeDownHidesTheDashboardFromColleagues()

	/**
	 * The owner still finds a taken-down dashboard, reported as a draft.
	 *
	 * @return void
	 */
	public function testTheOwnerStillSeesATakenDownDashboardAsADraft(): void {
		$past = $this->makePublished('uuid-past', 'sanne', '-1 day');
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[['dashboard' => $past, 'source' => Dashboard::SOURCE_USER]]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);

		$visible = $this->service->getVisibleToUser(userId: 'sanne');

		$this->assertCount(1, $visible);
		$this->assertSame(Dashboard::STATUS_DRAFT, $visible[0]['dashboard']->getPublicationStatus());
	}//end testTheOwnerStillSeesATakenDownDashboardAsADraft()

	/**
	 * A scheduled dashboard that went live and already came down is hidden too.
	 *
	 * @return void
	 */
	public function testAScheduledWindowThatClosedIsHidden(): void {
		$dashboard = $this->makePublished('uuid-window', 'sanne', '-1 hour');
		$dashboard->setPublicationStatus(Dashboard::STATUS_SCHEDULED);
		$dashboard->setPublishAt((new DateTime('-2 hours'))->format('Y-m-d H:i:s'));
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[['dashboard' => $dashboard, 'source' => Dashboard::SOURCE_GROUP]]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);

		$this->assertSame([], $this->service->getVisibleToUser(userId: 'colleague'));
	}//end testAScheduledWindowThatClosedIsHidden()
}//end class
