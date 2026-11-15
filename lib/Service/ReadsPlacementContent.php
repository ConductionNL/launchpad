<?php

/**
 * ReadsPlacementContent
 *
 * Reads the widget config stored on a placement.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Reads a placement's `content` config, with the legacy
 * `style_config.content` slot as fallback for rows from before the column.
 *
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
trait ReadsPlacementContent {
	/**
	 * Read the placement's widget config.
	 *
	 * @param object $placement The {@see \OCA\LaunchPad\Db\WidgetPlacement} entity.
	 *
	 * @return array<string,mixed> The config, or an empty array.
	 *
	 * @spec openspec/specs/live-data-tile-widget/spec.md
	 */
	private function readPlacementConfig(object $placement): array {
		if (method_exists(object_or_class: $placement, method: 'getContentArray') === true) {
			$content = $placement->getContentArray();
			if (is_array(value: $content) === true && $content !== []) {
				return $content;
			}
		}

		if (method_exists(object_or_class: $placement, method: 'getStyleConfigArray') === true) {
			$legacy = $placement->getStyleConfigArray();
			if (isset($legacy['content']) === true && is_array(value: $legacy['content']) === true) {
				return $legacy['content'];
			}

			if (is_array(value: $legacy) === true) {
				return $legacy;
			}
		}

		return [];
	}//end readPlacementConfig()
}//end trait
