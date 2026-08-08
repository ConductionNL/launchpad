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
class WidgetPlacementService
{
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
     * @param int   $depth   Current nesting depth (0 at the top level).
     *
     * @return void
     *
     * @throws InvalidArgumentException When the depth limit is exceeded.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-15
     */
    public function validateContainerDepth(array $content, int $depth=0): void
    {
        if (isset($content['placements']) === false
            || is_array($content['placements']) === false
        ) {
            return;
        }

        // The `placements[]` key marks this content as a container —
        // the current node IS one container level. If we're already at
        // (or beyond) the cap, having any placements at all violates
        // the invariant: a container at depth MAX cannot contain a
        // container child (which would push the tree to MAX + 1).
        // We only reject when one of those children is itself another
        // container, matching the proposal wording: "depth > 3 AND any
        // placements[] child is also a container".
        $children = $content['placements'];
        foreach ($children as $child) {
            if (is_array($child) === false) {
                continue;
            }

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
     * @param int   $depth The parent container's nesting depth.
     *
     * @return void
     *
     * @throws InvalidArgumentException When the depth limit is exceeded.
     */
    private function validateChildDepth(array $child, int $depth): void
    {
        $childContent = $child['content'] ?? null;
        if (is_array($childContent) === false) {
            return;
        }

        $isChildContainer = (
            ($child['type'] ?? null) === 'container'
            || (isset($childContent['placements']) === true
                && is_array($childContent['placements']) === true)
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
}//end class
