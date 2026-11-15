<?php

/**
 * StorageReportService Test
 *
 * REQ-DWMS-010: the administrator guard, the removable total, the series and the CSV.
 *
 * @category  Test
 * @package   Unit\Service\StorageReport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\StorageReport;

use OCA\LaunchPad\Service\StorageReport\StorageFigures;
use OCA\LaunchPad\Service\StorageReport\StorageReportRefusedException;
use OCA\LaunchPad\Service\StorageReport\StorageReportService;
use OCA\LaunchPad\Service\StorageReport\StorageSeries;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(StorageReportService::class)]
#[UsesClass(StorageReportRefusedException::class)]
class StorageReportServiceTest extends TestCase {

	// 2026-11-15T12:00:00Z
	private const NOW = 1_794_744_000;

	private StorageFigures&MockObject $figures;
	private StorageSeries&MockObject $series;
	private IGroupManager&MockObject $groups;

	protected function setUp(): void {
		$this->figures = $this->createMock(StorageFigures::class);
		$this->series  = $this->createMock(StorageSeries::class);
		$this->groups  = $this->createMock(IGroupManager::class);
		$this->groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
	}

	private function service(string $retention='auto'): StorageReportService {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('trashbin_retention_obligation', 'auto')->willReturn($retention);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(self::NOW);

		return new StorageReportService($this->figures, $this->series, $this->groups, $config, $time);
	}

	private function group(string $gid, array $uids): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($gid);
		$group->method('getUsers')->willReturn(array_map(function (string $uid): IUser {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		}, $uids));
		return $group;
	}

	public function testANonAdministratorIsRefusedWith403BeforeAnythingIsRead(): void {
		$this->figures->expects($this->never())->method('homeUsage');
		$this->figures->expects($this->never())->method('serviceUsage');
		$this->series->expects($this->never())->method('all');

		foreach (['anna', ''] as $reader) {
			try {
				$this->service()->report($reader);
				$this->fail('The report was built for ' . $reader);
			} catch (StorageReportRefusedException $refused) {
				$this->assertSame(403, $refused->statusCode);
				$this->assertSame('Only an administrator can read the storage report.', $refused->getMessage());
			}
		}
	}

	public function testEachWorkspaceCarriesItsMembersFilesAndTheTotalComesFromTheServices(): void {
		$this->figures->method('homeUsage')->willReturn(['anna' => 100, 'bob' => 50, 'carol' => 7]);
		$this->figures->method('serviceUsage')->willReturn(['app:launchpad' => 3, 'files' => 157, 'trash' => 40]);
		$this->groups->method('search')->with('')->willReturn([
			$this->group('team-b', ['bob', 'anna']),
			$this->group('team-a', ['anna']),
			$this->group('empty', ['nobody']),
		]);

		$this->assertSame(
			['total' => 200, 'workspaces' => ['empty' => 0, 'team-a' => 100, 'team-b' => 150], 'services' => ['app:launchpad' => 3, 'files' => 157, 'trash' => 40]],
			$this->service()->snapshot()
		);
	}

	public function testTheReportCarriesTwelveMonthsAndNamesTheRemovableTotalApart(): void {
		$this->figures->method('homeUsage')->willReturn(['anna' => 100]);
		$this->figures->method('serviceUsage')->willReturn(['files' => 100, 'trash' => 30]);
		$this->groups->method('search')->willReturn([$this->group('team', ['anna'])]);
		$this->series->method('all')->willReturn([
			'2025-11-30' => ['total' => 1, 'workspaces' => ['team' => 1], 'services' => ['files' => 1]],
			'2025-12-01' => ['total' => 10, 'workspaces' => ['team' => 5], 'services' => ['files' => 5]],
			'2025-12-31' => ['total' => 20, 'workspaces' => ['team' => 6], 'services' => ['files' => 6]],
			'2026-03-15' => ['total' => 50, 'workspaces' => [], 'services' => ['files' => 40]],
		]);
		$this->figures->method('trashEntries')->willReturn([
			['user' => 'anna', 'name' => 'old.txt.d' . (self::NOW - (31 * 86400)), 'bytes' => 25],
			['user' => 'anna', 'name' => 'new.txt.d' . (self::NOW - 86400), 'bytes' => 5],
		]);
		$this->figures->method('uploads')->willReturn([['name' => 'resource_a.png', 'bytes' => 8, 'mtime' => 0]]);
		$this->figures->method('referencedUploads')->willReturn([]);
		$this->figures->method('registerCounts')->willReturn(['Cases' => 4]);

		$report = $this->service()->report('admin');

		$this->assertSame('2026-11-15', $report['date']);
		$this->assertSame(130, $report['total']);
		$this->assertCount(12, $report['series']);
		$this->assertSame('2025-12', array_key_first($report['series']));
		$this->assertSame(20, $report['series']['2025-12']);
		$this->assertNull($report['series']['2026-01']);
		$this->assertSame(130, $report['series']['2026-11']);
		$this->assertSame('team', $report['workspaces'][0]['id']);
		$this->assertSame(100, $report['workspaces'][0]['bytes']);
		$this->assertSame(6, $report['workspaces'][0]['series']['2025-12']);
		$this->assertNull($report['workspaces'][0]['series']['2026-03']);
		$this->assertSame(['name' => 'trash', 'bytes' => 30], array_slice($report['services'][1], 0, 2));
		$this->assertSame(['Cases' => 4], $report['registers']);
		$this->assertSame(33, $report['removable']['total']);
		$this->assertSame(25, $report['removable']['pastRetention']['bytes']);
		$this->assertSame(8, $report['removable']['orphanedUploads']['bytes']);
	}

	public function testTrashPastItsRetentionIsRemovableAndNewerTrashIsNot(): void {
		$trash = [
			['user' => 'a', 'name' => 'x.d' . (self::NOW - (10 * 86400) - 1), 'bytes' => 100],
			['user' => 'a', 'name' => 'y.d' . (self::NOW - (10 * 86400) + 1), 'bytes' => 200],
			['user' => 'b', 'name' => 'no-timestamp', 'bytes' => 400],
		];

		$removable = StorageReportService::removable($trash, 10, [], [], self::NOW);

		$this->assertSame(['count' => 1, 'bytes' => 100, 'retentionDays' => 10], $removable['pastRetention']);
		$this->assertSame(100, $removable['total']);
		$this->assertSame(0, StorageReportService::removable($trash, null, [], [], self::NOW)['total']);
	}

	public function testAnUploadNoRowNamesIsOrphanedOnceItIsADayOld(): void {
		$uploads = [
			['name' => 'resource_used.png', 'bytes' => 1, 'mtime' => 0],
			['name' => 'resource_orphan.png', 'bytes' => 10, 'mtime' => 0],
			['name' => 'resource_fresh.png', 'bytes' => 100, 'mtime' => self::NOW - 60],
		];

		$removable = StorageReportService::removable([], 30, $uploads, ['resource_used.png' => true], self::NOW);

		$this->assertSame(['count' => 1, 'bytes' => 10, 'names' => ['resource_orphan.png'], 'known' => true], $removable['orphanedUploads']);
		$this->assertSame(10, $removable['total']);
	}

	public function testWhenTheReferencesCannotBeReadNoUploadCountsAsOrphaned(): void {
		$removable = StorageReportService::removable([], 30, [['name' => 'resource_x.png', 'bytes' => 10, 'mtime' => 0]], null, self::NOW);

		$this->assertSame(['count' => 0, 'bytes' => 0, 'names' => [], 'known' => false], $removable['orphanedUploads']);
		$this->assertSame(0, $removable['total']);
	}

	public function testTheEmptyCaseRemovesNothing(): void {
		$this->assertSame(
			[
				'total'           => 0,
				'pastRetention'   => ['count' => 0, 'bytes' => 0, 'retentionDays' => 30],
				'orphanedUploads' => ['count' => 0, 'bytes' => 0, 'names' => [], 'known' => true],
			],
			StorageReportService::removable([], 30, [], [], self::NOW)
		);
	}

	public function testTheRetentionIsReadAsNextcloudReadsIt(): void {
		$this->assertSame(30, StorageReportService::retentionDays('auto'));
		$this->assertSame(30, StorageReportService::retentionDays(''));
		$this->assertSame(14, StorageReportService::retentionDays('auto, 14'));
		$this->assertSame(7, StorageReportService::retentionDays('7, auto'));
		$this->assertSame(60, StorageReportService::retentionDays('30, 60'));
		$this->assertSame(5, StorageReportService::retentionDays('5'));
		$this->assertNull(StorageReportService::retentionDays('disabled'));
	}

	public function testTodaysSnapshotIsKeptUnderTodaysDate(): void {
		$this->figures->method('homeUsage')->willReturn([]);
		$this->figures->method('serviceUsage')->willReturn(['files' => 9]);
		$this->groups->method('search')->willReturn([]);
		$this->series->expects($this->once())->method('record')
			->with('2026-11-15', ['total' => 9, 'workspaces' => [], 'services' => ['files' => 9]])
			->willReturn([]);

		$this->assertSame(9, $this->service()->recordToday()['total']);
	}

	public function testTheCsvCarriesTheSameNumbersAsTheReport(): void {
		$report = [
			'date'       => '2026-11-15',
			'total'      => 130,
			'series'     => ['2026-10' => null, '2026-11' => 130],
			'workspaces' => [['id' => 'team', 'bytes' => 100, 'series' => ['2026-10' => 90, '2026-11' => 100]]],
			'services'   => [['name' => 'files', 'bytes' => 130, 'series' => ['2026-10' => null, '2026-11' => 130]]],
			'removable'  => ['total' => 33, 'pastRetention' => ['bytes' => 25], 'orphanedUploads' => ['bytes' => 8]],
		];

		$this->assertSame(
			"kind,name,bytes,2026-10,2026-11\n"
			. "total,,130,,130\n"
			. "workspace,team,100,90,100\n"
			. "service,files,130,,130\n"
			. "removable,\"past retention\",25\n"
			. "removable,\"orphaned uploads\",8\n"
			. "removable,total,33\n",
			StorageReportService::csv($report)
		);
	}
}
