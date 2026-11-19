<?php

/**
 * DashboardDiscussionMirror Test
 *
 * A shared dashboard gets a discussion object carrying its audience; an
 * unshared one gets none; a revoked share narrows the object; a deleted
 * dashboard loses it; without OpenRegister nothing is written or logged.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Discussion
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
 */

declare(strict_types=1);

namespace Unit\Service\Discussion;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\Discussion\DashboardDiscussionMirror;
use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for {@see DashboardDiscussionMirror}.
 */
class DashboardDiscussionMirrorTest extends TestCase {

	/**
	 * The object store.
	 *
	 * @var ExportObjectStore&MockObject
	 */
	private ExportObjectStore&MockObject $store;

	/**
	 * The dashboards.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private DashboardMapper&MockObject $dashboards;

	/**
	 * The shares.
	 *
	 * @var DashboardShareMapper&MockObject
	 */
	private DashboardShareMapper&MockObject $shares;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * The mirror under test.
	 *
	 * @var DashboardDiscussionMirror
	 */
	private DashboardDiscussionMirror $mirror;

	/**
	 * Wire the mirror to mocks, with OpenRegister present.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->store      = $this->createMock(originalClassName: ExportObjectStore::class);
		$this->dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$this->shares     = $this->createMock(originalClassName: DashboardShareMapper::class);
		$this->logger     = $this->createMock(originalClassName: LoggerInterface::class);
		$this->store->method('isAvailable')->willReturn(true);
		$this->mirror = new DashboardDiscussionMirror(
			store: $this->store,
			dashboards: $this->dashboards,
			shares: $this->shares,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * A newly shared dashboard gets an object keyed by its uuid, with its audience.
	 *
	 * @return void
	 */
	public function testASharedDashboardGetsAnObjectWithItsAudience(): void {
		$this->dashboards->method('find')->willReturn($this->dashboard(id: 7, owner: 'alice'));
		$this->shares->method('findByDashboardId')->willReturn([$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob')]);
		$this->store->method('find')->willReturn(null);
		$this->store->expects($this->once())->method('save')->with(
			'dashboard',
			$this->callback(callback: fn (array $data): bool => $data['id'] === 'uuid-7'
				&& $data['slug'] === 'uuid-7'
				&& $data['audienceUsers'] === ['alice', 'bob']
				&& $data['audienceGroups'] === []
				&& $data['audienceEveryone'] === false)
		)->willReturn([]);

		$this->mirror->syncDashboard(dashboardId: 7);
	}//end testASharedDashboardGetsAnObjectWithItsAudience()

	/**
	 * An unshared dashboard gets no object, so no room can be made for it.
	 *
	 * @return void
	 */
	public function testAnUnsharedDashboardGetsNoObject(): void {
		$this->dashboards->method('find')->willReturn($this->dashboard(id: 7, owner: 'alice'));
		$this->shares->method('findByDashboardId')->willReturn([]);
		$this->store->method('find')->willReturn(null);
		$this->store->expects($this->never())->method('save');

		$this->mirror->syncDashboard(dashboardId: 7);
	}//end testAnUnsharedDashboardGetsNoObject()

	/**
	 * A revoked share narrows the existing object to the owner, keeping its id.
	 *
	 * @return void
	 */
	public function testARevokedShareNarrowsTheObjectToTheOwner(): void {
		$this->dashboards->method('findByUserId')->with('alice')->willReturn([$this->dashboard(id: 7, owner: 'alice')]);
		$this->shares->method('findByDashboardId')->willReturn([]);
		$this->store->method('find')->willReturn(['id' => 'legacy-object-id']);
		$this->store->expects($this->once())->method('save')->with(
			'dashboard',
			$this->callback(callback: fn (array $data): bool => $data['id'] === 'legacy-object-id' && $data['audienceUsers'] === ['alice'])
		)->willReturn([]);

		$this->mirror->syncOwnedBy(ownerId: 'alice');
	}//end testARevokedShareNarrowsTheObjectToTheOwner()

	/**
	 * A deleted dashboard loses its object.
	 *
	 * @return void
	 */
	public function testADeletedDashboardLosesItsObject(): void {
		$this->store->method('find')->with('dashboard', 'uuid-7')->willReturn(['id' => 'obj-1']);
		$this->store->expects($this->once())->method('delete')->with('obj-1')->willReturn(true);

		$this->mirror->remove(dashboardUuid: 'uuid-7');
	}//end testADeletedDashboardLosesItsObject()

	/**
	 * Without OpenRegister nothing is read, written or logged.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterNothingHappens(): void {
		$store = $this->createMock(originalClassName: ExportObjectStore::class);
		$store->method('isAvailable')->willReturn(false);
		$store->expects($this->never())->method('find');
		$store->expects($this->never())->method('save');
		$this->dashboards->method('find')->willReturn($this->dashboard(id: 7, owner: 'alice'));
		$this->logger->expects($this->never())->method('warning');
		$mirror = new DashboardDiscussionMirror(store: $store, dashboards: $this->dashboards, shares: $this->shares, logger: $this->logger);

		$mirror->syncDashboard(dashboardId: 7);
		$mirror->remove(dashboardUuid: 'uuid-7');
		$this->assertSame(expected: 0, actual: $mirror->syncAllShared());
	}//end testWithoutOpenRegisterNothingHappens()

	/**
	 * A failed write is logged and does not reach the caller.
	 *
	 * @return void
	 */
	public function testAFailedWriteIsLoggedNotThrown(): void {
		$this->dashboards->method('find')->willReturn($this->dashboard(id: 7, owner: 'alice'));
		$this->shares->method('findByDashboardId')->willReturn([$this->share(type: DashboardShare::SHARE_TYPE_GROUP, with: 'finance')]);
		$this->store->method('find')->willReturn(null);
		$this->store->method('save')->willThrowException(new RuntimeException('schema refused'));
		$this->logger->expects($this->once())->method('warning');

		$this->mirror->syncDashboard(dashboardId: 7);
	}//end testAFailedWriteIsLoggedNotThrown()

	/**
	 * Every shared and every group dashboard is synced once.
	 *
	 * @return void
	 */
	public function testSyncAllSharedVisitsSharedAndGroupDashboardsOnce(): void {
		$this->shares->method('findSharedDashboardIds')->willReturn([7, 8]);
		$this->dashboards->method('findAllGroupShared')->willReturn([$this->dashboard(id: 8, owner: null), $this->dashboard(id: 9, owner: null)]);
		$this->dashboards->expects($this->exactly(count: 3))->method('find')
			->willReturnCallback(fn (int $id): Dashboard => $this->dashboard(id: $id, owner: 'alice'));
		$this->shares->method('findByDashboardId')->willReturn([]);
		$this->store->method('find')->willReturn(null);

		$this->assertSame(expected: 3, actual: $this->mirror->syncAllShared());
	}//end testSyncAllSharedVisitsSharedAndGroupDashboardsOnce()

	/**
	 * A personal dashboard.
	 *
	 * @param int         $id    Its id.
	 * @param string|null $owner Its owner.
	 *
	 * @return Dashboard
	 */
	private function dashboard(int $id, ?string $owner): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid('uuid-'.$id);
		$dashboard->setName('Board '.$id);
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId($owner);
		return $dashboard;
	}//end dashboard()

	/**
	 * A share.
	 *
	 * @param string $type Its type.
	 * @param string $with Its recipient.
	 *
	 * @return DashboardShare
	 */
	private function share(string $type, string $with): DashboardShare {
		$share = new DashboardShare();
		$share->setShareType($type);
		$share->setShareWith($with);
		return $share;
	}//end share()
}//end class
