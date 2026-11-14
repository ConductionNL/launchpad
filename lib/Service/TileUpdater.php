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

		if (isset($data['content']) === true
			&& is_array($data['content']) === true
			&& array_key_exists('internalUrl', $data['content']) === true
		) {
			$this->assertValidInternalUrl(url: $data['content']['internalUrl']);
		}
	}//end applyTileUpdates()

	/**
	 * Refuse an office-network address that is not an http or https address or a path on this instance.
	 *
	 * Empty clears the address. Anything else, `javascript:` and `data:`
	 * included, is refused before the placement is saved.
	 *
	 * @param mixed $url The `content.internalUrl` value.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the address is not allowed; the caller answers 400.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-a-tile-can-hold-an-office-network-address-req-tia-002
	 */
	public function assertValidInternalUrl(mixed $url): void {
		if ($url === null || $url === '') {
			return;
		}

		if (is_string($url) === false || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
			throw new InvalidArgumentException(message: 'The address on the office network is not a valid address');
		}

		// A path on this instance, but not a protocol-relative `//host`.
		if (str_starts_with(haystack: $url, needle: '/') === true && str_starts_with(haystack: $url, needle: '//') === false) {
			return;
		}

		$scheme = strtolower((string)parse_url(url: $url, component: PHP_URL_SCHEME));
		if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === true
			&& filter_var(value: $url, filter: FILTER_VALIDATE_URL) !== false
		) {
			return;
		}

		throw new InvalidArgumentException(message: 'The address on the office network is not a valid address');
	}//end assertValidInternalUrl()
}//end class
