<?php

/**
 * OrphanedPersonalLayerEntriesCategoryTest
 *
 * The sweep that gives `PersonalLayerService::pruneOrphans()` its caller:
 * the daily cleanup job runs every Tier-A category, and this one walks
 * every stored layer against the placements its dashboard still has.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Cleanup
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Cleanup;

use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Cleanup\OrphanedPersonalLayerEntriesCategory;
use OCA\LaunchPad\Service\PersonalLayerService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the personal layer sweep.
 */
class OrphanedPersonalLayerEntriesCategoryTest extends TestCase {
	/**
	 * Stored layers, keyed by "user|dashboard".
	 *
	 * @var array<string, PersonalLayer>
	 */
	private array $stored = [];

	/**
	 * Live placement ids per dashboard.
	 *
	 * @var array<int, array<int, int>>
	 */
	private array $live = [];

	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];
		// Dashboard 7 lost placements 3 and 5 in a re-sync; dashboard 9 is intact.
		$this->live = [7 => [1, 2, 4, 6], 9 => [20, 21]];
		$this->layer(userId: 'anna', dashboardId: 7, overrides: [1 => ['sortOrder' => 2], 5 => ['gridWidth' => 3]], hidden: [3]);
		$this->layer(userId: 'bram', dashboardId: 7, overrides: [5 => ['gridWidth' => 1]], hidden: []);
		$this->layer(userId: 'anna', dashboardId: 9, overrides: [20 => ['sortOrder' => 1]], hidden: [21]);
	}//end setUp()

	public function testScanCountsEntriesForPlacementsThatAreGoneAndWritesNothing(): void {
		$category = $this->category();

		$this->assertSame(3, $category->scan());
		$this->assertCount(3, $this->stored);
		$this->assertSame([3], $this->stored['anna|7']->hiddenArray());
	}//end testScanCountsEntriesForPlacementsThatAreGoneAndWritesNothing()

	public function testPurgeDropsTheStaleEntriesAndLeavesLiveOnesAlone(): void {
		$category = $this->category();

		$this->assertSame(3, $category->purge());

		$this->assertSame([1 => ['sortOrder' => 2]], $this->stored['anna|7']->overridesArray());
		$this->assertSame([], $this->stored['anna|7']->hiddenArray());
		// Bram's layer only held a removed placement, so the row is gone and
		// he is back on the owner's arrangement.
		$this->assertArrayNotHasKey('bram|7', $this->stored);
		// The intact dashboard is the control.
		$this->assertSame([21], $this->stored['anna|9']->hiddenArray());
		$this->assertSame(0, $category->scan());
	}//end testPurgeDropsTheStaleEntriesAndLeavesLiveOnesAlone()

	public function testALayerOnADeletedDashboardIsRemovedWhole(): void {
		$this->live[9] = [];
		$category = $this->category();

		$category->purge();

		$this->assertArrayNotHasKey('anna|9', $this->stored);
	}//end testALayerOnADeletedDashboardIsRemovedWhole()

	public function testItRunsInTheDailyJobBecauseTheEntriesAreInvisible(): void {
		$category = $this->category();

		$this->assertSame('orphaned_personal_layer_entries', $category->getName());
		$this->assertTrue($category->getSafeToPurgeAutomatically());
		$this->assertTrue($category->isAvailable());
	}//end testItRunsInTheDailyJobBecauseTheEntriesAreInvisible()

	/**
	 * Store one layer.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 * @param array $overrides Per placement overrides.
	 * @param array $hidden Hidden placement ids.
	 *
	 * @return void
	 */
	private function layer(string $userId, int $dashboardId, array $overrides, array $hidden): void {
		$layer = new PersonalLayer();
		$layer->setId((count($this->stored) + 1));
		$layer->setUserId($userId);
		$layer->setDashboardId($dashboardId);
		$layer->setOverrides(json_encode($overrides));
		$layer->setHidden(json_encode($hidden));
		$this->stored[$userId . '|' . $dashboardId] = $layer;
	}//end layer()

	/**
	 * The category over a fake layer store and a fake placement table.
	 *
	 * Both doubles use `onlyMethods`, so they cannot answer a method the
	 * real mappers do not have.
	 *
	 * @return OrphanedPersonalLayerEntriesCategory
	 */
	private function category(): OrphanedPersonalLayerEntriesCategory {
		$layers = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser', 'findAllLayers', 'deleteForUser', 'update'])
			->getMock();
		$layers->method('findForUser')->willReturnCallback(
			fn (string $userId, int $dashboardId): ?PersonalLayer => ($this->stored[$userId . '|' . $dashboardId] ?? null)
		);
		$layers->method('findAllLayers')->willReturnCallback(fn (): array => array_values($this->stored));
		$layers->method('deleteForUser')->willReturnCallback(
			function (string $userId, int $dashboardId): int {
				unset($this->stored[$userId . '|' . $dashboardId]);
				return 1;
			}
		);
		$layers->method('update')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$this->stored[$layer->getUserId() . '|' . $layer->getDashboardId()] = $layer;
				return $layer;
			}
		);

		$placements = $this->getMockBuilder(WidgetPlacementMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByDashboardId'])
			->getMock();
		$placements->method('findByDashboardId')->willReturnCallback(
			function (int $dashboardId): array {
				$out = [];
				foreach (($this->live[$dashboardId] ?? []) as $id) {
					$placement = new WidgetPlacement();
					$placement->setId($id);
					$placement->setDashboardId($dashboardId);
					$out[] = $placement;
				}

				return $out;
			}
		);

		return new OrphanedPersonalLayerEntriesCategory(
			layerMapper: $layers,
			placementMapper: $placements,
			personalLayers: new PersonalLayerService($layers),
		);
	}//end category()
}//end class
