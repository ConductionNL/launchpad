<?php

/**
 * A stored date is read as `YYYY-MM-DD` from ISO and the common day-first
 * formats; anything else is no date.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\StoredDate;
use PHPUnit\Framework\TestCase;

/**
 * StoredDate::toIso.
 */
class StoredDateTest extends TestCase {
	/**
	 * ISO, day-first with `-`, `/` and `.`, and the refusals.
	 *
	 * @return void
	 */
	public function testStoredDatesReadAsIso(): void {
		$this->assertSame(expected: '1990-06-10', actual: StoredDate::toIso(raw: '1990-06-10'));
		$this->assertSame(expected: '1990-06-10', actual: StoredDate::toIso(raw: ' 10-06-1990 '));
		$this->assertSame(expected: '1990-06-10', actual: StoredDate::toIso(raw: '10/06/1990'));
		$this->assertSame(expected: '1990-06-10', actual: StoredDate::toIso(raw: '10.6.1990'));
		$this->assertNull(actual: StoredDate::toIso(raw: null));
		$this->assertNull(actual: StoredDate::toIso(raw: '31-02-1990'));
		$this->assertNull(actual: StoredDate::toIso(raw: '10-06-90'));
		$this->assertNull(actual: StoredDate::toIso(raw: 'tomorrow'));
	}//end testStoredDatesReadAsIso()
}//end class
