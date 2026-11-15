<?php

/**
 * Unit tests for SaltRotationJob: daily interval and today's salt rotation.
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

use OCA\LaunchPad\BackgroundJob\SaltRotationJob;
use OCA\LaunchPad\Service\UniqueViewerDedup;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\SaltRotationJob
 * @uses   \OCA\LaunchPad\Service\UniqueViewerDedup
 */
class SaltRotationJobTest extends TestCase {
	public function testIntervalIsOneDay(): void {
		$job = new SaltRotationJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(UniqueViewerDedup::class),
			$this->createMock(LoggerInterface::class)
		);

		$this->assertSame(86400, $job->getInterval());
	}

	public function testRunRotatesTodaysSaltAndLogsTheDate(): void {
		$rotated = null;
		$dedup = $this->createMock(UniqueViewerDedup::class);
		$dedup->expects($this->once())
			->method('rotateSalt')
			->willReturnCallback(function (string $viewBucketDate) use (&$rotated): string {
				$rotated = $viewBucketDate;
				return 'salt';
			});
		$logged = null;
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->willReturnCallback(function (string $message, array $context) use (&$logged): void {
				$logged = [$message, $context];
			});
		$job = new SaltRotationJob($this->createMock(ITimeFactory::class), $dedup, $logger);

		$before = UniqueViewerDedup::utcDateFor();
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
		$after = UniqueViewerDedup::utcDateFor();

		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $rotated);
		$this->assertContains($rotated, [$before, $after]);
		$this->assertSame(['launchpad analytics salt rotated for ' . $rotated, ['date' => $rotated]], $logged);
	}
}
