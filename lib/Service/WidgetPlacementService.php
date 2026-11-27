<?php

/**
 * WidgetPlacementService
 *
 * Server-side validation for widget placement payloads. Currently hosts
 * the REQ-CONT-006 maximum-nesting-depth invariant for the `container`
 * widget type; future placement-level validators (depth limits, schema
 * sanity, etc.) should live here so the dispatch is single-responsibility.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Server-side validators for widget placement payloads.
 */
class WidgetPlacementService {
	/**
	 * Maximum container nesting depth (REQ-CONT-006).
	 *
	 * Depth counts the number of nested CONTAINER levels — not the total
	 * number of children. A container holding a container holding a
	 * container holding a label has depth 3 (allowed). Adding a fourth
	 * nested container makes it depth 4 (rejected).
	 *
	 * @var int
	 */
	public const MAX_CONTAINER_DEPTH = 3;

	/**
	 * Recursively walk a widget placement's `content` blob and reject
	 * payloads whose nested-container depth exceeds
	 * {@see self::MAX_CONTAINER_DEPTH} (REQ-CONT-006).
	 *
	 * Tolerant of non-container payloads — when `$content` has no
	 * `placements[]` array (i.e. the widget is not a container) the
	 * method returns immediately without raising. This keeps the
	 * controller wiring trivial: every save can call
	 * `validateContainerDepth` regardless of widget type.
	 *
	 * @param array $content The widget placement's `content` blob.
	 * @param int $depth Current nesting depth (0 at the top level).
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the depth limit is exceeded.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-15
	 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-1
	 */
	public function validateContainerDepth(array $content, int $depth = 0): void {
		// The `placements[]` key marks this content as a container, and a
		// Tabs widget is a container whose tabs each hold a `placements[]`
		// (REQ-CONT-006 counts wells and tabs together). The current node
		// IS one level; only a child that is itself a container or tabs
		// adds the next one, matching the proposal wording: "depth > 3 AND
		// any placements[] child is also a container".
		foreach ($this->childPlacements(content: $content) as $child) {
			$this->validateChildDepth(child: $child, depth: $depth);
		}//end foreach
	}//end validateContainerDepth()

	/**
	 * Validate a single `placements[]` child of a container node.
	 *
	 * Non-container children are ignored — only a child that is itself a
	 * container adds a nesting level, and only then can the cap be
	 * breached. Container children are charged one level and recursed
	 * into via {@see self::validateContainerDepth()}.
	 *
	 * @param array $child The child placement to inspect.
	 * @param int $depth The parent container's nesting depth.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the depth limit is exceeded.
	 */
	private function validateChildDepth(array $child, int $depth): void {
		$childContent = $child['content'] ?? null;
		if (is_array($childContent) === false) {
			return;
		}

		$isChildContainer = (
			in_array(needle: ($child['type'] ?? null), haystack: ['container', 'tabs'], strict: true) === true
			|| (isset($childContent['placements']) === true
				&& is_array($childContent['placements']) === true)
			|| (isset($childContent['tabs']) === true
				&& is_array($childContent['tabs']) === true)
		);

		if ($isChildContainer === false) {
			return;
		}

		$childDepth = ($depth + 1);
		if ($childDepth > self::MAX_CONTAINER_DEPTH) {
			throw new InvalidArgumentException(
				message: 'container_depth_exceeded'
			);
		}

		$this->validateContainerDepth(
			content: $childContent,
			depth: $childDepth
		);
	}//end validateChildDepth()

	/**
	 * The child placements of a container's or a Tabs widget's content: the
	 * container's `placements[]`, and every tab's `placements[]`.
	 *
	 * @param array $content The widget placement's `content` blob.
	 *
	 * @return list<array> The child placements; none for another widget.
	 *
	 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-1
	 */
	private function childPlacements(array $content): array {
		$lists = [($content['placements'] ?? null)];
		if (is_array($content['tabs'] ?? null) === true) {
			foreach ($content['tabs'] as $tab) {
				if (is_array($tab) === true) {
					$lists[] = ($tab['placements'] ?? null);
				}
			}
		}

		$children = [];
		foreach ($lists as $list) {
			if (is_array($list) === false) {
				continue;
			}

			foreach ($list as $child) {
				if (is_array($child) === true) {
					$children[] = $child;
				}
			}
		}

		return $children;
	}//end childPlacements()
}//end class
