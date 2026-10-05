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
	 * Request keys a tile update may change, with the placement setter for each.
	 */
	private const TILE_SETTERS = [
		'tileTitle'           => 'setTileTitle',
		'tileIcon'            => 'setTileIcon',
		'tileIconType'        => 'setTileIconType',
		'tileBackgroundColor' => 'setTileBackgroundColor',
		'tileTextColor'       => 'setTileTextColor',
		'tileLinkType'        => 'setTileLinkType',
		'tileLinkValue'       => 'setTileLinkValue',
	];

	/**
	 * Constructor.
	 *
	 * @param TileLaunchValidator $launchValidator Checks link types and
	 *                                             addresses (REQ-TLT-001
	 *                                             to REQ-TLT-003).
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function __construct(
		private readonly TileLaunchValidator $launchValidator,
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
		// REQ-TLT-001 to REQ-TLT-003: an unknown type or a refused address
		// stops the create before anything is set.
		$this->launchValidator->assertValidLink(
			type: ($tileData['linkType'] ?? 'app'),
			value: ($tileData['linkVal'] ?? '')
		);

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

		foreach (self::TILE_SETTERS as $key => $setter) {
			if (isset($data[$key]) === true) {
				$placement->$setter($data[$key]);
			}
		}

		// REQ-TLT-001 to REQ-TLT-003: when the link or the content changes,
		// the tile as it would be stored must be valid; the caller writes
		// nothing when this throws.
		if (isset($data['tileLinkType']) === true || isset($data['tileLinkValue']) === true || is_array(value: $content) === true) {
			$this->launchValidator->assertValidTile(
				type: $placement->getTileLinkType(),
				value: $placement->getTileLinkValue(),
				content: $placement->getContentArray()
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

		if (self::isSafeInternalUrl(value: $value) === false) {
			throw new InvalidArgumentException(message: 'The office network address must be an http or https address, or a path');
		}
	}//end assertValidInternalUrl()

	/**
	 * An http or https address with a host, or a path on this Nextcloud.
	 *
	 * @param mixed $value The submitted, non-empty value.
	 *
	 * @return boolean
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	private static function isSafeInternalUrl(mixed $value): bool {
		if (is_string(value: $value) === false || strlen(string: $value) > 2048) {
			return false;
		}

		if (str_starts_with(haystack: $value, needle: '/') === true && str_starts_with(haystack: $value, needle: '//') === false) {
			return true;
		}

		$scheme = strtolower(string: (string)parse_url(url: $value, component: PHP_URL_SCHEME));
		$host = (string)parse_url(url: $value, component: PHP_URL_HOST);

		return in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === true && $host !== '';
	}//end isSafeInternalUrl()
}//end class
