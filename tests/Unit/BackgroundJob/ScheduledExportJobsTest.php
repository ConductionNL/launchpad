<?php

/**
 * ScheduledExportJobsTest
 *
 * The scheduled-exports background jobs (tasks.md tasks 25-26): the runner
 * fires every due export as a scheduled fire, keeps going when one fails,
 * then runs the due retries, every minute; the janitor sweeps hourly.
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
use OCA\LaunchPad\BackgroundJob\ScheduledExportRunner;
use OCA\LaunchPad\Service\ScheduledExport\ExportFire;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCA\LaunchPad\Service\ScheduledExport\RetentionJanitor;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\ScheduledExportRunner
 * @covers \OCA\LaunchPad\BackgroundJob\ScheduledExportJanitor
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\FireTrigger
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-25
 */
class ScheduledExportJobsTest extends TestCase {

	/**
	 * Every due export is fired as a scheduled fire; a failing one is logged and the next still fires; retries follow.
	 *
	 * @return void
	 */
	public function testRunnerFiresDueExportsThenRetries(): void {
		$now  = new DateTimeImmutable('2026-11-16T09:00:00+01:00');
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn($now);
		$exports = $this->createMock(ScheduledExportService::class);
		$exports->expects($this->once())->method('due')->with($now)->willReturn([['id' => 'bad'], ['id' => 'good']]);

		$fired = [];
		$fire  = $this->createMock(ExportFire::class);
		$fire->method('fire')->willReturnCallback(static function (array $export, FireTrigger $by) use (&$fired): array {
			$fired[] = [$export['id'], $by->kind];
			if ($export['id'] === 'bad') {
				throw new RuntimeException('boom');
			}

			return [];
		});
		$fire->expects($this->once())->method('retryDue')->willReturn(0);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with($this->stringContains('scheduled export bad failed: boom'));

		$job = new ScheduledExportRunner($time, $exports, $fire, $logger);
		(new ReflectionMethod(ScheduledExportRunner::class, 'run'))->invoke($job, null);

		$this->assertSame([['bad', 'schedule'], ['good', 'schedule']], $fired);
		$this->assertSame(60, $job->getInterval());
	}//end testRunnerFiresDueExportsThenRetries()

	/**
	 * The janitor runs one sweep and logs its counts, hourly.
	 *
	 * @return void
	 */
	public function testJanitorSweeps(): void {
		$janitor = $this->createMock(RetentionJanitor::class);
		$janitor->expects($this->once())->method('sweep')->willReturn(['artefactsPurged' => 2, 'runsDeleted' => 1]);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info')->with('launchpad scheduled export retention: purged artefacts of 2 runs, deleted 1 runs', ['app' => 'launchpad', 'artefactsPurged' => 2, 'runsDeleted' => 1]);

		$job = new ScheduledExportJanitor($this->createMock(ITimeFactory::class), $janitor, $logger);
		(new ReflectionMethod(ScheduledExportJanitor::class, 'run'))->invoke($job, null);
		$this->assertSame(3600, $job->getInterval());
	}//end testJanitorSweeps()
}//end class
