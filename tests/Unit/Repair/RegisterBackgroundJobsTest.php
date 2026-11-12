<?php

/**
 * RegisterBackgroundJobsTest
 *
 * Pins the repair step that registers LaunchPad's background jobs: which
 * jobs it adds, in which order, and what it reports and logs.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\BackgroundJob\PurgeViewsJob;
use OCA\LaunchPad\BackgroundJob\SaltRotationJob;
use OCA\LaunchPad\Job\FeedRefreshJob;
use OCA\LaunchPad\Repair\RegisterBackgroundJobs;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for RegisterBackgroundJobs.
 *
 * @covers \OCA\LaunchPad\Repair\RegisterBackgroundJobs
 */
class RegisterBackgroundJobsTest extends TestCase {

	/**
	 * Ordered record of job-list, output and logger calls.
	 *
	 * @var list<string>
	 */
	private array $trail = [];

	/**
	 * Build the step over recording collaborators.
	 *
	 * @param string|null $failOn A job class whose registration throws.
	 *
	 * @return array{0: RegisterBackgroundJobs, 1: IOutput}
	 */
	private function step(?string $failOn = null): array {
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('add')->willReturnCallback(function ($job, $argument = null) use ($failOn): void {
			$this->trail[] = 'add ' . $job . ' ' . var_export($argument, true);
			if ($job === $failOn) {
				throw new RuntimeException('job table locked');
			}
		});

		$output = $this->createMock(IOutput::class);
		$output->method('info')->willReturnCallback(function (string $message): void {
			$this->trail[] = 'info ' . $message;
		});

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')->willReturnCallback(function ($message): void {
			$this->trail[] = 'log ' . $message;
		});

		return [new RegisterBackgroundJobs(jobList: $jobList, logger: $logger), $output];
	}//end step()

	/**
	 * The three jobs are added without arguments, each one reported, then one log line.
	 *
	 * @return void
	 */
	public function testRegistersEveryJobAndReportsEach(): void {
		[$step, $output] = $this->step();

		$step->run($output);

		$this->assertSame(
			[
				'add ' . PurgeViewsJob::class . ' NULL',
				'info Ensured LaunchPad background job is registered: ' . PurgeViewsJob::class,
				'add ' . SaltRotationJob::class . ' NULL',
				'info Ensured LaunchPad background job is registered: ' . SaltRotationJob::class,
				'add ' . FeedRefreshJob::class . ' NULL',
				'info Ensured LaunchPad background job is registered: ' . FeedRefreshJob::class,
				'log RegisterBackgroundJobs: ensured LaunchPad background jobs are registered.',
			],
			$this->trail
		);
	}//end testRegistersEveryJobAndReportsEach()

	/**
	 * A failing registration is not swallowed: it aborts the step before
	 * the later jobs and before the success log line.
	 *
	 * @return void
	 */
	public function testAFailingRegistrationAbortsTheStep(): void {
		[$step, $output] = $this->step(failOn: SaltRotationJob::class);

		try {
			$step->run($output);
			$this->fail('The registration failure should propagate.');
		} catch (RuntimeException $e) {
			$this->assertSame('job table locked', $e->getMessage());
		}

		$this->assertSame(
			[
				'add ' . PurgeViewsJob::class . ' NULL',
				'info Ensured LaunchPad background job is registered: ' . PurgeViewsJob::class,
				'add ' . SaltRotationJob::class . ' NULL',
			],
			$this->trail
		);
	}//end testAFailingRegistrationAbortsTheStep()

	/**
	 * The name shown by `occ upgrade` describes the step.
	 *
	 * @return void
	 */
	public function testNameDescribesTheStep(): void {
		[$step] = $this->step();

		$this->assertSame('Register LaunchPad background jobs', $step->getName());
	}//end testNameDescribesTheStep()
}//end class
