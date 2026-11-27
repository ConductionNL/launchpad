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
		return [
			'id'          => $id,
			'widgetId'    => $widgetId,
			'gridX'       => 0,
			'gridY'       => $y,
			'gridWidth'   => 4,
			'gridHeight'  => 2,
			'isVisible'   => 1,
			'showTitle'   => 1,
			'customTitle' => 'W'.$id,
		];
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
			 * Constructor.
			 *
			 * @param array<string, mixed> $row The row.
			 */
			public function __construct(private array $row) {
			}

			/**
			 * The row.
			 *
			 * @return array<string, mixed>
			 */
			public function jsonSerialize(): array {
				return $this->row;
			}
		},
		];
	}//end opened()

	/**
	 * The page values for an opened user dashboard u-1 with two placements.
	 *
	 * @return array<string, mixed>
	 */
	private static function page(): array {
		return [
			'firstLoad'         => ['visible' => [], 'active' => null],
			'activeDashboardId' => 'u-1',
			'dashboardSource'   => 'user',
			'layout'            => [self::placement(id: 1, widgetId: 'text'), self::placement(id: 2, widgetId: 'text', y: 2)],
			'active'            => self::opened(row: ['uuid' => 'u-1', 'name' => 'Projecten', 'gridColumns' => 12]),
		];
	}//end page()

	/**
	 * REQ-FPS-006: no first load, nothing opened, or no dashboard means no shell.
	 *
	 * @return void
	 */
	public function testNoDashboardNoShell(): void {
		$this->assertNull(actual: FirstPaintPage::model(page: ['firstLoad' => null] + self::page(), reader: []));
		$this->assertNull(actual: FirstPaintPage::model(page: ['activeDashboardId' => ''] + self::page(), reader: []));
		$this->assertNull(actual: FirstPaintPage::model(page: ['active' => null] + self::page(), reader: []));
		$this->assertNotNull(actual: FirstPaintPage::model(page: self::page(), reader: []));
	}//end testNoDashboardNoShell()

	/**
	 * The first-load envelope is what the store renders: its placements (after
	 * the visibility rules) win when it is the opened dashboard; a deep link to
	 * another dashboard draws that dashboard's layout.
	 *
	 * @return void
	 */
	public function testTheShellDrawsWhatTheStoreRenders(): void {
		$envelope = [
			'dashboard'  => ['uuid' => 'u-1', 'name' => 'Vandaag', 'gridColumns' => 12, 'isDefault' => 1],
			'placements' => [self::placement(id: 1, widgetId: 'text')],
		];
		$user     = $this->createMock(originalClassName: IUser::class);
		$user->method('getDisplayName')->willReturn('Pieter Jansen');

		$model = FirstPaintPage::model(
			page: ['firstLoad' => ['visible' => [], 'active' => $envelope]] + self::page(),
			reader: ['user' => $user, 'timezone' => 'Europe/Amsterdam']
		);
		$this->assertSame(expected: ['1'], actual: array_column($model['frames'], 'id'));
		$this->assertSame(expected: 'Vandaag', actual: $model['header']['name']);
		$this->assertSame(expected: ['name' => 'Pieter Jansen', 'timezone' => 'Europe/Amsterdam'], actual: $model['reader']);
		$this->assertTrue(condition: $model['isDefault']);
		$this->assertSame(expected: 'user', actual: $model['source']);

		$other = ['dashboard' => ['uuid' => 'u-9'] + $envelope['dashboard']] + $envelope;
		$deep  = FirstPaintPage::model(page: ['firstLoad' => ['visible' => [], 'active' => $other]] + self::page(), reader: []);
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
		$page = [
			'layout' => [
				self::placement(id: 1, widgetId: 'text'),
				self::placement(id: 2, widgetId: 'wiki-pages'),
				self::placement(id: 3, widgetId: 'assigned-cases'),
				self::placement(id: 4, widgetId: 'work-packages'),
			],
			'apps'   => ['openregister' => false, 'dossiq' => 'zaakafhandel', 'integration_openproject' => true],
		] + self::page();
		$model = FirstPaintPage::model(page: $page, reader: []);
		$this->assertSame(expected: ['1', '3', '4'], actual: array_column($model['frames'], 'id'));
		$this->assertFalse(condition: FirstPaintPage::offered(widgetId: 'connections', apps: ['integriq' => '']));
		$this->assertTrue(condition: FirstPaintPage::offered(widgetId: 'calendar', apps: []));
	}//end testWidgetsOfAMissingAppAreLeftOut()

	/**
	 * The parameters carry the shell and the page's own address for Reload;
	 * the reader's zone comes with the page values.
	 *
	 * @return void
	 */
	public function testParamsCarryTheShellAndTheReloadAddress(): void {
		$params = FirstPaintPage::params(
			page: ['timezone' => 'Europe/Amsterdam', 'dashboardSource' => 'group'] + self::page(),
			context: ['user' => null, 'reloadUrl' => '/apps/launchpad/team']
		);
		$this->assertSame(expected: '/apps/launchpad/team', actual: $params['reloadUrl']);
		$this->assertSame(expected: ['1', '2'], actual: array_column($params['firstPaint']['frames'], 'id'));
		$this->assertSame(expected: 'group', actual: $params['firstPaint']['source']);
		$this->assertSame(expected: 'Europe/Amsterdam', actual: $params['firstPaint']['reader']['timezone']);
		$this->assertNull(actual: FirstPaintPage::params(page: ['firstLoad' => null] + self::page(), context: [])['firstPaint']);
	}//end testParamsCarryTheShellAndTheReloadAddress()

	/**
	 * The PHP list is the browser's list: every `requiresApp` in the widget
	 * registry, and nothing else.
	 *
	 * @return void
	 */
	public function testRequiresAppMatchesTheWidgetRegistry(): void {
		$source = (string) file_get_contents(filename: __DIR__.'/../../../src/constants/widgetRegistry.js');
		preg_match_all(
			pattern: "/registerDashboardWidget\\('([^']+)'.*?(?=registerDashboardWidget\\(|\\z)/s",
			subject: $source,
			matches: $blocks,
			flags: PREG_SET_ORDER
		);
		$found = [];
		foreach ($blocks as $block) {
			if (preg_match(pattern: "/requiresApp:\\s*'([^']+)'/", subject: $block[0], matches: $app) === 1) {
				$found[$block[1]] = $app[1];
			}
		}

		ksort(array: $found);
		$expected = FirstPaintPage::REQUIRES_APP;
		ksort(array: $expected);
		$this->assertNotEmpty(actual: $found);
		$this->assertSame(expected: $expected, actual: $found);
	}//end testRequiresAppMatchesTheWidgetRegistry()
}//end class
