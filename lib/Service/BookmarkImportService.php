<?php

/**
 * BookmarkImportService
 *
 * Imports the bookmarks a person chose from their browser's bookmarks file
 * (launcher-bookmark-import, REQ-BMI-001..003). The browser parses the file
 * and sends a clean list; this service keeps only web addresses, checks the
 * widget quota once for the whole import, and creates a container of link
 * tiles per folder plus a tile per loose bookmark, all in one transaction,
 * appended below the lowest widget.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IDBConnection;
use Throwable;

/**
 * Bookmark import.
 *
 * @spec openspec/specs/tiles/spec.md
 */
class BookmarkImportService {
	/**
	 * Most bookmarks one import may carry (REQ-BMI-004, also checked here).
	 *
	 * @var int
	 */
	public const MAX_BOOKMARKS = 2000;

	/**
	 * Outer grid columns the loose tiles fill.
	 *
	 * @var int
	 */
	private const GRID_COLUMNS = 12;

	/**
	 * Tile size in grid cells, outer and inner.
	 *
	 * @var int
	 */
	private const TILE_SIZE = 2;

	/**
	 * Container width in outer columns; its inner grid has 4 columns.
	 *
	 * @var int
	 */
	private const CONTAINER_WIDTH = 4;

	/**
	 * Constructor.
	 *
	 * @param PlacementService $placements Creates placements (with the per-placement quota check).
	 * @param WidgetPlacementMapper $placementMapper Reads the current layout for the bottom row.
	 * @param QuotaService $quota Checks the whole import against the widget quota.
	 * @param IDBConnection $db Transaction.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function __construct(
		private readonly PlacementService $placements,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly QuotaService $quota,
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Import the chosen folders and loose bookmarks.
	 *
	 * @param int $dashboardId The dashboard (the caller checked add rights).
	 * @param array $folders `[{name, bookmarks: [{title, url}]}]`.
	 * @param array $bookmarks Loose `[{title, url}]`.
	 *
	 * @return array{placements: array, containers: int, tiles: int, skipped: array}
	 *
	 * @throws InvalidArgumentException When the import carries too many bookmarks.
	 * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the dashboard has no room.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function import(int $dashboardId, array $folders, array $bookmarks): array {
		$skipped = [];
		$cleanFolders = [];
		$total = 0;
		foreach ($folders as $folder) {
			$items = $this->keepWebAddresses(raw: (array)($folder['bookmarks'] ?? []), skipped: $skipped, total: $total);
			if ($items !== []) {
				$cleanFolders[] = ['name' => $this->folderName(raw: $folder['name'] ?? ''), 'bookmarks' => $items];
			}
		}

		$loose = $this->keepWebAddresses(raw: $bookmarks, skipped: $skipped, total: $total);
		if ($total > self::MAX_BOOKMARKS) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_BOOKMARKS . ' bookmarks per import');
		}

		// REQ-BMI-003: one check for the whole import, before anything is written.
		$this->quota->assertRoomFor(dashboardId: $dashboardId, count: count(value: $cleanFolders) + count(value: $loose));

		$created = [];
		$this->db->beginTransaction();
		try {
			$created = $this->createAll(dashboardId: $dashboardId, folders: $cleanFolders, loose: $loose);
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$tiles = count(value: $loose);
		foreach ($cleanFolders as $folder) {
			$tiles += count(value: $folder['bookmarks']);
		}

		return [
			'placements' => array_map(callback: static fn ($placement) => $placement->jsonSerialize(), array: $created),
			'containers' => count(value: $cleanFolders),
			'tiles' => $tiles,
			'skipped' => $skipped,
		];
	}//end import()

	/**
	 * Create the containers, then the loose tiles, below the lowest widget.
	 *
	 * @param int $dashboardId The dashboard.
	 * @param array $folders Clean folders.
	 * @param array $loose Clean loose bookmarks.
	 *
	 * @return array The created placements.
	 */
	private function createAll(int $dashboardId, array $folders, array $loose): array {
		$created = [];
		$row = $this->bottomRow(dashboardId: $dashboardId);
		$column = 0;
		$rowHeight = 0;
		foreach ($folders as $folder) {
			$height = $this->containerHeight(count: count(value: $folder['bookmarks']));
			if ($column + self::CONTAINER_WIDTH > self::GRID_COLUMNS) {
				$column = 0;
				$row += $rowHeight;
				$rowHeight = 0;
			}

			$created[] = $this->placements->addWidget(
				dashboardId: $dashboardId,
				widgetId: 'container',
				gridX: $column,
				gridY: $row,
				gridWidth: self::CONTAINER_WIDTH,
				gridHeight: $height,
				content: [
					'title' => $folder['name'],
					'backgroundColor' => 'transparent',
					'padding' => 'medium',
					'placements' => $this->children(bookmarks: $folder['bookmarks']),
				]
			);
			$column += self::CONTAINER_WIDTH;
			$rowHeight = max($rowHeight, $height);
		}//end foreach

		if ($loose !== []) {
			$row += $rowHeight;
			$column = 0;
		}

		foreach ($loose as $bookmark) {
			if ($column + self::TILE_SIZE > self::GRID_COLUMNS) {
				$column = 0;
				$row += self::TILE_SIZE;
			}

			$created[] = $this->placements->addTileFromArray(
				dashboardId: $dashboardId,
				tileData: [
					'title' => $bookmark['title'],
					'icon' => 'icon-link',
					'iconType' => 'class',
					'linkType' => 'url',
					'linkVal' => $bookmark['url'],
					'gridX' => $column,
					'gridY' => $row,
					'gridWidth' => self::TILE_SIZE,
					'gridHeight' => self::TILE_SIZE,
				]
			);
			$column += self::TILE_SIZE;
		}//end foreach

		return $created;
	}//end createAll()

