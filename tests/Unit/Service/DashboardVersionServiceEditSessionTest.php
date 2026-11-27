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

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\VersionStores;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DashboardVersionServiceEditSessionTest extends TestCase {

	private DashboardVersionMapper&MockObject $versionMapper;

	private DashboardMapper&MockObject $dashboardMapper;

	private WidgetPlacementMapper&MockObject $placementMapper;

	/** @var array<string, mixed> A working in-memory cache. */
	private array $store = [];

	private DashboardVersionService $service;

	protected function setUp(): void {
		$this->versionMapper   = $this->createMock(DashboardVersionMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);

		$cache = $this->createMock(ICache::class);
		$cache->method('hasKey')->willReturnCallback(fn (string $key): bool => array_key_exists($key, $this->store));
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => $this->store[$key] ?? null);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value): bool {
			$this->store[$key] = $value;
			return true;
		});
		$cache->method('remove')->willReturnCallback(function (string $key): bool {
			unset($this->store[$key]);
			return true;
		});
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$cacheFactory->method('isAvailable')->willReturn(true);

		$this->dashboardMapper->method('findByUuid')->with(uuid: 'd-uuid-1')->willReturn($this->dashboard());
		$this->versionMapper->method('findMaxVersionNumber')->willReturn(0);
		$this->versionMapper->method('insert')->willReturnArgument(0);

		$this->service = new DashboardVersionService(
			stores: new VersionStores(
				versionMapper: $this->versionMapper,
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper
			),
			groupManager: $this->createMock(IGroupManager::class),
			cacheFactory: $cacheFactory,
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end setUp()

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

	private function notes(string $title, int $id = 5): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setId($id);
		$placement->setDashboardId(7);
		$placement->setWidgetId('notes');
		$placement->setCustomTitle($title);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}//end notes()

	/**
	 * @param list<list<WidgetPlacement>> $states The widgets per read, in order.
	 */
	private function widgetsRead(array $states): void {
		$this->placementMapper->method('findByDashboardId')->willReturnOnConsecutiveCalls(...$states);
	}//end widgetsRead()

	/**
	 * Renaming a widget during the session stores the state from before it,
	 * by the editor, so restoring it brings the old title back.
	 */
	public function testAChangedDashboardStoresTheStateFromBeforeEditing(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);
		$this->versionMapper->expects($this->once())->method('insert');

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$version = $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $version);
		$this->assertSame(expected: 'ruben', actual: $version->getCreatedBy());
		$this->assertSame(expected: 1, actual: $version->getVersionNumber());
		$this->assertStringContainsString(needle: '"Today"', haystack: (string)$version->getSnapshotJson());
		$this->assertStringNotContainsString(needle: 'This week', haystack: (string)$version->getSnapshotJson());
		$this->assertSame(
			expected: ['launchpad_ver_debounce_d-uuid-1'],
			actual: array_keys($this->store),
			message: 'the session ends; the stored version starts the debounce window'
		);
	}//end testAChangedDashboardStoresTheStateFromBeforeEditing()

	public function testNoChangeStoresNoVersion(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'Today', id: 5)]]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testNoChangeStoresNoVersion()

	/**
	 * A presence renewal keeps the state from the first join, not the one
	 * after the change.
	 */
	public function testARenewalKeepsTheFirstState(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$version = $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $version);
		$this->assertStringContainsString(needle: '"Today"', haystack: (string)$version->getSnapshotJson());
	}//end testARenewalKeepsTheFirstState()

	/**
	 * Outside the debounce: a settings save a few seconds earlier does not
	 * swallow the session's version.
	 */
	public function testTheVersionIsStoredInsideTheDebounceWindow(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([]);
		$this->store['launchpad_ver_debounce_d-uuid-1'] = 1;
		$this->versionMapper->expects($this->once())->method('insert');

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNotNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testTheVersionIsStoredInsideTheDebounceWindow()

	/**
	 * The newest version already holds the state from before editing (a
	 * settings save stored it): no duplicate.
	 */
	public function testNoDuplicateOfTheNewestVersion(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');
		$kept = array_values(array_filter($this->store, 'is_string'))[0];

		$newest = new DashboardVersion();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$newest->setSnapshotJson($kept);
		$this->versionMapper->method('findLatestByDashboard')->willReturn([$newest]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->assertNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testNoDuplicateOfTheNewestVersion()

	/**
	 * Sessions are per editor: Anna leaving does not end Ruben's.
	 */
	public function testSessionsArePerEditor(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')]]);
		$this->versionMapper->expects($this->never())->method('insert');

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'anna'));
		$this->assertCount(expectedCount: 1, haystack: $this->store, message: "Ruben's session is still kept");
	}//end testSessionsArePerEditor()

	public function testALeaveWithoutASessionStoresNothing(): void {
		$this->dashboardMapper->expects($this->never())->method('findByUuid');

		$this->assertNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testALeaveWithoutASessionStoresNothing()

	public function testAFailingWriteIsSwallowed(): void {
		$this->widgetsRead(states: [[$this->notes(title: 'Today')], [$this->notes(title: 'This week')]]);
		$this->versionMapper->method('findLatestByDashboard')->willThrowException(new RuntimeException('db down'));

		$this->service->beginEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben');

		$this->assertNull(actual: $this->service->endEditSession(dashboardUuid: 'd-uuid-1', userId: 'ruben'));
	}//end testAFailingWriteIsSwallowed()
}//end class
