<?php

/**
 * StorageFigures Test
 *
 * REQ-DWMS-010: the platform's figures, read without walking files.
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
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StorageFigures::class)]
class StorageFiguresTest extends TestCase {

	/**
	 * A database answering every query with the next of these row sets.
	 *
	 * @param list<list<array<string,mixed>>> $answers The row sets.
	 */
	private function db(array $answers): IDBConnection {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use (&$answers): IQueryBuilder {
			$result = $this->createMock(IResult::class);
			$result->method('fetchAll')->willReturn(array_shift($answers) ?? []);
			$query = $this->createMock(IQueryBuilder::class);
			foreach (['select', 'selectAlias', 'from', 'innerJoin', 'where', 'andWhere', 'groupBy', 'setFirstResult', 'setMaxResults'] as $method) {
				$query->method($method)->willReturnSelf();
			}

			$query->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
			$func = $this->createMock(IFunctionBuilder::class);
			$func->method('count')->willReturn($this->createMock(IQueryFunction::class));
			$query->method('func')->willReturn($func);
			$query->method('createNamedParameter')->willReturn(':p');
			$query->method('executeQuery')->willReturn($result);
			return $query;
		});
		return $db;
	}

	public function testAHomeStorageNamesItsUserAndNoOtherStorageDoes(): void {
		$this->assertSame('anna', StorageFigures::userOfStorage('home::anna'));
		$this->assertSame('bob', StorageFigures::userOfStorage('object::user:bob'));
		$this->assertNull(StorageFigures::userOfStorage('local::/var/www/data/'));
		$this->assertNull(StorageFigures::userOfStorage('home::'));
	}

	public function testHomeUsageAddsTheFilesFolderPerPersonAndIgnoresUnscannedSizes(): void {
		$figures = new StorageFigures($this->db([[
			['path' => 'files', 'size' => 100, 'storage_id' => 'home::anna'],
			['path' => 'files', 'size' => -1, 'storage_id' => 'home::bob'],
			['path' => 'files', 'size' => 5, 'storage_id' => 'local::/srv/'],
		]]));

		$this->assertSame(['anna' => 100, 'bob' => 0], $figures->homeUsage());
	}

	public function testServicesAreFilesTrashVersionsAndEachAppsAppData(): void {
		$figures = new StorageFigures($this->db([
			[
				['path' => 'files', 'size' => 100, 'storage_id' => 'home::anna'],
				['path' => 'files', 'size' => 50, 'storage_id' => 'home::bob'],
				['path' => 'files_trashbin', 'size' => 30, 'storage_id' => 'object::user:anna'],
				['path' => 'files_versions', 'size' => 20, 'storage_id' => 'home::anna'],
				['path' => 'files', 'size' => 999, 'storage_id' => 'local::/srv/'],
			],
			[
				['path' => 'appdata_oc1/launchpad', 'size' => 7, 'storage_id' => 'local::/srv/'],
				['path' => 'appdata_oc1/preview', 'size' => 70, 'storage_id' => 'local::/srv/'],
			],
		]));

		$this->assertSame(
			['app:launchpad' => 7, 'app:preview' => 70, 'files' => 150, 'trash' => 30, 'versions' => 20],
			$figures->serviceUsage()
		);
	}

	public function testTrashEntriesAndUploadsCarryTheirNamesAndBytes(): void {
		$figures = new StorageFigures($this->db([
			[['path' => 'files_trashbin/files/a.txt.d100', 'name' => 'a.txt.d100', 'size' => 9, 'storage_id' => 'home::anna']],
			[['path' => 'appdata_oc1/launchpad/resources/resource_x.png', 'name' => 'resource_x.png', 'size' => 3, 'mtime' => 50, 'storage_id' => 'local::/srv/']],
		]));

		$this->assertSame([['user' => 'anna', 'name' => 'a.txt.d100', 'bytes' => 9]], $figures->trashEntries());
		$this->assertSame([['name' => 'resource_x.png', 'bytes' => 3, 'mtime' => 50]], $figures->uploads());
	}

	public function testReferencesAreTheResourceNamesAnyLaunchpadRowCarries(): void {
		$answers = array_fill(0, count(StorageFigures::REFERENCE_TABLES), []);
		$answers[0] = [['background' => '/apps/launchpad/resource/resource_6650a1b2c3.123.png']];
		$answers[1] = [['content' => '{"icon":"\/apps\/launchpad\/resource\/resource_abc.svg"}', 'tile_icon' => 'resource_abc.svg']];

		$this->assertSame(
			['resource_6650a1b2c3.123.png' => true, 'resource_abc.svg' => true],
			(new StorageFigures($this->db($answers)))->referencedUploads()
		);
	}

	public function testRegisterCountsAreByTitle(): void {
		$figures = new StorageFigures($this->db([[['title' => 'Zaken', 'objects' => '12'], ['title' => 'Archief', 'objects' => 3]]]));

		$this->assertSame(['Archief' => 3, 'Zaken' => 12], $figures->registerCounts());
	}

	public function testAFailedReadAnswersEmptyAndNeverAGuess(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new RuntimeException('no such table'));
		$figures = new StorageFigures($db);

		$this->assertSame([], $figures->homeUsage());
		$this->assertSame([], $figures->serviceUsage());
		$this->assertSame([], $figures->trashEntries());
		$this->assertSame([], $figures->uploads());
		$this->assertNull($figures->referencedUploads());
		$this->assertNull($figures->registerCounts());
	}
}
