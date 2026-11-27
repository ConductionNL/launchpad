<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use JsonSerializable;
use OCA\LaunchPad\Controller\FirstPaintPage;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * The template's first-paint parameter: drawn from what the mounted page
 * draws first, none without a dashboard.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 */
class FirstPaintPageTest extends TestCase {

	/**
	 * A placement as the page serialises it.
	 *
	 * @param int    $id       The id.
	 * @param string $widgetId The type.
	 * @param int    $y        The row.
	 *
	 * @return array<string, mixed>
	 */
	private static function placement(int $id, string $widgetId, int $y=0): array {
		return ['id' => $id, 'widgetId' => $widgetId, 'gridX' => 0, 'gridY' => $y, 'gridWidth' => 4, 'gridHeight' => 2, 'isVisible' => 1, 'showTitle' => 1, 'customTitle' => 'W'.$id];
	}//end placement()

	/**
	 * The page's resolved entry for an opened dashboard.
	 *
	 * @param array<string, mixed> $row The dashboard as it serialises.
	 *
	 * @return array{dashboard: JsonSerializable}
	 */
	private static function opened(array $row): array {
		return ['dashboard' => new class($row) implements JsonSerializable {
			/**
			 * @param array<string, mixed> $row The row.
			 */
			public function __construct(private array $row) {
			}

			/**
			 * @return array<string, mixed>
			 */
			public function jsonSerialize(): array {
				return $this->row;
			}
		}];
	}//end opened()

	/**
	 * REQ-FPS-006: no first load, or nothing opened, means no shell.
	 *
	 * @return void
	 */
	public function testNoDashboardNoShell(): void {
		$state = ['activeDashboardId' => 'u-1', 'dashboardSource' => 'user', 'layout' => [self::placement(id: 1, widgetId: 'text')]];
		$this->assertNull(FirstPaintPage::model(firstLoad: null, activeState: $state, active: self::opened(row: ['name' => 'A']), widgets: [], reader: []));
		$this->assertNull(FirstPaintPage::model(firstLoad: ['visible' => [], 'active' => null], activeState: ['activeDashboardId' => ''] + $state, active: self::opened(row: ['name' => 'A']), widgets: [], reader: []));
		$this->assertNull(FirstPaintPage::model(firstLoad: ['visible' => [], 'active' => null], activeState: $state, active: null, widgets: [], reader: []));
	}//end testNoDashboardNoShell()

	/**
	 * The first-load envelope is what the store renders: its placements (after
	 * the visibility rules) win when it is the opened dashboard; a deep link to
	 * another dashboard draws that dashboard's layout.
	 *
	 * @return void
	 */
	public function testTheShellDrawsWhatTheStoreRenders(): void {
		$state     = ['activeDashboardId' => 'u-1', 'dashboardSource' => 'user', 'layout' => [self::placement(id: 1, widgetId: 'text'), self::placement(id: 2, widgetId: 'text', y: 2)]];
		$firstLoad = ['visible' => [], 'active' => ['dashboard' => ['uuid' => 'u-1', 'name' => 'Vandaag', 'gridColumns' => 12, 'isDefault' => 1], 'placements' => [self::placement(id: 1, widgetId: 'text')]]];

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getDisplayName')->willReturn('Pieter Jansen');
		$model = FirstPaintPage::model(firstLoad: $firstLoad, activeState: $state, active: self::opened(row: ['name' => 'Old']), widgets: [], reader: ['user' => $user, 'timezone' => 'Europe/Amsterdam']);
		$this->assertSame(expected: ['1'], actual: array_column($model['frames'], 'id'));
		$this->assertSame(expected: 'Vandaag', actual: $model['header']['name']);
		$this->assertSame(expected: ['name' => 'Pieter Jansen', 'timezone' => 'Europe/Amsterdam'], actual: $model['reader']);
		$this->assertTrue($model['isDefault']);
		$this->assertSame(expected: 'user', actual: $model['source']);

		$deep = FirstPaintPage::model(firstLoad: $firstLoad, activeState: ['activeDashboardId' => 'u-2'] + $state, active: self::opened(row: ['uuid' => 'u-2', 'name' => 'Projecten', 'gridColumns' => 12]), widgets: [], reader: []);
		$this->assertSame(expected: ['1', '2'], actual: array_column($deep['frames'], 'id'));
		$this->assertSame(expected: 'Projecten', actual: $deep['header']['name']);
	}//end testTheShellDrawsWhatTheStoreRenders()

	/**
	 * A widget that reads through an app the viewer lacks is left out, as
	 * the mounted page's offeredForViewer does; a renamed fleet app counts.
	 *
	 * @return void
	 */
	public function testWidgetsOfAMissingAppAreLeftOut(): void {
		$state = ['activeDashboardId' => 'u-1', 'dashboardSource' => 'group', 'layout' => [
			self::placement(id: 1, widgetId: 'text'),
			self::placement(id: 2, widgetId: 'wiki-pages'),
			self::placement(id: 3, widgetId: 'assigned-cases'),
			self::placement(id: 4, widgetId: 'work-packages'),
		]];
		$model = FirstPaintPage::model(
			firstLoad: ['visible' => [], 'active' => null],
			activeState: $state,
			active: self::opened(row: ['uuid' => 'u-1', 'name' => 'Team', 'gridColumns' => 12]),
			widgets: [],
			reader: [],
			apps: ['openregister' => false, 'dossiq' => 'zaakafhandel', 'integration_openproject' => true]
		);
		$this->assertSame(expected: ['1', '3', '4'], actual: array_column($model['frames'], 'id'));
		$this->assertFalse(FirstPaintPage::offered(widgetId: 'connections', apps: ['integriq' => '']));
		$this->assertTrue(FirstPaintPage::offered(widgetId: 'calendar', apps: []));
	}//end testWidgetsOfAMissingAppAreLeftOut()

	/**
	 * The PHP list is the browser's list: every `requiresApp` in the widget
	 * registry, and nothing else.
	 *
	 * @return void
	 */
	public function testRequiresAppMatchesTheWidgetRegistry(): void {
		$source = (string) file_get_contents(__DIR__.'/../../../src/constants/widgetRegistry.js');
		preg_match_all("/registerDashboardWidget\\('([^']+)'.*?(?=registerDashboardWidget\\(|\\z)/s", $source, $blocks, PREG_SET_ORDER);
		$found = [];
		foreach ($blocks as $block) {
			if (preg_match("/requiresApp:\\s*'([^']+)'/", $block[0], $app) === 1) {
				$found[$block[1]] = $app[1];
			}
		}

		ksort($found);
		$expected = FirstPaintPage::REQUIRES_APP;
		ksort($expected);
		$this->assertNotEmpty($found);
		$this->assertSame(expected: $expected, actual: $found);
	}//end testRequiresAppMatchesTheWidgetRegistry()
}//end class
