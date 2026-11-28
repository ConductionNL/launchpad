<?php

/**
 * MultiTileWell
 *
 * Turns the retired multi-tile widget (`app-tiles`, "Tiles for several apps
 * at once") into a well (`container`) that lists each viewer's apps
 * (layout-widgets-replace-multi-tile D2b, REQ-CONT-009). The well takes the
 * multi-tile's settings as its app list: `pinned` and `exclude` as they were,
 * `mostUsedLimit` as it was while "most used" was on (0 otherwise), and the
 * filter as it was. Nothing is resolved here, so each viewer keeps seeing
 * their own apps. A multi-tile with its own fixed list of apps becomes a
 * well of one App or link tile per app, in the same order. Everything else
 * on the placement (id, cell, size, order, rules, style, title) stays.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-5
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The well a multi-tile becomes; {@see TileModeNode} converts the type.
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-5
 */
final class MultiTileWell {

	/**
	 * The multi-tile's most-used limit when it was never stored.
	 */
	private const DEFAULT_MOST_USED_LIMIT = 4;

	/**
	 * The App or link tile's widget type.
	 */
	private const TILE = 'tile';

	/**
	 * A multi-tile's content as a well's: its app settings as the well's app
	 * list, or its fixed apps as App or link tiles; title and style kept.
	 *
	 * @param array<string, mixed> $content The multi-tile's content.
	 *
	 * @return array<string, mixed> The well's content.
	 *
	 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-5
	 */
	public function wellContent(array $content): array {
		$well = array_diff_key($content, array_flip(['pinned', 'exclude', 'showFilter', 'showMostUsed', 'mostUsedLimit', 'apps', 'status']));
		$well['placements'] = [];
		if (is_array($content['apps'] ?? null) === true && $content['apps'] !== []) {
			$well['placements'] = $this->fixedAppTiles(apps: $content['apps']);
			return $well;
		}

		$well['appList'] = [
			'pinned'        => $this->ids(value: $content['pinned'] ?? []),
			'exclude'       => $this->ids(value: $content['exclude'] ?? []),
			'mostUsedLimit' => $this->mostUsedLimit(content: $content),
			'showFilter'    => (($content['showFilter'] ?? true) !== false),
		];
		return $well;
	}//end wellContent()

	/**
	 * The well's most-used limit: the multi-tile's while "most used" was on, else 0.
	 *
	 * @param array<string, mixed> $content The multi-tile's content.
	 *
	 * @return int The limit, 0 for none.
	 */
	private function mostUsedLimit(array $content): int {
		if (($content['showMostUsed'] ?? true) === false) {
			return 0;
		}

		return max(0, (int) ($content['mostUsedLimit'] ?? self::DEFAULT_MOST_USED_LIMIT));
	}//end mostUsedLimit()

	/**
	 * A stored list of app ids.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return list<string> The ids.
	 */
	private function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter(array_map(callback: 'strval', array: $value), callback: static fn (string $id): bool => $id !== ''));
	}//end ids()

	/**
	 * A multi-tile's fixed apps as App or link tiles in app mode, in order,
	 * one cell wide and two high, row by row on the well's four columns.
	 *
	 * @param array<mixed> $apps The `{id, ...}` entries.
	 *
	 * @return list<array<string, mixed>> The child placements.
	 */
	private function fixedAppTiles(array $apps): array {
		$tiles = [];
		foreach (array_values($apps) as $index => $app) {
			$appId = $this->appIdOf(app: $app);

			if ($appId === '') {
				continue;
			}

			$tiles[] = [
				'type'       => self::TILE,
				'gridX'      => (count($tiles) % 4),
				'gridY'      => (intdiv(count($tiles), 4) * 2),
				'gridWidth'  => 1,
				'gridHeight' => 2,
				'uuid'       => 'app-tiles-' . $index . '-' . $appId,
				'content'    => ['mode' => 'app', 'appId' => $appId],
			];
		}

		return $tiles;
	}//end fixedAppTiles()

	/**
	 * The id of one entry of a fixed app list: `{id}` or a plain id.
	 *
	 * @param mixed $app The entry.
	 *
	 * @return string The id, '' for none.
	 */
	private function appIdOf(mixed $app): string {
		if (is_array($app) === true) {
			return (string) ($app['id'] ?? '');
		}

		if (is_string($app) === true) {
			return $app;
		}

		return '';
	}//end appIdOf()
}//end class
