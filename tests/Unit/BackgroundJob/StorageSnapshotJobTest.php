<?php

/**
 * StorageSnapshotJob Test
 *
 * REQ-DWMS-010: one snapshot a day, and a failed run is logged rather than thrown.
 *
 * @category  Test
 * @package   Unit\BackgroundJob
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

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\StorageSnapshotJob;
use OCA\LaunchPad\Service\StorageReport\StorageReportService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(StorageSnapshotJob::class)]
class StorageSnapshotJobTest extends TestCase {

	private function runJob(StorageSnapshotJob $job): void {
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}

	public function testItRunsDailyAndKeepsTodaysSnapshot(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->expects($this->once())->method('recordToday')->willReturn([]);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('warning');
		$job = new StorageSnapshotJob($this->createMock(ITimeFactory::class), $storage, $logger);

		$this->assertSame(86400, $job->getInterval());
		$this->runJob($job);
	}

	public function testAFailedRunIsLogged(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->method('recordToday')->willThrowException(new RuntimeException('disk'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with('launchpad.storage_report.snapshot_failed', $this->callback(static fn (array $context): bool => $context['exception'] === 'disk'));

		$this->runJob(new StorageSnapshotJob($this->createMock(ITimeFactory::class), $storage, $logger));
	}
}
