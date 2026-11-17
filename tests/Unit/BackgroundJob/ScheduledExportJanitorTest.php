<?php

/**
 * ScheduledExportJanitorTest
 *
 * Retention of scheduled exports (Task 9, Task 26, REQ-SCH-007): artefacts
 * expire while the audit metadata stays, a null retention keeps forever,
 * expired run-rows are deleted with their files, running runs are left
 * alone, and an export without retention gets the defaults.
 *
 * @category  Test
 * @package   Unit\BackgroundJob
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

namespace Unit\BackgroundJob;

use DateTimeImmutable;
use OCA\LaunchPad\BackgroundJob\ScheduledExportJanitor;
use OCA\LaunchPad\Service\ScheduledExportArtefactStorage;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\ScheduledExportJanitor
 * @uses   \OCA\LaunchPad\Service\ScheduledExportService
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-26
 */
class ScheduledExportJanitorTest extends TestCase {

	private ScheduledExportService&MockObject $exports;

	private ScheduledExportAuditLog&MockObject $auditLog;

	private ScheduledExportArtefactStorage&MockObject $artefacts;

	private ScheduledExportJanitor $janitor;

	private DateTimeImmutable $now;

	/**
	 * Build the janitor over mocked services.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->exports   = $this->createMock(ScheduledExportService::class);
		$this->auditLog  = $this->createMock(ScheduledExportAuditLog::class);
		$this->artefacts = $this->createMock(ScheduledExportArtefactStorage::class);
		$this->janitor   = new ScheduledExportJanitor(
			$this->createMock(ITimeFactory::class),
			$this->exports,
			$this->auditLog,
			$this->artefacts,
			$this->createMock(LoggerInterface::class)
		);
		$this->now = new DateTimeImmutable('2026-11-30T10:00:00+01:00');
	}//end setUp()

	/**
	 * A finished run-row of an export that started some days before now, with one stored PDF.
	 *
	 * @param string $id      The run id.
	 * @param int    $daysAgo How long ago it started.
	 * @param string $status  Its status.
	 *
	 * @return array<string, mixed>
	 */
	private function runRow(string $id, int $daysAgo, string $status='success'): array {
		return [
			'id' => $id,
			'exportId' => 'e-1',
			'status' => $status,
			'startedAt' => $this->now->modify('-' . $daysAgo . ' days')->format(DATE_ATOM),
			'renderArtefacts' => [['format' => 'pdf', 'sha256' => 'abc', 'storagePath' => 'Apps/LaunchPad/Exports/e-1/' . $id . '.pdf']],
			'deliveries' => [['recipientId' => 'recipient-0', 'status' => 'success']],
		];
	}//end runRow()

	/**
	 * After 14 days the artefacts are gone and their paths nulled, while hashes and deliveries stay.
	 *
	 * @return void
	 */
	public function testArtefactsExpireAndTheAuditMetadataStays(): void {
		$this->exports->method('findForRun')->with('e-1')->willReturn(
			['id' => 'e-1', '@self' => ['owner' => 'anna'], 'retention' => ['keepArtefactsForDays' => 14, 'keepAuditForDays' => 365]]
		);
		$this->auditLog->method('allRuns')->willReturn([$this->runRow('old', 20), $this->runRow('new', 10)]);
		$this->artefacts->expects($this->once())->method('delete')->with('anna', 'Apps/LaunchPad/Exports/e-1/old.pdf')->willReturn(true);
		$this->auditLog->expects($this->never())->method('deleteRun');
		$this->auditLog->expects($this->once())->method('write')->with(
			$this->callback(function (array $run): bool {
				$this->assertSame('old', $run['id']);
				$this->assertNull($run['renderArtefacts'][0]['storagePath']);
				$this->assertSame('abc', $run['renderArtefacts'][0]['sha256']);
				$this->assertSame('success', $run['deliveries'][0]['status']);
				return true;
			})
		);

		$this->assertSame(['artefacts' => 1, 'runs' => 0], $this->janitor->purge(now: $this->now));
	}//end testArtefactsExpireAndTheAuditMetadataStays()

	/**
	 * A null artefact retention deletes nothing, however old.
	 *
	 * @return void
	 */
	public function testANullRetentionKeepsForever(): void {
		$this->exports->method('findForRun')->willReturn(
			['id' => 'e-1', 'retention' => ['keepArtefactsForDays' => null, 'keepAuditForDays' => null]]
		);
		$this->auditLog->method('allRuns')->willReturn([$this->runRow('ancient', 4000)]);
		$this->artefacts->expects($this->never())->method('delete');
		$this->auditLog->expects($this->never())->method('deleteRun');
		$this->auditLog->expects($this->never())->method('write');

		$this->assertSame(['artefacts' => 0, 'runs' => 0], $this->janitor->purge(now: $this->now));
	}//end testANullRetentionKeepsForever()

	/**
	 * A run-row past its audit retention is deleted together with the files it still holds.
	 *
	 * @return void
	 */
	public function testExpiredRunRowsArePurgedWithTheirFiles(): void {
		$this->exports->method('findForRun')->willReturn(
			['id' => 'e-1', '@self' => ['owner' => 'anna'], 'retention' => ['keepArtefactsForDays' => null, 'keepAuditForDays' => 30]]
		);
		$this->auditLog->method('allRuns')->willReturn([$this->runRow('expired', 31)]);
		$this->artefacts->expects($this->once())->method('delete')->with('anna', 'Apps/LaunchPad/Exports/e-1/expired.pdf')->willReturn(true);
		$this->auditLog->expects($this->once())->method('deleteRun')->with('expired');

		$this->assertSame(['artefacts' => 1, 'runs' => 1], $this->janitor->purge(now: $this->now));
	}//end testExpiredRunRowsArePurgedWithTheirFiles()

	/**
	 * A running run is never cleaned; an export without retention gets 30 days for artefacts.
	 *
	 * @return void
	 */
	public function testRunningRunsAreLeftAndDefaultsApply(): void {
		$this->exports->method('findForRun')->willReturn(['id' => 'e-1', '@self' => ['owner' => 'anna']]);
		$this->auditLog->method('allRuns')->willReturn([$this->runRow('busy', 400, 'running'), $this->runRow('month', 31), $this->runRow('fresh', 29)]);
		$this->artefacts->expects($this->once())->method('delete')->with('anna', 'Apps/LaunchPad/Exports/e-1/month.pdf')->willReturn(true);
		$this->auditLog->expects($this->never())->method('deleteRun');

		$this->assertSame(['artefacts' => 1, 'runs' => 0], $this->janitor->purge(now: $this->now));
	}//end testRunningRunsAreLeftAndDefaultsApply()
}//end class
