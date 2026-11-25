<?php

/**
 * HiddenWidgetsOverview Test
 *
 * Personal-settings REQ-PSET-003: a person sees every widget they hid, on
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

	/**
	 * Two people's layers: Pieter on three dashboards, Sanne on one.
	 *
	 * @return void
	 */
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

	/**
	 * Every hidden widget, named, with its dashboard, read for the caller only.
	 *
	 * @return void
	 */
	public function testItListsEveryHiddenWidgetWithItsDashboard(): void {
		$list = $this->service()->listFor(userId: 'pieter');

		$this->assertSame(expected: [101, 102, 201], actual: array_column($list, 'placementId'));
		$this->assertSame(
			expected: ['Intranet Zuiddrecht', 'Intranet Zuiddrecht', 'Team Vergunningen'],
			actual: array_column($list, 'dashboardName')
		);
		$this->assertSame(expected: 'Verjaardagen', actual: $list[0]['placement']['customTitle']);
		$this->assertSame(expected: ['pieter'], actual: array_values(array_unique($this->askedFor)));
	}//end testItListsEveryHiddenWidgetWithItsDashboard()

	/**
	 * A dashboard the person can no longer see is left out.
	 *
	 * @return void
	 */
	public function testADashboardThePersonLostIsLeftOut(): void {
		$list = $this->service(visible: [10])->listFor(userId: 'pieter');

		$this->assertSame(expected: [101, 102], actual: array_column($list, 'placementId'));
	}//end testADashboardThePersonLostIsLeftOut()

	/**
	 * A hidden id whose placement is gone is left out.
	 *
	 * @return void
	 */
	public function testAHiddenIdWhosePlacementIsGoneIsLeftOut(): void {
		$this->layers['pieter'][1] = $this->layer(id: 2, userId: 'pieter', dashboardId: 20, hidden: [201, 999]);

		$list = $this->service()->listFor(userId: 'pieter');

		$this->assertNotContains(needle: 999, haystack: array_column($list, 'placementId'));
	}//end testAHiddenIdWhosePlacementIsGoneIsLeftOut()

	/**
	 * Nothing hidden, or nobody: an empty list.
	 *
	 * @return void
	 */
	public function testNothingHiddenIsAnEmptyList(): void {
		$this->assertSame(expected: [], actual: $this->service()->listFor(userId: 'nobody'));
		$this->assertSame(expected: [], actual: $this->service()->listFor(userId: ''));
	}//end testNothingHiddenIsAnEmptyList()

	/**
	 * Show again takes one widget off the caller's layer and keeps the rest.
	 *
	 * @return void
	 */
	public function testShowAgainTakesOneWidgetOffTheCallersLayerOnly(): void {
		$this->assertTrue(condition: $this->service()->showAgain(userId: 'pieter', placementId: 101));

		$this->assertCount(expectedCount: 1, haystack: $this->updated);
		$this->assertSame(expected: 'pieter', actual: $this->updated[0]->getUserId());
		$this->assertSame(expected: [102], actual: $this->updated[0]->hiddenArray());
		// The other adjustments on that layer stay.
		$this->assertSame(expected: [103 => ['gridX' => 2]], actual: $this->updated[0]->overridesArray());
		// Sanne hid the same placement; her layer is untouched.
		$this->assertSame(expected: [101], actual: $this->layers['sanne'][0]->hiddenArray());
	}//end testShowAgainTakesOneWidgetOffTheCallersLayerOnly()

	/**
	 * A widget the caller did not hide: nothing changes.
	 *
	 * @return void
	 */
	public function testShowAgainOfAWidgetTheCallerDidNotHideChangesNothing(): void {
		$this->assertFalse(condition: $this->service()->showAgain(userId: 'sanne', placementId: 201));
		$this->assertFalse(condition: $this->service()->showAgain(userId: 'pieter', placementId: 0));

		$this->assertSame(expected: [], actual: $this->updated);
	}//end testShowAgainOfAWidgetTheCallerDidNotHideChangesNothing()

	/**
	 * Show all again empties every hidden set of the caller, and only theirs.
	 *
	 * @return void
	 */
	public function testShowAllEmptiesEveryHiddenSetOfTheCaller(): void {
		$this->assertSame(expected: 3, actual: $this->service()->showAll(userId: 'pieter'));

		$this->assertSame(expected: [1, 2], actual: array_map(static fn (PersonalLayer $l): int => (int)$l->getId(), $this->updated));
		foreach ($this->updated as $layer) {
			$this->assertSame(expected: [], actual: $layer->hiddenArray());
		}

		$this->assertSame(expected: [101], actual: $this->layers['sanne'][0]->hiddenArray());
		$this->assertSame(expected: 0, actual: $this->service()->showAll(userId: ''));
	}//end testShowAllEmptiesEveryHiddenSetOfTheCaller()

	/**
	 * The service over fakes.
	 *
	 * @param int[] $visible The dashboard ids the person may see.
	 *
	 * @return HiddenWidgetsOverview
	 */
	private function service(array $visible = [10, 20, 30]): HiddenWidgetsOverview {
		$mapper = $this->getMockBuilder(className: PersonalLayerMapper::class)
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

		$placements = $this->getMockBuilder(className: WidgetPlacementMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByDashboardId'])
			->getMock();
		$placements->method('findByDashboardId')->willReturnCallback(
			fn (int $dashboardId): array => match ($dashboardId) {
				10 => [
					$this->placement(id: 101, dashboardId: 10, title: 'Verjaardagen'),
					$this->placement(id: 102, dashboardId: 10, title: 'Kantinemenu'),
					$this->placement(id: 103, dashboardId: 10, title: 'Nieuws'),
				],
				20 => [$this->placement(id: 201, dashboardId: 20, title: 'Teamagenda')],
				default => [],
			}
		);

		$dashboards = $this->getMockBuilder(className: DashboardService::class)
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
				$name = 'Team Vergunningen';
				if ($dashboardId === 10) {
					$name = 'Intranet Zuiddrecht';
				}

				$dashboard->setName($name);
				return ['dashboard' => $dashboard, 'placements' => []];
			}
		);

		return new HiddenWidgetsOverview(layers: $mapper, placements: $placements, dashboards: $dashboards);
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
