<?php

/**
 * SubjectTablesTest
 *
 * The tables behind a subject (REQ-SCH-003/004), read from LaunchPad's own
 * dashboards and widget placements: one per visible widget, from a text
 * widget's table or the rows its content carries; a widget subject by its
 * placement id; the creator must be able to see the subject; filter context
 * applied, and an unknown filter field refused.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\ScheduledExport\InvalidFilterContextException;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCA\LaunchPad\Service\ScheduledExport\SubjectTables;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\SubjectTables
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-22
 */
class SubjectTablesTest extends TestCase {

	private DashboardMapper&MockObject $dashboards;

	private WidgetPlacementMapper&MockObject $placements;

	private PermissionService&MockObject $permissions;

	/**
	 * Dashboard 7 (`d1`) holds placements 11 (a text widget's table), 12
	 * (rows of objects) and 13 (hidden); alice may see it, mallory may not.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('d1');

		$placements = [
			self::placement(id: 11, title: 'Bezwaren', content: ['tableMode' => true, 'tableData' => ['headerRow' => true, 'columnAlignments' => ['left', 'left'], 'rows' => [
				[['text' => 'gemeenteCode'], ['text' => 'aantal']],
				[['text' => 'GM0355'], ['text' => '4']],
				[['text' => 'GM0344'], ['text' => '7']],
			]]]),
			self::placement(id: 12, title: '', content: ['rows' => [['naam' => 'a'], ['naam' => 'b', 'extra' => 'x']]]),
			self::placement(id: 13, title: 'Hidden', content: ['rows' => [['geheim' => 'ja']]], visible: 0),
		];

		$this->dashboards = $this->createMock(DashboardMapper::class);
		$this->dashboards->method('findByUuid')->willReturnCallback(static function (string $uuid) use ($dashboard): Dashboard {
			if ($uuid !== 'd1') {
				throw new DoesNotExistException('gone');
			}

			return $dashboard;
		});

		$this->placements = $this->createMock(WidgetPlacementMapper::class);
		$this->placements->method('findByDashboardId')->with(7)->willReturn($placements);
		$this->placements->method('find')->willReturnCallback(static function (int $id) use ($placements): WidgetPlacement {
			foreach ($placements as $placement) {
				if ($placement->getId() === $id) {
					return $placement;
				}
			}

			throw new DoesNotExistException('gone');
		});

		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('canViewDashboard')->willReturnCallback(static fn (string $userId, int $dashboardId): bool => $userId === 'alice' && $dashboardId === 7);
		$this->permissions->method('canViewPlacement')->willReturnCallback(static fn (string $userId, int $placementId): bool => $userId === 'alice');
	}//end setUp()

	/**
	 * A stored widget placement on dashboard 7.
	 *
	 * @param int                  $id      The placement id.
	 * @param string               $title   Its custom title.
	 * @param array<string, mixed> $content Its content.
	 * @param int                  $visible Whether it is shown.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(int $id, string $title, array $content, int $visible = 1): WidgetPlacement {
		// Entity setters forward positional arguments through __call.
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId(7);
		$placement->setWidgetId('text');
		$placement->setCustomTitle($title === '' ? null : $title);
		$placement->setContent((string)json_encode($content));
		$placement->setIsVisible($visible);

		return $placement;
	}//end placement()

	/**
	 * The tables of the given subject as the given creator.
	 *
	 * @param string               $type          dashboard or widget.
	 * @param string               $id            The subject id.
	 * @param string|null          $creator       The export's creator.
	 * @param array<string, mixed> $filterContext The filter context.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function tables(string $type, string $id, ?string $creator = 'alice', array $filterContext = []): array {
		$export = ['subjectType' => $type, 'subjectId' => $id];
		if ($creator !== null) {
			$export['createdBy'] = $creator;
		}

		return (new SubjectTables($this->dashboards, $this->placements, $this->permissions))->tables(export: $export, filterContext: $filterContext);
	}//end tables()

	/**
	 * A LaunchPad dashboard gives one table per visible widget: a text
	 * widget's table with its header row as headers, and rows of objects
	 * with the union of their keys; a placement without a title is named by
	 * its widget.
	 *
	 * @return void
	 */
	public function testDashboardGivesOneTablePerVisibleWidget(): void {
		$this->assertSame([
			['title' => 'Bezwaren', 'headers' => ['gemeenteCode', 'aantal'], 'rows' => [['GM0355', '4'], ['GM0344', '7']]],
			['title' => 'text', 'headers' => ['naam', 'extra'], 'rows' => [['a', null], ['b', 'x']]],
		], $this->tables(type: 'dashboard', id: 'd1'));
	}//end testDashboardGivesOneTablePerVisibleWidget()

