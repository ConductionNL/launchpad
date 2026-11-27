<?php
/**
 * ContainerChildKeys
 *
 * Every child of a well (container) or a tabs widget carries a key of its own,
 * so it can be named from outside its parent: an embed token for one widget
 * inside a well (REQ-WDG-050) names `<placement id>.<child key>`.
 *
 * @category Db
 * @package  OCA\LaunchPad\Db
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

namespace OCA\LaunchPad\Db;

/**
 * Stamps and finds the keys of the children of a well or a tabs widget. A
 * child's key is its `id` when it has one, else its `uuid`. A child without
 * either gets a `uuid`: `<owner id>-<index>` for a direct child of a well
 * whose placement id is known (the key the well already used to count tile
 * use, ContainerWidget.vue), otherwise one derived from the child and its
 * place, so writing the same content twice gives the same keys.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
final class ContainerChildKeys {
	/**
	 * What separates the placement id from the child key in an embed subject.
	 */
	public const SEPARATOR = '.';

	/**
	 * The nesting a lookup follows at most (REQ-CONT-006 allows 3).
	 */
	private const MAX_DEPTH = 4;

	/**
	 * Give every child of this content, at every depth, a key; children that
	 * have one keep it. Content without children comes back unchanged.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 * @param int|null             $ownerId The placement id, when known.
	 * @param int                  $depth   How deep this content sits.
	 * @param string               $path    Where this content sits, for derived keys.
	 *
	 * @return array<string, mixed> The content with every child keyed.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function stamp(array $content, ?int $ownerId=null, int $depth=0, string $path=''): array {
		if ($depth > self::MAX_DEPTH) {
			return $content;
		}

		if (is_array($content['placements'] ?? null) === true) {
			$owner = null;
			if ($depth === 0) {
				$owner = $ownerId;
			}

			$content['placements'] = $this->stampList(list: $content['placements'], ownerId: $owner, depth: $depth, path: $path . '/p');
		}

		if (is_array($content['tabs'] ?? null) === true) {
			foreach ($content['tabs'] as $at => $tab) {
				if (is_array($tab) === true && is_array($tab['placements'] ?? null) === true) {
					$content['tabs'][$at]['placements'] = $this->stampList(list: $tab['placements'], ownerId: null, depth: $depth, path: $path . '/t' . $at);
				}
			}
		}

		return $content;
	}//end stamp()

	/**
	 * A child's key: its id, else its uuid, else ''.
	 *
	 * @param array<string, mixed> $child The child.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function keyOf(array $child): string {
		$id = ($child['id'] ?? null);
		if (is_int($id) === true || (is_string($id) === true && $id !== '')) {
			return (string)$id;
		}

		$uuid = ($child['uuid'] ?? null);
		if (is_string($uuid) === true) {
			return $uuid;
		}

		return '';
	}//end keyOf()

	/**
	 * The child with this key, at any depth, or null.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 * @param string               $key     The child's key.
	 * @param int                  $depth   How deep this content sits.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function find(array $content, string $key, int $depth=0): ?array {
		if ($key === '' || $depth > self::MAX_DEPTH) {
			return null;
		}

		foreach ($this->children(content: $content) as $child) {
			if ($this->keyOf(child: $child) === $key) {
				return $child;
			}

			if (is_array($child['content'] ?? null) === true) {
				$found = $this->find(content: $child['content'], key: $key, depth: ($depth + 1));
				if ($found !== null) {
					return $found;
				}
			}
		}

		return null;
	}//end find()

	/**
	 * Split an embed subject id into the placement id and the child key; null
	 * when it does not name a child (a plain placement id, or anything else).
	 *
	 * @param string $subjectId The subject id.
	 *
	 * @return array{0: int, 1: string}|null
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function splitSubject(string $subjectId): ?array {
		if (preg_match('/^([1-9][0-9]*)\.([A-Za-z0-9_-]{1,64})$/', $subjectId, $match) !== 1) {
			return null;
		}

		return [(int)$match[1], $match[2]];
	}//end splitSubject()

	/**
	 * Key the children of one list.
	 *
	 * @param array<int|string, mixed> $list    The children.
	 * @param int|null                 $ownerId The well's placement id, for its direct children.
	 * @param int                      $depth   How deep the list's parent sits.
	 * @param string                   $path    Where the list sits, for derived keys.
	 *
	 * @return array<int|string, mixed>
	 */
	private function stampList(array $list, ?int $ownerId, int $depth, string $path): array {
		$taken = [];
		foreach ($list as $child) {
			if (is_array($child) === true && $this->keyOf(child: $child) !== '') {
				$taken[$this->keyOf(child: $child)] = true;
			}
		}

		foreach ($list as $index => $child) {
			if (is_array($child) === false) {
				continue;
			}

			if ($this->keyOf(child: $child) === '') {
				$key = self::newKey(ownerId: $ownerId, index: $index, child: $child, path: $path);
				if (isset($taken[$key]) === true) {
					$key = self::newKey(ownerId: null, index: $index, child: $child, path: $path);
				}

				$child['uuid'] = $key;
				$taken[$key]   = true;
			}

			if (is_array($child['content'] ?? null) === true) {
				$child['content'] = $this->stamp(content: $child['content'], ownerId: null, depth: ($depth + 1), path: $path . '/' . $index);
			}

			$list[$index] = $child;
		}

		return $list;
	}//end stampList()

	/**
	 * A new key for a child that has none.
	 *
	 * @param int|null             $ownerId The well's placement id, when known.
	 * @param int|string           $index   The child's place in its list.
	 * @param array<string, mixed> $child   The child.
	 * @param string               $path    Where its list sits.
	 *
	 * @return string
	 */
	private static function newKey(?int $ownerId, int|string $index, array $child, string $path): string {
		if ($ownerId !== null && is_int($index) === true) {
			return $ownerId . '-' . $index;
		}

		return substr(hash('sha256', $path . '/' . $index . '|' . (string)json_encode($child)), 0, 16);
	}//end newKey()

	/**
	 * The direct children of a well's or a tabs widget's content.
	 *
	 * @param array<string, mixed> $content The content.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function children(array $content): array {
		$lists = [($content['placements'] ?? null)];
		foreach ((array)($content['tabs'] ?? []) as $tab) {
			if (is_array($tab) === true) {
				$lists[] = ($tab['placements'] ?? null);
			}
		}

		$children = [];
		foreach ($lists as $list) {
			foreach ((array)$list as $child) {
				if (is_array($child) === true) {
					$children[] = $child;
				}
			}
		}

		return $children;
	}//end children()
}//end class
