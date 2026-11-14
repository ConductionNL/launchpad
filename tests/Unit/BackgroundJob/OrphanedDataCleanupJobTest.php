<?php

/**
 * OrphanedDataCleanupJobTest
 *
 * Pins which categories the nightly cleanup purges: the auto-safe set
 * when nothing or invalid JSON is configured (with a warning), otherwise
 * the configured entries that name a registered category, and the skip
 * when none remain.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\CleanupJobTools;
use OCA\LaunchPad\BackgroundJob\OrphanedDataCleanupJob;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrphanedDataCleanupJobTest extends TestCase {

	/**
	 * Run the job with the given stored config; record the purge and log calls.
	 *
	 * @param string $stored The stored categories config.
	 *
	 * @return list<string>
	 */
	private function runWith(string $stored): array {
		$log = [];
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturn($stored);
		$logger = $this->createMock(LoggerInterface::class);
		foreach (['info', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(function (string $message) use ($level, &$log): void {
				$log[] = $level . ' ' . $message;
			});
		}

		$registry = $this->createMock(CategoryRegistryService::class);
		$registry->method('getAutoSafeCategoryNames')->willReturn(['locks']);
		$registry->method('getCategoryNames')->willReturn(['locks', 'shares', 'rules']);

		$result = $this->createMock(CleanupResult::class);
		$result->method('getTotalRows')->willReturn(4);
		$result->method('getDurationMs')->willReturn(9);
		$service = $this->createMock(OrphanedDataCleanupService::class);
		$service->method('purge')->willReturnCallback(function (array $categoryNames) use ($result, &$log): CleanupResult {
			$log[] = 'purge ' . implode(',', $categoryNames);
			return $result;
		});

		$job = new OrphanedDataCleanupJob(
			time: $this->createMock(ITimeFactory::class),
			cleanupService: $service,
			registry: $registry,
			tools: new CleanupJobTools(appConfig: $config, logger: $logger)
		);
		$run = new \ReflectionMethod($job, 'run');
		$run->setAccessible(true);
		$run->invoke($job, null);

		return $log;
	}

	public function testCategoriesThePurgeRuns(): void {
		$this->assertSame(['purge locks', 'info launchpad.cleanup.job_run rows=4 duration_ms=9 categories=locks'], $this->runWith(''));
		$this->assertSame(
			['warning launchpad.cleanup.job_config_invalid raw="x"', 'purge locks', 'info launchpad.cleanup.job_run rows=4 duration_ms=9 categories=locks'],
			$this->runWith('"x"')
		);
		$this->assertSame(
			['purge rules,shares', 'info launchpad.cleanup.job_run rows=4 duration_ms=9 categories=rules,shares'],
			$this->runWith('["rules","",5,"nope","shares"]')
		);
		$this->assertSame(['info launchpad.cleanup.job_skipped reason=no_categories_enabled'], $this->runWith('["nope"]'));
	}
}
