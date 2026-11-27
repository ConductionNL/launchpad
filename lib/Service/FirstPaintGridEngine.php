<?php

/**
 * FirstPaintGridEngine
 *
 * The part of GridStack's engine (gridstack 14, gridstack-engine.js) that
 * decides where widgets end up after the grid takes them in or changes its
 * column count: every node is added in turn, a node pushes whatever it
 * overlaps down (the whole row is the hit area while the grid batches), and
 * once all are in, top gravity packs every node up as far as it goes. The
 * first-paint shell runs it so its frames sit where the grid draws them,
 * also when move-and-scale makes widgets overlap at 8 columns.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Collision push and top-gravity pack, as GridStack's engine does them.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */
class FirstPaintGridEngine {

	/**
	 * The nodes by their engine key.
	 *
	 * @var array<int, array<string, int|string>>
	 */
	private array $nodes = [];

	/**
	 * The engine keys in the engine's current order (GridStack re-sorts its node list as it works).
	 *
	 * @var list<int>
	 */
	private array $order = [];

	/**
	 * A grid of $column columns.
	 *
	 * @param int $column The column count.
	 */
	private function __construct(private readonly int $column) {
	}//end __construct()

	/**
	 * Add the nodes in GridStack's order, then pack them up: what the grid
	 * holds after it took them in (direction 1, row order) or after a column
	 * change (direction -1: columnChanged() adds the last node first).
	 *
	 * @param list<array<string, int|string>> $nodes     The nodes with x, y, w and h set.
	 * @param int                             $column    The column count.
	 * @param int                             $direction 1 to add first to last, -1 last to first.
	 *
	 * @return list<array<string, int|string>> The placed nodes, top to bottom.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function settle(array $nodes, int $column, int $direction): array {
		$engine = new self(column: max(1, $column));
		foreach (self::sorted(nodes: $nodes, direction: $direction) as $node) {
			$engine->add(node: $node);
		}

		$engine->pack();

		return array_map(fn (int $key): array => $engine->nodes[$key], $engine->order);
	}//end settle()

	/**
	 * Utils.sort(): by row, then column; $direction -1 reverses both.
	 *
	 * @param list<array<string, int|string>> $nodes     The nodes.
	 * @param int                             $direction 1 or -1.
	 *
	 * @return list<array<string, int|string>>
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function sorted(array $nodes, int $direction): array {
		usort(
			$nodes,
			static function (array $left, array $right) use ($direction): int {
				$byRow = ($direction * ((int) $left['y'] - (int) $right['y']));
				if ($byRow === 0) {
					return ($direction * ((int) $left['x'] - (int) $right['x']));
				}

				return $byRow;
			}
		);

		return $nodes;
	}//end sorted()

	/**
	 * addNode() while the grid batches: fit the node in the grid, then push what it overlaps down.
	 *
	 * @param array<string, int|string> $node The node.
	 *
	 * @return void
	 */
	private function add(array $node): void {
		$key = count($this->nodes);
		$this->nodes[$key] = $this->boundFix(node: $node);
		$this->order[]     = $key;
		$this->fixCollisions(key: $key, target: $this->nodes[$key], collide: null, skip: null);
	}//end add()

	/**
	 * _fixCollisions(): every node in the target's rows moves below the target.
	 * GridStack only reports a move of the node itself, which this push never makes, so the answer is always false.
	 *
	 * @param int                       $key     The node that takes its place.
	 * @param array<string, int|string> $target  Where it goes.
	 * @param int|null                  $collide A node already known to overlap it.
	 * @param int|null                  $skip    A node the push ignores (the one that started it).
	 *
	 * @return bool Whether the node itself moved (never, see above).
	 */
	private function fixCollisions(int $key, array $target, ?int $collide, ?int $skip): bool {
		$this->sortOrder(direction: -1);
		$collide = ($collide ?? $this->collide(key: $key, area: $target, skip: null));
		if ($collide === null) {
			return false;
		}

		$area    = ['x' => 0, 'w' => $this->column, 'y' => (int) $target['y'], 'h' => (int) $target['h']];
		$collide = $this->collide(key: $key, area: $area, skip: $skip);
		$guard   = 0;
		while ($collide !== null && $guard++ <= (count($this->nodes) * 2)) {
			$other = $this->nodes[$collide];
			$moved = $this->moveNode(
				key: $collide,
				target: ['x' => (int) $other['x'], 'y' => ((int) $target['y'] + (int) $target['h']), 'w' => (int) $other['w'], 'h' => (int) $other['h']],
				skip: $key
			);
			if ($moved === false) {
				return false;
			}

			$collide = $this->collide(key: $key, area: $area, skip: $skip);
		}

		return false;
	}//end fixCollisions()

