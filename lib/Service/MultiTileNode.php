<?php

/**
 * MultiTileNode
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
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Converts multi-tile placements, and multi-tiles nested in wells and tabs,
 * into wells. Idempotent: a well is never changed again.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
 */
final class MultiTileNode {

	/**
	 * The retired multi-tile widget type.
	 */
	public const MULTI_TILE = 'app-tiles';

	/**
	 * The well's widget type.
	 */
	public const WELL = 'container';

	/**
	 * The multi-tile's defaults when a setting was never stored.
	 */
	private const DEFAULT_MOST_USED_LIMIT = 4;

	/**
	 * Convert one placement in place, its nested children included.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return int The number of multi-tiles converted.
	 *
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
	 */
	public function convertPlacement(WidgetPlacement $placement): int {
		[$type, $content, $changed] = $this->convertNode(type: (string) $placement->getWidgetId(), content: $placement->getContentArray());
		if ($changed === 0) {
			return 0;
		}

		// Entity setters resolve through __call, which reads $args[0].
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setWidgetId($type);
		$placement->setContentArray(content: $content);
		return $changed;
	}//end convertPlacement()

	/**
	 * Convert one node (a placement's type and content, or a child's).
	 *
	 * @param string               $type    The widget type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return array{0: string, 1: array<string, mixed>, 2: int} The type, the content and the number converted.
	 *
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
	 */
	public function convertNode(string $type, array $content): array {
		$changed = 0;
		if ($type === self::MULTI_TILE) {
			$type    = self::WELL;
			$content = $this->wellContent(content: $content);
			$changed++;
		}

		[$content, $nested] = $this->convertChildren(value: $content);
		return [$type, $content, $changed + $nested];
	}//end convertNode()

	/**
	 * A multi-tile's content as a well's: its app settings as the well's app
	 * list, or its fixed apps as App or link tiles; title and style kept.
	 *
	 * @param array<string, mixed> $content The multi-tile's content.
	 *
	 * @return array<string, mixed> The well's content.
	 */
	private function wellContent(array $content): array {
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
				'type'       => TileModeNode::TILE,
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

	/**
	 * Walk a content value and convert every child (an array with a `type`
	 * and a `content`) it holds, at any depth.
	 *
	 * @param array<mixed> $value The value.
	 *
	 * @return array{0: array<mixed>, 1: int} The value and the number converted.
	 */
	private function convertChildren(array $value): array {
		$changed = 0;
		foreach ($value as $key => $item) {
			if (is_array($item) === false) {
				continue;
			}

			[$item, $count] = $this->convertItem(item: $item);
			if ($count > 0) {
				$value[$key] = $item;
				$changed    += $count;
			}
		}

		return [$value, $changed];
	}//end convertChildren()

	/**
	 * Convert one nested item: a child as a node, any other array by walking it.
	 *
	 * @param array<mixed> $item The item.
	 *
	 * @return array{0: array<mixed>, 1: int} The item and the number converted.
	 */
	private function convertItem(array $item): array {
		if (is_string($item['type'] ?? null) === false || is_array($item['content'] ?? null) === false) {
			return $this->convertChildren(value: $item);
		}

		[$item['type'], $item['content'], $count] = $this->convertNode(type: $item['type'], content: $item['content']);
		return [$item, $count];
	}//end convertItem()
}//end class
