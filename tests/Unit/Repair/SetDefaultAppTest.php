<?php

/**
 * SetDefaultApp Repair Step Test
 *
 * The install step reports what happened to the default app and never lets a
 * failure abort the install (REQ-PSP-003).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\SetDefaultApp;
use OCA\LaunchPad\Service\DefaultAppSetting;
use OCP\Migration\IOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for the default-app install step.
 */
#[CoversClass(SetDefaultApp::class)]
class SetDefaultAppTest extends TestCase {

	/**
	 * The step names itself.
	 *
	 * @return void
	 */
	public function testName(): void {
		$step = new SetDefaultApp($this->createMock(DefaultAppSetting::class), $this->createMock(LoggerInterface::class));

		$this->assertSame('Make LaunchPad the default app', $step->getName());
	}//end testName()

	/**
	 * Each outcome of the claim is reported.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function outcomes(): array {
		return [
			'set'       => [DefaultAppSetting::SET, 'LaunchPad is now the default app.'],
			'kept'      => [DefaultAppSetting::KEPT, 'A default app is already set — keeping it.'],
			'disabled'  => [DefaultAppSetting::DISABLED, 'Making LaunchPad the default app is switched off — skipping.'],
			'unchanged' => [DefaultAppSetting::UNCHANGED, 'LaunchPad already is the default app — skipping.'],
		];
	}//end outcomes()

	/**
	 * The step claims the default app once and reports the outcome.
	 *
	 * @param string $outcome The claim's outcome.
	 * @param string $message The reported line.
	 *
	 * @return void
	 */
	#[DataProvider('outcomes')]
	public function testRunClaimsAndReports(string $outcome, string $message): void {
		$defaultApp = $this->createMock(DefaultAppSetting::class);
		$defaultApp->expects($this->once())->method('claim')->willReturn($outcome);

		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('info')->with($message);
		$output->expects($this->never())->method('warning');

		(new SetDefaultApp($defaultApp, $this->createMock(LoggerInterface::class)))->run($output);
	}//end testRunClaimsAndReports()

	/**
	 * A failure is a warning, never an exception out of the install.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarning(): void {
		$defaultApp = $this->createMock(DefaultAppSetting::class);
		$defaultApp->method('claim')->willThrowException(new RuntimeException('config is read-only'));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')
			->with('[SetDefaultApp] could not set the default app: config is read-only');

		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning')
			->with('LaunchPad was not made the default app: config is read-only');
		$output->expects($this->never())->method('info');

		(new SetDefaultApp($defaultApp, $logger))->run($output);
	}//end testAFailureIsAWarning()
}//end class
