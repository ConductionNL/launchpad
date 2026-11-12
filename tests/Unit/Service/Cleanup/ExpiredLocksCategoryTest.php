<?php

/**
 * ExpiredLocksCategory Test
 *
 * Pins the Tier-A cleanup category for stale dashboard locks: its stable identity,
 * its auto-purge tier, and that scan() and purge() delegate to exactly
 * one mapper query each and report the mapper's row count unchanged.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Cleanup
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Cleanup;

use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Service\Cleanup\CleanupCategoryInterface;
use OCA\LaunchPad\Service\Cleanup\ExpiredLocksCategory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExpiredLocksCategory.
 *
 * @covers \OCA\LaunchPad\Service\Cleanup\ExpiredLocksCategory
 */
class ExpiredLocksCategoryTest extends TestCase {
	/**
	 * The mapper mock the category delegates to.
	 *
	 * @var DashboardLockMapper&MockObject
	 */
	private DashboardLockMapper&MockObject $mapper;

	/**
	 * The category under test.
	 *
	 * @var ExpiredLocksCategory
	 */
	private ExpiredLocksCategory $category;

	/**
	 * Build the category around a mapper mock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper   = $this->createMock(originalClassName: DashboardLockMapper::class);
		$this->category = new ExpiredLocksCategory(lockMapper: $this->mapper);
	}

	/**
	 * The category is a cleanup category with a stable identifier and label.
	 *
	 * @return void
	 */
	public function testIdentityIsStable(): void {
		$this->assertInstanceOf(expected: CleanupCategoryInterface::class, actual: $this->category);
		$this->assertSame(expected: 'expired_locks', actual: ExpiredLocksCategory::NAME);
		$this->assertSame(expected: 'expired_locks', actual: $this->category->getName());
		$this->assertSame(expected: 'Expired dashboard locks', actual: $this->category->getDisplayName());
	}

	/**
	 * The category is safe to purge automatically and is always available.
	 *
	 * @return void
	 */
	public function testTierAndAvailability(): void {
		$this->assertTrue(condition: $this->category->getSafeToPurgeAutomatically());
		$this->assertTrue(condition: $this->category->isAvailable());
	}

	/**
	 * scan() returns the mapper's count and never deletes anything.
	 *
	 * @return void
	 */
	public function testScanReturnsTheMapperCountWithoutDeleting(): void {
		$this->mapper->expects($this->once())->method('countAllExpired')->willReturn(7);
		$this->mapper->expects($this->never())->method('deleteAllExpired');

		$this->assertSame(expected: 7, actual: $this->category->scan());
	}

	/**
	 * scan() reports zero when nothing is orphaned.
	 *
	 * @return void
	 */
	public function testScanReportsZero(): void {
		$this->mapper->expects($this->once())->method('countAllExpired')->willReturn(0);

		$this->assertSame(expected: 0, actual: $this->category->scan());
	}

	/**
	 * purge() deletes through the mapper and returns the deleted row count.
	 *
	 * @return void
	 */
	public function testPurgeReturnsTheMapperDeleteCount(): void {
		$this->mapper->expects($this->once())->method('deleteAllExpired')->willReturn(4);
		$this->mapper->expects($this->never())->method('countAllExpired');

		$this->assertSame(expected: 4, actual: $this->category->purge());
	}

	/**
	 * A dry-run purge takes the same delete path: the orchestrator's
	 * transaction rollback, not the category, keeps a dry run harmless.
	 *
	 * @return void
	 */
	public function testDryRunPurgeUsesTheSameDeletePath(): void {
		$this->mapper->expects($this->once())->method('deleteAllExpired')->willReturn(2);
		$this->mapper->expects($this->never())->method('countAllExpired');

		$this->assertSame(expected: 2, actual: $this->category->purge(dryRun: true));
	}
}