	/**
	 * Keep bookmarks with an http or https address; list the rest as skipped.
	 *
	 * @param array $raw Raw `[{title, url}]`.
	 * @param array $skipped Skipped list, appended to.
	 * @param int $total Running count of every bookmark seen.
	 *
	 * @return array<int, array{title: string, url: string}>
	 */
	private function keepWebAddresses(array $raw, array &$skipped, int &$total): array {
		$kept = [];
		foreach ($raw as $bookmark) {
			$total++;
			$url = trim(string: (string)($bookmark['url'] ?? ''));
			$title = mb_substr(string: trim(string: (string)($bookmark['title'] ?? '')), start: 0, length: 255);
			if (self::isWebAddress(url: $url) === false) {
				$skipped[] = ['title' => $title, 'url' => mb_substr(string: $url, start: 0, length: 255), 'reason' => 'not-a-web-address'];
				continue;
			}

			if ($title === '') {
				$title = (string)parse_url(url: $url, component: PHP_URL_HOST);
			}

			$kept[] = ['title' => $title, 'url' => $url];
		}

		return $kept;
	}//end keepWebAddresses()

	/**
	 * Whether an address is an http or https web address with a host
	 * (REQ-BMI-002). A tile only opens the address in the person's own
	 * browser, so the server never fetches it and intranet hosts are fine.
	 *
	 * @param string $url The address.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public static function isWebAddress(string $url): bool {
		if ($url === '' || strlen(string: $url) > 2048) {
			return false;
		}

		$scheme = strtolower(string: (string)parse_url(url: $url, component: PHP_URL_SCHEME));
		if ($scheme !== 'http' && $scheme !== 'https') {
			return false;
		}

		return (string)parse_url(url: $url, component: PHP_URL_HOST) !== '';
	}//end isWebAddress()

	/**
	 * A folder name, or "Bookmarks" when empty.
	 *
	 * @param mixed $raw The name.
	 *
	 * @return string
	 */
	private function folderName(mixed $raw): string {
		$name = mb_substr(string: trim(string: (string)$raw), start: 0, length: 255);
		if ($name === '') {
			return 'Bookmarks';
		}

		return $name;
	}//end folderName()

	/**
	 * Link tiles for a container's inner grid: 2 by 2, two per row.
	 *
	 * @param array $bookmarks Clean bookmarks.
	 *
	 * @return array Child placements.
	 */
	private function children(array $bookmarks): array {
		$children = [];
		foreach (array_values(array: $bookmarks) as $index => $bookmark) {
			$children[] = [
				'uuid' => bin2hex(string: random_bytes(length: 16)),
				'type' => 'link',
				'content' => [
					'label' => $bookmark['title'],
					'url' => $bookmark['url'],
					'actionType' => 'external',
					'icon' => '',
					'displayMode' => 'button',
				],
				'gridX' => ($index % 2) * self::TILE_SIZE,
				'gridY' => intdiv(num1: $index, num2: 2) * self::TILE_SIZE,
				'gridWidth' => self::TILE_SIZE,
				'gridHeight' => self::TILE_SIZE,
			];
		}

		return $children;
	}//end children()

	/**
	 * Outer height of a container holding `$count` tiles: inner rows of
	 * 2 cells of 40 px, in outer cells of 60 px, plus one for the title.
	 *
	 * @param int $count Number of tiles.
	 *
	 * @return int Outer grid cells.
	 */
	private function containerHeight(int $count): int {
		$innerRows = (int)ceil(num: $count / 2);
		return max(2, (int)ceil(num: ($innerRows * self::TILE_SIZE * 40) / 60) + 1);
	}//end containerHeight()

	/**
	 * The first free row below every widget on the dashboard.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return int The row.
	 */
	private function bottomRow(int $dashboardId): int {
		$bottom = 0;
		foreach ($this->placementMapper->findByDashboardId(dashboardId: $dashboardId) as $placement) {
			$bottom = max($bottom, (int)$placement->getGridY() + (int)$placement->getGridHeight());
		}

		return $bottom;
	}//end bottomRow()
}//end class
