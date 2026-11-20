<?php

/**
 * Unit tests for the MCP dashboard tree and template gallery read tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-8
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpCatalogueTools;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Kernel\Contracts\AnalyticsServiceInterface;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use Psr\Log\NullLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use OCA\LaunchPad\Mcp\McpCatalogueSources;
use OCA\LaunchPad\Service\TileService;

/**
 * getDashboardTree is the tree filtered to the caller's visible set, and
 * listTemplates is the gallery, as their routes answer.
 *
 * @covers \OCA\LaunchPad\Mcp\McpCatalogueTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpCatalogueToolsTest extends TestCase {

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * @var DashboardService&MockObject
	 */
	private DashboardService $dashboards;

	/**
	 * @var DashboardTreeService&MockObject
	 */
	private DashboardTreeService $tree;

	/**
	 * @var AdminTemplateService&MockObject
	 */
	private AdminTemplateService $templates;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->tree       = $this->createMock(originalClassName: DashboardTreeService::class);
		$this->templates  = $this->createMock(originalClassName: AdminTemplateService::class);
	}//end setUp()

	/**
	 * The tools under test.
	 *
	 * @return McpCatalogueTools
	 */
	private function tools(): McpCatalogueTools {
		return new McpCatalogueTools(
			gate: $this->gate,
			services: new DashboardApiServices(dashboardService: $this->dashboards),
			history: new DashboardApiHistory(
				treeService: $this->tree,
				versionService: $this->createMock(originalClassName: DashboardVersionService::class),
				analyticsService: $this->createMock(originalClassName: AnalyticsServiceInterface::class),
				logger: new NullLogger()
			),
			sources: new McpCatalogueSources(templates: $this->templates, tiles: $this->createMock(originalClassName: TileService::class))
		);
	}//end tools()

	/**
	 * A dashboard with a UUID.
	 *
	 * @param string $uuid The UUID.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(string $uuid): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		return $dashboard;
	}//end dashboard()

	/**
	 * The tree is built over exactly the UUIDs the caller can see.
	 *
	 * @return void
	 */
	public function testTheTreeIsFilteredToTheVisibleSet(): void {
		$this->gate->expects($this->once())->method('requireAction')->willReturnCallback(
			callback: static fn (string $action): ?array => match ($action) {
				'dashboard.tree' => null,
				default => ['isError' => true],
			}
		);
		$this->dashboards->method('getVisibleToUser')->willReturnCallback(
			callback: static fn (string $userId): array => match ($userId) {
				'alice' => [
					['dashboard' => self::dashboard(uuid: 'a'), 'source' => 'own'],
					['dashboard' => self::dashboard(uuid: 'b'), 'source' => 'group'],
			],
				default => [],
			}
		);
		$seen = null;
		$this->tree->method('getFilteredTree')->willReturnCallback(
			callback: static function (array $visibleUuids) use (&$seen): array {
				$seen = $visibleUuids;
				return [['uuid' => 'a', 'children' => []]];
			}
		);

		$this->assertSame(expected: ['tree' => [['uuid' => 'a', 'children' => []]]], actual: $this->tools()->getDashboardTree(args: []));
		$this->assertSame(expected: ['a' => true, 'b' => true], actual: $seen);
	}//end testTheTreeIsFilteredToTheVisibleSet()

	/**
	 * A refused caller gets no tree.
	 *
	 * @return void
	 */
	public function testARefusedCallerGetsNoTree(): void {
		$this->gate->method('requireAction')->willReturn(McpArgumentValidator::error(code: 'forbidden', message: 'x'));
		$this->tree->expects($this->never())->method('getFilteredTree');

		$this->assertSame(expected: 'forbidden', actual: $this->tools()->getDashboardTree(args: [])['error']);
	}//end testARefusedCallerGetsNoTree()

	/**
	 * The templates are the caller's gallery for the category and sort asked.
	 *
	 * @return void
	 */
	public function testListTemplatesIsTheGallery(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$this->templates->expects($this->once())->method('getGallery')->willReturnCallback(
			callback: static fn (string $userId, ?string $category, string $sortBy): array => [['user' => $userId, 'category' => $category, 'sort' => $sortBy]]
		);

		$this->assertSame(
			expected: ['templates' => [['user' => 'alice', 'category' => 'sprint', 'sort' => 'updatedAt']]],
			actual: $this->tools()->listTemplates(args: ['category' => 'sprint', 'sort' => 'updatedAt'])
		);
	}//end testListTemplatesIsTheGallery()

	/**
	 * An unknown sort is refused before the gallery is read.
	 *
	 * @return void
	 */
	public function testAnUnknownSortIsRefused(): void {
		$this->templates->expects($this->never())->method('getGallery');

		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->listTemplates(args: ['sort' => 'random'])['error']);
	}//end testAnUnknownSortIsRefused()
}//end class
