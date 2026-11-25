<?php

/**
 * StoreUpdateServiceTest
 *
 * Updating an installed marketplace dashboard in place: REQ-STORE-012.
 *
 * The real install record, pairing and hydrator run; the two mappers are an
 * in-memory store, the register, the re-sync and the version service are mocks.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PlacementPairing;
use OCA\LaunchPad\Service\PlacementPayloadHydrator;
use OCA\LaunchPad\Service\ShippedTemplateStores;
use OCA\LaunchPad\Service\StoreInstalls;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Service\StoreUpdateService;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Wires the real services.
 */
class StoreUpdateServiceTest extends TestCase {
	/** @var string The stored install map. */
	private string $stored = '{}';

	/** @var array<int, Dashboard> Stored dashboards by id. */
	private array $dashboards = [];

	/** @var array<int, WidgetPlacement> Stored widgets by id. */
	private array $placements = [];

	private int $nextId = 200;

	/** @var StoreService&MockObject */
	private $store;

	/** @var TemplateResyncService&MockObject */
	private $resync;

	/** @var DashboardVersionService&MockObject */
	private $versions;

	private StoreInstalls $installs;

	private StoreUpdateService $updates;

	/**
	 * Wire the service over the in-memory store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(fn (): string => $this->stored);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->stored = $value;
				return true;
			}
		);

		$placementMapper = $this->placementMapper();
		$stores = new ShippedTemplateStores(
			dashboardMapper: $this->dashboardMapper(),
			placementMapper: $placementMapper,
			db: $this->createMock(IDBConnection::class),
			appConfig: $config
		);

		$this->store = $this->createMock(StoreService::class);
		$this->resync = $this->createMock(TemplateResyncService::class);
		$this->versions = $this->createMock(DashboardVersionService::class);
		$this->installs = new StoreInstalls(appConfig: $config, placements: $placementMapper);

		$this->updates = new StoreUpdateService(
			store: $this->store,
			installs: $this->installs,
			stores: $stores,
			hydrator: new PlacementPayloadHydrator(),
			pairing: new PlacementPairing(stores: $stores),
			resync: $this->resync,
			versions: $this->versions,
			logger: new NullLogger()
		);
	}//end setUp()

	/**
	 * Scenario "The sociaal domein dashboard is updated": the same dashboard
	 * gains the new widget, keeps Hanneke's own one, and the paired widget
	 * keeps its row.
	 *
	 * @return void
	 */
	public function testAnUpdateKeepsTheDashboardAndThePeoplesOwnWidgets(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_USER);
		$wmo = $this->widgetsOn(dashboard: $dashboard)[0];
		$notes = $this->addWidget(dashboard: $dashboard, title: 'Notities', y: 8);
		$this->offer(version: '1.1.0', widgets: [self::widget(title: 'Wmo cijfers', y: 2), self::widget(title: 'Wachtlijst Wmo', y: 4)]);

		$this->versions->expects($this->once())->method('captureSnapshot')->with(
			$this->identicalTo($dashboard),
			'admin',
			['note' => StoreUpdateService::VERSION_NOTE, 'explicit' => true]
		);
		$this->resync->expects($this->never())->method('resync');

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertTrue($result['success']);
		$this->assertSame('1.1.0', $result['version']);
		$this->assertSame(['Wachtlijst Wmo (text)'], $result['added']);
		$this->assertSame([], $result['removed']);
		$this->assertSame(1, $result['kept']);

		$titles = array_map(static fn (WidgetPlacement $p): string => (string)$p->getCustomTitle(), $this->widgetsOn(dashboard: $dashboard));
		sort($titles);
		$this->assertSame(['Notities', 'Wachtlijst Wmo', 'Wmo cijfers'], $titles);
		$this->assertArrayHasKey($wmo->getId(), $this->placements, 'the paired widget keeps its row');
		$this->assertSame(2, $this->placements[$wmo->getId()]->getGridY());
		$this->assertArrayHasKey($notes->getId(), $this->placements);
		$this->assertSame('owner-1', $dashboard->getUserId(), 'owner unchanged');

