<?php

/**
 * TreeLevelTest
 *
 * Unit tests for TreeLevel: the depth cap and the level below a child.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\TreeLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TreeLevel.
 */
#[CoversClass(TreeLevel::class)]
class TreeLevelTest extends TestCase {
	/**
	 * Depths below the cap are allowed; the cap and deeper are not.
	 *
	 * @return void
	 */
	public function testCapIsReachedAtMaxDepth(): void {
		$this->assertFalse((new TreeLevel(parentUuid: null, depth: 0))->isBeyondCap());
		$this->assertFalse((new TreeLevel(parentUuid: 'p', depth: (Dashboard::MAX_DEPTH - 1)))->isBeyondCap());
		$this->assertTrue((new TreeLevel(parentUuid: 'p', depth: Dashboard::MAX_DEPTH))->isBeyondCap());
		$this->assertTrue((new TreeLevel(parentUuid: 'p', depth: (Dashboard::MAX_DEPTH + 1)))->isBeyondCap());
	}//end testCapIsReachedAtMaxDepth()

	/**
	 * The level below a child has that child as parent and one more depth,
	 * leaving the original level unchanged.
	 *
	 * @return void
	 */
	public function testBelowDescendsOneLevel(): void {
		$root = new TreeLevel(parentUuid: null, depth: 0);

		$child = $root->below(childUuid: 'child-1');
		$grandchild = $child->below(childUuid: 'child-2');

		$this->assertSame('child-1', $child->parentUuid);
		$this->assertSame(1, $child->depth);
		$this->assertSame('child-2', $grandchild->parentUuid);
		$this->assertSame(2, $grandchild->depth);
		$this->assertNull($root->parentUuid);
		$this->assertSame(0, $root->depth);
	}//end testBelowDescendsOneLevel()

	/**
	 * Descending from the root reaches the cap after MAX_DEPTH steps.
	 *
	 * @return void
	 */
	public function testDescendingReachesTheCap(): void {
		$level = new TreeLevel(parentUuid: null, depth: 0);
		$steps = 0;
		while ($level->isBeyondCap() === false) {
			$level = $level->below(childUuid: 'c' . $steps);
			$steps++;
		}

		$this->assertSame(Dashboard::MAX_DEPTH, $steps);
	}//end testDescendingReachesTheCap()
}//end class
