<?php

/**
 * FirstPaintNoticesTest
 *
 * The notice banners the first-paint shell prints come from the reads the
 * page already carries, with NoticeBannerRegion's rule: notices only, not
 * ended, not dismissed by this reader (first-paint-skeleton REQ-FPS-003).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintNotices;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FirstPaintNotices.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintNotices
 */
class FirstPaintNoticesTest extends TestCase {

	/**
	 * The page's reads with these announcements and dismissed uuids.
	 *
	 * @param list<array<string, mixed>> $announcements The announcements.
	 * @param string                     $dismissed     The preference value.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function reads(array $announcements, string $dismissed='[]'): array {
		return [
			['path' => '/api/announcements', 'params' => [], 'data' => ['announcements' => $announcements]],
			['path' => '/api/preferences/dismissed-notices', 'params' => [], 'data' => ['value' => $dismissed]],
		];
	}//end reads()

	/**
	 * Notices that have not ended and were not dismissed, in order; news and
	 * ended or dismissed notices are left out.
	 *
	 * @return void
	 */
	public function testTheRegionsRule(): void {
		$now   = strtotime('2026-11-28T10:00:00Z');
		$reads = self::reads(
			announcements: [
				['uuid' => 'a', 'kind' => 'notice', 'title' => 'Maintenance', 'body' => 'Saturday', 'level' => 'warning', 'expiresAt' => '2026-11-29T00:00:00Z'],
				['uuid' => 'b', 'kind' => 'news', 'title' => 'News'],
				['uuid' => 'c', 'kind' => 'notice', 'title' => 'Ended', 'expiresAt' => '2026-11-28T09:00:00Z'],
				['uuid' => 'd', 'kind' => 'notice', 'title' => 'Dismissed'],
				['uuid' => 'e', 'kind' => 'notice', 'title' => 'Open-ended', 'expiresAt' => null],
			],
			dismissed: '["d"]'
		);

		$this->assertSame(
			expected: [
				['uuid' => 'a', 'title' => 'Maintenance', 'body' => 'Saturday', 'warning' => true],
				['uuid' => 'e', 'title' => 'Open-ended', 'body' => '', 'warning' => false],
			],
			actual: (new FirstPaintNotices())->shown(reads: $reads, now: $now)
		);
	}//end testTheRegionsRule()

	/**
	 * Without the reads, or with a broken preference, nothing breaks.
	 *
	 * @return void
	 */
	public function testMissingOrBrokenReads(): void {
		$notices = new FirstPaintNotices();
		$this->assertSame(expected: [], actual: $notices->shown(reads: [], now: 0));
		$broken = self::reads(announcements: [['uuid' => 'a', 'kind' => 'notice', 'title' => 'T', 'expiresAt' => 'not a date']], dismissed: '{oops');
		$this->assertSame(expected: ['a'], actual: array_column($notices->shown(reads: $broken, now: 0), 'uuid'));
	}//end testMissingOrBrokenReads()
}//end class
