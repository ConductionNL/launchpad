<?php

/**
 * Unit tests for the MCP widget and tile write tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpWidgetTools;
use OCA\LaunchPad\Service\CalculatedFieldService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The widget and tile tools call WidgetService as the widgetApi routes do,
 * after the route's action and per-object check, and refuse what they leave
 * to the app.
 *
 * @covers \OCA\LaunchPad\Mcp\McpWidgetTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpWidgetToolsTest extends TestCase {

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * @var WidgetService&MockObject
	 */
	private WidgetService $widgets;

	/**
	 * @var CalculatedFieldService&MockObject
	 */
	private CalculatedFieldService $calculated;

	/**
	 * @var RoleFeaturePermissionService&MockObject
	 */
	private RoleFeaturePermissionService $roles;

	/**
	 * The actions the gate was asked for.
	 *
	 * @var list<string>
	 */
	private array $asked = [];

	/**
	 * Build the doubles; the gate allows everything and records the actions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->gate->method('requireAction')->willReturnCallback(
			callback: function (string $action): ?array {
				$this->asked[] = $action;
				return null;
			}
		);
		$this->widgets    = $this->createMock(originalClassName: WidgetService::class);
		$this->calculated = $this->createMock(originalClassName: CalculatedFieldService::class);
		$this->roles      = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$this->roles->method('isWidgetAllowed')->willReturn(true);
	}//end setUp()

	/**
	 * The tools under test.
	 *
	 * @return McpWidgetTools
	 */
	private function tools(): McpWidgetTools {
		return new McpWidgetTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			sources: new WidgetApiSources(
				widgetService: $this->widgets,
				newsWidgetService: $this->createMock(originalClassName: NewsWidgetService::class),
				calendarService: $this->createMock(originalClassName: \OCA\LaunchPad\Kernel\Contracts\CalendarWidgetServiceInterface::class),
				placementService: $this->createMock(originalClassName: WidgetPlacementService::class),
				calculatedFields: $this->calculated
			),
			roleWidgets: $this->roles
		);
	}//end tools()

	/**
	 * A placement of a widget type with content.
	 *
	 * @param string               $type    The widget type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(string $type, array $content = []): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId(5);
		$placement->setWidgetId($type);
		$placement->setContentArray($content);
		return $placement;
	}//end placement()

	/**
	 * addWidget places the widget with the layout asked, behind widget.add-widget.
	 *
	 * @return void
	 */
	public function testAddWidgetPlacesTheWidget(): void {
		$this->gate->method('requireAddWidget')->willReturn(null);
		$placed = self::placement(type: 'calendar');
		$this->widgets->expects($this->once())->method('addWidget')->willReturnCallback(
			callback: static fn (int $dashboardId, string $widgetId, array $layout): WidgetPlacement => match (true) {
				$dashboardId === 3 && $widgetId === 'calendar' && $layout['gridWidth'] === 6 => $placed,
			}
		);

		$result = $this->tools()->addWidget(args: ['dashboardId' => 3, 'widgetId' => 'calendar', 'gridWidth' => 6]);

		$this->assertEquals(expected: $placed->jsonSerialize(), actual: $result);
		$this->assertSame(expected: ['widget.add-widget'], actual: $this->asked);
	}//end testAddWidgetPlacesTheWidget()

	/**
	 * A widget the role may not place, or a dashboard the caller may not add to, changes nothing.
	 *
	 * @return void
	 */
	public function testAddWidgetRefusals(): void {
		$this->widgets->expects($this->never())->method('addWidget');
		$this->gate->method('requireAddWidget')->willReturnCallback(
			callback: static fn (int $dashboardId): ?array => match ($dashboardId) {
				3 => null,
				default => McpArgumentValidator::error(code: 'forbidden', message: 'x'),
			}
		);
		$roles = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$roles->method('isWidgetAllowed')->willReturn(false);
		$this->roles = $roles;

		$this->assertSame(expected: 'forbidden', actual: $this->tools()->addWidget(args: ['dashboardId' => 4, 'widgetId' => 'news'])['error']);
		$this->assertSame(expected: 'forbidden', actual: $this->tools()->addWidget(args: ['dashboardId' => 3, 'widgetId' => 'news'])['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->addWidget(args: ['dashboardId' => 3])['error']);
	}//end testAddWidgetRefusals()

	/**
	 * createTile is addWidget with the type tile and the tile's content.
	 *
	 * @return void
	 */
	public function testCreateTileAddsATileWidget(): void {
		$this->gate->method('requireAddWidget')->willReturn(null);
		$seen = null;
		$this->widgets->method('addWidget')->willReturnCallback(
			callback: static function (int $dashboardId, string $widgetId, array $layout) use (&$seen): WidgetPlacement {
				$seen = [$widgetId, $layout['content']];
				return self::placement(type: 'tile', content: $layout['content']);
			}
		);

		$this->tools()->createTile(args: ['dashboardId' => 3, 'title' => 'Intranet', 'linkType' => 'url', 'linkValue' => 'https://intra.example.nl']);

		$this->assertSame(expected: ['tile', ['title' => 'Intranet', 'linkType' => 'url', 'linkValue' => 'https://intra.example.nl']], actual: $seen);
		$ftp = ['dashboardId' => 3, 'title' => 'x', 'linkType' => 'ftp', 'linkValue' => 'y'];
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->createTile(args: $ftp)['error']);
	}//end testCreateTileAddsATileWidget()

	/**
	 * updateTile keeps the tile's other content and refuses a non-tile.
	 *
	 * @return void
	 */
	public function testUpdateTileMergesContent(): void {
		$this->gate->method('requireStyleWidget')->willReturn(null);
		$this->widgets->method('getPlacement')->willReturnOnConsecutiveCalls(
			self::placement(type: 'tile', content: ['title' => 'Old', 'linkType' => 'app', 'linkValue' => 'files']),
			self::placement(type: 'tile', content: ['title' => 'Old', 'linkType' => 'app', 'linkValue' => 'files']),
			self::placement(type: 'news')
		);
		$this->calculated->method('hasServerFields')->willReturn(false);
		$seen = null;
		$this->widgets->method('updatePlacement')->willReturnCallback(
			callback: static function (int $placementId, array $data) use (&$seen): WidgetPlacement {
				$seen = $data;
				return self::placement(type: 'tile', content: $data['content']);
			}
		);

		$this->tools()->updateTile(args: ['placementId' => 5, 'title' => 'New']);

		$this->assertSame(expected: ['content' => ['title' => 'New', 'linkType' => 'app', 'linkValue' => 'files']], actual: $seen);
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->updateTile(args: ['placementId' => 5, 'title' => 'New'])['error']);
		$this->assertSame(expected: ['widget.update-placement', 'widget.update-placement'], actual: $this->asked);
	}//end testUpdateTileMergesContent()

	/**
	 * updateWidgetPlacement leaves calculated fields, acknowledgement settings
	 * and the content of materialised widgets to the app, and maps a stale version.
	 *
	 * @return void
	 */
	public function testUpdateWidgetPlacementBoundaries(): void {
		$this->gate->method('requireStyleWidget')->willReturn(null);
		$this->widgets->method('getPlacement')->willReturn(self::placement(type: 'chart'));
		$this->calculated->method('hasServerFields')->willReturn(true);
		$this->widgets->method('updatePlacement')->willThrowException(
			exception: new StalePlacementException(current: self::placement(type: 'chart'))
		);

		$update = fn (array $fields): string => $this->tools()->updateWidgetPlacement(args: ['placementId' => 5] + $fields)['error'];
		$this->assertSame(expected: 'invalid_arguments', actual: $update(['calculatedFields' => []]));
		$this->assertSame(expected: 'invalid_arguments', actual: $update(['content' => ['a' => 1]]));
		$this->assertSame(expected: 'stale', actual: $update(['gridX' => 2, 'version' => 1]));
	}//end testUpdateWidgetPlacementBoundaries()

	/**
	 * removeWidget cleans the OpenRegister fields first; deleteTile refuses a non-tile.
	 *
	 * @return void
	 */
	public function testRemoveWidgetAndDeleteTile(): void {
		$this->gate->method('requireRemoveWidget')->willReturn(null);
		$this->widgets->method('getPlacement')->willReturnOnConsecutiveCalls(
			self::placement(type: 'chart', content: ['dataSource' => ['register' => 'r', 'schema' => 's']]),
			self::placement(type: 'chart')
		);
		$this->calculated->method('hasServerFields')->willReturn(true);
		$this->calculated->expects($this->once())->method('cleanupServerFields')->willReturn([['warning' => 'server_fields_not_removed']]);
		$this->widgets->expects($this->once())->method('removePlacement');

		$this->assertSame(
			expected: ['removed' => true, 'warnings' => [['warning' => 'server_fields_not_removed']]],
			actual: $this->tools()->removeWidget(args: ['placementId' => 5])
		);
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->deleteTile(args: ['placementId' => 5])['error']);
	}//end testRemoveWidgetAndDeleteTile()
}//end class
