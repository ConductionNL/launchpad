<?php

/**
 * TileUpdater
 *
 * Service for applying tile-specific updates to widget placements.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Service for applying tile-specific updates to widget placements.
 */
class TileUpdater {
	/**
	 * Apply tile configuration to a new placement entity.
	 *
	 * @param WidgetPlacement $placement The placement entity.
	 * @param array $tileData The tile configuration data.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When `linkTarget` is not a link target.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function applyTileConfig(
		WidgetPlacement $placement,
		array $tileData,
	): void {
		$placement->setTileType('custom');
		$placement->setTileTitle(
			$tileData['title'] ?? 'New Tile'
		);
		$placement->setTileIcon(
			$tileData['icon'] ?? 'icon-link'
		);
		$placement->setTileIconType(
			$tileData['iconType'] ?? 'class'
		);
		$placement->setTileBackgroundColor(
			$tileData['bgColor'] ?? '#0082c9'
		);
		$placement->setTileTextColor(
			$tileData['txtColor'] ?? '#ffffff'
		);
		$placement->setTileLinkType(
			$tileData['linkType'] ?? 'app'
		);
		$placement->setTileLinkValue(
			$tileData['linkVal'] ?? ''
		);

		$linkTarget = TileLinkTarget::validated(value: ($tileData['linkTarget'] ?? null));
		if ($linkTarget !== null) {
			$placement->setContentArray(
				array_merge($placement->getContentArray(), ['linkTarget' => $linkTarget])
			);
		}
	}//end applyTileConfig()

	/**
	 * Apply tile-specific field updates to a placement.
	 *
	 * @param WidgetPlacement $placement The placement entity.
	 * @param array $data The update data.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When `content.linkTarget` is not a link target.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function applyTileUpdates(
		WidgetPlacement $placement,
		array $data,
	): void {
		// Launcher-tile-open-behaviour D1: the tile's link target rides in
		// content; anything but the three values is refused before saving.
		if (is_array($data['content'] ?? null) === true && array_key_exists('linkTarget', $data['content']) === true) {
			TileLinkTarget::validated(value: $data['content']['linkTarget'], field: 'content.linkTarget');
		}

		if (isset($data['tileTitle']) === true) {
			$placement->setTileTitle($data['tileTitle']);
		}

		if (isset($data['tileIcon']) === true) {
			$placement->setTileIcon($data['tileIcon']);
		}

		if (isset($data['tileIconType']) === true) {
			$placement->setTileIconType(
				$data['tileIconType']
			);
		}

		if (isset($data['tileBackgroundColor']) === true) {
			$placement->setTileBackgroundColor(
				$data['tileBackgroundColor']
			);
		}

		if (isset($data['tileTextColor']) === true) {
			$placement->setTileTextColor(
				$data['tileTextColor']
			);
		}

		if (isset($data['tileLinkType']) === true) {
			$placement->setTileLinkType(
				$data['tileLinkType']
			);
		}

		if (isset($data['tileLinkValue']) === true) {
			$placement->setTileLinkValue(
				$data['tileLinkValue']
			);
		}
	}//end applyTileUpdates()
}//end class
