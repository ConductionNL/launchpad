<?php

/**
 * BookmarkImportPlan — what a bookmark import will create, and what it skips.
 *
 * The browser parses the bookmarks file and posts the chosen folders and loose
 * bookmarks as JSON. This class checks every address, keeps only web
 * addresses, flattens what it is given into containers (one per folder) and
 * loose tiles, and lays them out below everything already on the dashboard.
 * It holds no state beyond the request and touches no storage.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * The containers, loose tiles and skipped bookmarks of one import.
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */
final class BookmarkImportPlan {
	/**
	 * The most bookmarks one import may carry (REQ-BMI-004).
	 */
	public const MAX_BOOKMARKS = 2000;

	/**
	 * The longest address a tile keeps.
	 */
	public const MAX_URL_LENGTH = 2048;

	/**
	 * The longest title a tile or container keeps.
	 */
	public const MAX_TITLE_LENGTH = 255;

	/**
	 * Why a bookmark that is not a web address is skipped (REQ-BMI-002).
	 */
	public const REASON_NOT_WEB = 'only web addresses can become tiles';

	/**
	 * Dashboard columns, and how many containers and tiles share a row.
	 */
	private const COLUMNS = 12;
	private const CONTAINER_WIDTH = 4;
	private const TILE_SIZE = 2;

	/**
	 * Constructor.
	 *
	 * @param list<array{title: string, bookmarks: list<array{title: string, url: string}>}> $containers The folders.
	 * @param list<array{title: string, url: string}>                                         $tiles      The loose bookmarks.
	 * @param list<array{title: string, url: string, reason: string}>                          $skipped    What is not imported.
	 */
	private function __construct(
		private readonly array $containers,
		private readonly array $tiles,
		private readonly array $skipped,
	) {
	}//end __construct()

	/**
	 * Build the plan from the posted folders and loose bookmarks.
	 *
	 * @param mixed $folders   A list of `{name, bookmarks: [{title, url}]}`.
	 * @param mixed $bookmarks A list of `{title, url}`.
	 *
	 * @return self The plan.
	 *
	 * @throws InvalidArgumentException When the shape is wrong, nothing is chosen, or the import is too large.
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-checks-every-address-req-bmi-002
	 */
	public static function fromRequest(mixed $folders, mixed $bookmarks): self {
		$folders = self::listOf(value: ($folders ?? []), what: 'folders');
		$bookmarks = self::listOf(value: ($bookmarks ?? []), what: 'bookmarks');

		$total = count($bookmarks);
		foreach ($folders as $index => $folder) {
			if (is_array($folder) === false) {
				throw new InvalidArgumentException('Each folder must be an object with a name and bookmarks.');
			}

			$folders[$index]['bookmarks'] = self::listOf(value: ($folder['bookmarks'] ?? []), what: 'folder bookmarks');
			$total += count($folders[$index]['bookmarks']);
		}

		if ($total === 0) {
			throw new InvalidArgumentException('Choose at least one bookmark to import.');
		}

		if ($total > self::MAX_BOOKMARKS) {
			throw new InvalidArgumentException('An import holds at most ' . self::MAX_BOOKMARKS . ' bookmarks.');
		}

		$skipped = [];
		$containers = [];
		foreach ($folders as $folder) {
			$kept = self::keep(bookmarks: $folder['bookmarks'], skipped: $skipped);
			if ($kept !== []) {
				$containers[] = ['title' => self::title(value: ($folder['name'] ?? ''), fallback: 'Bookmarks'), 'bookmarks' => $kept];
			}
		}

		return new self(containers: $containers, tiles: self::keep(bookmarks: $bookmarks, skipped: $skipped), skipped: $skipped);
	}//end fromRequest()

	/**
	 * Whether an address may become a tile: http or https, with a host and
	 * no credentials in it, and not longer than a tile keeps.
	 *
	 * @param string $url The address.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-checks-every-address-req-bmi-002
	 */
	public static function isWebAddress(string $url): bool {
		if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
			return false;
		}

		$parts = parse_url($url);
		if (is_array($parts) === false) {
			return false;
		}

		$scheme = strtolower((string)($parts['scheme'] ?? ''));
		if (in_array($scheme, ['http', 'https'], true) === false || (string)($parts['host'] ?? '') === '') {
			return false;
		}

