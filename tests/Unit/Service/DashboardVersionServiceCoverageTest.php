<?php

/**
 * DashboardVersionService Coverage Test
 *
 * Additional unit tests for the dashboard versioning service covering the
 * paths the main test leaves untouched:
 *   - fetchSnapshot happy path and groupfolder refusal (REQ-VERS-004 / -009)
 *   - createExplicitSnapshot refusals (groupfolder, nothing persisted)
 *   - restoreVersion refusal for groupfolder-backed dashboards
 *   - debounce cache soft-fails (factory, hasKey and set failures)
 *   - default snapshot assembly (placements, placement load failure,
 *     unencodable payload)
 *   - snapshot payload application edge cases and failure logging
 *   - owner-or-admin guard denying when the group manager throws
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

use Exception;
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
use OCP\IDBConnection;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Coverage-focused unit tests for the dashboard version service.
 *
 * @covers \OCA\LaunchPad\Service\DashboardVersionService
 * @uses   \OCA\LaunchPad\Service\VersionStores
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardVersion
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 */
class DashboardVersionServiceCoverageTest extends TestCase {

	/**
	 * Version mapper mock.
	 *
	 * @var DashboardVersionMapper&MockObject
	 */
	private $versionMapper;

	/**
	 * Dashboard mapper mock.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private $dashboardMapper;

	/**
	 * Placement mapper mock.
	 *
	 * @var WidgetPlacementMapper&MockObject
	 */
	private $placementMapper;

	/**
	 * Group manager mock.
	 *
	 * @var IGroupManager&MockObject
	 */
	private $groupManager;

	/**
	 * Cache factory mock.
	 *
	 * @var ICacheFactory&MockObject
	 */
	private $cacheFactory;

	/**
	 * Cache mock.
	 *
	 * @var ICache&MockObject
	 */
	private $cache;

	/**
	 * Logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * DB connection mock.
	 *
	 * @var IDBConnection&MockObject
	 */
	private $db;

	/**
	 * Set up fresh mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->versionMapper = $this->createMock(DashboardVersionMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(ICache::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->db = $this->createMock(IDBConnection::class);
	}//end setUp()

	/**
	 * Build the service under test from the current mocks.
	 *
	 * @return DashboardVersionService The service.
	 */
	private function makeService(): DashboardVersionService {
		return new DashboardVersionService(
			stores: new VersionStores(
				versionMapper: $this->versionMapper,
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				db: $this->db
			),
			groupManager: $this->groupManager,
			cacheFactory: $this->cacheFactory,
			logger: $this->logger
		);
	}//end makeService()

	/**
	 * Wire the cache factory to hand back the cache mock.
	 *
	 * @return void
	 */
	private function useCache(): void {
		$this->cacheFactory->method('createDistributed')
			->willReturn($this->cache);
	}//end useCache()

	/**
	 * Build a minimal owner-stamped dashboard fixture.
	 *
	 * @param string $userId The owning user.
	 * @param string $uuid   The UUID to assign.
	 *
	 * @return Dashboard The fixture entity.
	 */
	private function makeDashboard(string $userId='alice', string $uuid='d-uuid-1'): Dashboard {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setUuid($uuid);
		$dashboard->setUserId($userId);
		$dashboard->setName('Test');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $dashboard;
	}//end makeDashboard()

	/**
	 * Build a groupfolder-backed dashboard fixture.
	 *
	 * @return Dashboard The fixture entity.
	 */
	private function makeGroupfolderDashboard(): Dashboard {
		$dashboard = new class extends Dashboard {
			/**
			 * Report the groupfolder backend.
			 *
			 * @return string The backend.
			 */
			public function getContentBackend(): string {
				return 'groupfolder';
			}//end getContentBackend()
		};
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setUuid('d-uuid-1');
		$dashboard->setUserId('alice');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $dashboard;
	}//end makeGroupfolderDashboard()

	/**
	 * Build a stored version row carrying the given snapshot JSON.
	 *
	 * @param int    $number       The version number.
	 * @param string $snapshotJson The snapshot body.
	 *
	 * @return DashboardVersion The version row.
	 */
	private function makeVersion(int $number, string $snapshotJson): DashboardVersion {
		$version = new DashboardVersion();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$version->setDashboardUuid('d-uuid-1');
		$version->setVersionNumber($number);
		$version->setSnapshotJson($snapshotJson);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $version;
	}//end makeVersion()

