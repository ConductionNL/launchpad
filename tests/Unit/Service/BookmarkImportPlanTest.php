<?php

/**
 * BookmarkImportPlanTest
 *
 * The plan of a bookmark import: which bookmarks are web addresses, which are
 * skipped and why, how many placements it adds, and where they go.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\BookmarkImportPlan;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\BookmarkImportPlan
 */
class BookmarkImportPlanTest extends TestCase {
	/**
	 * Five web bookmarks and a bookmarklet: the bookmarklet is skipped with
	 * the reason and the folder becomes one container of five (REQ-BMI-002).
	 *
	 * @return void
	 */
	public function testAScriptBookmarkIsSkippedWithItsReason(): void {
		$bookmarks = [['title' => 'Run', 'url' => 'javascript:alert(1)']];
		for ($i = 1; $i <= 5; $i++) {
			$bookmarks[] = ['title' => 'Site ' . $i, 'url' => 'https://example.org/' . $i];
		}

		$plan = BookmarkImportPlan::fromRequest(folders: [['name' => 'Werk', 'bookmarks' => $bookmarks]], bookmarks: []);

		self::assertSame(1, $plan->placementCount());
		self::assertSame([['title' => 'Run', 'url' => 'javascript:alert(1)', 'reason' => 'only web addresses can become tiles']], $plan->skipped());
		$layout = $plan->layout(bottom: 0);
		self::assertCount(1, $layout);
		self::assertSame('container', $layout[0]['widgetId']);
		self::assertSame('Werk', $layout[0]['content']['title']);
		self::assertCount(5, $layout[0]['content']['placements']);
		self::assertSame(
			['type' => 'link', 'content' => ['label' => 'Site 1', 'url' => 'https://example.org/1', 'actionType' => 'external'], 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 2, 'gridHeight' => 2],
			$layout[0]['content']['placements'][0]
		);
		self::assertSame([2, 0], [$layout[0]['content']['placements'][1]['gridX'], $layout[0]['content']['placements'][1]['gridY']]);
		self::assertSame([0, 2], [$layout[0]['content']['placements'][2]['gridX'], $layout[0]['content']['placements'][2]['gridY']]);
	}//end testAScriptBookmarkIsSkippedWithItsReason()

	/**
	 * Only http and https addresses with a host and no credentials pass.
	 *
	 * @return void
	 */
	public function testOnlyWebAddressesPass(): void {
		self::assertTrue(BookmarkImportPlan::isWebAddress('https://example.org/a?b=c'));
		self::assertTrue(BookmarkImportPlan::isWebAddress('http://intranet.local/'));
		self::assertTrue(BookmarkImportPlan::isWebAddress('HTTPS://EXAMPLE.ORG'));
		foreach (['javascript:alert(1)', 'file:///etc/passwd', 'data:text/html,x', 'ftp://example.org', 'https://', 'https://user:pw@example.org', 'https://exa mple.org', 'example.org', '', 'https://example.org/' . str_repeat('a', 2050)] as $url) {
			self::assertFalse(BookmarkImportPlan::isWebAddress($url), $url);
		}
	}//end testOnlyWebAddressesPass()

	/**
	 * Containers go three to a row from the dashboard's lowest edge, loose
	 * tiles six to a row below them; folders whose bookmarks are all skipped
	 * create nothing (REQ-BMI-001).
	 *
	 * @return void
	 */
	public function testEverythingGoesBelowTheDashboard(): void {
		$folder = static fn (string $name, int $count): array => [
			'name' => $name,
			'bookmarks' => array_map(static fn (int $i): array => ['title' => $name . $i, 'url' => 'https://example.org/' . $name . $i], range(1, $count)),
		];
		$loose = array_map(static fn (int $i): array => ['title' => '', 'url' => 'https://loose.example/' . $i], range(1, 7));

		$plan = BookmarkImportPlan::fromRequest(
			folders: [$folder('A', 2), $folder('B', 6), $folder('C', 1), $folder('D', 1), ['name' => 'Scripts', 'bookmarks' => [['title' => 'x', 'url' => 'javascript:void(0)']]]],
			bookmarks: $loose
		);

		self::assertSame(4 + 7, $plan->placementCount());
		$layout = $plan->layout(bottom: 10);
		$at = array_map(static fn (array $p): array => [$p['widgetId'], $p['gridX'], $p['gridY'], $p['gridWidth'], $p['gridHeight']], $layout);
		self::assertSame(['container', 0, 10, 4, 3], $at[0]);
		self::assertSame(['container', 4, 10, 4, 5], $at[1]);
		self::assertSame(['container', 8, 10, 4, 3], $at[2]);
		self::assertSame(['container', 0, 15, 4, 3], $at[3]);
		self::assertSame(['tile', 0, 18, 2, 2], $at[4]);
		self::assertSame(['tile', 10, 18, 2, 2], $at[9]);
		self::assertSame(['tile', 0, 20, 2, 2], $at[10]);
		self::assertSame(['title' => 'https://loose.example/1', 'url' => 'https://loose.example/1'], $layout[4]['tile']);
	}//end testEverythingGoesBelowTheDashboard()

	/**
	 * A folder without a name is called Bookmarks, and long titles are cut.
	 *
	 * @return void
	 */
	public function testTitlesAreBoundedAndNeverEmpty(): void {
		$plan = BookmarkImportPlan::fromRequest(
			folders: [['name' => '  ', 'bookmarks' => [['title' => str_repeat('x', 300), 'url' => 'https://example.org']]]],
			bookmarks: []
		);

		$container = $plan->layout(bottom: 0)[0]['content'];
		self::assertSame('Bookmarks', $container['title']);
		self::assertSame(255, mb_strlen($container['placements'][0]['content']['label']));
	}//end testTitlesAreBoundedAndNeverEmpty()

	/**
	 * A wrong shape, an empty choice and more than 2,000 bookmarks are refused.
	 *
	 * @return void
	 */
	public function testMalformedEmptyAndOversizedImportsAreRefused(): void {
		$tooMany = array_fill(0, 2001, ['title' => 'x', 'url' => 'https://example.org']);
		$cases = [
			[['name' => 'not a list'], [], 'must be a list'],
			[[], 'x', 'must be a list'],
			[['folder'], [], 'Each folder must be an object'],
			[[['name' => 'F', 'bookmarks' => 'x']], [], 'must be a list'],
			[[], [], 'at least one bookmark'],
			[[['name' => 'F', 'bookmarks' => []]], [], 'at least one bookmark'],
			[[['name' => 'F', 'bookmarks' => array_slice($tooMany, 0, 1000)]], array_slice($tooMany, 0, 1001), 'at most 2000'],
		];
		foreach ($cases as [$folders, $bookmarks, $reason]) {
			try {
				BookmarkImportPlan::fromRequest(folders: $folders, bookmarks: $bookmarks);
				self::fail('Accepted: ' . $reason);
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString($reason, $e->getMessage());
			}
		}
	}//end testMalformedEmptyAndOversizedImportsAreRefused()
}//end class
