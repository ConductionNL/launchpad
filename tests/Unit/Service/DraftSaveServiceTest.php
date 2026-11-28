<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for DraftSaveService: an edit-mode Save stores the whole draft
 * in one go, every change checked first, nothing stored when one is refused
 * or a colleague changed a widget first (REQ-EDIT-002).
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\DraftConflictException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\Service\DashboardDraft;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\DraftRules;
use OCA\LaunchPad\Service\DraftSaveGuard;
use OCA\LaunchPad\Service\DraftSaveService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DraftSaveService.
 */
class DraftSaveServiceTest extends TestCase {
	/** @var WidgetService&MockObject */
	private WidgetService $widgets;

	/** @var PermissionService&MockObject */
	private PermissionService $permissions;

	/** @var RoleFeaturePermissionService&MockObject */
	private RoleFeaturePermissionService $roleWidgets;

	/** @var DashboardService&MockObject */
	private DashboardService $dashboards;

	/** @var DashboardVersionService&MockObject */
	private DashboardVersionService $versions;

	/** @var list<string> The writes, in order. */
	private array $writes = [];

	/**
	 * The visibility rules.
	 *
	 * @var ConditionalServiceInterface&MockObject
	 */
	private ConditionalServiceInterface&MockObject $rules;

	/**
	 * Mocks: two stored widgets (1 at the top, 2 below), everything allowed,
	 * the version service running the save it wraps.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->widgets     = $this->createMock(originalClassName: WidgetService::class);
		$this->permissions = $this->createMock(originalClassName: PermissionService::class);
		$this->roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$this->dashboards  = $this->createMock(originalClassName: DashboardService::class);
		$this->versions    = $this->createMock(originalClassName: DashboardVersionService::class);
		$this->rules       = $this->createMock(originalClassName: ConditionalServiceInterface::class);

		$this->widgets->method('getDashboardPlacements')->willReturn([
			$this->placement(id: 1, gridY: 0),
			$this->placement(id: 2, gridY: 4),
		]);
		$this->widgets->method('removePlacement')->willReturnCallback(function (int $placementId): void {
			$this->writes[] = 'remove '.$placementId;
		});
		$this->widgets->method('addWidget')->willReturnCallback(function (int $dashboardId, string $widgetId, array $layout): WidgetPlacement {
			$this->writes[] = 'add '.$widgetId.' '.json_encode(value: $layout);
			return $this->placement(id: 9, gridY: 0);
		});
		$this->dashboards->method('updateDashboard')->willReturnCallback(function (int $dashboardId, string $userId, array $data): Dashboard {
			$this->writes[] = 'dashboard '.json_encode(value: $data);
			return new Dashboard();
		});
		$this->versions->method('saveWithVersion')->willReturnCallback(
			static fn (int $dashboardId, string $createdBy, \Closure $save): Dashboard => $save()
		);

		foreach (['canRemoveWidget', 'canAddWidget', 'canStyleWidget', 'canManageAcknowledgement', 'mayPlaceCompulsory'] as $check) {
			$this->permissions->method($check)->willReturn(true);
		}

		$this->roleWidgets->method('isWidgetAllowed')->willReturn(true);
	}//end setUp()

	/**
	 * A stored placement.
	 *
	 * @param int $id    Its id.
	 * @param int $gridY Its row.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $id, int $gridY): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setId($id);
		$placement->setDashboardId(7);
		$placement->setWidgetId('text');
		$placement->setGridX(0);
		$placement->setGridY($gridY);
		$placement->setGridWidth(4);
		$placement->setGridHeight(4);
		$placement->setVersion(3);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}//end placement()

	/**
	 * A draft of dashboard 7.
	 *
	 * @param list<array<string, mixed>> $placements  The placement list.
	 * @param string                     $userId      The person saving.
	 * @param int|null                   $gridColumns The grid columns.
	 *
	 * @return DashboardDraft
	 */
	private function draft(array $placements, string $userId='alice', ?int $gridColumns=null): DashboardDraft {
		return new DashboardDraft(dashboardId: 7, userId: $userId, gridColumns: $gridColumns, placements: $placements);
	}//end draft()

