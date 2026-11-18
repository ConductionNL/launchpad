<?php

/**
 * The dashboard default tile link target (launcher-tile-open-behaviour D2).
 *
 * Ensures `updateDashboard()` stores each of the three values, clears the
 * default on an empty value, keeps it when an update does not name it, and
 * refuses any other value before saving.
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

use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardServiceLinkTargetTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var FooterService&MockObject */
	private $footerService;

	private DashboardService $service;

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->footerService = $this->createMock(FooterService::class);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				settingMapper: $this->createMock(AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(DashboardResolver::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(DashboardTreeService::class),
				personalLayers: $this->createMock(PersonalLayerService::class),
				footerService: $this->footerService
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(IGroupManager::class),
				db: $this->createMock(IDBConnection::class),
				config: $this->createMock(IConfig::class),
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(LoggerInterface::class)
			)
		);
	}

	private function makeDashboard(string $userId = 'alice'): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId($userId);
		$dashboard->setUuid('uuid-1');
		$dashboard->setName('Test');
		return $dashboard;
	}

	/**
	 * Each of the three values is stored; an empty value clears the default.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testTheDefaultIsStoredAndCleared(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$stored = [];
		foreach (['same-tab', 'new-tab', 'side-panel', ''] as $value) {
			$stored[] = $this->service->updateDashboard(
				dashboardId: 1,
				userId: 'alice',
				data: ['defaultLinkTarget' => $value]
			)->getDefaultLinkTarget();
		}

		$this->assertSame(['same-tab', 'new-tab', 'side-panel', null], $stored);
		$this->assertArrayHasKey('defaultLinkTarget', $dashboard->jsonSerialize());
	}

	/**
	 * An update that does not name the default leaves it alone.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testAnUpdateWithoutTheDefaultKeepsIt(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setDefaultLinkTarget('side-panel');
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$result = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['name' => 'Renamed']);

		$this->assertSame('side-panel', $result->getDefaultLinkTarget());
	}

	/**
	 * Any other value is refused and nothing is saved.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testAnUnknownDefaultIsRefused(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('defaultLinkTarget must be one of same-tab, new-tab, side-panel');
		$this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['defaultLinkTarget' => 'popup']);
	}
}