		return isset($parts['user']) === false && isset($parts['pass']) === false;
	}//end isWebAddress()

	/**
	 * How many dashboard placements the import adds: one per container and
	 * one per loose tile. Tiles inside a container are its content, as for
	 * any container (REQ-BMI-003).
	 *
	 * @return int
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-respects-the-widget-quota-as-one-operation-req-bmi-003
	 */
	public function placementCount(): int {
		return count($this->containers) + count($this->tiles);
	}//end placementCount()

	/**
	 * The bookmarks that are not imported, each with its reason.
	 *
	 * @return list<array{title: string, url: string, reason: string}>
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-checks-every-address-req-bmi-002
	 */
	public function skipped(): array {
		return $this->skipped;
	}//end skipped()

	/**
	 * The placements to create, laid out from the row below the dashboard's
	 * lowest edge: containers three to a row, then loose tiles six to a row
	 * (REQ-BMI-001, bottom-append).
	 *
	 * @param int $bottom The dashboard's lowest occupied edge (`gridY + gridHeight`).
	 *
	 * @return list<array<string, mixed>> Each `{widgetId, gridX, gridY, gridWidth, gridHeight}` with
	 *                                    `content` for a container or `tile` (`{title, url}`) for a tile.
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-users-can-import-a-browser-bookmarks-file-as-tiles-req-bmi-001
	 */
	public function layout(int $bottom): array {
		$out = [];
		$row = max(0, $bottom);
		foreach (array_chunk($this->containers, intdiv(self::COLUMNS, self::CONTAINER_WIDTH)) as $chunk) {
			$height = 0;
			foreach ($chunk as $index => $container) {
				$containerHeight = self::containerHeight(count: count($container['bookmarks']));
				$height = max($height, $containerHeight);
				$out[] = [
					'widgetId' => 'container',
					'gridX' => ($index * self::CONTAINER_WIDTH),
					'gridY' => $row,
					'gridWidth' => self::CONTAINER_WIDTH,
					'gridHeight' => $containerHeight,
					'content' => ['title' => $container['title'], 'placements' => self::children(bookmarks: $container['bookmarks'])],
				];
			}

			$row += $height;
		}

		foreach (array_chunk($this->tiles, intdiv(self::COLUMNS, self::TILE_SIZE)) as $chunk) {
			foreach ($chunk as $index => $tile) {
				$out[] = [
					'widgetId' => 'tile',
					'gridX' => ($index * self::TILE_SIZE),
					'gridY' => $row,
					'gridWidth' => self::TILE_SIZE,
					'gridHeight' => self::TILE_SIZE,
					'tile' => $tile,
				];
			}

			$row += self::TILE_SIZE;
		}

		return $out;
	}//end layout()

	/**
	 * The link children of a container, two to a row of its four-column grid.
	 *
	 * @param list<array{title: string, url: string}> $bookmarks The folder's bookmarks.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function children(array $bookmarks): array {
		$children = [];
		foreach (array_values($bookmarks) as $index => $bookmark) {
			$children[] = [
				'type' => 'link',
				'content' => ['label' => $bookmark['title'], 'url' => $bookmark['url'], 'actionType' => 'external'],
				'gridX' => (($index % 2) * self::TILE_SIZE),
				'gridY' => (intdiv($index, 2) * self::TILE_SIZE),
				'gridWidth' => self::TILE_SIZE,
				'gridHeight' => self::TILE_SIZE,
			];
		}

		return $children;
	}//end children()

	/**
	 * The outer rows a container needs: a title row, and its children's rows
	 * of 40 px inner cells in 60 px outer cells.
	 *
	 * @param int $count How many bookmarks it holds.
	 *
	 * @return int
	 */
	private static function containerHeight(int $count): int {
		return 1 + (int)ceil((intdiv($count + 1, 2) * self::TILE_SIZE * 40) / 60);
	}//end containerHeight()

	/**
	 * The web-address bookmarks of a list; the rest go to `$skipped`.
	 *
	 * @param array<mixed>                                             $bookmarks The posted bookmarks.
	 * @param list<array{title: string, url: string, reason: string}> $skipped   Collects what is skipped.
	 *
	 * @return list<array{title: string, url: string}>
	 */
	private static function keep(array $bookmarks, array &$skipped): array {
		$kept = [];
		foreach ($bookmarks as $bookmark) {
			$url = '';
			$title = '';
			if (is_array($bookmark) === true && is_scalar($bookmark['url'] ?? null) === true) {
				$url = trim((string)$bookmark['url']);
			}

			if (is_array($bookmark) === true) {
				$title = self::title(value: ($bookmark['title'] ?? ''), fallback: $url);
			}

			if (self::isWebAddress(url: $url) === false) {
				$skipped[] = ['title' => $title, 'url' => mb_substr($url, 0, self::MAX_URL_LENGTH), 'reason' => self::REASON_NOT_WEB];
				continue;
			}

			$kept[] = ['title' => $title, 'url' => $url];
		}

		return $kept;
	}//end keep()

	/**
	 * A trimmed, bounded title, or the fallback when it is empty.
	 *
	 * @param mixed  $value    The posted title.
	 * @param string $fallback What to use when it is empty.
	 *
	 * @return string
	 */
	private static function title(mixed $value, string $fallback): string {
		$title = '';
		if (is_scalar($value) === true) {
			$title = trim((string)$value);
		}

		if ($title === '') {
			$title = $fallback;
		}

		return mb_substr($title, 0, self::MAX_TITLE_LENGTH);
	}//end title()

	/**
	 * The value as a list, or a refusal naming what it should have been.
	 *
	 * @param mixed  $value The posted value.
	 * @param string $what  What it is, for the message.
	 *
	 * @return list<mixed>
	 *
	 * @throws InvalidArgumentException When it is not a list.
	 */
	private static function listOf(mixed $value, string $what): array {
		if (is_array($value) === false || array_is_list($value) === false) {
			throw new InvalidArgumentException('The ' . $what . ' must be a list.');
		}

		return $value;
	}//end listOf()
}//end class