	/**
	 * The service under test.
	 *
	 * @return DraftSaveService
	 */
	private function service(): DraftSaveService {
		return new DraftSaveService(
			widgets: $this->widgets,
			guard: new DraftSaveGuard(
				permissions: $this->permissions,
				roleWidgets: $this->roleWidgets,
				placements: $this->createMock(originalClassName: WidgetPlacementService::class),
				rules: new DraftRules(rules: $this->rules, permissions: $this->permissions)
			),
			dashboards: $this->dashboards,
			versions: $this->versions
		);
	}//end service()

	/**
	 * A draft that moves widget 1, drops widget 2 and adds a clock is sorted
	 * into one change (with the version it started from), one removal and
	 * one addition; an untouched widget is no change.
	 *
	 * @return void
	 */
	public function testThePlanSortsTheDraft(): void {
		$plan = $this->service()->plan(draft: $this->draft(placements: [
			['id' => 1, 'gridX' => 0, 'gridY' => 8, 'gridWidth' => 4, 'gridHeight' => 4, 'version' => 3],
			['widgetId' => 'clock', 'gridX' => 4, 'gridY' => 0],
		]));

		$this->assertSame(expected: [1 => ['gridY' => 8, 'version' => 3]], actual: $plan['change']);
		$this->assertSame(expected: [2], actual: $plan['remove']);
		$this->assertSame(expected: 'clock', actual: $plan['add'][0]['widgetId']);

		$unchanged = $this->service()->plan(draft: $this->draft(placements: [
			['id' => 1, 'gridY' => 0, 'version' => 3],
			['id' => 2, 'gridY' => 4],
		]));
		$this->assertSame(expected: ['add' => [], 'change' => [], 'remove' => [], 'rules' => []], actual: $unchanged);
	}//end testThePlanSortsTheDraft()

	/**
	 * The rules dialog changes the draft only: Save replaces the widget's
	 * rules inside the version's save, after the placement changes.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
	 *
	 * @return void
	 */
	public function testSaveReplacesTheRulesTheDraftChanged(): void {
		$stored = new \OCA\LaunchPad\Db\ConditionalRule();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$stored->setId(40);
		$this->rules->method('getRules')->with(1)->willReturn([$stored]);
		$this->rules->method('deleteRule')->willReturnCallback(function (int $ruleId): void {
			$this->writes[] = 'delete rule '.$ruleId;
		});
		$this->rules->method('addRule')->willReturnCallback(
			function (int $placementId, string $ruleType, array $ruleConfig, bool $isInclude): \OCA\LaunchPad\Db\ConditionalRule {
				$this->writes[] = 'add rule '.$placementId.' '.$ruleType.' '.json_encode(value: $ruleConfig).' '.var_export(value: $isInclude, return: true);
				return new \OCA\LaunchPad\Db\ConditionalRule();
			}
		);
		$this->permissions->expects($this->once())->method('verifyPlacementOwnership')->with('alice', 1);

		$this->service()->save(draft: $this->draft(placements: [
			['id' => 1, 'rules' => [['ruleType' => 'group', 'ruleConfig' => ['groups' => ['hr']], 'isInclude' => false]]],
			['id' => 2],
		]));

		$this->assertSame(expected: ['dashboard []', 'delete rule 40', "add rule 1 group {\"groups\":[\"hr\"]} false"], actual: $this->writes);
	}//end testSaveReplacesTheRulesTheDraftChanged()

	/**
	 * Someone who does not own the dashboard may not change who sees a
	 * widget; malformed rules and rules on a new widget are refused; nothing
	 * is stored.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 *
	 * @return void
	 */
	public function testRefusedRulesStoreNothing(): void {
		$this->versions->expects($this->never())->method('saveWithVersion');
		$this->permissions->method('verifyPlacementOwnership')->willThrowException(new ForbiddenException(message: 'no'));
		$rule = ['ruleType' => 'group', 'ruleConfig' => []];
		$refusals = [
			[[['id' => 1, 'rules' => [$rule]], ['id' => 2]], ForbiddenException::class],
			[[['id' => 1, 'rules' => [['ruleType' => 'weather', 'ruleConfig' => []]]], ['id' => 2]], InvalidArgumentException::class],
			[[['id' => 1, 'rules' => [['ruleType' => 'group']]], ['id' => 2]], InvalidArgumentException::class],
			[[['id' => 1, 'rules' => 'all']], InvalidArgumentException::class],
			[[['id' => 1], ['id' => 2], ['widgetId' => 'text', 'rules' => [$rule]]], InvalidArgumentException::class],
		];
		foreach ($refusals as [$placements, $expected]) {
			try {
				$this->service()->save(draft: $this->draft(placements: $placements));
				$this->fail(message: 'The draft was saved');
			} catch (\Exception $e) {
				$this->assertInstanceOf(expected: $expected, actual: $e);
			}
		}
	}//end testRefusedRulesStoreNothing()

