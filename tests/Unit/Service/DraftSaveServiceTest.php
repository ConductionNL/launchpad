<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for DraftSaveService: an edit-mode Save stores the whole draft
 * in one go, every change checked first, nothing stored when one is refused
 * or a colleague changed a widget first (REQ-EDIT-002).
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\DraftConflictException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardVersionService;
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
				placements: $this->createMock(originalClassName: WidgetPlacementService::class)
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
		$plan = $this->service()->plan(dashboardId: 7, draft: [
			['id' => 1, 'gridX' => 0, 'gridY' => 8, 'gridWidth' => 4, 'gridHeight' => 4, 'version' => 3],
			['widgetId' => 'clock', 'gridX' => 4, 'gridY' => 0],
		]);

		$this->assertSame(expected: [1 => ['gridY' => 8, 'version' => 3]], actual: $plan['change']);
		$this->assertSame(expected: [2], actual: $plan['remove']);
		$this->assertSame(expected: 'clock', actual: $plan['add'][0]['widgetId']);

		$unchanged = $this->service()->plan(dashboardId: 7, draft: [
			['id' => 1, 'gridY' => 0, 'version' => 3],
			['id' => 2, 'gridY' => 4],
		]);
		$this->assertSame(expected: ['add' => [], 'change' => [], 'remove' => []], actual: $unchanged);
	}//end testThePlanSortsTheDraft()

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

		$this->service()->save(dashboardId: 7, userId: 'alice', gridColumns: 8, draft: [
			['id' => 1, 'gridY' => 8, 'version' => 3],
			['widgetId' => 'clock', 'gridX' => 4, 'gridY' => 0, 'customTitle' => 'Tijd'],
		]);

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
		$this->service()->save(dashboardId: 7, userId: 'member', gridColumns: null, draft: [
			['id' => 1],
			['widgetId' => 'text'],
		]);
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
		$this->service()->save(dashboardId: 7, userId: 'member', gridColumns: null, draft: [
			['id' => 1],
			['id' => 2],
			['widgetId' => 'iframe'],
		]);
	}//end testAWidgetTheRoleMayNotAddRefusesTheSave()

	/**
	 * A widget of another dashboard is refused as a bad request.
	 *
	 * @return void
	 */
	public function testAWidgetOfAnotherDashboardIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->plan(dashboardId: 7, draft: [['id' => 99]]);
	}//end testAWidgetOfAnotherDashboardIsRefused()

	/**
	 * Calculated fields stay with the widget form; a draft changing them is refused.
	 *
	 * @return void
	 */
	public function testADraftChangingCalculatedFieldsIsRefused(): void {
		$this->versions->expects($this->never())->method('saveWithVersion');
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->save(dashboardId: 7, userId: 'alice', gridColumns: null, draft: [
			['id' => 1, 'calculatedFields' => [['key' => 'x']]],
			['id' => 2],
		]);
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
			$this->service()->save(dashboardId: 7, userId: 'alice', gridColumns: null, draft: [
				['id' => 1, 'gridY' => 8, 'version' => 2],
				['id' => 2, 'gridY' => 9, 'version' => 2],
			]);
			$this->fail(message: 'Expected a conflict');
		} catch (DraftConflictException $e) {
			$this->assertSame(expected: [1, 2], actual: array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $e->getCurrent()));
		}
	}//end testColleaguesChangesFailTheSaveWithThoseWidgets()
}//end class
