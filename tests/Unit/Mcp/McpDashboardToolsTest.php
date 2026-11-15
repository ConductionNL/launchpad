<?php

/**
 * Unit tests for the MCP dashboard read tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpDashboardTools;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The read tools see what the caller sees through the routes' own services.
 *
 * @covers \OCA\LaunchPad\Mcp\McpDashboardTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 * @uses   \OCA\LaunchPad\Controller\AllowedWidgetFilter
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class McpDashboardToolsTest extends TestCase {

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate&MockObject $gate;

	/**
	 * @var DashboardService&MockObject
	 */
	private DashboardService&MockObject $dashboards;

	/**
	 * @var WidgetService&MockObject
	 */
	private WidgetService&MockObject $widgets;

	/**
	 * @var RoleFeaturePermissionService&MockObject
	 */
	private RoleFeaturePermissionService&MockObject $roles;

	/**
	 * Set up a signed-in caller whose actions are all allowed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->dashboards = $this->createMock(DashboardService::class);
		$this->widgets = $this->createMock(WidgetService::class);
		$this->roles = $this->createMock(RoleFeaturePermissionService::class);
	}//end setUp()

	/**
	 * The tools under test.
	 *
	 * @return McpDashboardTools
	 */
	private function tools(): McpDashboardTools {
		return new McpDashboardTools($this->gate, new McpArgumentValidator(), $this->dashboards, $this->widgets, $this->roles);
	}//end tools()

	/**
	 * A dashboard entity.
	 *
	 * @param int    $id    The id.
	 * @param string $owner The owner uid.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(int $id, string $owner): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid('123e4567-e89b-42d3-a456-42661417400' . $id);
		$dashboard->setUserId($owner);

		return $dashboard;
	}//end dashboard()

	/**
	 * listDashboards lists exactly the visible set, tagging the source and ownership.
	 *
	 * @return void
	 */
	public function testListDashboardsIsTheVisibleSet(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('dashboard.visible')->willReturn(null);
		$this->dashboards->method('getVisibleToUser')->with('alice')->willReturn(
			[
				['dashboard' => self::dashboard(1, 'alice'), 'source' => 'user'],
				['dashboard' => self::dashboard(2, 'bob'), 'source' => 'shared'],
			]
		);

		$items = $this->tools()->listDashboards([])['items'];

		$this->assertSame([1, 2], array_column($items, 'id'));
		$this->assertSame(['user', 'shared'], array_column($items, 'source'));
		$this->assertSame([true, false], array_column($items, 'isOwner'));
	}//end testListDashboardsIsTheVisibleSet()

	/**
	 * A refused action returns the refusal and reads nothing.
	 *
	 * @return void
	 */
	public function testARefusedActionReadsNothing(): void {
		$refusal = McpArgumentValidator::error(code: 'forbidden', message: 'no');
		$this->gate->method('requireAction')->willReturn($refusal);
		$this->dashboards->expects($this->never())->method('getVisibleToUser');
		$this->widgets->expects($this->never())->method('getAvailableWidgets');

		$this->assertSame($refusal, $this->tools()->listDashboards([]));
		$this->assertSame($refusal, $this->tools()->listWidgetTypes([]));
	}//end testARefusedActionReadsNothing()

	/**
	 * A malformed UUID is refused before any authorisation or lookup.
	 *
	 * @return void
	 */
	public function testGetDashboardRefusesAMalformedUuidFirst(): void {
		$this->gate->expects($this->never())->method('requireAction');
		$this->dashboards->expects($this->never())->method('findByUuid');

		$this->assertSame('invalid_arguments', $this->tools()->getDashboard(['dashboardUuid' => '42'])['error']);
	}//end testGetDashboardRefusesAMalformedUuidFirst()

	/**
	 * A dashboard that exists but is not visible, and one that does not exist, both answer not_found.
	 *
	 * @return void
	 */
	public function testGetDashboardDoesNotRevealExistence(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$this->dashboards->method('findByUuid')->willReturnCallback(
			static function (string $uuid): Dashboard {
				if ($uuid === '123e4567-e89b-42d3-a456-426614174009') {
					throw new DoesNotExistException('gone');
				}

				return self::dashboard(3, 'bob');
			}
		);
		$this->dashboards->method('getDashboardForUser')->with(3, 'alice')->willReturn(null);

		$hidden = $this->tools()->getDashboard(['dashboardUuid' => '123e4567-e89b-42d3-a456-426614174003']);
		$missing = $this->tools()->getDashboard(['dashboardUuid' => '123e4567-e89b-42d3-a456-426614174009']);

		$this->assertSame($hidden, $missing);
		$this->assertSame('not_found', $hidden['error']);
	}//end testGetDashboardDoesNotRevealExistence()

	/**
	 * A visible dashboard comes back with its placements and the caller's level.
	 *
	 * @return void
	 */
	public function testGetDashboardReturnsTheEnvelope(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('dashboard.show')->willReturn(null);
		$dashboard = self::dashboard(4, 'bob');
		$this->dashboards->method('findByUuid')->willReturn($dashboard);
		$this->dashboards->method('getDashboardForUser')->with(4, 'alice')
			->willReturn(['dashboard' => $dashboard, 'placements' => [], 'permissionLevel' => 'view_only']);

		$result = $this->tools()->getDashboard(['dashboardUuid' => '123e4567-e89b-42d3-a456-426614174004']);

		$this->assertSame(4, $result['dashboard']['id']);
		$this->assertSame('view_only', $result['permissionLevel']);
		$this->assertFalse($result['isOwner']);
		$this->assertSame([], $result['placements']);
	}//end testGetDashboardReturnsTheEnvelope()

	/**
	 * The active dashboard is the service's effective one, and names its owner when that is someone else.
	 *
	 * @return void
	 */
	public function testGetActiveDashboardIsTheEffectiveOne(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('dashboard.get-active')->willReturn(null);
		$dashboard = self::dashboard(7, 'bob');
		$this->dashboards->expects($this->once())->method('getEffectiveDashboard')->with('alice')
			->willReturn(['dashboard' => $dashboard, 'placements' => [], 'permissionLevel' => 'view_only']);

		$result = $this->tools()->getActiveDashboard([]);

		$this->assertSame(7, $result['dashboard']['id']);
		$this->assertSame('view_only', $result['permissionLevel']);
		$this->assertFalse($result['isOwner']);
		$this->assertSame('bob', $result['sharedBy']);
	}//end testGetActiveDashboardIsTheEffectiveOne()

	/**
	 * The caller's own active dashboard has no sharer; none at all is a not-found error.
	 *
	 * @return void
	 */
	public function testGetActiveDashboardOwnedOrMissing(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$this->dashboards->method('getEffectiveDashboard')->willReturnOnConsecutiveCalls(
			['dashboard' => self::dashboard(2, 'alice'), 'placements' => [], 'permissionLevel' => 'full'],
			null
		);

		$own = $this->tools()->getActiveDashboard([]);
		$this->assertTrue($own['isOwner']);
		$this->assertNull($own['sharedBy']);

		$none = $this->tools()->getActiveDashboard([]);
		$this->assertTrue($none['isError']);
		$this->assertSame('not_found', $none['error']);
	}//end testGetActiveDashboardOwnedOrMissing()

	/**
	 * A refused action reads no dashboard.
	 *
	 * @return void
	 */
	public function testGetActiveDashboardRefusedReadsNothing(): void {
		$refusal = McpArgumentValidator::error(code: 'forbidden', message: 'no');
		$this->gate->method('requireAction')->willReturn($refusal);
		$this->dashboards->expects($this->never())->method('getEffectiveDashboard');

		$this->assertSame($refusal, $this->tools()->getActiveDashboard([]));
	}//end testGetActiveDashboardRefusedReadsNothing()

	/**
	 * A caller outside the group is refused before the group's dashboards are read.
	 *
	 * @return void
	 */
	public function testListGroupDashboardsChecksMembershipFirst(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$this->dashboards->method('userCanAccessGroup')->with('alice', 'sales')->willReturn(false);
		$this->dashboards->expects($this->never())->method('listGroupDashboards');

		$this->assertSame('forbidden', $this->tools()->listGroupDashboards(['groupId' => 'sales'])['error']);
	}//end testListGroupDashboardsChecksMembershipFirst()

	/**
	 * Widget types follow the caller's role allow-list.
	 *
	 * @return void
	 */
	public function testListWidgetTypesFollowsTheRoleAllowList(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('widget.list-available')->willReturn(null);
		$this->widgets->method('getAvailableWidgets')->willReturn([['id' => 'calendar'], ['id' => 'news'], ['id' => 'weather']]);
		$this->roles->method('getAllowedWidgetIds')->with('alice')->willReturn(['calendar', 'weather']);

		$this->assertSame([['id' => 'calendar'], ['id' => 'weather']], $this->tools()->listWidgetTypes([])['items']);
	}//end testListWidgetTypesFollowsTheRoleAllowList()
}//end class
