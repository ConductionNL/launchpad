<?php

/**
 * MintAnnouncementKeysTest
 *
 * REQ-ACK-009: on upgrade every placement that asks for confirmation without
 * a key gets one; a failure is a warning, never a failed upgrade.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-has-a-key-req-ack-009
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Repair\MintAnnouncementKeys;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MintAnnouncementKeysTest extends TestCase {

	/**
	 * Every placement without a key is saved, which gives it one.
	 *
	 * @return void
	 */
	public function testSavesEveryPlacementWithoutAKey(): void {
		$first  = new WidgetPlacement();
		$second = new WidgetPlacement();
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAcknowledged')->willReturn([$first, $second]);
		$mapper->expects($this->exactly(count: 2))->method('update')->willReturnArgument(0);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('2 widget(s) got an announcement key.');

		$step = new MintAnnouncementKeys(placements: $mapper, logger: $this->createMock(originalClassName: LoggerInterface::class));
		$this->assertNotSame(expected: '', actual: $step->getName());
		$step->run(output: $output);
	}//end testSavesEveryPlacementWithoutAKey()

	/**
	 * A failure is a warning.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarning(): void {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAcknowledged')->willThrowException(new RuntimeException('database gone'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The announcement keys were not given: database gone');
		$output->expects($this->never())->method('info');

		(new MintAnnouncementKeys(placements: $mapper, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run(output: $output);
	}//end testAFailureIsAWarning()
}//end class
