<?php

/**
 * PersonalLayerService Test
 *
 * REQ-DWMS-001 and REQ-DWMS-002: one person rearranges a dashboard somebody
 * else owns, nobody else sees it, a compulsory widget stays, and one action
 * puts the whole thing back.
 *
 * The control every test here keeps is the shared dashboard itself. A service
 * that returned nothing would satisfy "the other member does not see it"
 * perfectly, so each test asserts what the other member DOES see as well.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\PersonalLayerService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PersonalLayerService.
 */
class PersonalLayerServiceTest extends TestCase {
	/**
	 * The fake store of layers, keyed by "user|dashboard".
	 *
	 * @var array<string, PersonalLayer>
	 */
	private array $stored = [];

	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];
	}//end setUp()

	public function testAPersonWithNoLayerSeesWhatTheOwnerComposed(): void {
		$service = $this->service();
		$shared = $this->dashboardOfSix();

		$seen = $service->applyTo(placements: $shared, userId: 'handler-anna', dashboardId: 7);

		$this->assertSame([1, 2, 3, 4, 5, 6], array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $seen));
	}//end testAPersonWithNoLayerSeesWhatTheOwnerComposed()

	public function testOneMembersArrangementIsInvisibleToTheOthers(): void {
		$service = $this->service();

		$saved = $service->save(
			userId: 'handler-anna',
			dashboardId: 7,
			overrides: [1 => ['sortOrder' => 10], 2 => ['sortOrder' => 11, 'gridWidth' => 4]],
			hide: [3],
			placements: $this->dashboardOfSix()
		);
		$this->assertSame(['saved' => true], $saved);

		$hers = $service->applyTo(placements: $this->dashboardOfSix(), userId: 'handler-anna', dashboardId: 7);
		$this->assertSame([4, 5, 6, 1, 2], array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $hers));
		$this->assertSame(4, (int)$hers[4]->getGridWidth());

		// The control: everybody else still reads the administrator's order,
		// with the third widget in it.
		$theirs = $service->applyTo(placements: $this->dashboardOfSix(), userId: 'handler-bram', dashboardId: 7);
		$this->assertSame([1, 2, 3, 4, 5, 6], array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $theirs));
	}//end testOneMembersArrangementIsInvisibleToTheOthers()

	public function testACompulsoryPlacementCannotBeHiddenAndTheRefusalNamesIt(): void {
		$service = $this->service();
		$placements = $this->dashboardOfSix(compulsory: 4);

		$refused = $service->save(
			userId: 'handler-anna',
			dashboardId: 7,
			overrides: [1 => ['sortOrder' => 9]],
			hide: [4],
			placements: $placements
		);

		$this->assertSame('placement_compulsory', $refused['error']);
		$this->assertSame(4, $refused['placementId']);
		// Nothing landed at all, not even the move that was allowed: a batch
		// that touches a compulsory widget writes nothing.
		$this->assertSame([], $this->stored);
		$this->assertSame(
			[1, 2, 3, 4, 5, 6],
			array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $service->applyTo(placements: $placements, userId: 'handler-anna', dashboardId: 7))
		);
	}//end testACompulsoryPlacementCannotBeHiddenAndTheRefusalNamesIt()

	public function testAPlacementMadeCompulsoryAfterTheFactIsShownAgain(): void {
		$service = $this->service();
		$service->save(userId: 'handler-anna', dashboardId: 7, overrides: [], hide: [4], placements: $this->dashboardOfSix());

		// The administrator marks the widget compulsory later. The hidden
		// entry is still in her layer, and the widget comes back.
		$seen = $service->applyTo(placements: $this->dashboardOfSix(compulsory: 4), userId: 'handler-anna', dashboardId: 7);

		$this->assertContains(4, array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $seen));
	}//end testAPlacementMadeCompulsoryAfterTheFactIsShownAgain()

	public function testResetDeletesTheWholeLayerAndReturnsTheOwnersArrangement(): void {
		$service = $this->service();
		$service->save(
			userId: 'handler-anna',
			dashboardId: 7,
			overrides: [1 => ['sortOrder' => 5]],
			hide: [3],
			placements: $this->dashboardOfSix()
		);
		$this->assertCount(1, $this->stored);

		$this->assertTrue($service->reset(userId: 'handler-anna', dashboardId: 7));

		$this->assertSame([], $this->stored);
		$this->assertSame(
			[1, 2, 3, 4, 5, 6],
			array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $service->applyTo(placements: $this->dashboardOfSix(), userId: 'handler-anna', dashboardId: 7))
		);
		// Resetting again is not a failure, it is a person asking for what
		// they already have.
		$this->assertFalse($service->reset(userId: 'handler-anna', dashboardId: 7));
	}//end testResetDeletesTheWholeLayerAndReturnsTheOwnersArrangement()

	public function testOnlyAdjustableKeysAndKnownPlacementsAreKept(): void {
		$service = $this->service();

		$service->save(
			userId: 'handler-anna',
			dashboardId: 7,
			overrides: [
				1 => ['sortOrder' => 2, 'customTitle' => 'Mine', 'isCompulsory' => 1],
				99 => ['sortOrder' => 1],
			],
			hide: [3, 99],
			placements: $this->dashboardOfSix()
		);

		$layer = $this->stored['handler-anna|7'];
		$this->assertSame([1 => ['sortOrder' => 2]], $layer->overridesArray());
		$this->assertSame([3], $layer->hiddenArray());
	}//end testOnlyAdjustableKeysAndKnownPlacementsAreKept()

	public function testOrphanedEntriesGoWhenAPlacementDisappears(): void {
		$service = $this->service();
		$service->save(
			userId: 'handler-anna',
			dashboardId: 7,
			overrides: [1 => ['sortOrder' => 2], 5 => ['gridWidth' => 3]],
			hide: [3],
			placements: $this->dashboardOfSix()
		);

		// A template re-sync leaves only 1 and 2 behind.
		$dropped = $service->pruneOrphans(userId: 'handler-anna', dashboardId: 7, liveIds: [1, 2]);

		$this->assertSame(2, $dropped);
		$layer = $this->stored['handler-anna|7'];
		$this->assertSame([1 => ['sortOrder' => 2]], $layer->overridesArray());
		$this->assertSame([], $layer->hiddenArray());
	}//end testOrphanedEntriesGoWhenAPlacementDisappears()

	public function testALayerLeftEmptyByTheSweepIsRemovedEntirely(): void {
		$service = $this->service();
		$service->save(userId: 'handler-anna', dashboardId: 7, overrides: [5 => ['gridWidth' => 3]], hide: [3], placements: $this->dashboardOfSix());

		$service->pruneOrphans(userId: 'handler-anna', dashboardId: 7, liveIds: [1, 2]);

		// An empty layer row would keep answering "this person has an
		// arrangement" when they no longer have one.
		$this->assertSame([], $this->stored);
	}//end testALayerLeftEmptyByTheSweepIsRemovedEntirely()

	/**
	 * Six placements in the administrator's order.
	 *
	 * @param int|null $compulsory The placement the administrator marked
	 *                             compulsory, if any.
	 *
	 * @return array<int, WidgetPlacement>
	 */
	private function dashboardOfSix(?int $compulsory = null): array {
		$placements = [];
		for ($id = 1; $id <= 6; $id++) {
			$placement = new WidgetPlacement();
			$placement->setId($id);
			$placement->setDashboardId(7);
			$placement->setSortOrder($id);
			$placement->setGridWidth(2);
			$placement->setIsCompulsory(($id === $compulsory) ? 1 : 0);
			$placements[] = $placement;
		}

		return $placements;
	}//end dashboardOfSix()

	/**
	 * The service over a fake layer store.
	 *
	 * The mapper double uses `onlyMethods`, so it cannot answer a method the
	 * real mapper does not have.
	 *
	 * @return PersonalLayerService
	 */
	private function service(): PersonalLayerService {
		$mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser', 'deleteForUser', 'insert', 'update'])
			->getMock();

		$mapper->method('findForUser')->willReturnCallback(
			function (string $userId, int $dashboardId): ?PersonalLayer {
				return ($this->stored[$userId . '|' . $dashboardId] ?? null);
			}
		);
		$mapper->method('deleteForUser')->willReturnCallback(
			function (string $userId, int $dashboardId): int {
				$key = $userId . '|' . $dashboardId;
				if (isset($this->stored[$key]) === false) {
					return 0;
				}

				unset($this->stored[$key]);
				return 1;
			}
		);
		$mapper->method('insert')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$layer->setId((count($this->stored) + 1));
				$this->stored[$layer->getUserId() . '|' . $layer->getDashboardId()] = $layer;
				return $layer;
			}
		);
		$mapper->method('update')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$this->stored[$layer->getUserId() . '|' . $layer->getDashboardId()] = $layer;
				return $layer;
			}
		);

		return new PersonalLayerService($mapper);
	}//end service()
}//end class