	/**
	 * moveNode() without dragging: push what the new place overlaps, then move.
	 *
	 * @param int                       $key    The node.
	 * @param array<string, int|string> $target Where it goes.
	 * @param int                       $skip   The node that pushes it.
	 *
	 * @return bool Whether it moved.
	 */
	private function moveNode(int $key, array $target, int $skip): bool {
		$before = $this->nodes[$key];
		$target = $this->boundFix(node: $target);
		if (self::samePos(left: $before, right: $target) === true) {
			return false;
		}

		$collides = $this->collideAll(key: $key, area: $target, skip: $skip);
		if ($collides !== []) {
			$this->fixCollisions(key: $key, target: $target, collide: $collides[0], skip: $skip);
		}

		$node = $this->nodes[$key];
		$this->nodes[$key] = ['x' => (int) $target['x'], 'y' => (int) $target['y'], 'w' => (int) $target['w'], 'h' => (int) $target['h']] + $node;

		return self::samePos(left: $this->nodes[$key], right: $before) === false;
	}//end moveNode()

	/**
	 * _packNodes() with top gravity: first to last, each node moves up while nothing is in the way.
	 *
	 * @return void
	 */
	private function pack(): void {
		$this->sortOrder(direction: 1);
		foreach ($this->order as $index => $key) {
			while ((int) $this->nodes[$key]['y'] > 0) {
				$up = 0;
				if ($index !== 0) {
					$up = ((int) $this->nodes[$key]['y'] - 1);
				}

				$area = ['y' => $up] + $this->nodes[$key];
				if ($index !== 0 && $this->collide(key: $key, area: $area, skip: null) !== null) {
					break;
				}

				$this->nodes[$key]['y'] = $up;
			}
		}
	}//end pack()

	/**
	 * The first node, in the engine's order, that overlaps $area.
	 *
	 * @param int                       $key  The node itself, left out.
	 * @param array<string, int|string> $area The area.
	 * @param int|null                  $skip Another node to leave out.
	 *
	 * @return int|null
	 */
	private function collide(int $key, array $area, ?int $skip): ?int {
		return ($this->collideAll(key: $key, area: $area, skip: $skip)[0] ?? null);
	}//end collide()

	/**
	 * Every node, in the engine's order, that overlaps $area.
	 *
	 * @param int                       $key  The node itself, left out.
	 * @param array<string, int|string> $area The area.
	 * @param int|null                  $skip Another node to leave out.
	 *
	 * @return list<int>
	 */
	private function collideAll(int $key, array $area, ?int $skip): array {
		$hits = [];
		foreach ($this->order as $other) {
			if ($other !== $key && $other !== $skip && self::intercepts(left: $this->nodes[$other], right: $area) === true) {
				$hits[] = $other;
			}
		}

		return $hits;
	}//end collideAll()

	/**
	 * Re-sort the engine's order (sortNodes()).
	 *
	 * @param int $direction 1 or -1.
	 *
	 * @return void
	 */
	private function sortOrder(int $direction): void {
		$nodes = $this->nodes;
		usort(
			$this->order,
			static function (int $left, int $right) use ($nodes, $direction): int {
				$byRow = ($direction * ((int) $nodes[$left]['y'] - (int) $nodes[$right]['y']));
				if ($byRow === 0) {
					return ($direction * ((int) $nodes[$left]['x'] - (int) $nodes[$right]['x']));
				}

				return $byRow;
			}
		);
	}//end sortOrder()

	/**
	 * nodeBoundFix() for a move: at least one cell, inside the grid, moved left rather than narrowed.
	 *
	 * @param array<string, int|string> $node The node or target.
	 *
	 * @return array<string, int|string>
	 */
	private function boundFix(array $node): array {
		$node['w'] = min(max((int) $node['w'], 1), $this->column);
		$node['h'] = max((int) $node['h'], 1);
		$node['x'] = max((int) $node['x'], 0);
		$node['y'] = max((int) $node['y'], 0);
		if (($node['x'] + $node['w']) > $this->column) {
			$node['x'] = ($this->column - $node['w']);
		}

		return $node;
	}//end boundFix()

	/**
	 * Utils.isIntercepted().
	 *
	 * @param array<string, int|string> $left  One area.
	 * @param array<string, int|string> $right The other.
	 *
	 * @return bool
	 */
	private static function intercepts(array $left, array $right): bool {
		return ((int) $left['y'] >= ((int) $right['y'] + (int) $right['h'])
			|| ((int) $left['y'] + (int) $left['h']) <= (int) $right['y']
			|| ((int) $left['x'] + (int) $left['w']) <= (int) $right['x']
			|| (int) $left['x'] >= ((int) $right['x'] + (int) $right['w'])) === false;
	}//end intercepts()

	/**
	 * Utils.samePos().
	 *
	 * @param array<string, int|string> $left  One position.
	 * @param array<string, int|string> $right The other.
	 *
	 * @return bool
	 */
	private static function samePos(array $left, array $right): bool {
		foreach (['x', 'y', 'w', 'h'] as $field) {
			if ((int) $left[$field] !== (int) $right[$field]) {
				return false;
			}
		}

		return true;
	}//end samePos()
}//end class
