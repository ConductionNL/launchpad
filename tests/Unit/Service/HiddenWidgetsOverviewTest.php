<?php

/**
 * HiddenWidgetsOverview Test
 *
 * personal-settings REQ-PSET-003: a person sees every widget they hid, on
 * every dashboard they may still see, and can show one or all of them again.
 * Only the caller's own layers are read or written.
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
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\HiddenWidgetsOverview;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HiddenWidgetsOverview.
 */
class HiddenWidgetsOverviewTest extends TestCase {
	/**
	 * Layers per user id.
	 *
	 * @var array<string, PersonalLayer[]>
	 */
	private array $layers = [];

	/**
	 * Layers the service wrote back.
	 *
	 * @var PersonalLayer[]
	 */
	private array $updated = [];

	/**
	 * User ids the mapper was asked about.
	 *
	 * @var string[]
	 */
	private array $askedFor = [];

	protected function setUp(): void {
		parent::setUp();
		$this->layers = [
			'pieter' => [
				$this->layer(id: 1, userId: 'pieter', dashboardId: 10, hidden: [101, 102], overrides: '{"103":{"gridX":2}}'),
				$this->layer(id: 2, userId: 'pieter', dashboardId: 20, hidden: [201]),
				$this->layer(id: 3, userId: 'pieter', dashboardId: 30, hidden: []),
			],
			'sanne' => [
				$this->layer(id: 4, userId: 'sanne', dashboardId: 10, hidden: [101]),
			],
		];
		$this->updated = [];
		$this->askedFor = [];
	}//end setUp()

	public function testItListsEveryHiddenWidgetWithItsDashboard(): void {
		$list = $this->service()->listFor(userId: 'pieter');

		$this->assertSame([101, 102, 201], array_column($list, 'placementId'));
		$this->assertSame(['Intranet Zuiddrecht', 'Intranet Zuiddrecht', 'Team Vergunningen'], array_column($list, 'dashboardName'));
		$this->assertSame('Verjaardagen', $list[0]['placement']['customTitle']);
		$this->assertSame(['pieter'], array_values(array_unique($this->askedFor)));
	}//end testItListsEveryHiddenWidgetWithItsDashboard()

	public function testADashboardThePersonLostIsLeftOut(): void {
		$list = $this->service(visible: [10])->listFor(userId: 'pieter');

		$this->assertSame([101, 102], array_column($list, 'placementId'));
	}//end testADashboardThePersonLostIsLeftOut()

	public function testAHiddenIdWhosePlacementIsGoneIsLeftOut(): void {
		$this->layers['pieter'][1] = $this->layer(id: 2, userId: 'pieter', dashboardId: 20, hidden: [201, 999]);

		$list = $this->service()->listFor(userId: 'pieter');

		$this->assertNotContains(999, array_column($list, 'placementId'));
	}//end testAHiddenIdWhosePlacementIsGoneIsLeftOut()

	public function testNothingHiddenIsAnEmptyList(): void {
		$this->assertSame([], $this->service()->listFor(userId: 'nobody'));
		$this->assertSame([], $this->service()->listFor(userId: ''));
	}//end testNothingHiddenIsAnEmptyList()

	public function testShowAgainTakesOneWidgetOffTheCallersLayerOnly(): void {
		$this->assertTrue($this->service()->showAgain(userId: 'pieter', placementId: 101));

		$this->assertCount(1, $this->updated);
		$this->assertSame('pieter', $this->updated[0]->getUserId());
		$this->assertSame([102], $this->updated[0]->hiddenArray());
		// The other adjustments on that layer stay.
		$this->assertSame([103 => ['gridX' => 2]], $this->updated[0]->overridesArray());
		// Sanne hid the same placement; her layer is untouched.
		$this->assertSame([101], $this->layers['sanne'][0]->hiddenArray());
	}//end testShowAgainTakesOneWidgetOffTheCallersLayerOnly()

