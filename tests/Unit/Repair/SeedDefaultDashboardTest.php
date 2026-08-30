<?php

/**
 * Seed-Default-Dashboard Repair Step Test.
 *
 * Covers the repair step that provisions LaunchPad's default dashboard on
 * install/upgrade. All four paths: the name, a seed that creates a dashboard,
 * a seed that finds one already present, and a seed that throws.
 *
 * The throwing path is the one worth having. A repair step that lets an
 * exception escape aborts `occ upgrade` for the whole instance, so this step
 * deliberately swallows and warns — and a test that did not assert the
 * swallow would let a future refactor turn a cosmetic seeding failure into a
 * failed upgrade.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Repair\SeedDefaultDashboard;
use OCA\LaunchPad\Service\DashboardService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for the default-dashboard seeding repair step.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SeedDefaultDashboardTest extends TestCase {

	/** @var ContainerInterface&MockObject */
	private $container;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/** @var DashboardService&MockObject */
	private $dashboardService;

	/** @var IOutput&MockObject */
	private $output;

	private SeedDefaultDashboard $step;

	/**
	 * Build the step over mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->container        = $this->createMock(ContainerInterface::class);
		$this->logger           = $this->createMock(LoggerInterface::class);
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->output           = $this->createMock(IOutput::class);

		$this->step = new SeedDefaultDashboard($this->container, $this->logger);

	}//end setUp()

	/**
	 * The step names itself for `occ upgrade`'s progress output.
	 *
	 * @return void
	 */
	public function testGetNameDescribesTheStep(): void {
		$this->assertSame('Seed the default LaunchPad dashboard', $this->step->getName());

	}//end testGetNameDescribesTheStep()

	/**
	 * A dashboard was provisioned: say so on both the log and the output.
	 *
	 * @return void
	 */
	public function testRunReportsAProvisionedDashboard(): void {
		$this->container->method('get')->willReturn($this->dashboardService);
		$this->dashboardService->expects($this->once())
			->method('ensureDefaultDashboard')
			->willReturn($this->createMock(Dashboard::class));

		$this->logger->expects($this->once())->method('info');
		$this->output->expects($this->never())->method('warning');

		$messages = [];
		$this->output->method('info')->willReturnCallback(
			function (string $message) use (&$messages): void {
				$messages[] = $message;
			}
		);

		$this->step->run($this->output);

		$this->assertCount(1, $messages);
		$this->assertStringContainsString('seeded', $messages[0]);

	}//end testRunReportsAProvisionedDashboard()

	/**
	 * A null return means one already existed — that is a skip, not a seed.
	 *
	 * Asserting the message matters: both branches call `$output->info()`, so a
	 * test that only counted calls would pass with the two swapped.
	 *
	 * @return void
	 */
	public function testRunSkipsWhenADefaultDashboardAlreadyExists(): void {
		$this->container->method('get')->willReturn($this->dashboardService);
		$this->dashboardService->expects($this->once())
			->method('ensureDefaultDashboard')
			->willReturn(null);

		$this->logger->expects($this->never())->method('info');
		$this->output->expects($this->never())->method('warning');

		$messages = [];
		$this->output->method('info')->willReturnCallback(
			function (string $message) use (&$messages): void {
				$messages[] = $message;
			}
		);

		$this->step->run($this->output);

		$this->assertCount(1, $messages);
		$this->assertStringContainsString('already has a default dashboard', $messages[0]);

	}//end testRunSkipsWhenADefaultDashboardAlreadyExists()

	/**
	 * A throwing seed must NOT escape — it warns and returns.
	 *
	 * An exception out of a repair step aborts `occ upgrade` for the entire
	 * instance. A missing demo dashboard is cosmetic; a failed upgrade is not.
	 *
	 * @return void
	 */
	public function testRunSwallowsAFailureAndWarnsInstead(): void {
		$this->container->method('get')->willReturn($this->dashboardService);
		$this->dashboardService->method('ensureDefaultDashboard')
			->willThrowException(new RuntimeException('database is gone'));

		$this->logger->expects($this->once())->method('warning');
		$this->output->expects($this->never())->method('info');

		$warnings = [];
		$this->output->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings): void {
				$warnings[] = $message;
			}
		);

		$this->step->run($this->output);

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('database is gone', $warnings[0]);

	}//end testRunSwallowsAFailureAndWarnsInstead()

	/**
	 * The container itself failing is the same contract: warn, do not throw.
	 *
	 * This is the realistic shape of the failure — on a partially-installed app
	 * it is the service RESOLUTION that dies, before any seeding is attempted.
	 *
	 * @return void
	 */
	public function testRunSwallowsAContainerResolutionFailure(): void {
		$this->container->method('get')
			->willThrowException(new RuntimeException('service not registered'));

		$this->logger->expects($this->once())->method('warning');
		$this->output->expects($this->once())->method('warning');
		$this->output->expects($this->never())->method('info');

		$this->step->run($this->output);

	}//end testRunSwallowsAContainerResolutionFailure()

}//end class
