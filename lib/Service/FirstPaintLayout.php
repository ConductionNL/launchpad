<?php

/**
 * FirstPaintLayout
 *
 * The server-side twin of src/utils/narrowGridLayout.js
 * (first-paint-skeleton D3): where every frame of the first-paint shell sits
 * for each column count the dashboard grid can show: the dashboard's own
 * (1400 px and wider), 8 (from 1100 px, GridStack's move-and-scale) and 4
 * (below 1100 px, the reading-order flow). The shell prints these positions so
 * it lays out exactly what the grid lays out after the hand-over. One fixture,
 * tests/fixtures/first-paint-layouts.json, runs in both PHPUnit and Vitest so
 * the two cannot drift.
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
 * Positions of the first-paint frames per column count.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */
class FirstPaintLayout {

	/**
	 * Grids this narrow or narrower lay the widgets out in reading order.
	 */
	public const NARROW_COLUMNS = 4;

	/**
	 * The middle breakpoint's column count (from 1100 px).
	 */
	public const MEDIUM_COLUMNS = 8;

	/**
	 * The column counts the grid shows for a dashboard of $columns columns,
	 * widest first: the dashboard's own, then 8 and 4 when they narrow it
	 * (dashboardGridColumnOpts()).
	 *
	 * @param int $columns The dashboard's column count.
	 *
	 * @return list<int>
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function columnCounts(int $columns): array {
		$max = 12;
		if ($columns > 0) {
			$max = $columns;
		}

		$counts = [$max];
		foreach ([self::MEDIUM_COLUMNS, self::NARROW_COLUMNS] as $count) {
			if (min($count, $max) < $counts[count($counts) - 1]) {
				$counts[] = min($count, $max);
			}
		}

		return $counts;
	}//end columnCounts()

	/**
	 * Every node's position for every column count the grid shows.
	 *
	 * @param list<array{id: string|int, x?: int, y?: int, w?: int, h?: int}> $nodes   The placements as stored, at the dashboard's columns.
	 * @param int                                                          $columns The dashboard's column count.
	 *
	 * @return array<int, array<string, array{x: int, y: int, w: int, h: int}>> Column count => node id => position.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function positions(array $nodes, int $columns): array {
		$counts = self::columnCounts(columns: $columns);
		$wide   = array_map(static fn (array $node): array => self::normalise(node: $node), $nodes);
		$out    = [];
		foreach ($counts as $count) {
			$placed = $wide;
			if ($count !== $counts[0]) {
				$placed = self::columnLayout(column: $count, oldColumn: $counts[0], nodes: $wide);
			}

			foreach ($placed as $node) {
				$out[$count][(string) $node['id']] = ['x' => $node['x'], 'y' => $node['y'], 'w' => $node['w'], 'h' => $node['h']];
			}
		}

		return $out;
	}//end positions()

	/**
	 * GridStack's column change as dashboardColumnLayout() does it:
	 * reading-order flow on a narrow grid, move-and-scale everywhere else.
	 *
	 * @param int                             $column    The new column count.
	 * @param int                             $oldColumn The column count the nodes come from.
	 * @param list<array<string, int|string>> $nodes     The nodes.
	 *
	 * @return list<array<string, int|string>> The placed nodes.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function columnLayout(int $column, int $oldColumn, array $nodes): array {
		if ($column <= self::NARROW_COLUMNS && $oldColumn > $column) {
			return self::flowNarrow(nodes: self::readingOrder(nodes: $nodes, fromColumns: $oldColumn), column: $column, fromColumns: $oldColumn);
		}

		$ratio = ($column / $oldColumn);
		foreach ($nodes as $i => $node) {
			$nodes[$i]['x'] = min(self::round(value: ($node['x'] * $ratio)), ($column - 1));
			$nodes[$i]['w'] = min(max(self::round(value: ($node['w'] * $ratio)), 1), $column);
		}

		return $nodes;
	}//end columnLayout()

	/**
	 * Reading order on a narrow grid: top to bottom, left to right, except
	 * that a tile belongs to the nearest heading above it whose columns it
	 * falls in, so two tile groups side by side each stay under their heading.
	 *
	 * @param list<array<string, int|string>> $nodes       The nodes in the wider layout.
	 * @param int                             $fromColumns The column count of that layout.
	 *
	 * @return list<array<string, int|string>> The nodes in reading order.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function readingOrder(array $nodes, int $fromColumns): array {
		$headings = array_values(
			array_filter(
				$nodes,
				static fn (array $node): bool => $node['h'] === 1 && self::isCell(node: $node, fromColumns: $fromColumns) === false
			)
		);
		$keyed    = [];
		foreach ($nodes as $index => $node) {
			$keyed[] = ['node' => $node, 'key' => self::keyOf(node: $node, headings: $headings, fromColumns: $fromColumns), 'index' => $index];
		}

		usort(
			$keyed,
			static function (array $left, array $right): int {
				foreach ($left['key'] as $i => $value) {
					if ($value !== $right['key'][$i]) {
						return ($value <=> $right['key'][$i]);
					}
				}

				// Array.prototype.sort is stable: equal keys keep their order.
				return ($left['index'] <=> $right['index']);
			}
		);

		return array_map(static fn (array $entry): array => $entry['node'], $keyed);
	}//end readingOrder()

	/**
	 * Place nodes on a narrow grid in reading order: tiles flow one cell
	 * each, as many to a row as fit; every other widget takes a full row.
	 *
	 * @param list<array<string, int|string>> $nodes       The nodes in reading order.
	 * @param int                             $column      The narrow column count.
	 * @param int                             $fromColumns The column count the nodes come from.
	 *
	 * @return list<array<string, int|string>> The placed nodes.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function flowNarrow(array $nodes, int $column, int $fromColumns): array {
		$col       = 0;
		$row       = 0;
		$rowHeight = 0;
		foreach ($nodes as $i => $node) {
			$cell = self::isCell(node: $node, fromColumns: $fromColumns);
			if ($cell === false || ($col + 1) > $column) {
				// A new row: below the tiles placed so far.
				if ($col > 0) {
					$row += $rowHeight;
				}

				$col       = 0;
				$rowHeight = 0;
			}

			if ($cell === true) {
				$nodes[$i] = ['x' => $col, 'y' => $row, 'w' => 1] + $node;
				$col      += 1;
				$rowHeight = max($rowHeight, (int) $node['h']);
				continue;
			}

			$nodes[$i] = ['x' => 0, 'y' => $row, 'w' => $column] + $node;
			$row      += (int) $node['h'];
		}

		return $nodes;
	}//end flowNarrow()

	/**
	 * The sort key of a node: its own place, or for a tile its heading's place first.
	 *
	 * @param array<string, int|string>       $node        The node.
	 * @param list<array<string, int|string>> $headings    The one-row headings.
	 * @param int                             $fromColumns The column count of the layout.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: int}
	 */
	private static function keyOf(array $node, array $headings, int $fromColumns): array {
		if (self::isCell(node: $node, fromColumns: $fromColumns) === true) {
			$owner = null;
			foreach ($headings as $heading) {
				$covers = $heading['y'] < $node['y'] && $node['x'] >= $heading['x'] && $node['x'] < ($heading['x'] + $heading['w']);
				if ($covers === true && ($owner === null || $heading['y'] > $owner['y'])) {
					$owner = $heading;
				}
			}

			if ($owner !== null) {
				return [(int) $owner['y'], (int) $owner['x'], (int) $node['y'], (int) $node['x']];
			}
		}

		return [(int) $node['y'], (int) $node['x'], -1, -1];
	}//end keyOf()

	/**
	 * Whether a node is one cell wide in the layout it comes from: an app tile.
	 *
	 * @param array<string, int|string> $node        The node.
	 * @param int                       $fromColumns The column count of that layout.
	 *
	 * @return bool
	 */
	private static function isCell(array $node, int $fromColumns): bool {
		return ((int) $node['w'] * 12) <= $fromColumns;
	}//end isCell()

	/**
	 * A node with every field set as the JS defaults do (x, y 0; w, h 1).
	 *
	 * @param array<string, mixed> $node The node.
	 *
	 * @return array{id: string, x: int, y: int, w: int, h: int}
	 */
	private static function normalise(array $node): array {
		return [
			'id' => (string) ($node['id'] ?? ''),
			'x'  => (int) ($node['x'] ?? 0),
			'y'  => (int) ($node['y'] ?? 0),
			'w'  => (int) ($node['w'] ?? 1),
			'h'  => (int) ($node['h'] ?? 1),
		];
	}//end normalise()

	/**
	 * Math.round: half rounds up (towards +infinity), as the browser does.
	 *
	 * @param float $value The value.
	 *
	 * @return int
	 */
	private static function round(float $value): int {
		return (int) floor($value + 0.5);
	}//end round()
}//end class
