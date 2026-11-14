<?php

/**
 * RetentionJanitorTest
 *
 * Retention (tasks.md task 26, REQ-SCH-007): expired artefacts are deleted
 * and their paths nulled with the metadata kept, null keeps forever,
 * expired run-rows are deleted with their files, running rows are left.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use DateTimeImmutable;
use OCA\LaunchPad\Service\ScheduledExport\AuditLog;
use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use OCA\LaunchPad\Service\ScheduledExport\RetentionJanitor;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\RetentionJanitor
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-26
 */
class RetentionJanitorTest extends TestCase {

	/**
	 * A run started a number of days before 1 December 2026.
	 *
	 * @param string      $id       The run id.
	 * @param string      $exportId Its export.
	 * @param int         $daysAgo  Its age.
	 * @param string|null $path     Its one artefact's path.
	 *
	 * @return array<string, mixed>
	 */
	private function runRow(string $id, string $exportId, int $daysAgo, ?string $path, string $status = 'success'): array {
		return [
			'id' => $id,
			'exportId' => $exportId,
			'status' => $status,
			'startedAt' => (new DateTimeImmutable('2026-12-01T00:00:00+00:00'))->modify('-' . $daysAgo . ' days')->format(DATE_ATOM),
			'renderArtefacts' => [['format' => 'pdf', 'sha256' => str_repeat('a', 64), 'storagePath' => $path]],
		];
	}//end runRow()

	/**
	 * The sweep across exports with 14-day, forever and default retention.
	 *
	 * @return void
	 */
	public function testSweep(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->method('find')->willReturnCallback(static fn (string $schema, string $id): ?array => match ($id) {
			'short' => ['retention' => ['keepArtefactsForDays' => 14, 'keepAuditForDays' => 30]],
			'forever' => ['retention' => ['keepArtefactsForDays' => null, 'keepAuditForDays' => null]],
			default => null,
		});

		$log = $this->createMock(AuditLog::class);
		$log->method('allRuns')->willReturn([
			$this->runRow(id: 'a', exportId: 'short', daysAgo: 20, path: '/alice/files/a.pdf'),
			$this->runRow(id: 'b', exportId: 'short', daysAgo: 40, path: '/alice/files/b.pdf'),
			$this->runRow(id: 'c', exportId: 'forever', daysAgo: 900, path: '/alice/files/c.pdf'),
			$this->runRow(id: 'd', exportId: 'gone', daysAgo: 20, path: '/alice/files/d.pdf'),
			$this->runRow(id: 'e', exportId: 'short', daysAgo: 20, path: null),
			$this->runRow(id: 'f', exportId: 'short', daysAgo: 99, path: '/x', status: 'running'),
		]);

		$updated = [];
		$log->method('update')->willReturnCallback(static function (array $run) use (&$updated): array {
			$updated[] = $run;
			return $run;
		});
		$log->expects($this->once())->method('delete')->with('b')->willReturn(true);

		$deleted = [];
		$root    = $this->createMock(IRootFolder::class);
		$root->method('get')->willReturnCallback(function (string $path) use (&$deleted): File {
			if ($path === '/alice/files/b.pdf') {
				throw new NotFoundException();
			}

			$file = $this->createMock(File::class);
			$file->method('delete')->willReturnCallback(static function () use (&$deleted, $path): void {
				$deleted[] = $path;
			});
			return $file;
		});

		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-12-01T00:00:00+00:00'));

		$result = (new RetentionJanitor($store, $log, $root, $time))->sweep();

		$this->assertSame(['artefactsPurged' => 1, 'runsDeleted' => 1], $result);
		$this->assertSame(['/alice/files/a.pdf'], $deleted);
		$this->assertSame(['a'], array_column($updated, 'id'));
		$this->assertNull($updated[0]['renderArtefacts'][0]['storagePath']);
		$this->assertSame(str_repeat('a', 64), $updated[0]['renderArtefacts'][0]['sha256']);
	}//end testSweep()
}//end class
