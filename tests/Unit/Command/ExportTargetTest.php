<?php

/**
 * ExportTargetTest
 *
 * Unit tests for ExportTarget: the export command's scope, dashboard and
 * output path options.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\ExportTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Tests for ExportTarget.
 */
#[CoversClass(ExportTarget::class)]
class ExportTargetTest extends TestCase {
	/**
	 * An input double answering the given options.
	 *
	 * @param array<string, mixed> $options Option values by name.
	 *
	 * @return InputInterface
	 */
	private function input(array $options): InputInterface {
		$input = $this->createMock(InputInterface::class);
		$input->method('getOption')->willReturnCallback(
			static fn (string $name): mixed => ($options[$name] ?? null)
		);

		return $input;
	}//end input()

	/**
	 * Given options are read under their own names.
	 *
	 * @return void
	 */
	public function testGivenOptionsAreRead(): void {
		$target = new ExportTarget($this->input(['scope' => 'dashboard', 'dashboard-uuid' => 'u-1', 'output' => '/tmp/out.json']));

		$this->assertSame('dashboard', $target->scope);
		$this->assertSame('u-1', $target->dashboardUuid);
		$this->assertSame('/tmp/out.json', $target->outputPath);
	}//end testGivenOptionsAreRead()

	/**
	 * Missing options become empty strings.
	 *
	 * @return void
	 */
	public function testMissingOptionsAreEmpty(): void {
		$target = new ExportTarget($this->input([]));

		$this->assertSame('', $target->scope);
		$this->assertSame('', $target->dashboardUuid);
		$this->assertSame('', $target->outputPath);
	}//end testMissingOptionsAreEmpty()
}//end class
