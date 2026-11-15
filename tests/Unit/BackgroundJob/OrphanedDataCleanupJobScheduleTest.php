<?php

/**
 * Unit tests for OrphanedDataCleanupJob scheduling and the exact purge call.
 *
 * @category Test
 * @package  Unit\BackgroundJob
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\OrphanedDataCleanupJob
 * @uses   \OCA\LaunchPad\BackgroundJob\CleanupJobTools
 */
class OrphanedDataCleanupJobScheduleTest extends TestCase {
	private OrphanedDataCleanupService&MockObject $service;
	private CategoryRegistryService&MockObject $registry;
	private IAppConfig&MockObject $config;
	private OrphanedDataCleanupJob $job;

	protected function setUp(): void {
		$this->service = $this->createMock(OrphanedDataCleanupService::class);
		$this->registry = $this->createMock(CategoryRegistryService::class);
		$this->config = $this->createMock(IAppConfig::class);
		$this->job = new OrphanedDataCleanupJob(
			time: $this->createMock(ITimeFactory::class),
			cleanupService: $this->service,
			registry: $this->registry,
			tools: new CleanupJobTools(appConfig: $this->config, logger: $this->createMock(LoggerInterface::class))
		);
	}

	public function testRunsDailyAndIsNotTimeSensitive(): void {
		$this->assertSame(OrphanedDataCleanupJob::INTERVAL_SECONDS, $this->job->getInterval());
		$this->assertSame(86400, OrphanedDataCleanupJob::INTERVAL_SECONDS);
		$this->assertFalse($this->job->isTimeSensitive());
	}

	public function testPurgeIsARealJobRunWithoutAUser(): void {
		$this->config->expects($this->once())
			->method('getValueString')
			->with('launchpad', OrphanedDataCleanupJob::CONFIG_KEY_CATEGORIES, '')
			->willReturn('["locks"]');
		$this->registry->method('getCategoryNames')->willReturn(['locks']);
		$this->registry->expects($this->never())->method('getAutoSafeCategoryNames');
		$result = $this->createMock(CleanupResult::class);
		$this->service->expects($this->once())
			->method('purge')
			->with(['locks'], false, null, 'job')
			->willReturn($result);

		(new ReflectionMethod($this->job, 'run'))->invoke($this->job, null);
	}

	public function testAnEmptyConfiguredListPurgesNothing(): void {
		$this->config->method('getValueString')->willReturn('[]');
		$this->registry->expects($this->never())->method('getAutoSafeCategoryNames');
		$this->service->expects($this->never())->method('purge');

		(new ReflectionMethod($this->job, 'run'))->invoke($this->job, null);
	}
}
