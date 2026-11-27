<?php
/**
 * TileModeNode
 *
 * The App or link tile (REQ-APPTILE-006): a retired `app-tile` placement or
 * group child becomes a `tile` in app mode with the same id and everything
 * else, and a `tile` without a mode becomes a link tile.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Converts placements and the children stored inside groups and tabs to the
 * App or link tile. Idempotent: a converted tile is never changed again.
 *
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */
final class TileModeNode {

	/**
	 * The retired widget type of an app tile.
	 */
	public const RETIRED_APP_TILE = 'app-tile';

	/**
	 * The widget type of the App or link tile.
	 */
	public const TILE = 'tile';

	/**
	 * Convert one placement in place, its nested children included.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return int The number of tiles converted.
	 *
	 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
	 */
	public function convertPlacement(WidgetPlacement $placement): int {
		$type = (string) $placement->getWidgetId();
		[$type, $content, $changed] = $this->convertNode(type: $type, content: $placement->getContentArray());
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
	 * @return array{0: string, 1: array<string, mixed>, 2: int} The type, the content and the number of tiles converted.
	 *
	 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
	 */
	public function convertNode(string $type, array $content): array {
		$changed = 0;
		if ($type === self::RETIRED_APP_TILE) {
			$type    = self::TILE;
			$content = $this->appTileContent(content: $content);
			$changed++;
		}

		if ($type === self::TILE && isset($content['mode']) === false) {
			$content['mode'] = 'link';
			$changed++;
		}

		[$content, $nested] = $this->convertChildren(value: $content);
		return [$type, $content, $changed + $nested];
	}//end convertNode()

	/**
	 * The content of an app tile as an App or link tile in app mode: its
	 * palette colour also as a theme token, everything else kept.
	 *
	 * @param array<string, mixed> $content The app tile's content.
	 *
	 * @return array<string, mixed> The tile's content.
	 */
	private function appTileContent(array $content): array {
		$content['mode'] = 'app';
		$colour          = $content['colour'] ?? '';
		if (empty($content['colourToken']) === true && is_string($colour) === true && $colour !== '') {
			$content['colourToken'] = '--sc-' . $colour;
		}

		return $content;
	}//end appTileContent()

	/**
	 * Convert one nested item: a child (an array with a `type` and a
	 * `content`) as a node, any other array by walking it.
	 *
	 * @param array<mixed> $item The item.
	 *
	 * @return array{0: array<mixed>, 1: int} The item and the number of tiles converted.
	 */
	private function convertItem(array $item): array {
		if (is_string($item['type'] ?? null) === false || is_array($item['content'] ?? null) === false) {
			return $this->convertChildren(value: $item);
		}

		[$item['type'], $item['content'], $count] = $this->convertNode(type: $item['type'], content: $item['content']);
		return [$item, $count];
	}//end convertItem()

	/**
	 * Walk a content value and convert every child (an array with a `type`
	 * and a `content`) it holds, at any depth.
	 *
	 * @param array<mixed> $value The value.
	 *
	 * @return array{0: array<mixed>, 1: int} The value and the number of tiles converted.
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
}//end class
