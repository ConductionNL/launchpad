<?php

/**
 * WidgetTypeList test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Support\WidgetTypeList;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The shipped list reads, a broken one is loud.
 *
 * @covers \OCA\LaunchPad\Support\WidgetTypeList
 */
class WidgetTypeListTest extends TestCase {

	/**
	 * The shipped list holds LaunchPad's own types and nothing blank.
	 *
	 * @return void
	 */
	public function testTheShippedListReads(): void {
		$types = (new WidgetTypeList())->types();

		$this->assertContains(needle: 'text', haystack: $types);
		$this->assertContains(needle: 'calendar', haystack: $types);
		$this->assertNotContains(needle: '', haystack: $types);
		$this->assertSame(expected: array_values(array: array_unique(array: $types)), actual: $types);
	}//end testTheShippedListReads()

	/**
	 * The default rule allows the two Nextcloud widgets it always did, then every type.
	 *
	 * @return void
	 */
	public function testTheDefaultRuleAddsTheTypesToTheDashboardWidgets(): void {
		$list    = new WidgetTypeList();
		$allowed = $list->defaultRuleWidgets();

		$this->assertSame(expected: ['activity', 'recommendations'], actual: array_slice(array: $allowed, offset: 0, length: 2));
		$this->assertSame(expected: $list->types(), actual: array_slice(array: $allowed, offset: 2));
	}//end testTheDefaultRuleAddsTheTypesToTheDashboardWidgets()

	/**
	 * Non-string and empty entries are dropped, duplicates collapse.
	 *
	 * @return void
	 */
	public function testEntriesAreCleaned(): void {
		$path = tempnam(directory: sys_get_temp_dir(), prefix: 'wtl');
		file_put_contents(filename: $path, data: json_encode(value: ['types' => ['clock', '', 7, 'clock', 'text']]));

		try {
			$this->assertSame(expected: ['clock', 'text'], actual: (new WidgetTypeList(path: $path))->types());
		} finally {
			unlink(filename: $path);
		}
	}//end testEntriesAreCleaned()

	/**
	 * A missing or malformed list throws instead of answering with nothing.
	 *
	 * @return void
	 */
	public function testABrokenListThrows(): void {
		$path = tempnam(directory: sys_get_temp_dir(), prefix: 'wtl');
		file_put_contents(filename: $path, data: '{"kinds": []}');

		try {
			(new WidgetTypeList(path: $path))->types();
			$this->fail(message: 'a malformed list must throw');
		} catch (RuntimeException) {
			$this->addToAssertionCount(count: 1);
		} finally {
			unlink(filename: $path);
		}

		$this->expectException(exception: RuntimeException::class);
		(new WidgetTypeList(path: $path . '-missing'))->types();
	}//end testABrokenListThrows()
}//end class
