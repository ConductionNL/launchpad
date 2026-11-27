<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Leaving edit mode keeps the state from before editing as a version
 * (REQ-VERS-010, versions-on-widget-edits).
 *
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Service\Versions;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\Versions\EditSessionVersions;
use OCA\LaunchPad\Service\VersionStores;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The edit session runs on the real version service with mocked tables and a
 * working in-memory cache, so the stored row is the one a restore reads.
 */
class EditSessionVersionsTest extends TestCase {

	/**
	 * The version table.
	 *
	 * @var DashboardVersionMapper&MockObject
	 */
	private DashboardVersionMapper&MockObject $versionMapper;

	/**
	 * The widget table.
	 *
	 * @var WidgetPlacementMapper&MockObject
	 */
	private WidgetPlacementMapper&MockObject $placementMapper;

	/**
	 * Both caches (sessions and the debounce), by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = [];

	/**
	 * The unit under test.
	 *
	 * @var EditSessionVersions
	 */
	private EditSessionVersions $sessions;

	/**
	 * Wire the session on the real version service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->versionMapper   = $this->createMock(originalClassName: DashboardVersionMapper::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$dashboardMapper       = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboardMapper->method('findByUuid')->willReturn($this->dashboard());
		$dashboardMapper->method('find')->willReturn($this->dashboard());
		$this->versionMapper->method('findMaxVersionNumber')->willReturn(0);
		$this->versionMapper->method('insert')->willReturnArgument(0);

		$stores = new VersionStores(
			versionMapper: $this->versionMapper,
			dashboardMapper: $dashboardMapper,
			placementMapper: $this->placementMapper
		);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('isAvailable')->willReturn(true);
		$cacheFactory->method('createDistributed')->willReturn($this->workingCache());
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->sessions = new EditSessionVersions(
			stores: $stores,
			versions: new DashboardVersionService(
				stores: $stores,
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				cacheFactory: $cacheFactory,
				logger: $logger
			),
			cacheFactory: $cacheFactory,
			logger: $logger
		);
	}//end setUp()

	/**
	 * A cache that keeps what is set, shared by every prefix.
	 *
	 * @return ICache&MockObject The cache.
	 */
	private function workingCache(): ICache&MockObject {
		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('hasKey')->willReturnCallback(fn (string $key): bool => array_key_exists($key, $this->store));
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => ($this->store[$key] ?? null));
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value): bool {
			$this->store[$key] = $value;
			return true;
		});
		$cache->method('remove')->willReturnCallback(function (string $key): bool {
			unset($this->store[$key]);
			return true;
		});
		return $cache;
	}//end workingCache()

	/**
	 * Ruben's dashboard.
	 *
	 * @return Dashboard The dashboard.
	 */
	private function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId(7);
		$dashboard->setUuid('d-uuid-1');
		$dashboard->setUserId('ruben');
		$dashboard->setName('My Dashboard');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $dashboard;
	}//end dashboard()

	/**
	 * A Notes widget.
	 *
	 * @param string $title   Its title.
	 * @param int    $version Its row version (moves on every save).
	 *
	 * @return WidgetPlacement The widget.
	 */
	private function notes(string $title, int $version = 1): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setId(5);
		$placement->setDashboardId(7);
		$placement->setWidgetId('notes');
		$placement->setCustomTitle($title);
		$placement->setVersion($version);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}//end notes()

	/**
	 * The widgets each read of the dashboard finds, in order.
	 *
	 * @param list<list<WidgetPlacement>> $states The widgets per read.
	 *
	 * @return void
	 */
	private function widgetsRead(array $states): void {
		$this->placementMapper->method('findByDashboardId')->willReturnOnConsecutiveCalls(...$states);
	}//end widgetsRead()

	/**
	 * Renaming a widget stores the state from before it, by the editor, so
	 * restoring it brings the old title back; the session ends.
	 *
	 * @return void
	 */
	public function testAChangeStoresTheStateFromBeforeEditing(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week', version: 2)]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);
		$this->versionMapper->expects($this->once())->method('insert');

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$version = $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $version);
		$this->assertSame(expected: 'ruben', actual: $version->getCreatedBy());
		$this->assertStringContainsString(needle: '"Today"', haystack: (string)$version->getSnapshotJson());
		$this->assertStringNotContainsString(needle: 'This week', haystack: (string)$version->getSnapshotJson());
		$this->assertSame(
			expected: ['launchpad_ver_debounce_d-uuid-1'],
			actual: array_keys($this->store),
			message: 'the session ends; the stored version starts the debounce window'
		);
	}//end testAChangeStoresTheStateFromBeforeEditing()

	/**
	 * Only a row version that moved (a save without a change) is no change.
	 *
	 * @return void
	 */
	public function testNoChangeStoresNoVersion(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'Today', version: 3)]]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testNoChangeStoresNoVersion()

	/**
	 * A presence renewal keeps the state from the first join.
	 *
	 * @return void
	 */
	public function testARenewalKeepsTheFirstState(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$version = $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $version);
		$this->assertStringContainsString(needle: '"Today"', haystack: (string)$version->getSnapshotJson());
	}//end testARenewalKeepsTheFirstState()

	/**
	 * A settings save seconds earlier opened the debounce window; the
	 * session's version is stored all the same.
	 *
	 * @return void
	 */
	public function testTheVersionIsStoredInsideTheDebounceWindow(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);
		$this->store['launchpad_ver_debounce_d-uuid-1'] = 1;
		$this->versionMapper->expects($this->once())->method('insert');

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testTheVersionIsStoredInsideTheDebounceWindow()

	/**
	 * The newest version already holds the state from before editing (a
	 * settings save stored it): no duplicate.
	 *
	 * @return void
	 */
	public function testNoDuplicateOfTheNewestVersion(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$kept = array_values(array: $this->store)[0];

		$newest = new DashboardVersion();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$newest->setSnapshotJson($kept);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([$newest]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->assertNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testNoDuplicateOfTheNewestVersion()

	/**
	 * Sessions are per editor: Anna leaving does not end Ruben's.
	 *
	 * @return void
	 */
	public function testSessionsArePerEditor(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')]]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'anna'));
		$this->assertCount(expectedCount: 1, haystack: $this->store, message: "Ruben's session is still kept");
	}//end testSessionsArePerEditor()

	/**
	 * A leave without a session reads nothing.
	 *
	 * @return void
	 */
	public function testALeaveWithoutASessionStoresNothing(): void {
		$this->placementMapper->expects($this->never())->method('findByDashboardId');

		$this->assertNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testALeaveWithoutASessionStoresNothing()

	/**
	 * A failing version read is swallowed: leaving edit mode still works.
	 *
	 * @return void
	 */
	public function testAFailingReadIsSwallowed(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willThrowException(new RuntimeException('db down'));

		$this->sessions->begin(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->sessions->end(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testAFailingReadIsSwallowed()

	/**
	 * Row ids and timestamps do not make content different.
	 *
	 * @return void
	 */
	public function testSameContentIgnoresIdsAndTimestamps(): void {
		$first  = '{"dashboard":{"name":"A","updatedAt":"1"},"placements":[{"id":1,"customTitle":"X","updatedAt":"1"}]}';
		$second = '{"dashboard":{"updatedAt":"2","name":"A"},"placements":[{"id":9,"customTitle":"X","updatedAt":"2"}]}';
		$third  = '{"dashboard":{"name":"B"},"placements":[{"id":1,"customTitle":"X"}]}';

		$this->assertTrue(condition: EditSessionVersions::sameContent(firstJson: $first, secondJson: $second));
		$this->assertFalse(condition: EditSessionVersions::sameContent(firstJson: $first, secondJson: $third));
	}//end testSameContentIgnoresIdsAndTimestamps()
}//end class