	/**
	 * Save writes the columns, then removals, changes and additions, inside
	 * the version service's save.
	 *
	 * @return void
	 */
	public function testSaveWritesTheWholeDraftInsideTheVersionedSave(): void {
		$this->versions->expects($this->once())->method('saveWithVersion');
		$this->widgets->method('updatePlacement')->willReturnCallback(function (int $placementId, array $data): WidgetPlacement {
			$this->writes[] = 'change '.$placementId.' '.json_encode(value: $data);
			return $this->placement(id: $placementId, gridY: 0);
		});

		$this->service()->save(draft: $this->draft(userId: 'alice', gridColumns: 8, placements: [
			['id' => 1, 'gridY' => 8, 'version' => 3],
			['widgetId' => 'clock', 'gridX' => 4, 'gridY' => 0, 'customTitle' => 'Tijd'],
		]));

		$this->assertSame(
			expected: [
				'dashboard {"gridColumns":8}',
				'remove 2',
				'change 1 {"gridY":8,"version":3}',
				'add clock {"isCompulsory":0,"gridX":4,"gridY":0}',
				'change 9 {"customTitle":"Tijd"}',
			],
			actual: $this->writes
		);
	}//end testSaveWritesTheWholeDraftInsideTheVersionedSave()

	/**
	 * The save hands back the widgets it removed as they were stored, so
	 * their calculated fields can leave the source schema after the Save.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function testSaveReturnsTheRemovedWidgets(): void {
		$this->widgets->method('updatePlacement')->willReturnCallback(
			fn (int $placementId): WidgetPlacement => $this->placement(id: $placementId, gridY: 0)
		);

		$saved = $this->service()->save(draft: $this->draft(placements: [['id' => 1, 'version' => 3]]));

		$this->assertSame(expected: [2], actual: array_map(static fn (WidgetPlacement $placement): int => (int) $placement->getId(), $saved['removed']));

		$kept = $this->service()->save(draft: $this->draft(placements: [['id' => 1, 'version' => 3], ['id' => 2, 'version' => 3]]));
		$this->assertSame(expected: [], actual: $kept['removed']);
	}//end testSaveReturnsTheRemovedWidgets()

	/**
	 * One refused change stores nothing: the check runs before the save starts.
	 *
	 * @return void
	 */
	public function testARefusedRemovalStoresNothing(): void {
		$permissions = $this->createMock(originalClassName: PermissionService::class);
		$permissions->method('canRemoveWidget')->willReturn(false);
		$permissions->method('canAddWidget')->willReturn(true);
		$permissions->method('widgetRefusal')->willReturn('This widget is required');
		$this->permissions = $permissions;
		$this->versions->expects($this->never())->method('saveWithVersion');

		$this->expectException(exception: ForbiddenException::class);
		$this->expectExceptionMessage(message: 'This widget is required');
		$this->service()->save(draft: $this->draft(userId: 'member', gridColumns: null, placements: [
			['id' => 1],
			['widgetId' => 'text'],
		]));
	}//end testARefusedRemovalStoresNothing()

	/**
	 * A widget type the role may not add refuses the whole save.
	 *
	 * @return void
	 */
	public function testAWidgetTheRoleMayNotAddRefusesTheSave(): void {
		$roleWidgets = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roleWidgets->method('isWidgetAllowed')->willReturn(false);
		$this->roleWidgets = $roleWidgets;
		$this->versions->expects($this->never())->method('saveWithVersion');

		$this->expectException(exception: ForbiddenException::class);
		$this->service()->save(draft: $this->draft(userId: 'member', gridColumns: null, placements: [
			['id' => 1],
			['id' => 2],
			['widgetId' => 'iframe'],
		]));
	}//end testAWidgetTheRoleMayNotAddRefusesTheSave()