	/**
	 * Set up a restore of version 2 while version 5 is the latest.
	 *
	 * @param string $snapshotJson The snapshot body of version 2.
	 *
	 * @return DashboardVersion The target version row.
	 */
	private function prepareRestore(string $snapshotJson): DashboardVersion {
		$this->useCache();
		$target = $this->makeVersion(2, $snapshotJson);
		$this->versionMapper->method('findByDashboardAndVersion')
			->with('d-uuid-1', 2)
			->willReturn($target);
		$this->versionMapper->method('findMaxVersionNumber')
			->willReturn(5);
		$this->versionMapper->method('insert')
			->willReturnArgument(0);
		$this->placementMapper->method('findByDashboardId')
			->willReturn([]);
		return $target;
	}//end prepareRestore()

	/**
	 * Build an owner dashboard with a database id.
	 *
	 * @param int $id The dashboard id.
	 *
	 * @return Dashboard The fixture entity.
	 */
	private function makeDashboardWithId(int $id): Dashboard {
		$dashboard = $this->makeDashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId($id);
		return $dashboard;
	}//end makeDashboardWithId()

	/**
	 * fetchSnapshot returns the mapper's row for the owner's dashboard.
	 *
	 * @return void
	 */
	public function testFetchSnapshotReturnsTheRequestedVersion(): void {
		$version = $this->makeVersion(3, '{"a":1}');
		$this->versionMapper->expects($this->once())
			->method('findByDashboardAndVersion')
			->with('d-uuid-1', 3)
			->willReturn($version);

		$result = $this->makeService()->fetchSnapshot(
			dashboard: $this->makeDashboard(),
			versionNumber: 3,
			requestingUser: 'alice'
		);

		$this->assertSame($version, $result);
		$this->assertSame('{"a":1}', $result->getSnapshotJson());
	}//end testFetchSnapshotReturnsTheRequestedVersion()

	/**
	 * fetchSnapshot on a groupfolder-backed dashboard reports not-found
	 * without touching the mapper.
	 *
	 * @return void
	 */
	public function testFetchSnapshotOnGroupfolderDashboardThrowsNotFound(): void {
		$this->versionMapper->expects($this->never())
			->method('findByDashboardAndVersion');

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Version not found');

		$this->makeService()->fetchSnapshot(
			dashboard: $this->makeGroupfolderDashboard(),
			versionNumber: 1,
			requestingUser: 'alice'
		);
	}//end testFetchSnapshotOnGroupfolderDashboardThrowsNotFound()

	/**
	 * fetchSnapshot denies a non-owner when the group manager throws.
	 *
	 * @return void
	 */
	public function testGuardDeniesWhenGroupManagerThrows(): void {
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('mallory')
			->willThrowException(new RuntimeException('ldap down'));
		$this->versionMapper->expects($this->never())
			->method('findByDashboardAndVersion');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN);

