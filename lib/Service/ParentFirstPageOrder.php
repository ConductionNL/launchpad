<?php

/**
 * ParentFirstPageOrder
 *
 * One depth-first pass over Confluence pages that emits every page after
 * its known parent, each pageId once. Used by
 * {@see ConfluenceImportService} to create parents before children.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Service\Confluence\ParsedPage;

/**
 * Emits pages parent-first; pageIds compare case-insensitively.
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */
final class ParentFirstPageOrder {

	/**
	 * Lower-cased pageIds already emitted.
	 *
	 * @var array<string, true>
	 */
	private array $emitted = [];

	/**
	 * The emitted pages, in order.
	 *
	 * @var ParsedPage[]
	 */
	private array $ordered = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, ParsedPage> $byPageId Every page, keyed by lower-cased pageId.
	 */
	public function __construct(
		private readonly array $byPageId,
	) {
	}//end __construct()

	/**
	 * Order the given pages parent-first.
	 *
	 * @param ParsedPage[] $pages The pages, in visiting order.
	 *
	 * @return ParsedPage[] The pages with each known parent before its children.
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function order(array $pages): array {
		foreach ($pages as $page) {
			$this->visit(page: $page);
		}

		return $this->ordered;
	}//end order()

	/**
	 * Emit the page's parent chain first, then the page itself.
	 *
	 * @param ParsedPage $page The page to visit.
	 *
	 * @return void
	 */
	private function visit(ParsedPage $page): void {
		$key = strtolower(string: $page->pageId);
		if (isset($this->emitted[$key]) === true) {
			return;
		}

		$parentKey = $this->pendingParentKey(page: $page, key: $key);
		if ($parentKey !== null) {
			$this->visit(page: $this->byPageId[$parentKey]);
		}

		$this->emit(page: $page);
	}//end visit()

	/**
	 * The parent's key when that parent is known, is not the page itself and is not emitted yet.
	 *
	 * @param ParsedPage $page The page.
	 * @param string     $key  The page's lower-cased pageId.
	 *
	 * @return string|null The parent key to visit first, or null.
	 */
	private function pendingParentKey(ParsedPage $page, string $key): ?string {
		if ($page->parentPageId === null) {
			return null;
		}

		$parentKey = strtolower(string: $page->parentPageId);
		if ($parentKey !== $key
			&& isset($this->byPageId[$parentKey]) === true
			&& isset($this->emitted[$parentKey]) === false
		) {
			return $parentKey;
		}

		return null;
	}//end pendingParentKey()

	/**
	 * Append the page unless its pageId was emitted already.
	 *
	 * @param ParsedPage $page The page.
	 *
	 * @return void
	 */
	private function emit(ParsedPage $page): void {
		$key = strtolower(string: $page->pageId);
		if (isset($this->emitted[$key]) === true) {
			return;
		}

		$this->emitted[$key] = true;
		$this->ordered[]     = $page;
	}//end emit()
}//end class