	public function testShowAgainOfAWidgetTheCallerDidNotHideChangesNothing(): void {
		$this->assertFalse($this->service()->showAgain(userId: 'sanne', placementId: 201));
		$this->assertFalse($this->service()->showAgain(userId: 'pieter', placementId: 0));

		$this->assertSame([], $this->updated);
	}//end testShowAgainOfAWidgetTheCallerDidNotHideChangesNothing()

	public function testShowAllEmptiesEveryHiddenSetOfTheCaller(): void {
		$this->assertSame(3, $this->service()->showAll(userId: 'pieter'));

		$this->assertSame([1, 2], array_map(static fn (PersonalLayer $l): int => (int)$l->getId(), $this->updated));
		foreach ($this->updated as $layer) {
			$this->assertSame([], $layer->hiddenArray());
		}

		$this->assertSame([101], $this->layers['sanne'][0]->hiddenArray());
		$this->assertSame(0, $this->service()->showAll(userId: ''));
	}//end testShowAllEmptiesEveryHiddenSetOfTheCaller()

	/**
	 * The service over fakes.
	 *
	 * @param int[] $visible The dashboard ids the person may see.
	 *
	 * @return HiddenWidgetsOverview
	 */
	private function service(array $visible = [10, 20, 30]): HiddenWidgetsOverview {
		$mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllForUser', 'update'])
			->getMock();
		$mapper->method('findAllForUser')->willReturnCallback(
			function (string $userId): array {
				$this->askedFor[] = $userId;
				return $this->layers[$userId] ?? [];
			}
		);
		$mapper->method('update')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$this->updated[] = $layer;
				return $layer;
			}
		);

		$placements = $this->getMockBuilder(WidgetPlacementMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByDashboardId'])
			->getMock();
		$placements->method('findByDashboardId')->willReturnCallback(
			fn (int $dashboardId): array => match ($dashboardId) {
				10 => [$this->placement(101, 10, 'Verjaardagen'), $this->placement(102, 10, 'Kantinemenu'), $this->placement(103, 10, 'Nieuws')],
				20 => [$this->placement(201, 20, 'Teamagenda')],
				default => [],
			}
		);

		$dashboards = $this->getMockBuilder(DashboardService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getDashboardForUser'])
			->getMock();
		$dashboards->method('getDashboardForUser')->willReturnCallback(
			function (int $dashboardId, string $userId) use ($visible): ?array {
				if (in_array($dashboardId, $visible, true) === false) {
					return null;
				}

				$dashboard = new Dashboard();
				$dashboard->setId($dashboardId);
				$dashboard->setName($dashboardId === 10 ? 'Intranet Zuiddrecht' : 'Team Vergunningen');
				return ['dashboard' => $dashboard, 'placements' => []];
			}
		);

		return new HiddenWidgetsOverview($mapper, $placements, $dashboards);
	}//end service()

	/**
	 * A personal layer.
	 *
	 * @param int    $id          Row id.
	 * @param string $userId      Owner.
	 * @param int    $dashboardId Dashboard.
	 * @param int[]  $hidden      Hidden placement ids.
	 * @param string $overrides   Overrides JSON.
	 *
	 * @return PersonalLayer
	 */
	private function layer(int $id, string $userId, int $dashboardId, array $hidden, string $overrides = '{}'): PersonalLayer {
		$layer = new PersonalLayer();
		$layer->setId($id);
		$layer->setUserId($userId);
		$layer->setDashboardId($dashboardId);
		$layer->setHidden(json_encode($hidden));
		$layer->setOverrides($overrides);
		return $layer;
	}//end layer()

	/**
	 * A shared placement.
	 *
	 * @param int    $id          Placement id.
	 * @param int    $dashboardId Dashboard.
	 * @param string $title       Custom title.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $id, int $dashboardId, string $title): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId('text');
		$placement->setCustomTitle($title);
		return $placement;
	}//end placement()
}//end class
