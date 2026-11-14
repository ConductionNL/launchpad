<?php

/**
 * AuditLogTest
 *
 * The run-rows of scheduled exports (tasks.md task 7, REQ-SCH-008): a
 * `running` row per fire with its trigger and correlation id, closed with
 * its artefacts and deliveries and the rolled-up status, skipped fires, and
 * the per-export history.
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
use OCA\LaunchPad\Service\RetryHandler;
use OCA\LaunchPad\Service\ScheduledExport\AuditLog;
use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\AuditLog
 * @covers \OCA\LaunchPad\Service\ScheduledExport\FireTrigger
 * @uses   \OCA\LaunchPad\Service\RetryHandler
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
 */
class AuditLogTest extends TestCase {

	private ExportObjectStore&MockObject $store;

	private AuditLog $log;

	/**
	 * A fixed clock and a fixed random source; saves echo their data.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = $this->createMock(ExportObjectStore::class);
		$time        = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-11-16T09:00:00+01:00'));
		$random = $this->createMock(ISecureRandom::class);
		$random->method('generate')->with(32, '0123456789abcdef')->willReturn(str_repeat('a', 32));

		$this->log = new AuditLog($this->store, new RetryHandler(), $time, $random);
	}//end setUp()

	/**
	 * A manual fire opens a running row naming the user, with a v4 correlation id.
	 *
	 * @return void
	 */
	public function testStartOpensARunningRow(): void {
		$this->store->expects($this->once())->method('save')->with(ExportObjectStore::SCHEMA_RUN, [
			'exportId' => 'e1',
			'startedAt' => '2026-11-16T09:00:00+01:00',
			'triggeredBy' => 'manual',
			'triggeredByUser' => 'alice',
			'correlationId' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
			'status' => 'running',
		], true)->willReturnArgument(1);

		$this->log->start(export: ['id' => 'e1'], trigger: FireTrigger::manual(userId: 'alice'));
	}//end testStartOpensARunningRow()

	/**
	 * A row closes with the roll-up: all delivered is success and finished; a pending retry keeps it running.
	 *
	 * @return void
	 */
	public function testRecordRollsUpTheDeliveries(): void {
		$this->store->method('save')->willReturnArgument(1);
		$artefact = ['format' => 'pdf', 'sizeBytes' => 3, 'sha256' => str_repeat('0', 64), 'storagePath' => '/x.pdf'];

		$done = $this->log->record(run: ['id' => 'r1'], artefacts: [$artefact], deliveries: [['status' => 'success'], ['status' => 'success']]);
		$this->assertSame('success', $done['status']);
		$this->assertSame('2026-11-16T09:00:00+01:00', $done['finishedAt']);
		$this->assertSame([$artefact], $done['renderArtefacts']);

		$partial = $this->log->record(run: ['id' => 'r1'], artefacts: [], deliveries: [['status' => 'success'], ['status' => 'failed-permanent']]);
		$this->assertSame('partial', $partial['status']);

		$running = $this->log->record(run: ['id' => 'r1'], artefacts: [], deliveries: [['status' => 'retrying']]);
		$this->assertSame('running', $running['status']);
		$this->assertArrayNotHasKey('finishedAt', $running);
	}//end testRecordRollsUpTheDeliveries()

	/**
	 * A skipped scheduled fire is a finished row with its reason and nothing rendered.
	 *
	 * @return void
	 */
	public function testSkippedRecordsTheReason(): void {
		$this->store->method('save')->willReturnArgument(1);
		$row = $this->log->skipped(export: ['id' => 'e1'], trigger: FireTrigger::schedule(), status: 'partial', reason: 'The previous run is still running.');

		$this->assertSame('partial', $row['status']);
		$this->assertSame('schedule', $row['triggeredBy']);
		$this->assertNull($row['triggeredByUser']);
		$this->assertSame('The previous run is still running.', $row['skipReason']);
		$this->assertSame([], $row['deliveries']);
		$this->assertTrue(FireTrigger::schedule()->isScheduled());
		$this->assertFalse(FireTrigger::manual(userId: 'a')->isScheduled());
	}//end testSkippedRecordsTheReason()

	/**
	 * The history holds only that export's rows, newest first; a row of another export is not found.
	 *
	 * @return void
	 */
	public function testRunsOfAndRunOf(): void {
		$this->store->method('findAll')->with(ExportObjectStore::SCHEMA_RUN, ['exportId' => 'e1'], true)->willReturn([
			['id' => 'old', 'exportId' => 'e1', 'startedAt' => '2026-11-09T09:00:00+01:00'],
			['id' => 'other', 'exportId' => 'e2', 'startedAt' => '2026-11-15T09:00:00+01:00'],
			['id' => 'new', 'exportId' => 'e1', 'startedAt' => '2026-11-16T09:00:00+01:00'],
		]);
		$this->store->method('find')->willReturn(['id' => 'r9', 'exportId' => 'e2']);

		$this->assertSame(['new', 'old'], array_column($this->log->runsOf(exportId: 'e1'), 'id'));
		$this->assertNull($this->log->runOf(exportId: 'e1', runId: 'r9'));
		$this->assertSame('r9', $this->log->runOf(exportId: 'e2', runId: 'r9')['id']);
	}//end testRunsOfAndRunOf()

	/**
	 * update and delete go to the store as system calls.
	 *
	 * @return void
	 */
	public function testUpdateAndDelete(): void {
		$this->store->expects($this->once())->method('save')->with(ExportObjectStore::SCHEMA_RUN, ['id' => 'r1'], true)->willReturnArgument(1);
		$this->store->expects($this->once())->method('delete')->with('r1', true)->willReturn(true);
		$this->store->expects($this->once())->method('findAll')->with(ExportObjectStore::SCHEMA_RUN, [], true)->willReturn([]);

		$this->log->update(run: ['id' => 'r1']);
		$this->assertTrue($this->log->delete(runId: 'r1'));
		$this->assertSame([], $this->log->allRuns());
	}//end testUpdateAndDelete()
}//end class
