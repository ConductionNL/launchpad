<?php

/**
 * The hierarchy descriptors a dashboard stores (REQ-CWF-011).
 *
 * Ensures `updateDashboard()` stores validated descriptors, returns them on
 * the read, clears them on an empty list, keeps them when an update does not
 * name them, and refuses a malformed one before saving.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
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

class DashboardServiceHierarchiesTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var FooterService&MockObject */
	private $footerService;

	/**
	 * The service under test.
	 *
	 * @var DashboardService
	 */
	private DashboardService $service;

	/**
	 * Build the service on mocked stores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->footerService = $this->createMock(originalClassName: FooterService::class);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
				settingMapper: $this->createMock(originalClassName: AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(originalClassName: TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(originalClassName: DashboardResolver::class),
				adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				personalLayers: $this->createMock(originalClassName: PersonalLayerService::class),
				footerService: $this->footerService
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				db: $this->createMock(originalClassName: IDBConnection::class),
				config: $this->createMock(originalClassName: IConfig::class),
				l10nFactory: $this->createMock(originalClassName: IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			)
		);
	}

	/**
	 * A personal dashboard of alice.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId('alice');
		$dashboard->setUuid('uuid-1');
		$dashboard->setName('Test');
		return $dashboard;
	}

	/**
	 * The nl-administrative descriptor as stored.
	 *
	 * @return array<string, mixed>
	 */
	private static function nlAdministrative(): array {
		return [
			'id'     => 'nl-administrative',
			'levels' => [
				['dimension' => 'provincieCode', 'label' => 'Province', 'parentDimension' => null],
				['dimension' => 'gemeenteCode', 'label' => 'Municipality', 'parentDimension' => 'provincieCode'],
				['dimension' => 'wijkCode', 'label' => 'District', 'parentDimension' => 'gemeenteCode'],
			],
		];
	}

	/**
	 * Stored descriptors come back on the owner and the viewer read; the empty list clears them.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testHierarchiesAreStoredReadAndCleared(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$saved = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['hierarchies' => [self::nlAdministrative()]]);

		$this->assertSame(expected: [self::nlAdministrative()], actual: $saved->jsonSerialize()['hierarchies']);
		$this->assertSame(expected: [self::nlAdministrative()], actual: $saved->toViewerArray()['hierarchies']);

		$cleared = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['hierarchies' => []]);

		$this->assertNull(actual: $cleared->getHierarchies());
		$this->assertSame(expected: [], actual: $cleared->jsonSerialize()['hierarchies']);
	}

	/**
	 * An update that does not name the hierarchies leaves them alone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testAnUpdateWithoutHierarchiesKeepsThem(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setHierarchiesArray([self::nlAdministrative()]);
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$result = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['name' => 'Renamed']);

		$this->assertSame(expected: [self::nlAdministrative()], actual: $result->getHierarchiesArray());
	}

	/**
	 * A malformed descriptor is refused and nothing is saved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testAMalformedDescriptorIsRefused(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->expects($this->never())->method('update');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'hierarchies[0].levels must be a non-empty list');
		$this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['hierarchies' => [['id' => 'nl-administrative']]]);
	}

	/**
	 * A stored value that is no JSON list reads as no hierarchies.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testAStoredValueThatIsNoListReadsAsNone(): void {
		$dashboard = $this->makeDashboard();
		foreach (['', 'not json', '{"id":"x"}'] as $stored) {
			$dashboard->setHierarchies($stored);
			$this->assertSame(expected: [], actual: $dashboard->getHierarchiesArray());
		}
	}
}
