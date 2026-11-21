<?php

/**
 * DashboardListFiltersTest
 *
 * Unit tests for DashboardListFilters: the --user, --group and --status
 * options read as strings, null when not given.
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

use OCA\LaunchPad\Command\DashboardListFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Tests for DashboardListFilters.
 */
#[CoversClass(DashboardListFilters::class)]
class DashboardListFiltersTest extends TestCase {
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
	 * Given options are kept as strings.
	 *
	 * @return void
	 */
	public function testGivenOptionsAreRead(): void {
		$filters = new DashboardListFilters($this->input(['user' => 'alice', 'group' => 'staff', 'status' => 'published']));

		$this->assertSame('alice', $filters->user);
		$this->assertSame('staff', $filters->group);
		$this->assertSame('published', $filters->status);
	}//end testGivenOptionsAreRead()

	/**
	 * Options not given are null.
	 *
	 * @return void
	 */
	public function testMissingOptionsAreNull(): void {
		$filters = new DashboardListFilters($this->input(['group' => 'staff']));

		$this->assertNull($filters->user);
		$this->assertSame('staff', $filters->group);
		$this->assertNull($filters->status);
	}//end testMissingOptionsAreNull()

	/**
	 * Non-string option values are cast; an empty string is kept, not nulled.
	 *
	 * @return void
	 */
	public function testValuesAreCastToString(): void {
		$filters = new DashboardListFilters($this->input(['user' => 42, 'status' => '']));

		$this->assertSame('42', $filters->user);
		$this->assertSame('', $filters->status);
	}//end testValuesAreCastToString()
}//end class
