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
	 * Give every child of this content, at every depth, a key; children that
	 * have one keep it. Content without children comes back unchanged.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 * @param int|null             $ownerId The placement id, when known.
	 *
	 * @return array<string, mixed> The content with every child keyed.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function stamp(array $content, ?int $ownerId=null): array {
		if ($ownerId !== null && is_array($content['placements'] ?? null) === true) {
			$content['placements'] = $this->ownerKeys(list: $content['placements'], ownerId: $ownerId);
		}

		return $this->stampContent(content: $content, path: '');
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
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function find(array $content, string $key): ?array {
		if ($key === '') {
			return null;
		}

		foreach ($this->children(content: $content) as $child) {
			$found = $this->findIn(child: $child, key: $key);
			if ($found !== null) {
				return $found;
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
	 * This child when it has the key, else the child with the key inside it.
	 *
	 * @param array<string, mixed> $child The child.
	 * @param string               $key   The key.
	 *
	 * @return array<string, mixed>|null
	 */
	private function findIn(array $child, string $key): ?array {
		if ($this->keyOf(child: $child) === $key) {
			return $child;
		}

		if (is_array($child['content'] ?? null) === true) {
			return $this->find(content: $child['content'], key: $key);
		}

		return null;
	}//end findIn()

	/**
	 * The direct children of a well whose placement id is known get
	 * `<owner id>-<index>` when they have no key and no other child holds it.
	 *
	 * @param array<int|string, mixed> $list    The well's children.
	 * @param int                      $ownerId The well's placement id.
	 *
	 * @return array<int|string, mixed>
	 */
	private function ownerKeys(array $list, int $ownerId): array {
		$taken = $this->takenKeys(list: $list);
		foreach ($list as $index => $child) {
			$key = $ownerId . '-' . $index;
			if (is_array($child) === true && is_int($index) === true && $this->keyOf(child: $child) === '' && isset($taken[$key]) === false) {
				$list[$index]['uuid'] = $key;
				$taken[$key]          = true;
			}
		}

		return $list;
	}//end ownerKeys()

	/**
	 * Key the children of a content: a well's list and every tab's.
	 *
	 * @param array<string, mixed> $content The content.
	 * @param string               $path    Where the content sits, for derived keys.
	 *
	 * @return array<string, mixed>
	 */
	private function stampContent(array $content, string $path): array {
		if (is_array($content['placements'] ?? null) === true) {
			$content['placements'] = $this->stampList(list: $content['placements'], path: $path . '/p');
		}

		foreach ((array)($content['tabs'] ?? []) as $at => $tab) {
			if (is_array($tab) === true && is_array($tab['placements'] ?? null) === true) {
				$content['tabs'][$at]['placements'] = $this->stampList(list: $tab['placements'], path: $path . '/t' . $at);
			}
		}

		return $content;
	}//end stampContent()

	/**
	 * Key the children of one list, and the children inside each.
	 *
	 * @param array<int|string, mixed> $list The children.
	 * @param string                   $path Where the list sits, for derived keys.
	 *
	 * @return array<int|string, mixed>
	 */
	private function stampList(array $list, string $path): array {
		$taken = $this->takenKeys(list: $list);
		foreach ($list as $index => $child) {
			if (is_array($child) === false) {
				continue;
			}

			$where = $path . '/' . $index;
			if ($this->keyOf(child: $child) === '') {
				$key = self::derivedKey(where: $where, child: $child);
				if (isset($taken[$key]) === true) {
					$key = self::derivedKey(where: $where . '/again', child: $child);
				}

				$child['uuid'] = $key;
				$taken[$key]   = true;
			}

			if (is_array($child['content'] ?? null) === true) {
				$child['content'] = $this->stampContent(content: $child['content'], path: $where);
			}

			$list[$index] = $child;
		}

		return $list;
	}//end stampList()

	/**
	 * The keys the children of a list already hold.
	 *
	 * @param array<int|string, mixed> $list The children.
	 *
	 * @return array<string, true>
	 */
	private function takenKeys(array $list): array {
		$taken = [];
		foreach ($list as $child) {
			if (is_array($child) === true && $this->keyOf(child: $child) !== '') {
				$taken[$this->keyOf(child: $child)] = true;
			}
		}

		return $taken;
	}//end takenKeys()

	/**
	 * A key derived from a child and its place: the same content keys the same.
	 *
	 * @param string               $where Where the child sits.
	 * @param array<string, mixed> $child The child.
	 *
	 * @return string
	 */
	private static function derivedKey(string $where, array $child): string {
		return substr(hash('sha256', $where . '|' . (string)json_encode($child)), 0, 16);
	}//end derivedKey()

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