		$record = $this->installs->all()['sociaal-domein'];
		$this->assertSame('1.1.0', $record['version']);
		$this->assertSame($dashboard->getId(), $record['dashboardId']);
		$this->assertNotContains($notes->getId(), $record['placementIds'], 'a person\'s own widget never becomes an installed one');
		$this->assertCount(2, $record['placementIds']);
	}//end testAnUpdateKeepsTheDashboardAndThePeoplesOwnWidgets()

	/**
	 * A widget the new version drops is removed, but only one the install brought.
	 *
	 * @return void
	 */
	public function testADroppedInstalledWidgetIsRemovedAndAnOwnOneStays(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_USER);
		$notes = $this->addWidget(dashboard: $dashboard, title: 'Notities', y: 8);
		$this->offer(version: '2.0.0', widgets: [self::widget(title: 'Wachtlijst Wmo', y: 4), self::widget(title: 'Kaart', y: 6)]);

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertTrue($result['success']);
		$titles = array_map(static fn (WidgetPlacement $p): string => (string)$p->getCustomTitle(), $this->widgetsOn(dashboard: $dashboard));
		sort($titles);
		$this->assertSame(['Kaart', 'Notities', 'Wachtlijst Wmo'], $titles);
		$this->assertArrayHasKey($notes->getId(), $this->placements);
	}//end testADroppedInstalledWidgetIsRemovedAndAnOwnOneStays()

	/**
	 * A record written before the widget ids were kept removes nothing.
	 *
	 * @return void
	 */
	public function testARecordWithoutWidgetIdsRemovesNothing(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_USER);
		$this->addWidget(dashboard: $dashboard, title: 'Notities', y: 8);
		$this->stored = (string)json_encode(['sociaal-domein' => ['version' => '1.0.0', 'dashboardId' => $dashboard->getId(), 'dashboardUuid' => 'uuid-sd']]);
		$this->offer(version: '1.1.0', widgets: [self::widget(title: 'Wachtlijst Wmo', y: 4)]);

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertTrue($result['success']);
		$this->assertSame([], $result['removed']);
		$this->assertCount(3, $this->widgetsOn(dashboard: $dashboard));
	}//end testARecordWithoutWidgetIdsRemovesNothing()

	/**
	 * An installed admin template brings its copies along with the merge re-sync.
	 *
	 * @return void
	 */
	public function testAnInstalledTemplateResyncsItsCopies(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_ADMIN_TEMPLATE);
		$this->offer(version: '1.1.0', widgets: [self::widget(title: 'Wmo cijfers', y: 0)]);
		$this->resync->expects($this->once())->method('resync')
			->with($dashboard->getId(), TemplateResyncService::STRATEGY_MERGE, false, 'admin')
			->willReturn(['affectedCount' => 3]);

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertSame(3, $result['copies']);
	}//end testAnInstalledTemplateResyncsItsCopies()

	/**
	 * The same or an older version changes nothing.
	 *
	 * @return void
	 */
	public function testAnUpToDateInstallChangesNothing(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_USER);
		$this->offer(version: '1.0.0', widgets: [self::widget(title: 'Iets anders', y: 0)]);
		$this->versions->expects($this->never())->method('captureSnapshot');

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertTrue($result['success']);
		$this->assertTrue($result['upToDate']);
		$this->assertSame('Wmo cijfers', $this->widgetsOn(dashboard: $dashboard)[0]->getCustomTitle());
	}//end testAnUpToDateInstallChangesNothing()

	/**
	 * An item that is not installed, or whose dashboard is gone, has nothing to update.
	 *
	 * @return void
	 */
	public function testNothingInstalledIsNotInstalled(): void {
		$this->store->expects($this->never())->method('resolveItem');
		$this->assertTrue($this->updates->update(slug: 'onbekend', userId: 'admin')['notInstalled']);
	}//end testNothingInstalledIsNotInstalled()

	/**
	 * A deleted dashboard drops its record and reads as not installed.
	 *
	 * @return void
	 */
	public function testADeletedDashboardIsForgotten(): void {
		$dashboard = $this->install(type: Dashboard::TYPE_USER);
		unset($this->dashboards[$dashboard->getId()]);
		$this->offer(version: '1.1.0', widgets: []);

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertTrue($result['notInstalled']);
		$this->assertSame([], $this->installs->all());
	}//end testADeletedDashboardIsForgotten()

	/**
	 * A register refusal reaches the administrator unchanged.
	 *
	 * @return void
	 */
	public function testARegisterRefusalIsPassedThrough(): void {
		$this->install(type: Dashboard::TYPE_USER);
		$this->store->method('resolveItem')->willReturn(['message' => 'That template could not be found in the registry.']);

		$result = $this->updates->update(slug: 'sociaal-domein', userId: 'admin');

		$this->assertFalse($result['success']);
		$this->assertSame('That template could not be found in the registry.', $result['message']);
	}//end testARegisterRefusalIsPassedThrough()

	/**
	 * Install "Managementinfo sociaal domein" 1.0.0 with one widget and record it.
	 *
	 * @param string $type The dashboard type.
	 *
	 * @return Dashboard The installed dashboard.
	 */
	private function install(string $type): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid('uuid-sd');
		$dashboard->setName('Managementinfo sociaal domein');
		$dashboard->setUserId('owner-1');
		$dashboard->setType($type);
		$dashboard->setId($this->nextId++);
		$this->dashboards[$dashboard->getId()] = $dashboard;

		$this->addWidget(dashboard: $dashboard, title: 'Wmo cijfers', y: 0);
		$this->installs->record(slug: 'sociaal-domein', version: '1.0.0', dashboard: ['id' => $dashboard->getId(), 'uuid' => 'uuid-sd']);

		return $dashboard;
	}//end install()

	/**
	 * Put one stored widget on a dashboard.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param string    $title     Its title.
	 * @param int       $y         Its row.
	 *
	 * @return WidgetPlacement The stored widget.
	 */
	private function addWidget(Dashboard $dashboard, string $title, int $y): WidgetPlacement {
		$placement = (new PlacementPayloadHydrator())->hydrate(dashboardId: (int)$dashboard->getId(), payload: self::widget(title: $title, y: $y));
		$placement->setId($this->nextId++);
		$this->placements[$placement->getId()] = $placement;

		return $placement;
	}//end addWidget()

	/**
	 * Let the register offer a version with these widgets.
	 *
	 * @param string                           $version The offered version.
	 * @param array<int, array<string, mixed>> $widgets Its widgets.
	 *
	 * @return void
	 */
	private function offer(string $version, array $widgets): void {
		$this->store->method('resolveItem')->willReturn(
			['version' => $version, 'dashboards' => [['uuid' => 'remote-uuid', 'name' => 'Managementinfo sociaal domein', 'widgets' => $widgets]]]
		);
	}//end offer()

	/**
	 * The stored widgets of a dashboard.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return array<int, WidgetPlacement> Its widgets.
	 */
	private function widgetsOn(Dashboard $dashboard): array {
		return array_values(array_filter($this->placements, static fn (WidgetPlacement $p): bool => $p->getDashboardId() === $dashboard->getId()));
	}//end widgetsOn()

	/**
	 * One widget payload.
	 *
	 * @param string $title Its title.
	 * @param int    $y     Its row.
	 *
	 * @return array<string, mixed> The payload.
	 */
	private static function widget(string $title, int $y): array {
		return ['widgetId' => 'text', 'gridX' => 0, 'gridY' => $y, 'gridWidth' => 6, 'gridHeight' => 2, 'customTitle' => $title, 'content' => ['text' => $title]];
	}//end widget()

	/**
	 * An in-memory dashboard mapper.
	 *
	 * @return DashboardMapper&MockObject
	 */
	private function dashboardMapper() {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('find')->willReturnCallback(
			fn (int $id): Dashboard => $this->dashboards[$id] ?? throw new DoesNotExistException('no dashboard ' . $id)
		);

		return $mapper;
	}//end dashboardMapper()

	/**
	 * An in-memory placement mapper.
	 *
	 * @return WidgetPlacementMapper&MockObject
	 */
	private function placementMapper() {
		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				$placement->setId($this->nextId++);
				$this->placements[$placement->getId()] = $placement;
				return $placement;
			}
		);
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('delete')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				unset($this->placements[$placement->getId()]);
				return $placement;
			}
		);
		$mapper->method('findByDashboardId')->willReturnCallback(
			fn (int $dashboardId): array => array_values(array_filter(
				$this->placements,
				static fn (WidgetPlacement $p): bool => $p->getDashboardId() === $dashboardId
			))
		);

		return $mapper;
	}//end placementMapper()
}//end class
