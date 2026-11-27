<?php

/**
 * FirstPaintGridEngineTest
 *
 * GridStack's collision push and top-gravity pack as the first-paint shell
 * runs them (first-paint-skeleton D3, the 8-column band).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintGridEngine;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FirstPaintGridEngine.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintGridEngine
 */
class FirstPaintGridEngineTest extends TestCase {

	/**
	 * Positions by id.
	 *
	 * @param list<array<string, int|string>> $nodes The placed nodes.
	 *
	 * @return array<string, string>
	 */
	private static function at(array $nodes): array {
		$out = [];
		foreach ($nodes as $node) {
			$out[(string) $node['id']] = $node['x'].','.$node['y'].','.$node['w'].','.$node['h'];
		}

		ksort($out);

		return $out;
	}//end at()

	/**
	 * Nodes that do not overlap and leave no gap stay where they are.
	 *
	 * @return void
	 */
	public function testAPackedLayoutStays(): void {
		$nodes = [
			['id' => 'a', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2],
			['id' => 'b', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 3],
			['id' => 'c', 'x' => 0, 'y' => 2, 'w' => 6, 'h' => 2],
		];

		$this->assertSame(
			expected: ['a' => '0,0,6,2', 'b' => '6,0,6,3', 'c' => '0,2,6,2'],
			actual: self::at(nodes: FirstPaintGridEngine::settle(nodes: $nodes, column: 12, direction: 1))
		);
	}//end testAPackedLayoutStays()

	/**
	 * A gap closes: top gravity moves a node up as far as nothing is in the way.
	 *
	 * @return void
	 */
	public function testTopGravityClosesAGap(): void {
		$nodes = [
			['id' => 'a', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 2],
			['id' => 'b', 'x' => 0, 'y' => 5, 'w' => 4, 'h' => 2],
			['id' => 'c', 'x' => 4, 'y' => 3, 'w' => 4, 'h' => 1],
		];

		$this->assertSame(
			expected: ['a' => '0,0,4,2', 'b' => '0,2,4,2', 'c' => '4,0,4,1'],
			actual: self::at(nodes: FirstPaintGridEngine::settle(nodes: $nodes, column: 8, direction: 1))
		);
	}//end testTopGravityClosesAGap()

	/**
	 * Added last to first (a column change), the node added later pushes every
	 * node in its rows below it, then the pack lifts what it can: GridStack's
	 * answer for Mijn werkplek at 8 columns (Notities under Klok, not over it).
	 *
	 * @return void
	 */
	public function testOverlapsArePushedDownThenPacked(): void {
		$nodes = [
			['id' => 'clock', 'x' => 5, 'y' => 0, 'w' => 3, 'h' => 3],
			['id' => 'welcome', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 2],
			['id' => 'files', 'x' => 0, 'y' => 2, 'w' => 3, 'h' => 6],
			['id' => 'notes', 'x' => 3, 'y' => 2, 'w' => 3, 'h' => 4],
			['id' => 'tasks', 'x' => 5, 'y' => 3, 'w' => 3, 'h' => 4],
			['id' => 'news', 'x' => 3, 'y' => 6, 'w' => 3, 'h' => 3],
			['id' => 'calendar', 'x' => 5, 'y' => 7, 'w' => 3, 'h' => 8],
		];

		$this->assertSame(
			expected: [
				'calendar' => '5,14,3,8',
				'clock'    => '5,0,3,3',
				'files'    => '0,2,3,6',
				'news'     => '3,11,3,3',
				'notes'    => '3,3,3,4',
				'tasks'    => '5,7,3,4',
				'welcome'  => '0,0,5,2',
			],
			actual: self::at(nodes: FirstPaintGridEngine::settle(nodes: $nodes, column: 8, direction: -1))
		);
	}//end testOverlapsArePushedDownThenPacked()

	/**
	 * A node wider than the grid is narrowed, one past the edge moves left.
	 *
	 * @return void
	 */
	public function testNodesAreKeptInsideTheGrid(): void {
		$nodes = [
			['id' => 'wide', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
			['id' => 'edge', 'x' => 7, 'y' => 1, 'w' => 3, 'h' => 1],
		];

		$this->assertSame(
			expected: ['edge' => '5,1,3,1', 'wide' => '0,0,8,1'],
			actual: self::at(nodes: FirstPaintGridEngine::settle(nodes: $nodes, column: 8, direction: 1))
		);
	}//end testNodesAreKeptInsideTheGrid()

	/**
	 * Utils.sort(): by row then column, reversed for -1.
	 *
	 * @return void
	 */
	public function testSorted(): void {
		$nodes = [
			['id' => 'b', 'x' => 4, 'y' => 0, 'w' => 1, 'h' => 1],
			['id' => 'c', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1],
			['id' => 'a', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
		];

		$this->assertSame(expected: ['a', 'b', 'c'], actual: array_column(FirstPaintGridEngine::sorted(nodes: $nodes, direction: 1), 'id'));
		$this->assertSame(expected: ['c', 'b', 'a'], actual: array_column(FirstPaintGridEngine::sorted(nodes: $nodes, direction: -1), 'id'));
	}//end testSorted()
}//end class
