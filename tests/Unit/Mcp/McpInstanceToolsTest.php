<?php

/**
 * McpInstanceTools Test
 *
 * What the instance-wide MCP tools hand an administrator: group dashboards
 * and templates reach their services with the right arguments, bad
 * arguments are refused before any service call, and a service failure
 * comes back as an MCP error instead of an exception.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Mcp
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Mcp;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Kernel\Contracts\ExportServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\TemplateResyncServiceInterface;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpInstanceTools;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\ImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Mcp\McpInstanceTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 * @uses   \OCA\LaunchPad\Controller\AdminTemplateTools
 * @uses   \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses   \OCA\LaunchPad\Controller\DashboardApiServices
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class McpInstanceToolsTest extends TestCase {

	private const UUID = '0b1e8a52-6c1f-4c3a-9d55-1a2b3c4d5e6f';

	/**
	 * Dashboard service double.
	 *
	 * @var DashboardService&MockObject
	 */
	private $dashboards;

	/**
	 * Template service double.
	 *
	 * @var AdminTemplateService&MockObject
	 */
	private $templates;

	/**
	 * Resync service double.
	 *
	 * @var TemplateResyncServiceInterface&MockObject
	 */
	private $resync;

	/**
	 * The tools, signed in as the administrator "root".
	 *
	 * @var McpInstanceTools
	 */
	private McpInstanceTools $tools;

	/**
	 * Build the tools over service doubles, with an admin gate.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->templates  = $this->createMock(originalClassName: AdminTemplateService::class);
		$this->resync     = $this->createMock(originalClassName: TemplateResyncServiceInterface::class);

		$gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$gate->method('requireAdmin')->willReturn(null);
		$gate->method('currentUserId')->willReturn('root');

		$this->tools = new McpInstanceTools(
			gate: $gate,
			validator: new McpArgumentValidator(),
			services: new DashboardApiServices(dashboardService: $this->dashboards),
			templates: new AdminTemplateTools(
				templateService: $this->templates,
				resyncService: $this->resync,
				exportService: $this->createMock(originalClassName: ExportServiceInterface::class),
				importService: $this->createMock(originalClassName: ImportService::class)
			)
		);
	}//end setUp()

	/**
	 * A dashboard named for the tests.
	 *
	 * @param string $name The name.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setName($name);
		return $dashboard;
	}//end dashboard()

	/**
	 * Creating a group dashboard passes the group, name and description for the admin.
	 *
	 * @return void
	 */
	public function testCreateGroupDashboard(): void {
		$this->dashboards->expects($this->once())->method('createGroupShared')
			->with('root', 'sales', ['name' => 'Verkoop', 'description' => 'Het team'])
			->willReturn(self::dashboard(name: 'Verkoop'));

		$result = $this->tools->createGroupDashboard(args: ['groupId' => 'sales', 'name' => 'Verkoop', 'description' => 'Het team']);

		$this->assertSame(expected: 'Verkoop', actual: $result['dashboard']['name']);
	}//end testCreateGroupDashboard()

	/**
	 * A group dashboard without a name or group is refused before the service.
	 *
	 * @return void
	 */
	public function testCreateGroupDashboardRefusesMissingArguments(): void {
		$this->dashboards->expects($this->never())->method('createGroupShared');

		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools->createGroupDashboard(args: ['groupId' => 'sales'])['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools->createGroupDashboard(args: ['name' => 'Verkoop'])['error']);
	}//end testCreateGroupDashboardRefusesMissingArguments()

	/**
	 * An update passes only the patchable fields.
	 *
	 * @return void
	 */
	public function testUpdateGroupDashboardPassesOnlyThePatchableFields(): void {
		$this->dashboards->expects($this->once())->method('updateGroupShared')
			->with('root', 'sales', self::UUID, ['name' => 'Nieuw', 'gridColumns' => 8])
			->willReturn(self::dashboard(name: 'Nieuw'));

		$result = $this->tools->updateGroupDashboard(
			args: ['groupId' => 'sales', 'dashboardUuid' => self::UUID, 'name' => 'Nieuw', 'gridColumns' => 8, 'userId' => 'mallory']
		);

		$this->assertSame(expected: 'Nieuw', actual: $result['dashboard']['name']);
	}//end testUpdateGroupDashboardPassesOnlyThePatchableFields()

	/**
	 * A dashboard uuid that is no uuid is refused before the service.
	 *
	 * @return void
	 */
	public function testGroupDashboardToolsRefuseABadUuid(): void {
		$this->dashboards->expects($this->never())->method('updateGroupShared');
		$this->dashboards->expects($this->never())->method('deleteGroupShared');
		$this->dashboards->expects($this->never())->method('setGroupDefault');

		$args = ['groupId' => 'sales', 'dashboardUuid' => 'not-a-uuid'];
		foreach (['updateGroupDashboard', 'deleteGroupDashboard', 'setGroupDefault'] as $tool) {
			$this->assertSame(expected: 'invalid_arguments', actual: $this->tools->$tool($args)['error'], message: $tool);
		}
	}//end testGroupDashboardToolsRefuseABadUuid()

	/**
	 * Delete and set-default reach the service and say what they did.
	 *
	 * @return void
	 */
	public function testDeleteAndSetDefault(): void {
		$this->dashboards->expects($this->once())->method('deleteGroupShared')->with('root', 'sales', self::UUID);
		$this->dashboards->expects($this->once())->method('setGroupDefault')->with('root', 'sales', self::UUID);

		$args = ['groupId' => 'sales', 'dashboardUuid' => self::UUID];
		$this->assertSame(expected: ['deleted' => true], actual: $this->tools->deleteGroupDashboard(args: $args));
		$this->assertSame(expected: ['groupId' => 'sales', 'default' => self::UUID], actual: $this->tools->setGroupDefault(args: $args));
	}//end testDeleteAndSetDefault()

	/**
	 * A service failure comes back as an MCP error, named by its kind.
	 *
	 * @return void
	 */
	public function testServiceFailuresBecomeErrors(): void {
		$this->dashboards->method('deleteGroupShared')->willThrowException(new DoesNotExistException(msg: 'gone'));
		$this->dashboards->method('setGroupDefault')->willThrowException(new RuntimeException(message: 'database password in here'));

		$args    = ['groupId' => 'sales', 'dashboardUuid' => self::UUID];
		$failure = $this->tools->setGroupDefault(args: $args);
		$this->assertSame(expected: 'not_found', actual: $this->tools->deleteGroupDashboard(args: $args)['error']);
		$this->assertSame(expected: 'failed', actual: $failure['error']);
		$this->assertStringNotContainsString(needle: 'password', haystack: $failure['message']);
	}//end testServiceFailuresBecomeErrors()

	/**
	 * Creating a template passes the name and only the template settings.
	 *
	 * @return void
	 */
	public function testCreateTemplate(): void {
		$this->templates->expects($this->once())->method('createTemplate')
			->with('Start', ['description' => 'Voor iedereen', 'targetGroups' => ['all'], 'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY])
			->willReturn(self::dashboard(name: 'Start'));

		$result = $this->tools->createTemplate(
			args: [
				'name'            => 'Start',
				'description'     => 'Voor iedereen',
				'targetGroups'    => ['all'],
				'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
				'uuid'            => 'x',
			]
		);

		$this->assertSame(expected: 'Start', actual: $result['name']);
	}//end testCreateTemplate()

	/**
	 * Template fields with the wrong shape are refused before the service.
	 *
	 * @return void
	 */
	public function testTemplateFieldsAreChecked(): void {
		$this->templates->expects($this->never())->method('createTemplate');
		$this->templates->expects($this->never())->method('updateTemplate');

		$refused = [
			$this->tools->createTemplate(args: ['name' => 'Start', 'permissionLevel' => 'owner']),
			$this->tools->createTemplate(args: ['name' => 'Start', 'targetGroups' => 'all']),
			$this->tools->updateTemplate(args: ['templateId' => 0]),
			$this->tools->updateTemplate(args: ['templateId' => 4, 'name' => '']),
		];
		$this->assertSame(expected: array_fill(0, 4, 'invalid_arguments'), actual: array_column($refused, 'error'));
	}//end testTemplateFieldsAreChecked()

	/**
	 * Updating a template passes its id and only the updatable fields.
	 *
	 * @return void
	 */
	public function testUpdateTemplate(): void {
		$this->templates->expects($this->once())->method('updateTemplate')
			->with(4, ['gridColumns' => 6, 'isDefault' => true])
			->willReturn(self::dashboard(name: 'Start'));

		$result = $this->tools->updateTemplate(args: ['templateId' => 4, 'gridColumns' => 6, 'isDefault' => true, 'userId' => 'mallory']);

		$this->assertSame(expected: 'Start', actual: $result['name']);
	}//end testUpdateTemplate()

	/**
	 * Deleting a template reaches the service; an invalid id never does.
	 *
	 * @return void
	 */
	public function testDeleteTemplate(): void {
		$this->templates->expects($this->once())->method('deleteTemplate')->with(4);

		$this->assertSame(expected: ['deleted' => true], actual: $this->tools->deleteTemplate(args: ['templateId' => 4]));
		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools->deleteTemplate(args: ['templateId' => -1])['error']);
	}//end testDeleteTemplate()

	/**
	 * A resync needs a known strategy and a boolean dryRun; dryRun false really writes.
	 *
	 * @return void
	 */
	public function testResyncTemplateChecksItsArguments(): void {
		$this->resync->expects($this->once())->method('resync')->with(3, 'overwrite', false, 'root')->willReturn(['changed' => 2]);

		$unknownStrategy = $this->tools->resyncTemplate(args: ['templateId' => 3, 'strategy' => 'replace']);
		$textDryRun      = $this->tools->resyncTemplate(args: ['templateId' => 3, 'strategy' => 'merge', 'dryRun' => 'no']);
		$this->assertSame(expected: 'invalid_arguments', actual: $unknownStrategy['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $textDryRun['error']);
		$written = $this->tools->resyncTemplate(args: ['templateId' => 3, 'strategy' => 'overwrite', 'dryRun' => false]);
		$this->assertSame(expected: ['changed' => 2], actual: $written);
	}//end testResyncTemplateChecksItsArguments()

	/**
	 * A refused template input from the service comes back as invalid_arguments with its message.
	 *
	 * @return void
	 */
	public function testATemplateServiceRefusalKeepsItsMessage(): void {
		$this->templates->method('createTemplate')->willThrowException(new InvalidArgumentException(message: 'A template with this name exists'));

		$this->assertSame(
			expected: McpArgumentValidator::error(code: 'invalid_arguments', message: 'A template with this name exists'),
			actual: $this->tools->createTemplate(args: ['name' => 'Start'])
		);
	}//end testATemplateServiceRefusalKeepsItsMessage()
}//end class
