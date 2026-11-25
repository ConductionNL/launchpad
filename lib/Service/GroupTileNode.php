<?php
/**
 * GroupTileNode
 *
 * The tiles inside a group (a `container` widget) are full tile nodes: a
 * child `{type: tile, content: {title, linkType, linkValue, ...}}`. A child
 * written as a link that only opens one address (a bookmark import wrote
 * those) is turned into such a node here.
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

/**
 * Turns the address links of a group into tile nodes, in a group's content
 * and in the groups nested in it. A link that runs an action or lists
 * several links stays a link; every other key of a child is kept.
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
final class GroupTileNode {

	/**
	 * The link actions that only open an address.
	 */
	private const ADDRESS_ACTIONS = ['', 'external', 'internal'];

	/**
	 * The keys of a link child that the tile node replaces. A link's icon is an
	 * icon name, which a tile does not draw, so it goes too.
	 */
	private const LINK_ONLY_KEYS = ['label', 'url', 'actionType', 'icon', 'iconType'];

	/**
	 * The tile node for an address link child, or null for any other child.
	 *
	 * @param array<string, mixed> $child The container child.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
	 */
	public function fromLink(array $child): ?array {
		$content = $child['content'] ?? null;
		if (($child['type'] ?? null) !== 'link' || is_array($content) === false || $this->isAddressLink(content: $content) === false) {
			return null;
		}

		$tile = array_diff_key($content, array_flip(self::LINK_ONLY_KEYS));
		$tile['title']     = trim((string) ($content['label'] ?? ''));
		$tile['iconType']  = 'svg';
		$tile['linkType']  = 'url';
		$tile['linkValue'] = trim((string) $content['url']);

		$child['type']    = 'tile';
		$child['content'] = $tile;
		return $child;
	}//end fromLink()

	/**
	 * A group's content with every address link, also in nested groups, as a
	 * tile node. Content without children comes back unchanged.
	 *
	 * @param array<string, mixed> $content The group's content.
	 *
	 * @return array{content: array<string, mixed>, changed: int} The content and how many children changed.
	 *
	 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
	 */
	public function convertGroup(array $content): array {
		$children = $content['placements'] ?? null;
		if (is_array($children) === false) {
			return ['content' => $content, 'changed' => 0];
		}

		$changed = 0;
		foreach ($children as $index => $child) {
			if (is_array($child) === false) {
				continue;
			}

			[$children[$index], $count] = $this->convertChild(child: $child);
			$changed += $count;
		}

		$content['placements'] = $children;
		return ['content' => $content, 'changed' => $changed];
	}//end convertGroup()

	/**
	 * One child as a tile node, or with its own group converted.
	 *
	 * @param array<string, mixed> $child The container child.
	 *
	 * @return array{0: array<string, mixed>, 1: int} The child and how many children changed.
	 */
	private function convertChild(array $child): array {
		$tile = $this->fromLink(child: $child);
		if ($tile !== null) {
			return [$tile, 1];
		}

		if (is_array($child['content'] ?? null) === false) {
			return [$child, 0];
		}

		$nested           = $this->convertGroup(content: $child['content']);
		$child['content'] = $nested['content'];
		return [$child, $nested['changed']];
	}//end convertChild()

	/**
	 * Whether a link's content only opens one address.
	 *
	 * @param array<string, mixed> $content The link child's content.
	 *
	 * @return bool
	 */
	private function isAddressLink(array $content): bool {
		$url    = $content['url'] ?? null;
		$action = (string) ($content['actionType'] ?? '');
		return is_string($url) === true
			&& trim($url) !== ''
			&& is_array($content['links'] ?? null) === false
			&& in_array($action, self::ADDRESS_ACTIONS, true) === true;
	}//end isAddressLink()
}//end class
