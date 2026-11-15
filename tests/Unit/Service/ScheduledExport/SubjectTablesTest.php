<?php

/**
 * SubjectTablesTest
 *
 * The tables behind a subject (REQ-SCH-003/004): one per dashboard widget
 * from its rows, a widget subject found among the creator's dashboards,
 * filter context applied, and an unknown filter field refused.
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

use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use OCA\LaunchPad\Service\ScheduledExport\InvalidFilterContextException;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCA\LaunchPad\Service\ScheduledExport\SubjectTables;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\SubjectTables
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-22
 */
class SubjectTablesTest extends TestCase {

	/**
	 * A dashboard with a chart (props.rows) and a list (dataSource.rows).
	 *
	 * @return array<string, mixed>
	 */
	private function dashboard(): array {
		return ['id' => 'd1', 'widgets' => [
			['id' => 'w1', 'widgetKey' => 'chart', 'props' => ['title' => 'Bezwaren', 'rows' => [
				['gemeenteCode' => 'GM0355', 'aantal' => 4],
				['gemeenteCode' => 'GM0344', 'aantal' => 7, 'extra' => 'x'],
			]]],
			['id' => 'w2', 'widgetKey' => 'list', 'dataSource' => ['rows' => [['naam' => 'a']]]],
			'not-a-widget',
		]];
	}//end dashboard()

	/**
	 * A dashboard subject gives one table per widget, headers the union of the row keys.
	 *
	 * @return void
	 */
	public function testDashboardGivesOneTablePerWidget(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->expects($this->once())->method('find')->with('dashboard', 'd1')->willReturn($this->dashboard());

		$tables = (new SubjectTables($store))->tables(export: ['subjectType' => 'dashboard', 'subjectId' => 'd1'], filterContext: []);

		$this->assertSame([
			['title' => 'Bezwaren', 'headers' => ['gemeenteCode', 'aantal', 'extra'], 'rows' => [['GM0355', 4, null], ['GM0344', 7, 'x']]],
			['title' => 'list', 'headers' => ['naam'], 'rows' => [['a']]],
		], $tables);
	}//end testDashboardGivesOneTablePerWidget()

	/**
	 * A filter keeps the matching rows of the tables that have the field and leaves the others whole.
	 *
	 * @return void
	 */
	public function testFilterContextKeepsMatchingRows(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->method('find')->willReturn($this->dashboard());

		$tables = (new SubjectTables($store))->tables(export: ['subjectType' => 'dashboard', 'subjectId' => 'd1'], filterContext: ['gemeenteCode' => 'GM0355']);

		$this->assertSame([['GM0355', 4, null]], $tables[0]['rows']);
		$this->assertSame([['a']], $tables[1]['rows']);
	}//end testFilterContextKeepsMatchingRows()

	/**
	 * A filter on a field no table has is refused with the field's name.
	 *
	 * @return void
	 */
	public function testUnknownFilterFieldIsRefused(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->method('find')->willReturn($this->dashboard());

		$this->expectException(InvalidFilterContextException::class);
		$this->expectExceptionMessage('Unknown filter field: provincie');
		(new SubjectTables($store))->tables(export: ['subjectType' => 'dashboard', 'subjectId' => 'd1'], filterContext: ['provincie' => 'NH']);
	}//end testUnknownFilterFieldIsRefused()

	/**
	 * A widget subject is found among the creator's dashboards; a missing one is refused.
	 *
	 * @return void
	 */
	public function testWidgetSubjectIsFoundInTheCreatorsDashboards(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->method('findAll')->with('dashboard', ['@self' => ['owner' => 'alice']])->willReturn([$this->dashboard()]);
		$tables = new SubjectTables($store);

		$found = $tables->tables(export: ['subjectType' => 'widget', 'subjectId' => 'w2', 'createdBy' => 'alice'], filterContext: []);
		$this->assertSame('list', $found[0]['title']);

		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('The widget of this export no longer exists');
		$tables->tables(export: ['subjectType' => 'widget', 'subjectId' => 'w9', 'createdBy' => 'alice'], filterContext: []);
	}//end testWidgetSubjectIsFoundInTheCreatorsDashboards()

	/**
	 * A dashboard that is gone is refused.
	 *
	 * @return void
	 */
	public function testMissingDashboardIsRefused(): void {
		$store = $this->createMock(ExportObjectStore::class);
		$store->method('find')->willReturn(null);

		$this->expectExceptionMessage('The dashboard of this export no longer exists');
		(new SubjectTables($store))->tables(export: ['subjectType' => 'dashboard', 'subjectId' => 'gone'], filterContext: []);
	}//end testMissingDashboardIsRefused()
}//end class
