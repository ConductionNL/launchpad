<?php

/**
 * Unit tests for ConfluenceImportRun.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\Confluence\ParsedPage;
use OCA\LaunchPad\Service\ConfluenceImportRun;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The bookkeeping of one Confluence import: created pages, skipped pages and the summary.
 *
 * @covers \OCA\LaunchPad\Service\ConfluenceImportRun
 *
 * @uses \OCA\LaunchPad\Service\Confluence\ParsedPage
 */
class ConfluenceImportRunTest extends TestCase {

	/**
	 * A parsed page with the given id.
	 *
	 * @param string $pageId The Confluence page id.
	 *
	 * @return ParsedPage
	 */
	private static function page(string $pageId): ParsedPage {
		return new ParsedPage(relPath: $pageId . '.html', pageId: $pageId, title: 'Page ' . $pageId, body: '<p/>', sortOrder: 0);
	}//end page()

	/**
	 * A new run carries its user and root and has recorded nothing.
	 *
	 * @return void
	 */
	public function testANewRunHasRecordedNothing(): void {
		$run = new ConfluenceImportRun(currentUserId: 'alice', rootParentUuid: null);

		self::assertSame('alice', $run->currentUserId);
		self::assertNull($run->rootParentUuid);
		self::assertSame(
			['createdDashboardCount' => 0, 'skippedPageCount' => 0, 'errors' => [], 'warnings' => [], 'assetFolder' => ''],
			$run->result(warnings: [], assetFolder: '')
		);
	}//end testANewRunHasRecordedNothing()

	/**
	 * A created page is indexed by its lower-cased id for links and kept by its own id.
	 *
	 * @return void
	 */
	public function testACreatedPageIsIndexedForLinks(): void {
		$run = new ConfluenceImportRun(currentUserId: 'alice', rootParentUuid: 'root-uuid');
		$first = self::page('ABC123');
		$second = self::page('def456');

		$run->recordCreated(page: $first, dashboardUuid: 'uuid-1');
		$run->recordCreated(page: $second, dashboardUuid: 'uuid-2');

		self::assertSame(['abc123' => 'uuid-1', 'def456' => 'uuid-2'], $run->pageIdToUuid);
		self::assertSame(['ABC123' => $first, 'def456' => $second], $run->createdPages);
		self::assertSame(2, $run->createdCount);
	}//end testACreatedPageIsIndexedForLinks()

	/**
	 * A skipped page is counted and its reason recorded; the summary carries warnings and folder.
	 *
	 * @return void
	 */
	public function testASkippedPageIsReportedInTheSummary(): void {
		$run = new ConfluenceImportRun(currentUserId: 'alice', rootParentUuid: null);
		$run->recordCreated(page: self::page('1'), dashboardUuid: 'uuid-1');
		$run->recordSkipped(page: self::page('2'), reason: new RuntimeException('title too long'));
		$run->recordSkipped(page: self::page('3'), reason: new RuntimeException('no body'));

		self::assertSame(
			[
				'createdDashboardCount' => 1,
				'skippedPageCount' => 2,
				'errors' => [['pageId' => '2', 'reason' => 'title too long'], ['pageId' => '3', 'reason' => 'no body']],
				'warnings' => ['2 attachments missing'],
				'assetFolder' => '/Confluence import',
			],
			$run->result(warnings: ['2 attachments missing'], assetFolder: '/Confluence import')
		);
	}//end testASkippedPageIsReportedInTheSummary()
}//end class
