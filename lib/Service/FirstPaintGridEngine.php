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
	public function __construct(private readonly int $column) {
	}//end __construct()

	/**
	 * Add the nodes in GridStack's order, then pack them up: what the grid
	 * holds after it took them in (direction 1, row order) or after a column
	 * change (direction -1: columnChanged() adds the last node first).
	 *
	 * @param list<array<string, int|string>> $nodes     The nodes with x, y, w and h set.
	 * @param int                             $direction 1 to add first to last, -1 last to first.
	 *
	 * @return list<array<string, int|string>> The placed nodes, top to bottom.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public function settle(array $nodes, int $direction): array {
		$this->nodes = [];
		$this->order = [];
		foreach ($this->sorted(nodes: $nodes, direction: $direction) as $node) {
			$this->add(node: $node);
		}

		$this->pack();

		return array_map(fn (int $key): array => $this->nodes[$key], $this->order);
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
	public function sorted(array $nodes, int $direction): array {
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
		$this->fixCollisions(target: $this->nodes[$key], exclude: [$key]);
	}//end add()

	/**
	 * _fixCollisions(): every node in the target's rows moves below the target.
	 *
	 * @param array<string, int|string> $target  Where the node goes.
	 * @param list<int|null>            $exclude The node itself, then the node that started the push (if any).
	 *
	 * @return void
	 */
	private function fixCollisions(array $target, array $exclude): void {
		$this->sortOrder(direction: -1);
		if ($this->collide(area: $target, exclude: [$exclude[0]]) === null) {
			return;
		}

		$area    = ['x' => 0, 'w' => $this->column, 'y' => (int) $target['y'], 'h' => (int) $target['h']];
		$collide = $this->collide(area: $area, exclude: $exclude);
		$guard   = 0;
		$limit   = (count($this->nodes) * 2);
		while ($collide !== null && $guard++ <= $limit) {
			$other = $this->nodes[$collide];
			$moved = $this->moveNode(
				target: ['x' => (int) $other['x'], 'y' => ((int) $target['y'] + (int) $target['h']), 'w' => (int) $other['w'], 'h' => (int) $other['h']],
				exclude: [$collide, $exclude[0]]
			);
			if ($moved === false) {
				return;
			}

			$collide = $this->collide(area: $area, exclude: $exclude);
		}
	}//end fixCollisions()

	/**
	 * moveNode() without dragging: push what the new place overlaps, then move.
	 *
	 * @param array<string, int|string> $target  Where the node goes.
	 * @param array{0: int, 1: int}     $exclude The node, then the node that pushes it.
	 *
	 * @return bool Whether it moved.
	 */
	private function moveNode(array $target, array $exclude): bool {
		$key    = $exclude[0];
		$before = $this->nodes[$key];
		$target = $this->boundFix(node: $target);
		if (self::samePos(left: $before, right: $target) === true) {
			return false;
		}

		if ($this->collide(area: $target, exclude: $exclude) !== null) {
			$this->fixCollisions(target: $target, exclude: $exclude);
		}

		$this->nodes[$key] = ['x' => (int) $target['x'], 'y' => (int) $target['y'], 'w' => (int) $target['w'], 'h' => (int) $target['h']] + $before;

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
				$raised = 0;
				if ($index !== 0) {
					$raised = ((int) $this->nodes[$key]['y'] - 1);
				}

				$area = ['y' => $raised] + $this->nodes[$key];
				if ($index !== 0 && $this->collide(area: $area, exclude: [$key]) !== null) {
					break;
				}

				$this->nodes[$key]['y'] = $raised;
			}
		}
	}//end pack()

	/**
	 * The first node, in the engine's order, that overlaps $area.
	 *
	 * @param array<string, int|string> $area    The area.
	 * @param list<int|null>            $exclude Nodes left out.
	 *
	 * @return int|null
	 */
	private function collide(array $area, array $exclude): ?int {
		foreach ($this->order as $other) {
			if (in_array($other, $exclude, true) === false && self::intercepts(left: $this->nodes[$other], right: $area) === true) {
				return $other;
			}
		}

		return null;
	}//end collide()

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
