<?php

/**
 * OrphanedSharesCategory Test
 *
 * Pins the Tier-A cleanup category for expired or orphaned share tokens: its stable identity,
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

use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\Cleanup\CleanupCategoryInterface;
use OCA\LaunchPad\Service\Cleanup\OrphanedSharesCategory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrphanedSharesCategory.
 *
 * @covers \OCA\LaunchPad\Service\Cleanup\OrphanedSharesCategory
 */
class OrphanedSharesCategoryTest extends TestCase {
	/**
	 * The mapper mock the category delegates to.
	 *
	 * @var DashboardShareMapper&MockObject
	 */
	private DashboardShareMapper&MockObject $mapper;

	/**
	 * The category under test.
	 *
	 * @var OrphanedSharesCategory
	 */
	private OrphanedSharesCategory $category;

	/**
	 * Build the category around a mapper mock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper   = $this->createMock(originalClassName: DashboardShareMapper::class);
		$this->category = new OrphanedSharesCategory(shareMapper: $this->mapper);
	}

	/**
	 * The category is a cleanup category with a stable identifier and label.
	 *
	 * @return void
	 */
	public function testIdentityIsStable(): void {
		$this->assertInstanceOf(expected: CleanupCategoryInterface::class, actual: $this->category);
		$this->assertSame(expected: 'expired_share_tokens', actual: OrphanedSharesCategory::NAME);
		$this->assertSame(expected: 'expired_share_tokens', actual: $this->category->getName());
		$this->assertSame(expected: 'Expired or orphaned share tokens', actual: $this->category->getDisplayName());
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
		$this->mapper->expects($this->once())->method('countOrphaned')->willReturn(7);
		$this->mapper->expects($this->never())->method('deleteOrphaned');

		$this->assertSame(expected: 7, actual: $this->category->scan());
	}

	/**
	 * scan() reports zero when nothing is orphaned.
	 *
	 * @return void
	 */
	public function testScanReportsZero(): void {
		$this->mapper->expects($this->once())->method('countOrphaned')->willReturn(0);

		$this->assertSame(expected: 0, actual: $this->category->scan());
	}

	/**
	 * purge() deletes through the mapper and returns the deleted row count.
	 *
	 * @return void
	 */
	public function testPurgeReturnsTheMapperDeleteCount(): void {
		$this->mapper->expects($this->once())->method('deleteOrphaned')->willReturn(4);
		$this->mapper->expects($this->never())->method('countOrphaned');

		$this->assertSame(expected: 4, actual: $this->category->purge());
	}

	/**
	 * A dry-run purge takes the same delete path: the orchestrator's
	 * transaction rollback, not the category, keeps a dry run harmless.
	 *
	 * @return void
	 */
	public function testDryRunPurgeUsesTheSameDeletePath(): void {
		$this->mapper->expects($this->once())->method('deleteOrphaned')->willReturn(2);
		$this->mapper->expects($this->never())->method('countOrphaned');

		$this->assertSame(expected: 2, actual: $this->category->purge(dryRun: true));
	}
}
