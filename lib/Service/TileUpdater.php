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

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Service for applying tile-specific updates to widget placements.
 */
class TileUpdater {
	/**
	 * Constructor.
	 *
	 * @param TileLinkValidator|null $links Checks the program, remote-desktop and sso link types; without it those types are refused.
	 */
	public function __construct(
		private readonly ?TileLinkValidator $links = null,
	) {
	}//end __construct()

	/**
	 * Apply tile configuration to a new placement entity.
	 *
	 * @param WidgetPlacement $placement The placement entity.
	 * @param array $tileData The tile configuration data.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/tiles/spec.md
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
		if (is_array($tileData['content'] ?? null) === true) {
			$placement->setContentArray($tileData['content']);
		}

		$this->checkLaunchLink(placement: $placement);
	}//end applyTileConfig()

	/**
	 * Apply tile-specific field updates to a placement.
	 *
	 * @param WidgetPlacement $placement The placement entity.
	 * @param array $data The update data.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function applyTileUpdates(
		WidgetPlacement $placement,
		array $data,
	): void {
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

		$this->checkLaunchLink(placement: $placement);
	}//end applyTileUpdates()

	/**
	 * Check a program, remote-desktop or sso tile as it will be saved, and
	 * store the link value the check returns. Other link types are left as
	 * they are.
	 *
	 * The check reads the placement's final link type, link value and
	 * content, so a save that changes only one of them is checked too.
	 *
	 * @param WidgetPlacement $placement The placement, with this save's changes applied.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the tile may not be saved like this (HTTP 400).
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	private function checkLaunchLink(WidgetPlacement $placement): void {
		$type = $placement->getTileLinkType();
		if (TileLinkValidator::isLaunchType(type: $type) === false) {
			return;
		}

		if ($this->links === null) {
			throw new InvalidArgumentException(message: 'The link type "' . $type . '" cannot be checked here.');
		}

		$placement->setTileLinkValue(
			$this->links->check(
				type: (string)$type,
				value: (string)($placement->getTileLinkValue() ?? ''),
				content: $placement->getContentArray()
			)
		);
	}//end checkLaunchLink()
}//end class
