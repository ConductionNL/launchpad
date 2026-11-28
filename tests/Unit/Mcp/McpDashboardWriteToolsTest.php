<?php

/**
 * Unit tests for the MCP dashboard write tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCA\LaunchPad\Kernel\Contracts\AnalyticsServiceInterface;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpDashboardWriteTools;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The dashboard write tools call DashboardService as the dashboardApi routes
 * do, behind their action and permission check.
 *
 * @covers \OCA\LaunchPad\Mcp\McpDashboardWriteTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 * @uses   \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses   \OCA\LaunchPad\Controller\DashboardApiServices
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Exception\DashboardHasChildrenException
 * @uses   \OCA\LaunchPad\Exception\PersonalDashboardsDisabledException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class McpDashboardWriteToolsTest extends TestCase {

	private const UUID = '0b1e8a52-6c1f-4c3a-9d55-1a2b3c4d5e6f';

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * @var DashboardService&MockObject
	 */
	private DashboardService $dashboards;

	/**
	 * @var DashboardVersionService&MockObject
	 */
	private DashboardVersionService $versions;

	/**
	 * The actions the gate was asked for.
	 *
	 * @var list<string>
	 */
	private array $asked = [];

	/**
	 * Build the doubles; the gate allows every action and records it.
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
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->versions   = $this->createMock(originalClassName: DashboardVersionService::class);
	}//end setUp()

	/**
	 * The tools under test.
	 *
	 * @return McpDashboardWriteTools
	 */
	private function tools(): McpDashboardWriteTools {
		return new McpDashboardWriteTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			services: new DashboardApiServices(dashboardService: $this->dashboards),
			history: new DashboardApiHistory(
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				versionService: $this->versions,
				analyticsService: $this->createMock(originalClassName: AnalyticsServiceInterface::class),
				logger: new NullLogger()
			)
		);
	}//end tools()

	/**
	 * A dashboard with an id.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(9);
		$dashboard->setName('Sprint');
		return $dashboard;
	}//end dashboard()

	/**
	 * createDashboard creates a seeded dashboard and answers it with its placements.
	 *
	 * @return void
	 */
	public function testCreateDashboard(): void {
		$this->gate->method('requireCreateDashboard')->willReturn(null);
		$this->dashboards->method('getUserDashboards')->willReturn([]);
		$this->dashboards->expects($this->once())->method('createDashboard')->willReturnCallback(
			callback: static fn (string $userId, string $name, array $options): Dashboard => match (true) {
				$userId === 'alice' && $name === 'Sprint' && $options === ['seedDefaults' => true, 'slug' => 'sprint'] => self::dashboard(),
			}
		);
		$placement = new WidgetPlacement();
		$placement->setWidgetId('calendar');
		$this->dashboards->method('findPlacements')->willReturn([$placement]);

		$result = $this->tools()->createDashboard(args: ['name' => 'Sprint', 'slug' => 'sprint']);

		$this->assertEquals(expected: ['dashboard' => self::dashboard()->jsonSerialize(), 'placements' => [$placement->jsonSerialize()]], actual: $result);
		$this->assertSame(expected: ['dashboard.create'], actual: $this->asked);
	}//end testCreateDashboard()

	/**
	 * Personal dashboards off, or a second dashboard not allowed, creates nothing.
	 *
	 * @return void
	 */
	public function testCreateDashboardRefusals(): void {
		$this->dashboards->expects($this->never())->method('createDashboard');
		$this->dashboards->method('getUserDashboards')->willReturn([self::dashboard()]);
		$this->gate->method('requireCreateDashboard')->willReturnCallback(
			callback: static fn (bool $hasDashboards): ?array => match ($hasDashboards) {
				true => McpArgumentValidator::error(code: 'forbidden', message: 'x'),
				default => null,
			}
		);

		$this->assertSame(expected: 'forbidden', actual: $this->tools()->createDashboard(args: ['name' => 'Two'])['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->createDashboard(args: [])['error']);

		$this->dashboards->method('assertPersonalDashboardsAllowed')->willThrowException(exception: new PersonalDashboardsDisabledException());
		$this->assertTrue(condition: $this->tools()->createDashboard(args: ['name' => 'Three'])['isError']);
	}//end testCreateDashboardRefusals()

	/**
	 * updateDashboard needs the metadata edit check, changes only the given
	 * fields and saves an automatic version.
	 *
	 * @return void
	 */
	public function testUpdateDashboard(): void {
		$this->gate->method('requireMetadataEdit')->willReturnCallback(
			callback: static fn (int $dashboardId): ?array => match ($dashboardId) {
				9 => null,
				default => ['isError' => true, 'error' => 'forbidden'],
			}
		);
		$this->dashboards->expects($this->once())->method('updateDashboard')->willReturnCallback(
			callback: static fn (int $dashboardId, string $userId, array $data): Dashboard => match ($data) {
				['name' => 'Renamed', 'parentUuid' => null] => self::dashboard(),
			}
		);
		$this->versions->expects($this->once())->method('captureSnapshot');

		$this->assertSame(
			expected: ['dashboard' => self::dashboard()->jsonSerialize()],
			actual: $this->tools()->updateDashboard(args: ['dashboardId' => 9, 'name' => 'Renamed', 'parentUuid' => ''])
		);
		$this->assertSame(expected: 'forbidden', actual: $this->tools()->updateDashboard(args: ['dashboardId' => 8, 'name' => 'x'])['error']);
	}//end testUpdateDashboard()

	/**
	 * The automatic version holds the dashboard as it was before the update
	 * (REQ-VERS-001), not the updated one.
	 *
	 * @return void
	 */
	public function testUpdateDashboardSnapshotsTheStateItReplaces(): void {
		$this->gate->method('requireMetadataEdit')->willReturn(null);
		$before = self::dashboard();
		$after  = self::dashboard();
		$after->setName('Renamed');
		$this->versions->method('stateBeforeUpdate')->willReturn(['dashboard' => $before, 'snapshotJson' => '{"before":true}']);
		$this->dashboards->method('updateDashboard')->willReturn($after);
		$this->versions->expects($this->once())->method('captureSnapshot')
			->with($this->identicalTo(value: $before), $this->anything(), ['snapshotJson' => '{"before":true}']);

		$this->tools()->updateDashboard(args: ['dashboardId' => 9, 'name' => 'Renamed']);
	}//end testUpdateDashboardSnapshotsTheStateItReplaces()

	/**
	 * updateDashboard needs no name, but refuses a bad one or a bad description it is given.
	 *
	 * @return void
	 */
	public function testUpdateDashboardChecksTheFieldsItIsGiven(): void {
		$this->gate->method('requireMetadataEdit')->willReturn(null);
		$this->dashboards->expects($this->never())->method('updateDashboard');

		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->updateDashboard(args: ['dashboardId' => 9, 'name' => ''])['error']);
		$long = ['dashboardId' => 9, 'description' => str_repeat(string: 'x', times: 2001)];
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->updateDashboard(args: $long)['error']);
	}//end testUpdateDashboardChecksTheFieldsItIsGiven()

	/**
	 * deleteDashboard passes the cascade choice and names a refusal for children.
	 *
	 * @return void
	 */
	public function testDeleteDashboard(): void {
		$this->dashboards->method('deleteDashboard')->willReturnCallback(
			callback: static function (int $dashboardId, string $userId, bool $cascade): void {
				if ($cascade === false) {
					throw new DashboardHasChildrenException(childCount: 2);
				}
			}
		);

		$refused = $this->tools()->deleteDashboard(args: ['dashboardId' => 9]);
		$this->assertSame(expected: ['dashboard_has_children', 2], actual: [$refused['error'], $refused['childCount']]);
		$this->assertSame(expected: ['deleted' => true], actual: $this->tools()->deleteDashboard(args: ['dashboardId' => 9, 'cascade' => true]));
	}//end testDeleteDashboard()

	/**
	 * The active and default preferences are set for the caller; a fork is
	 * created for the caller under the name asked.
	 *
	 * @return void
	 */
	public function testPreferencesAndFork(): void {
		$this->gate->method('requireUser')->willReturn(null);
		$set = [];
		$this->dashboards->method('setActivePreference')->willReturnCallback(
			callback: static function (string $userId, string $uuid) use (&$set): void {
				$set[] = ['active', $userId, $uuid];
			}
		);
		$this->dashboards->method('setDefaultPreference')->willReturnCallback(
			callback: static function (string $userId, string $uuid) use (&$set): void {
				$set[] = ['default', $userId, $uuid];
			}
		);
		$this->dashboards->method('forkAsPersonal')->willReturnCallback(
			callback: static fn (string $userId, string $sourceUuid, ?string $name): Dashboard => match ($name) {
				'Mine' => self::dashboard(),
			}
		);
		$tools = $this->tools();

		$tools->switchActiveDashboard(args: ['dashboardUuid' => self::UUID]);
		$tools->setDefaultDashboard(args: ['dashboardUuid' => self::UUID]);

		$this->assertSame(expected: [['active', 'alice', self::UUID], ['default', 'alice', self::UUID]], actual: $set);
		$fork = $tools->forkDashboard(args: ['dashboardUuid' => self::UUID, 'name' => 'Mine']);
		$this->assertSame(expected: ['dashboard' => self::dashboard()->jsonSerialize()], actual: $fork);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->switchActiveDashboard(args: ['dashboardUuid' => 'nope'])['error']);
	}//end testPreferencesAndFork()
}//end class
