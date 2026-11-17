<?php

/**
 * ScheduledExportAuditLogTest
 *
 * The run-rows of scheduled exports (Task 7, REQ-SCH-008): opened per fire
 * with who triggered it, artefacts and deliveries written at once, a
 * delivery entry replaced per recipient and channel, closed with a status,
 * and read newest first for the history.
 *
 * @category  Test
 * @package   Unit\Service
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

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExportAuditLog
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
 */
class ScheduledExportAuditLogTest extends TestCase {

	/**
	 * The OpenRegister adapter.
	 *
	 * @var ScheduledExportStore&MockObject
	 */
	private ScheduledExportStore&MockObject $store;

	/**
	 * The audit log under test.
	 *
	 * @var ScheduledExportAuditLog
	 */
	private ScheduledExportAuditLog $log;

	/**
	 * Build the log over a mocked store that answers what it was given.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = $this->createMock(ScheduledExportStore::class);
		$this->log   = new ScheduledExportAuditLog($this->store);
	}//end setUp()

	/**
	 * A manual fire opens a running row with the user; written without access rules.
	 *
	 * @return void
	 */
	public function testAManualFireRecordsWhoTriggeredIt(): void {
		$this->store->expects($this->once())->method('save')
			->with('scheduled_export_run', $this->anything(), null, false)
			->willReturnCallback(static fn (string $schema, array $data): array => $data + ['id' => 'run-1']);

		$run = $this->log->start(
			exportId: 'e-1',
			triggeredBy: 'manual',
			user: 'anna',
			correlationId: 'corr-1',
			now: new DateTimeImmutable('2026-11-16T09:00:00+01:00')
		);

		$this->assertSame(
			[
				'exportId' => 'e-1',
				'startedAt' => '2026-11-16T09:00:00+01:00',
				'status' => 'running',
				'triggeredBy' => 'manual',
				'renderArtefacts' => [],
				'deliveries' => [],
				'correlationId' => 'corr-1',
				'triggeredByUser' => 'anna',
				'id' => 'run-1',
			],
			$run
		);
	}//end testAManualFireRecordsWhoTriggeredIt()

	/**
	 * A scheduled fire names no user even when one is passed.
	 *
	 * @return void
	 */
	public function testAScheduledFireNamesNoUser(): void {
		$this->store->method('save')->willReturnArgument(1);

		$run = $this->log->start(exportId: 'e-1', triggeredBy: 'schedule', user: 'anna', correlationId: 'c', now: new DateTimeImmutable());

		$this->assertArrayNotHasKey('triggeredByUser', $run);
	}//end testAScheduledFireNamesNoUser()

	/**
	 * A delivery of the same recipient and channel replaces its entry; another one is appended; each save is the run's uuid.
	 *
	 * @return void
	 */
	public function testADeliveryReplacesItsOwnEntryAndAppendsOthers(): void {
		$this->store->expects($this->exactly(2))->method('save')
			->with('scheduled_export_run', $this->anything(), 'run-1', false)
			->willReturnArgument(1);

		$run = [
			'id' => 'run-1',
			'deliveries' => [['recipientId' => 'r-0', 'channel' => 'email', 'status' => 'retrying', 'attemptCount' => 1]],
		];
		$run = $this->log->recordDelivery(run: $run, delivery: ['recipientId' => 'r-0', 'channel' => 'email', 'status' => 'success', 'attemptCount' => 2]);
		$run = $this->log->recordDelivery(run: $run, delivery: ['recipientId' => 'r-1', 'channel' => 'webhook', 'status' => 'retrying', 'attemptCount' => 1]);

		$this->assertSame(
			[
				['recipientId' => 'r-0', 'channel' => 'email', 'status' => 'success', 'attemptCount' => 2],
				['recipientId' => 'r-1', 'channel' => 'webhook', 'status' => 'retrying', 'attemptCount' => 1],
			],
			$run['deliveries']
		);
	}//end testADeliveryReplacesItsOwnEntryAndAppendsOthers()

	/**
	 * Artefacts are appended and the run is closed with its status, end time and reason.
	 *
	 * @return void
	 */
	public function testArtefactsAndTheCloseAreWritten(): void {
		$this->store->method('save')->willReturnArgument(1);

		$run = $this->log->addArtefacts(run: ['id' => 'run-1', 'renderArtefacts' => [['format' => 'pdf']]], artefacts: [['format' => 'csv']]);
		$this->assertSame([['format' => 'pdf'], ['format' => 'csv']], $run['renderArtefacts']);

		$run = $this->log->finish(run: $run, status: 'partial', now: new DateTimeImmutable('2026-11-16T09:01:00+01:00'), reason: 'skipped');
		$this->assertSame(['partial', '2026-11-16T09:01:00+01:00', 'skipped'], [$run['status'], $run['finishedAt'], $run['reason']]);
	}//end testArtefactsAndTheCloseAreWritten()

	/**
	 * A run still waiting for retries stays open: no end time.
	 *
	 * @return void
	 */
	public function testARunningCloseHasNoEndTime(): void {
		$this->store->method('save')->willReturnArgument(1);

		$run = $this->log->finish(run: ['id' => 'run-1'], status: 'running', now: new DateTimeImmutable());

		$this->assertArrayNotHasKey('finishedAt', $run);
	}//end testARunningCloseHasNoEndTime()

	/**
	 * The history lists the export's runs newest first, paged, under the user's access rules.
	 *
	 * @return void
	 */
	public function testTheHistoryIsNewestFirstAndPaged(): void {
		$this->store->expects($this->once())->method('list')
			->with('scheduled_export_run', ['exportId' => 'e-1'])
			->willReturn([
				['id' => 'a', 'startedAt' => '2026-11-02T09:00:00+01:00'],
				['id' => 'c', 'startedAt' => '2026-11-16T09:00:00+01:00'],
				['id' => 'b', 'startedAt' => '2026-11-09T09:00:00+01:00'],
			]);

		$this->assertSame(['b', 'a'], array_column($this->log->runsOf(exportId: 'e-1', limit: 2, offset: 1), 'id'));
	}//end testTheHistoryIsNewestFirstAndPaged()

	/**
	 * A run of another export is not answered for this one.
	 *
	 * @return void
	 */
	public function testARunOfAnotherExportIsNotAnswered(): void {
		$this->store->method('find')->willReturn(['id' => 'run-9', 'exportId' => 'e-2']);

		$this->assertNull($this->log->runOf(exportId: 'e-1', runId: 'run-9'));
		$this->assertSame('run-9', $this->log->runOf(exportId: 'e-2', runId: 'run-9')['id']);
	}//end testARunOfAnotherExportIsNotAnswered()

	/**
	 * Only a scheduled run that is still running counts as an overlap; a manual one does not block the schedule.
	 *
	 * @return void
	 */
	public function testOnlyARunningScheduledFireIsAnOverlap(): void {
		$this->store->method('list')->willReturnOnConsecutiveCalls(
			[['status' => 'running', 'triggeredBy' => 'manual']],
			[['status' => 'running', 'triggeredBy' => 'schedule']]
		);

		$this->assertFalse($this->log->isRunning(exportId: 'e-1'));
		$this->assertTrue($this->log->isRunning(exportId: 'e-1'));
	}//end testOnlyARunningScheduledFireIsAnOverlap()
}//end class
