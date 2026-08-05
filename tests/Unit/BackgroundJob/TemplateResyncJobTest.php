<?php

/**
 * TemplateResyncJobTest
 *
 * Unit tests for {@see \OCA\LaunchPad\BackgroundJob\TemplateResyncJob} —
 * the async apply path for large target groups (REQ-RESYNC-005 "Large
 * groups apply asynchronously"). Covers argument validation and the
 * delegation to {@see \OCA\LaunchPad\Service\TemplateResyncService::applyResync()}.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\TemplateResyncJob;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateResyncJobTest extends TestCase
{
    /** @var ITimeFactory&MockObject */
    private $time;

    /** @var TemplateResyncService&MockObject */
    private $resyncService;

    /** @var LoggerInterface&MockObject */
    private $logger;

    private TemplateResyncJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time          = $this->createMock(ITimeFactory::class);
        $this->resyncService = $this->createMock(TemplateResyncService::class);
        $this->logger        = $this->createMock(LoggerInterface::class);

        $this->job = new TemplateResyncJob(
            time: $this->time,
            resyncService: $this->resyncService,
            logger: $this->logger,
        );
    }

    public function testRunDelegatesToApplyResyncWithTheGivenArguments(): void
    {
        $this->resyncService->expects($this->once())
            ->method('applyResync')
            ->with(
                templateId: 1,
                strategy: 'merge',
                actingAdminId: 'admin1'
            )
            ->willReturn([
                'templateId'    => 1,
                'strategy'      => 'merge',
                'dryRun'        => false,
                'async'         => false,
                'totalCopies'   => 800,
                'affectedCount' => 780,
                'copies'        => [],
            ]);

        $this->invokeRun([
            'templateId'    => 1,
            'strategy'      => 'merge',
            'actingAdminId' => 'admin1',
        ]);
    }

    public function testRunSkipsWithoutThrowingWhenArgumentsAreMalformed(): void
    {
        $this->resyncService->expects($this->never())->method('applyResync');
        $this->logger->expects($this->once())->method('warning');

        $this->invokeRun(['templateId' => 0]);
    }

    public function testRunLogsAndSwallowsServiceExceptions(): void
    {
        $this->resyncService->method('applyResync')
            ->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())->method('error');

        // Must not throw — a throw here would make NC's job runner retry
        // indefinitely with the same payload.
        $this->invokeRun([
            'templateId'    => 1,
            'strategy'      => 'overwrite',
            'actingAdminId' => 'admin1',
        ]);
    }

    /**
     * Invoke the protected `run()` method via reflection.
     *
     * @param array $argument The job argument payload.
     */
    private function invokeRun(array $argument): void
    {
        $reflection = new \ReflectionMethod(TemplateResyncJob::class, 'run');
        $reflection->setAccessible(true);
        $reflection->invoke($this->job, $argument);
    }
}