		$this->makeService()->fetchSnapshot(
			dashboard: $this->makeDashboard(),
			versionNumber: 1,
			requestingUser: 'mallory'
		);
	}//end testGuardDeniesWhenGroupManagerThrows()

	/**
	 * createExplicitSnapshot refuses a groupfolder-backed dashboard.
	 *
	 * @return void
	 */
	public function testCreateExplicitSnapshotRefusesGroupfolderDashboard(): void {
		$this->versionMapper->expects($this->never())
			->method('insert');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage(DashboardVersionService::ERR_VERSIONING_UNAVAILABLE);

		$this->makeService()->createExplicitSnapshot(
			dashboard: $this->makeGroupfolderDashboard(),
			requestingUser: 'alice',
			note: 'n'
		);
	}//end testCreateExplicitSnapshotRefusesGroupfolderDashboard()

	/**
	 * createExplicitSnapshot throws when nothing was persisted (a
	 * dashboard without a UUID).
	 *
	 * @return void
	 */
	public function testCreateExplicitSnapshotThrowsWhenNothingPersisted(): void {
		$this->versionMapper->expects($this->never())
			->method('insert');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage(DashboardVersionService::ERR_VERSIONING_UNAVAILABLE);

		$this->makeService()->createExplicitSnapshot(
			dashboard: $this->makeDashboard('alice', ''),
			requestingUser: 'alice'
		);
	}//end testCreateExplicitSnapshotThrowsWhenNothingPersisted()

	/**
	 * restoreVersion refuses a groupfolder-backed dashboard before any
	 * mapper lookup.
	 *
	 * @return void
	 */
	public function testRestoreVersionRefusesGroupfolderDashboard(): void {
		$this->versionMapper->expects($this->never())
			->method('findByDashboardAndVersion');
		$this->db->expects($this->never())
			->method('beginTransaction');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage(DashboardVersionService::ERR_VERSIONING_UNAVAILABLE);

		$this->makeService()->restoreVersion(
			dashboard: $this->makeGroupfolderDashboard(),
			versionNumber: 1,
			restoringUser: 'alice'
		);
	}//end testRestoreVersionRefusesGroupfolderDashboard()

	/**
	 * When the cache factory fails, automatic snapshots are still
	 * captured (no debounce) and the failure is logged at debug level.
	 *
	 * @return void
	 */
	public function testSnapshotStillCapturedWhenCacheFactoryFails(): void {
		$this->cacheFactory->expects($this->exactly(2))
			->method('createDistributed')
			->with(DashboardVersionService::CACHE_NAMESPACE)
			->willThrowException(new RuntimeException('no cache'));
		$this->logger->expects($this->exactly(2))
			->method('debug')
			->with('launchpad: cache factory unavailable', $this->arrayHasKey('exception'));
		$this->versionMapper->method('findMaxVersionNumber')
			->with('d-uuid-1')
			->willReturn(0);
		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$result = $this->makeService()->captureSnapshot(
			dashboard: $this->makeDashboard(),
			createdBy: 'alice',
			options: ['snapshotJson' => '{"x":1}']
		);

		$this->assertInstanceOf(DashboardVersion::class, $result);
		$this->assertSame(1, $result->getVersionNumber());
		$this->assertSame('{"x":1}', $result->getSnapshotJson());
	}//end testSnapshotStillCapturedWhenCacheFactoryFails()

	/**
	 * A throwing hasKey() is treated as not debounced, and a throwing
	 * set() is swallowed; both are logged at debug level.
	 *
	 * @return void
	 */
	public function testCacheReadAndWriteFailuresAreSoftFailed(): void {
		$this->useCache();
		$this->cache->expects($this->once())
			->method('hasKey')
			->with('launchpad_ver_debounce_d-uuid-1')
			->willThrowException(new RuntimeException('read'));
		$this->cache->expects($this->once())
			->method('set')
			->with('launchpad_ver_debounce_d-uuid-1', 1, DashboardVersionService::DEBOUNCE_SECONDS)
			->willThrowException(new RuntimeException('write'));

		$messages = [];
		$this->logger->expects($this->exactly(2))
			->method('debug')
			->willReturnCallback(
				function (string $message) use (&$messages): void {
					$messages[] = $message;
				}
			);
		$this->versionMapper->method('findMaxVersionNumber')
			->willReturn(4);
		$this->versionMapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$result = $this->makeService()->captureSnapshot(
			dashboard: $this->makeDashboard(),
			createdBy: 'alice',
			options: ['snapshotJson' => '{}']
		);

		$this->assertInstanceOf(DashboardVersion::class, $result);
		$this->assertSame(5, $result->getVersionNumber());
		$this->assertSame(
			[
				'launchpad: debounce check failed, allowing snapshot',
				'launchpad: failed to set debounce key',
			],
			$messages
		);
	}//end testCacheReadAndWriteFailuresAreSoftFailed()

	/**
	 * The default snapshot embeds the dashboard's live placements.
	 *
	 * @return void
	 */
	public function testDefaultSnapshotEmbedsPlacements(): void {
		$this->useCache();
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setDashboardId(7);
		$placement->setWidgetId('w-clock');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->placementMapper->expects($this->once())
			->method('findByDashboardId')
			->with(7)
			->willReturn([$placement]);
		$this->versionMapper->method('findMaxVersionNumber')
			->willReturn(0);
		$this->versionMapper->method('insert')
			->willReturnArgument(0);

		$result = $this->makeService()->createExplicitSnapshot(
			dashboard: $this->makeDashboardWithId(7),
			requestingUser: 'alice'
		);

		$decoded = json_decode((string)$result->getSnapshotJson(), true);
		$this->assertSame('Test', $decoded['dashboard']['name']);
		$this->assertCount(1, $decoded['placements']);
		$this->assertSame('w-clock', $decoded['placements'][0]['widgetId']);
		$this->assertSame(7, $decoded['placements'][0]['dashboardId']);
	}//end testDefaultSnapshotEmbedsPlacements()

	/**
	 * A placement-load failure is logged and the snapshot is still
	 * written with an empty placement list.
	 *
	 * @return void
	 */
	public function testDefaultSnapshotSurvivesPlacementLoadFailure(): void {
		$this->useCache();
		$this->placementMapper->expects($this->once())
			->method('findByDashboardId')
			->with(7)
			->willThrowException(new RuntimeException('db'));
		$this->logger->expects($this->once())
			->method('debug')
			->with('launchpad: failed to load placements for snapshot', $this->arrayHasKey('exception'));
		$this->versionMapper->method('findMaxVersionNumber')
			->willReturn(0);
		$this->versionMapper->method('insert')
			->willReturnArgument(0);

		$result = $this->makeService()->createExplicitSnapshot(
			dashboard: $this->makeDashboardWithId(7),
			requestingUser: 'alice'
		);

		$decoded = json_decode((string)$result->getSnapshotJson(), true);
		$this->assertSame([], $decoded['placements']);
		$this->assertSame(7, $decoded['dashboard']['id']);
	}//end testDefaultSnapshotSurvivesPlacementLoadFailure()

	/**
	 * A dashboard whose state cannot be JSON-encoded yields the '{}'
	 * fallback body.
	 *
	 * @return void
	 */
	public function testDefaultSnapshotFallsBackToEmptyObjectOnEncodeFailure(): void {
		$this->useCache();
		$dashboard = $this->makeDashboard();
		// Invalid UTF-8 makes json_encode() return false.
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setName("\xB1\x31");
		$this->versionMapper->method('findMaxVersionNumber')
			->willReturn(0);
		$this->versionMapper->method('insert')
			->willReturnArgument(0);

		$result = $this->makeService()->createExplicitSnapshot(
			dashboard: $dashboard,
			requestingUser: 'alice'
		);

		$this->assertSame('{}', $result->getSnapshotJson());
	}//end testDefaultSnapshotFallsBackToEmptyObjectOnEncodeFailure()

	/**
	 * Restoring a snapshot whose placements entry is not a list wipes
	 * the current placements and inserts none.
	 *
	 * @return void
	 */
	public function testRestoreWithNonListPlacementsWipesAndInsertsNothing(): void {
		$target = $this->prepareRestore('{"placements":"garbage"}');
		$this->placementMapper->expects($this->once())
			->method('deleteByDashboardId')
			->with(7);
		$this->placementMapper->expects($this->never())
			->method('insert');
		$this->dashboardMapper->expects($this->once())
			->method('update');
		$this->db->expects($this->once())->method('commit');

		$result = $this->makeService()->restoreVersion(
			dashboard: $this->makeDashboardWithId(7),
			versionNumber: 2,
			restoringUser: 'alice'
		);

		$this->assertSame('{"placements":"garbage"}', $result['snapshot']);
		$this->assertSame($target, $result['version']);
	}//end testRestoreWithNonListPlacementsWipesAndInsertsNothing()

	/**
	 * Non-array entries in the placements list are skipped; valid ones
	 * are inserted onto the restored dashboard.
	 *
	 * @return void
	 */
	public function testRestoreSkipsNonArrayPlacementEntries(): void {
		$this->prepareRestore('{"placements":[5,"x",{"widgetId":"w-notes","dashboardId":99}]}');
		$this->placementMapper->expects($this->once())
			->method('deleteByDashboardId')
			->with(7);
		$inserted = [];
		$this->placementMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(
				function (WidgetPlacement $entity) use (&$inserted): WidgetPlacement {
					$inserted[] = $entity;
					return $entity;
				}
			);

		$this->makeService()->restoreVersion(
			dashboard: $this->makeDashboardWithId(7),
			versionNumber: 2,
			restoringUser: 'alice'
		);

		$this->assertCount(1, $inserted);
		$this->assertSame('w-notes', $inserted[0]->getWidgetId());
		$this->assertSame(7, $inserted[0]->getDashboardId());
	}//end testRestoreSkipsNonArrayPlacementEntries()

	/**
	 * A mapper failure while applying the payload is logged with the
	 * dashboard id, re-thrown, and the transaction rolled back.
	 *
	 * @return void
	 */
	public function testRestorePayloadFailureIsLoggedAndRethrown(): void {
		$this->prepareRestore('{"placements":[]}');
		$failure = new RuntimeException('delete failed');
		$this->placementMapper->expects($this->once())
			->method('deleteByDashboardId')
			->with(7)
			->willThrowException($failure);
		$this->dashboardMapper->expects($this->never())
			->method('update');
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$messages = [];
		$this->logger->expects($this->exactly(2))
			->method('error')
			->willReturnCallback(
				function (string $message, array $context) use (&$messages, $failure): void {
					$messages[] = $message;
					$this->assertSame($failure, $context['exception']);
				}
			);

		try {
			$this->makeService()->restoreVersion(
				dashboard: $this->makeDashboardWithId(7),
				versionNumber: 2,
				restoringUser: 'alice'
			);
			$this->fail('Expected the payload failure to propagate');
		} catch (RuntimeException $e) {
			$this->assertSame($failure, $e);
		}

		$this->assertSame(
			[
				'launchpad: applySnapshotPayload failed for dashboard 7',
				'launchpad: restoreVersion failed, transaction rolled back',
			],
			$messages
		);
	}//end testRestorePayloadFailureIsLoggedAndRethrown()
}//end class
