<?php

/**
 * HealthPingRefreshJobTest
 *
 * Unit tests for the {@see \OCA\LaunchPad\BackgroundJob\HealthPingRefreshJob}
 * TimedJob — delegates to {@see \OCA\LaunchPad\Service\HealthPingService::refreshDuePlacements()}
 * and never lets a service-level exception escape (REQ-HPING-003
 * "Background refresh of due entries").
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

use OCA\LaunchPad\BackgroundJob\HealthPingRefreshJob;
use OCA\LaunchPad\Service\HealthPingService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

#[Small]
class HealthPingRefreshJobTest extends TestCase
{

    /** @var ITimeFactory&MockObject */
    private $time;

    /** @var HealthPingService&MockObject */
    private $healthPingService;

    /** @var LoggerInterface&MockObject */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time              = $this->createMock(originalClassName: ITimeFactory::class);
        $this->healthPingService = $this->createMock(originalClassName: HealthPingService::class);
        $this->logger             = $this->createMock(originalClassName: LoggerInterface::class);

        $this->time->method('getTime')->willReturn(1000);
    }//end setUp()

    /**
     * The constructor sets the fixed run interval.
     *
     * @return void
     */
    public function testIntervalIsFixedAtFifteenSeconds(): void
    {
        $job = $this->buildJob();
        $this->assertSame(HealthPingRefreshJob::INTERVAL_SECONDS, $job->getInterval());
    }//end testIntervalIsFixedAtFifteenSeconds()

    /**
     * `run()` delegates to `HealthPingService::refreshDuePlacements()`.
     *
     * @return void
     */
    public function testRunDelegatesToRefreshDuePlacements(): void
    {
        $this->healthPingService->expects($this->once())
            ->method('refreshDuePlacements')
            ->willReturn(3);

        $job = $this->buildJob();
        $this->invokeRun(job: $job);
        $this->assertTrue(condition: true);
    }//end testRunDelegatesToRefreshDuePlacements()

    /**
     * `run()` never lets a service-level exception escape — the job list
     * must not be poisoned by one broken tick.
     *
     * @return void
     */
    public function testRunSwallowsServiceException(): void
    {
        $this->healthPingService->method('refreshDuePlacements')
            ->willThrowException(new RuntimeException('unexpected failure'));

        $job = $this->buildJob();
        // No exception should propagate out of run().
        $this->invokeRun(job: $job);
        $this->assertTrue(condition: true);
    }//end testRunSwallowsServiceException()

    /**
     * Build the job under test using the current mock state.
     *
     * @return HealthPingRefreshJob
     */
    private function buildJob(): HealthPingRefreshJob
    {
        return new HealthPingRefreshJob(
            time: $this->time,
            healthPingService: $this->healthPingService,
            logger: $this->logger,
        );
    }//end buildJob()

    /**
     * Invoke the protected `run` method via reflection.
     *
     * @param HealthPingRefreshJob $job The job under test.
     *
     * @return void
     */
    private function invokeRun(HealthPingRefreshJob $job): void
    {
        $ref    = new ReflectionClass(objectOrClass: $job);
        $method = $ref->getMethod(name: 'run');
        $method->setAccessible(accessible: true);
        $method->invoke($job, null);
    }//end invokeRun()
}//end class