	/**
	 * A widget of another dashboard is refused as a bad request.
	 *
	 * @return void
	 */
	public function testAWidgetOfAnotherDashboardIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->plan(draft: $this->draft(placements: [['id' => 99]]));
	}//end testAWidgetOfAnotherDashboardIsRefused()

	/**
	 * Calculated fields stay with the widget form; a draft changing them is refused.
	 *
	 * @return void
	 */
	public function testADraftChangingCalculatedFieldsIsRefused(): void {
		$this->versions->expects($this->never())->method('saveWithVersion');
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->save(draft: $this->draft(userId: 'alice', gridColumns: null, placements: [
			['id' => 1, 'calculatedFields' => [['key' => 'x']]],
			['id' => 2],
		]));
	}//end testADraftChangingCalculatedFieldsIsRefused()

	/**
	 * Widgets a colleague changed first make the save fail with all of them,
	 * so the transaction rolls back (design D4).
	 *
	 * @return void
	 */
	public function testColleaguesChangesFailTheSaveWithThoseWidgets(): void {
		$this->widgets->method('updatePlacement')->willReturnCallback(function (int $placementId): WidgetPlacement {
			throw new StalePlacementException(current: $this->placement(id: $placementId, gridY: 12));
		});

		try {
			$this->service()->save(draft: $this->draft(userId: 'alice', gridColumns: null, placements: [
				['id' => 1, 'gridY' => 8, 'version' => 2],
				['id' => 2, 'gridY' => 9, 'version' => 2],
			]));
			$this->fail(message: 'Expected a conflict');
		} catch (DraftConflictException $e) {
			$this->assertSame(expected: [1, 2], actual: array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $e->getCurrent()));
		}
	}//end testColleaguesChangesFailTheSaveWithThoseWidgets()

	/**
	 * A tile added in the draft is created the way the tile route does, with
	 * its title, colours and link under the tile route's keys.
	 *
	 * @return void
	 */
	public function testANewTileIsCreatedThroughTheTileRoute(): void {
		$this->widgets->method('addTileFromArray')->willReturnCallback(function (int $dashboardId, array $tileData): WidgetPlacement {
			$this->writes[] = 'tile '.json_encode(value: $tileData);
			return $this->placement(id: 10, gridY: 0);
		});
		$this->widgets->method('updatePlacement')->willReturnCallback(function (int $placementId, array $data): WidgetPlacement {
			$this->writes[] = 'change '.$placementId.' '.json_encode(value: $data);
			return $this->placement(id: $placementId, gridY: 0);
		});

		$this->service()->save(draft: $this->draft(placements: [
			['id' => 1],
			['id' => 2],
			[
				'widgetId' => 'tile', 'gridX' => 0, 'gridY' => 6, 'tileTitle' => 'Word',
				'tileLinkType' => 'url', 'tileLinkValue' => 'https://office.com', 'customTitle' => 'W',
			],
		]));

		$this->assertSame(
			expected: [
				'dashboard []',
				'tile {"gridX":0,"gridY":6,"title":"Word","linkType":"url","linkVal":"https:\\/\\/office.com"}',
				'change 10 {"customTitle":"W"}',
			],
			actual: $this->writes
		);
	}//end testANewTileIsCreatedThroughTheTileRoute()
	/**
	 * An App or link tile in app mode added in the draft is a `tile` widget
	 * with only its content: Save adds it as a widget, so it keeps its app.
	 *
	 * @return void
	 */
	public function testANewAppModeTileIsAddedAsAWidget(): void {
		$this->widgets->expects($this->never())->method('addTileFromArray');
		$this->widgets->method('updatePlacement')->willReturnCallback(function (int $placementId, array $data): WidgetPlacement {
			$this->writes[] = 'change '.$placementId.' '.json_encode(value: $data);
			return $this->placement(id: $placementId, gridY: 0);
		});

		$this->service()->save(draft: $this->draft(placements: [
			['id' => 1],
			['id' => 2],
			[
				'widgetId' => 'tile', 'gridX' => 0, 'gridY' => 8, 'gridWidth' => 4, 'gridHeight' => 4,
				'content' => ['mode' => 'app', 'appId' => 'files'],
			],
		]));

		$this->assertSame(
			expected: [
				'dashboard []',
				'add tile {"isCompulsory":0,"gridX":0,"gridY":8,"gridWidth":4,"gridHeight":4,"content":{"mode":"app","appId":"files"}}',
			],
			actual: $this->writes
		);
	}//end testANewAppModeTileIsAddedAsAWidget()
}//end class