	/**
	 * A text table without a header row is headed `Column 1` and on, and
	 * short rows are padded.
	 *
	 * @return void
	 */
	public function testATextTableWithoutHeaderRowGetsNumberedColumns(): void {
		$placement = self::placement(id: 21, title: 'Los', content: ['tableMode' => true, 'tableData' => ['headerRow' => false, 'rows' => [
			[['text' => 'a'], ['text' => 'b']],
			[['text' => 'c']],
		]]]);
		$this->placements = $this->createMock(WidgetPlacementMapper::class);
		$this->placements->method('find')->with(21)->willReturn($placement);

		$this->assertSame(
			[['title' => 'Los', 'headers' => ['Column 1', 'Column 2'], 'rows' => [['a', 'b'], ['c', '']]]],
			$this->tables(type: 'widget', id: '21')
		);
	}//end testATextTableWithoutHeaderRowGetsNumberedColumns()

	/**
	 * A filter keeps the matching rows of the tables that have the field and leaves the others whole.
	 *
	 * @return void
	 */
	public function testFilterContextKeepsMatchingRows(): void {
		$tables = $this->tables(type: 'dashboard', id: 'd1', filterContext: ['gemeenteCode' => 'GM0355']);

		$this->assertSame([['GM0355', '4']], $tables[0]['rows']);
		$this->assertSame([['a', null], ['b', 'x']], $tables[1]['rows']);
	}//end testFilterContextKeepsMatchingRows()

	/**
	 * A filter on a field no table has is refused with the field's name.
	 *
	 * @return void
	 */
	public function testUnknownFilterFieldIsRefused(): void {
		$this->expectException(InvalidFilterContextException::class);
		$this->expectExceptionMessage('Unknown filter field: provincie');
		$this->tables(type: 'dashboard', id: 'd1', filterContext: ['provincie' => 'NH']);
	}//end testUnknownFilterFieldIsRefused()

	/**
	 * A widget subject is a placement id the creator may see; an unknown one is refused.
	 *
	 * @return void
	 */
	public function testWidgetSubjectIsAPlacementTheCreatorMaySee(): void {
		$this->assertSame('Bezwaren', $this->tables(type: 'widget', id: '11')[0]['title']);

		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('The widget of this export no longer exists');
		$this->tables(type: 'widget', id: '99');
	}//end testWidgetSubjectIsAPlacementTheCreatorMaySee()

	/**
	 * A widget the creator may not see reads as gone.
	 *
	 * @return void
	 */
	public function testWidgetTheCreatorMayNotSeeIsRefused(): void {
		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('The widget of this export no longer exists');
		$this->tables(type: 'widget', id: '11', creator: 'mallory');
	}//end testWidgetTheCreatorMayNotSeeIsRefused()

	/**
	 * A dashboard that is gone is refused.
	 *
	 * @return void
	 */
	public function testMissingDashboardIsRefused(): void {
		$this->expectExceptionMessage('The dashboard of this export no longer exists');
		$this->tables(type: 'dashboard', id: 'gone');
	}//end testMissingDashboardIsRefused()

	/**
	 * A dashboard the creator may not see is not read: the render runs under
	 * the creator's access (REQ-SCH-002).
	 *
	 * @return void
	 */
	public function testDashboardTheCreatorMayNotSeeIsRefused(): void {
		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('The dashboard of this export no longer exists');
		$this->tables(type: 'dashboard', id: 'd1', creator: 'mallory');
	}//end testDashboardTheCreatorMayNotSeeIsRefused()

	/**
	 * An export without a creator reads no dashboard at all.
	 *
	 * @return void
	 */
	public function testDashboardWithoutCreatorIsRefused(): void {
		$this->expectException(RenderException::class);
		$this->tables(type: 'dashboard', id: 'd1', creator: null);
	}//end testDashboardWithoutCreatorIsRefused()

	/**
	 * A widget's calculated fields follow its table as one row of values,
	 * evaluated over the rows the recipient's filter left.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
	 */
	public function testCalculatedFieldsFollowTheirWidgetEvaluatedOverTheFilteredRows(): void {
		$total     = ['type' => 'fieldRef', 'source' => 'self', 'path' => 'total'];
		$placement = self::placement(id: 31, title: 'Bezwaren', content: ['rows' => [['gemeenteCode' => 'GM0355'], ['gemeenteCode' => 'GM0344']]]);
		$placement->setCalculatedFields((string)json_encode([['name' => 'cases', 'label' => 'Cases shown', 'compiledExpression' => $total]]));
		$this->placements = $this->createMock(WidgetPlacementMapper::class);
		$this->placements->method('find')->with(31)->willReturn($placement);

		$tables = $this->tables(type: 'widget', id: '31', filterContext: ['gemeenteCode' => 'GM0355']);

		$this->assertSame(expected: ['title' => 'Bezwaren: calculated fields', 'headers' => ['Cases shown'], 'rows' => [[1]]], actual: $tables[1]);
	}//end testCalculatedFieldsFollowTheirWidgetEvaluatedOverTheFilteredRows()
}//end class
