<?php

/**
 * ConfluenceImportRequestTest
 *
 * Unit tests for ConfluenceImportRequest: the import command's options and
 * their defaults.
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

use OCA\LaunchPad\Command\ConfluenceImportRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Tests for ConfluenceImportRequest.
 */
#[CoversClass(ConfluenceImportRequest::class)]
class ConfluenceImportRequestTest extends TestCase {
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
		$request = new ConfluenceImportRequest(
			$this->input(['file' => '/tmp/space.zip', 'parent-path' => 'Intranet/HR', 'user' => 'alice', 'dry-run' => true])
		);

		$this->assertSame('/tmp/space.zip', $request->file);
		$this->assertSame('Intranet/HR', $request->parentPath);
		$this->assertSame('alice', $request->userId);
		$this->assertTrue($request->isDryRun);
	}//end testGivenOptionsAreRead()

	/**
	 * Missing options default to empty paths, the `cli` user and a real run.
	 *
	 * @return void
	 */
	public function testMissingOptionsDefault(): void {
		$request = new ConfluenceImportRequest($this->input([]));

		$this->assertSame('', $request->file);
		$this->assertSame('', $request->parentPath);
		$this->assertSame('cli', $request->userId);
		$this->assertFalse($request->isDryRun);
	}//end testMissingOptionsDefault()
}//end class
