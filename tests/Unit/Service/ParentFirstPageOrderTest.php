<?php

/**
 * Unit tests for ParentFirstPageOrder: parents before children, each page once.
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\Confluence\ParsedPage;
use OCA\LaunchPad\Service\ParentFirstPageOrder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ParentFirstPageOrder
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedPage
 */
class ParentFirstPageOrderTest extends TestCase {
	private function page(string $id, ?string $parent = null): ParsedPage {
		return new ParsedPage(
			relPath: $id . '.html',
			pageId: $id,
			title: 'Page ' . $id,
			body: '',
			sortOrder: 0,
			parentPageId: $parent
		);
	}

	/**
	 * @param ParsedPage[] $pages
	 * @return string[]
	 */
	private function ids(array $pages): array {
		return array_map(static fn (ParsedPage $p): string => $p->pageId, $pages);
	}

	/**
	 * @param ParsedPage[] $pages
	 * @return array<string, ParsedPage>
	 */
	private function index(array $pages): array {
		$byId = [];
		foreach ($pages as $page) {
			$byId[strtolower($page->pageId)] = $page;
		}
		return $byId;
	}

	public function testChildVisitedFirstIsEmittedAfterItsParentChain(): void {
		$root = $this->page('root');
		$mid = $this->page('mid', 'root');
		$leaf = $this->page('leaf', 'mid');
		$pages = [$leaf, $mid, $root];

		$ordered = (new ParentFirstPageOrder($this->index($pages)))->order($pages);

		$this->assertSame(['root', 'mid', 'leaf'], $this->ids($ordered));
	}

	public function testAlreadyOrderedInputIsKept(): void {
		$pages = [$this->page('a'), $this->page('b', 'a'), $this->page('c')];

		$ordered = (new ParentFirstPageOrder($this->index($pages)))->order($pages);

		$this->assertSame(['a', 'b', 'c'], $this->ids($ordered));
	}

	public function testUnknownParentDoesNotBlockThePage(): void {
		$pages = [$this->page('orphan', 'missing'), $this->page('x')];

		$ordered = (new ParentFirstPageOrder($this->index($pages)))->order($pages);

		$this->assertSame(['orphan', 'x'], $this->ids($ordered));
	}

	public function testSelfParentIsEmittedOnce(): void {
		$pages = [$this->page('self', 'SELF')];

		$ordered = (new ParentFirstPageOrder($this->index($pages)))->order($pages);

		$this->assertSame(['self'], $this->ids($ordered));
	}

	public function testPageIdsCompareCaseInsensitively(): void {
		$parent = $this->page('Parent');
		$child = $this->page('child', 'PARENT');
		$duplicate = $this->page('PARENT');
		$pages = [$child, $parent, $duplicate];

		$ordered = (new ParentFirstPageOrder($this->index([$child, $parent])))->order($pages);

		$this->assertSame(['Parent', 'child'], $this->ids($ordered));
		$this->assertSame($parent, $ordered[0]);
	}

	public function testEmptyInputGivesEmptyOutput(): void {
		$this->assertSame([], (new ParentFirstPageOrder([]))->order([]));
	}
}
