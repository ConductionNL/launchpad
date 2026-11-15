<?php

/**
 * FeedRefreshJobLoggingTest
 *
 * Pins what FeedRefreshJobTest leaves unasserted: the order of lock,
 * refresh and release, the exact log line and context for a completed,
 * failing and skipped tick, and the interval for a non-positive or
 * in-range configuration.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Job
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Job;

use OCA\LaunchPad\Job\FeedRefreshJob;
use OCA\LaunchPad\Job\FeedRefreshRunTools;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Logging, ordering and interval of the feed refresh job.
 *
 * @covers \OCA\LaunchPad\Job\FeedRefreshJob
 * @covers \OCA\LaunchPad\Job\FeedRefreshRunTools
 */
class FeedRefreshJobLoggingTest extends TestCase {

	/**
	 * Ordered record of lock, refresh and log calls.
	 *
	 * @var list<string|array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private array $trail = [];

	/**
	 * Build the job over recording collaborators.
	 *
	 * @param int                  $interval The configured interval.
	 * @param callable|null        $refresh  What refreshAll does.
	 * @param bool                 $locked   Whether the lock is already held.
	 *
	 * @return FeedRefreshJob
	 */
	private function job(int $interval = 3600, ?callable $refresh = null, bool $locked = false): FeedRefreshJob {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')
			->with('launchpad', FeedRefreshJob::CONFIG_KEY_INTERVAL, FeedRefreshJob::DEFAULT_INTERVAL)
			->willReturn($interval);

		$service = $this->createMock(FeedRefreshService::class);
		$service->method('refreshAll')->willReturnCallback(function () use ($refresh): array {
			$this->trail[] = 'refresh';
			return $refresh === null ? [] : $refresh();
		});

		$locks = $this->createMock(ILockingProvider::class);
		$locks->method('acquireLock')->willReturnCallback(function (string $path, int $type) use ($locked): void {
			$this->trail[] = 'acquire ' . $path . ' ' . $type;
			if ($locked === true) {
				throw new LockedException($path);
			}
		});
		$locks->method('releaseLock')->willReturnCallback(function (string $path, int $type): void {
			$this->trail[] = 'release ' . $path . ' ' . $type;
		});

		$logger = $this->createMock(LoggerInterface::class);
		foreach (['info', 'warning', 'error'] as $level) {
			$logger->method($level)->willReturnCallback(function ($message, array $context = []) use ($level): void {
				$this->trail[] = [$level, (string)$message, $context];
			});
		}

		return new FeedRefreshJob(
			time: $this->createMock(ITimeFactory::class),
			appConfig: $config,
			run: new FeedRefreshRunTools(refreshService: $service, lockingProvider: $locks),
			logger: $logger
		);
	}//end job()

	/**
	 * Run one tick.
	 *
	 * @param FeedRefreshJob $job The job.
	 *
	 * @return void
	 */
	private function tick(FeedRefreshJob $job): void {
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}//end tick()

	/**
	 * A completed tick locks, refreshes, logs the totals, then releases.
	 *
	 * @return void
	 */
	public function testACompletedTickLogsItsTotals(): void {
		$this->tick(
			$this->job(
				refresh: static fn (): array => [
					'processedCount' => 4,
					'successCount' => 3,
					'failureCount' => 1,
					'durationMs' => 250,
					'perFeed' => ['ignored'],
				]
			)
		);

		$lock = FeedRefreshJob::LOCK_ID . ' ' . ILockingProvider::LOCK_EXCLUSIVE;
		$this->assertSame(
			[
				'acquire ' . $lock,
				'refresh',
				[
					'info',
					'FeedRefreshJob tick completed',
					['app' => 'launchpad', 'processedCount' => 4, 'successCount' => 3, 'failureCount' => 1, 'durationMs' => 250],
				],
				'release ' . $lock,
			],
			$this->trail
		);
	}//end testACompletedTickLogsItsTotals()

	/**
	 * A failing refresh is logged as an error with its message, and the lock is released.
	 *
	 * @return void
	 */
	public function testAFailingTickLogsTheException(): void {
		$this->tick(
			$this->job(
				refresh: static function (): array {
					throw new RuntimeException('feed host unreachable');
				}
			)
		);

		$this->assertSame(
			[
				'error',
				'FeedRefreshJob tick raised an unexpected exception',
				['app' => 'launchpad', 'exception' => 'feed host unreachable'],
			],
			$this->trail[2]
		);
		$this->assertSame('release ' . FeedRefreshJob::LOCK_ID . ' ' . ILockingProvider::LOCK_EXCLUSIVE, $this->trail[3]);
		$this->assertCount(4, $this->trail);
	}//end testAFailingTickLogsTheException()

	/**
	 * A tick that finds the lock held warns and does nothing else.
	 *
	 * @return void
	 */
	public function testASkippedTickWarns(): void {
		$this->tick($this->job(locked: true));

		$this->assertSame(
			[
				'acquire ' . FeedRefreshJob::LOCK_ID . ' ' . ILockingProvider::LOCK_EXCLUSIVE,
				['warning', 'FeedRefreshJob already running; skipping this tick', ['app' => 'launchpad']],
			],
			$this->trail
		);
	}//end testASkippedTickWarns()

	/**
	 * Configured interval to applied interval.
	 *
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function intervals(): array {
		return [
			'zero falls back to the default' => [0, 3600],
			'negative falls back to the default' => [-5, 3600],
			'in range is kept' => [900, 900],
			'minimum is kept' => [300, 300],
			'maximum is kept' => [86400, 86400],
		];
	}//end intervals()

	/**
	 * The applied interval for a configured value.
	 *
	 * @param int $configured The configured seconds.
	 * @param int $applied    The expected interval.
	 *
	 * @return void
	 */
	#[DataProvider('intervals')]
	public function testIntervalFromConfiguration(int $configured, int $applied): void {
		$this->assertSame($applied, $this->job(interval: $configured)->getInterval());
	}//end testIntervalFromConfiguration()
}//end class
