<?php

/**
 * Dashboard snapshot golden test
 *
 * Pins the version rows that captureSnapshot writes, and when it writes
 * none, for explicit and automatic captures, notes, given and derived
 * payloads and the debounce, against a recorded fixture.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\VersionStores;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The snapshot rows, case by case.
 *
 * @covers \OCA\LaunchPad\Service\DashboardVersionService
 * @uses   \OCA\LaunchPad\Service\VersionStores
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardVersion
 */
class DashboardSnapshotGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-snapshot.json';

	/**
	 * Call captureSnapshot with one argument set.
	 *
	 * @param DashboardVersionService $service The service.
	 * @param array<string, mixed>    $args    The case's named arguments.
	 *
	 * @return DashboardVersion|null
	 */
	private static function capture(DashboardVersionService $service, array $args): ?DashboardVersion {
		return $service->captureSnapshot(
			dashboard: $args['dashboard'],
			createdBy: $args['createdBy'],
			options: array_diff_key($args, ['dashboard' => true, 'createdBy' => true])
		);
	}//end capture()

	/**
	 * Every case writes the recorded row, or none.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$cases = [
			'automatic'          => [['snapshotJson' => '{"a":1}', 'createdBy' => 'alice'], false],
			'automatic note'     => [['snapshotJson' => '{"a":1}', 'createdBy' => 'alice', 'note' => 'ignored'], false],
			'automatic debounce' => [['snapshotJson' => '{"a":1}', 'createdBy' => 'alice'], true],
			'explicit'           => [['snapshotJson' => '{"b":2}', 'createdBy' => 'bob', 'note' => 'kept', 'explicit' => true], true],
			'explicit no note'   => [['snapshotJson' => '{"b":2}', 'createdBy' => 'bob', 'note' => '', 'explicit' => true], false],
			'derived payload'    => [['snapshotJson' => null, 'createdBy' => 'carol', 'explicit' => true], false],
			'empty payload'      => [['snapshotJson' => '', 'createdBy' => 'carol'], false],
			'no uuid'            => [['snapshotJson' => '{}', 'createdBy' => 'dave', 'explicit' => true], false, ''],
		];

		$actual = [];
		foreach ($cases as $case => $spec) {
			[$args, $debounced] = $spec;
			$uuid  = $spec[2] ?? 'u-1';
			$rows  = [];
			$versions = $this->createMock(DashboardVersionMapper::class);
			$versions->method('findMaxVersionNumber')->willReturn(4);
			$versions->method('insert')->willReturnCallback(
				static function (DashboardVersion $v) use (&$rows): DashboardVersion {
					$rows[] = [$v->getDashboardUuid(), $v->getVersionNumber(), $v->getSnapshotJson(), $v->getCreatedBy(), $v->getNote(), $v->getCreatedAt() !== null];
					return $v;
				}
			);
			$cache = $this->createMock(ICache::class);
			$cache->method('hasKey')->willReturn($debounced);
			$cacheFactory = $this->createMock(ICacheFactory::class);
			$cacheFactory->method('createDistributed')->willReturn($cache);
			$placements = $this->createMock(WidgetPlacementMapper::class);
			$placements->method('findByDashboardId')->willReturn([]);
			$service = new DashboardVersionService(
				stores: new VersionStores(
					versionMapper: $versions,
					dashboardMapper: $this->createMock(DashboardMapper::class),
					placementMapper: $placements
				),
				groupManager: $this->createMock(IGroupManager::class),
				cacheFactory: $cacheFactory,
				logger: $this->createMock(LoggerInterface::class)
			);

			$dashboard = new Dashboard();
			$dashboard->setId(7);
			$dashboard->setUuid($uuid);
			$dashboard->setName('Board');
			$returned      = self::capture(service: $service, args: ['dashboard' => $dashboard] + $args);
			$actual[$case] = ['rows' => $rows, 'returned' => $returned !== null];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testRowsMatchTheRecordedFixture()
}//end class
