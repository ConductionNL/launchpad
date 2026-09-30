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
		// REQ-TIA-002: the address on the office network follows the main
		// address rules; anything else refuses the whole update.
		$content = $data['content'] ?? null;
		if (is_array(value: $content) === true && array_key_exists(key: 'internalUrl', array: $content) === true) {
			self::assertValidInternalUrl(value: $content['internalUrl']);
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

	/**
	 * Check a tile's address on the office network: empty, an http or https
	 * address with a host, or a path on this Nextcloud (REQ-TIA-002).
	 *
	 * @param mixed $value The submitted value.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the value is not such an address.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public static function assertValidInternalUrl(mixed $value): void {
		if ($value === null || $value === '') {
			return;
		}

		if (is_string(value: $value) === false || strlen(string: $value) > 2048) {
			throw new InvalidArgumentException(message: 'The office network address must be an http or https address, or a path');
		}

		if (str_starts_with(haystack: $value, needle: '/') === true && str_starts_with(haystack: $value, needle: '//') === false) {
			return;
		}

		$scheme = strtolower(string: (string)parse_url(url: $value, component: PHP_URL_SCHEME));
		$host = (string)parse_url(url: $value, component: PHP_URL_HOST);
		if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
			throw new InvalidArgumentException(message: 'The office network address must be an http or https address, or a path');
		}
	}//end assertValidInternalUrl()
}//end class
