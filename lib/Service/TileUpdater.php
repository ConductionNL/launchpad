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
	 * @throws InvalidArgumentException When the office-network address, the link target or the launch link is not allowed (HTTP 400).
	 *
	 * @spec openspec/specs/tiles/spec.md
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-a-tile-can-hold-an-office-network-address-req-tia-002
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
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
			if (array_key_exists('internalUrl', $tileData['content']) === true) {
				$this->assertValidInternalUrl(url: $tileData['content']['internalUrl']);
			}

			$placement->setContentArray($tileData['content']);
		}

		$linkTarget = $this->validatedLinkTarget(value: ($tileData['linkTarget'] ?? null), field: 'linkTarget');
		if ($linkTarget !== null) {
			$placement->setContentArray(
				array_merge($placement->getContentArray(), ['linkTarget' => $linkTarget])
			);
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
	 * @throws \InvalidArgumentException When `content.linkTarget` is not a link target.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function applyTileUpdates(
		WidgetPlacement $placement,
		array $data,
	): void {
		$this->checkContentUpdate(content: ($data['content'] ?? null));

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
	 * Check the content of a new `tile` widget (a link tile that keeps its
	 * data in content) the way the tile route checks a tile: its link target,
	 * its office-network address and, for a program, remote-desktop or sso
	 * tile, its launch link.
	 *
	 * @param WidgetPlacement $placement The new placement, with its content set.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the content may not be saved like this (HTTP 400).
	 *
	 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
	 */
	public function checkTileWidget(WidgetPlacement $placement): void {
		if (self::keepsLinkInContent(placement: $placement) === false) {
			return;
		}

		$this->checkContentUpdate(content: $placement->getContentArray());
		$this->checkLaunchLink(placement: $placement);
	}//end checkTileWidget()

	/**
	 * Whether a placement is a `tile` widget that keeps its link in content:
	 * no legacy `tileType` and not an app-mode tile.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
	 */
	private static function keepsLinkInContent(WidgetPlacement $placement): bool {
		return $placement->getWidgetId() === 'tile'
			&& $placement->getTileType() === null
			&& ($placement->getContentArray()['mode'] ?? 'link') !== 'app';
	}//end keepsLinkInContent()

	/**
	 * Refuse an update whose content carries a link target or an office-network address that is not allowed.
	 *
	 * @param mixed $content The `content` of the update, when given.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When `content.linkTarget` or `content.internalUrl` is not allowed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-a-tile-can-hold-an-office-network-address-req-tia-002
	 */
	private function checkContentUpdate(mixed $content): void {
		if (is_array($content) === false) {
			return;
		}

		// Launcher-tile-open-behaviour D1: the tile's link target rides in
		// content; anything but the three values is refused before saving.
		$this->validatedLinkTarget(value: ($content['linkTarget'] ?? null), field: 'content.linkTarget');

		if (array_key_exists('internalUrl', $content) === true) {
			$this->assertValidInternalUrl(url: $content['internalUrl']);
		}
	}//end checkContentUpdate()

	/**
	 * A link target as given, or null when none is given.
	 *
	 * @param mixed  $value The value.
	 * @param string $field The field name, for the refusal message.
	 *
	 * @return string|null
	 *
	 * @throws \InvalidArgumentException When the value is not a link target.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) TileLinkTarget holds the rule as a pure function.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	private function validatedLinkTarget(mixed $value, string $field): ?string {
		return TileLinkTarget::validated(value: $value, field: $field);
	}//end validatedLinkTarget()

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
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-2
	 */
	private function checkLaunchLink(WidgetPlacement $placement): void {
		if (self::keepsLinkInContent(placement: $placement) === true) {
			$this->checkContentLaunchLink(placement: $placement);
			return;
		}

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

	/**
	 * Check the launch link of a `tile` widget that keeps its link in
	 * content, and store the link value the check returns in that content.
	 *
	 * @param WidgetPlacement $placement The placement, with this save's changes applied.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the tile may not be saved like this (HTTP 400).
	 *
	 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
	 */
	private function checkContentLaunchLink(WidgetPlacement $placement): void {
		$content = $placement->getContentArray();
		$type    = $content['linkType'] ?? null;
		if (is_string($type) === false || TileLinkValidator::isLaunchType(type: $type) === false) {
			return;
		}

		if ($this->links === null) {
			throw new InvalidArgumentException(message: 'The link type "' . $type . '" cannot be checked here.');
		}

		$content['linkValue'] = $this->links->check(
			type: $type,
			value: (string)($content['linkValue'] ?? ''),
			content: $content
		);
		$placement->setContentArray($content);
	}//end checkContentLaunchLink()

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
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-a-tile-can-hold-an-office-network-address-req-tia-002
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
