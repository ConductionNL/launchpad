<?php

/**
 * AdminController updateTemplate golden test
 *
 * Pins the patch that updateTemplate() hands to the admin template
 * service for every combination of optional fields, against a recorded
 * fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AdminCallerAndRoles;
use OCA\LaunchPad\Controller\AdminSettingsTools;
use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Controller\AdminController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The template patch of updateTemplate(), case by case.
 *
 * @covers \OCA\LaunchPad\Controller\AdminController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @covers \OCA\LaunchPad\Service\AdminTemplateService
 * @uses   \OCA\LaunchPad\Service\AdminTemplateStores
 * @uses   \OCA\LaunchPad\Controller\AdminTemplateTools
 * @uses   \OCA\LaunchPad\Controller\AdminSettingsTools
 * @uses   \OCA\LaunchPad\Controller\AdminCallerAndRoles
 */
class AdminControllerUpdateTemplateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/admin-update-template.json';

	/**
	 * Build the controller over the given template service.
	 *
	 * @param AdminTemplateService $templates The template service double.
	 *
	 * @return AdminController
	 */
	private function controller(AdminTemplateService $templates): AdminController {
		return new AdminController(
			request: $this->createMock(IRequest::class),
			templates: new AdminTemplateTools(
				templateService: $templates,
				exportService: $this->createMock(ExportService::class),
				importService: $this->createMock(ImportService::class),
				resyncService: $this->createMock(\OCA\LaunchPad\Service\TemplateResyncService::class)
			),
			settings: new AdminSettingsTools(
				settingsService: $this->createMock(AdminSettingsService::class),
				groupManager: $this->createMock(IGroupManager::class),
				footerService: $this->createMock(\OCA\LaunchPad\Service\FooterService::class),
				setupWizardService: $this->createMock(\OCA\LaunchPad\Service\SetupWizardService::class)
			),
			caller: new AdminCallerAndRoles(
				roleService: $this->createMock(\OCA\LaunchPad\Service\RoleService::class),
				feedRefresh: $this->createMock(\OCA\LaunchPad\Service\FeedRefreshService::class),
				userSession: $this->createMock(IUserSession::class),
				actionAuth: $this->createMock(\OCA\LaunchPad\Service\ActionAuthService::class)
			)
		);
	}//end controller()

	/**
	 * Every case sends the recorded patch and answers the recorded status.
	 *
	 * @return void
	 */
	public function testPatchMatchesTheRecordedFixture(): void {
		$cases = [
			'nothing'     => [],
			'name'        => ['name' => 'T'],
			'empty name'  => ['name' => ''],
			'description' => ['description' => 'd'],
			'groups'      => ['targetGroups' => ['staff', 'hr']],
			'no groups'   => ['targetGroups' => []],
			'level'       => ['permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY],
			'default'     => ['isDefault' => true],
			'not default' => ['isDefault' => false],
			'grid'        => ['gridColumns' => 6],
			'everything'  => ['gridColumns' => 8, 'isDefault' => false, 'permissionLevel' => 'full', 'targetGroups' => ['a'], 'description' => 'x', 'name' => 'y'],
			'failure'     => ['name' => 'boom'],
		];

		$actual = [];
		foreach ($cases as $case => $args) {
			$patch     = null;
			$templates = $this->createMock(AdminTemplateService::class);
			$templates->method('updateTemplate')->willReturnCallback(
				static function (int $id, array $data) use (&$patch): Dashboard {
					$patch = ['id' => $id, 'data' => $data];
					if (($data['name'] ?? null) === 'boom') {
						throw new RuntimeException('down');
					}

					$dashboard = new Dashboard();
					$dashboard->setName((string) ($data['name'] ?? 'kept'));
					return $dashboard;
				}
			);

			$response      = $this->controller(templates: $templates)->updateTemplate(4, ...$args);
			$actual[$case] = ['status' => $response->getStatus(), 'patch' => $patch, 'name' => $response->getData()['name'] ?? null];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$lines = [];
			foreach ($actual as $case => $row) {
				$lines[] = json_encode($case).': '.json_encode($row, JSON_UNESCAPED_SLASHES);
			}

			file_put_contents(self::FIXTURE, "{\n".implode(",\n", $lines)."\n}\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents(self::FIXTURE))), json_encode($actual));
	}//end testPatchMatchesTheRecordedFixture()

	/**
	 * Every create case writes the recorded template row.
	 *
	 * @return void
	 */
	public function testCreateTemplateRowsMatchTheRecordedFixture(): void {
		$cases = [
			'minimal'     => ['name' => 'T'],
			'description' => ['name' => 'T', 'description' => 'd'],
			'groups'      => ['name' => 'T', 'targetGroups' => ['staff']],
			'no groups'   => ['name' => 'T', 'targetGroups' => []],
			'level'       => ['name' => 'T', 'permissionLevel' => Dashboard::PERMISSION_FULL],
			'default'     => ['name' => 'T', 'isDefault' => true],
			'everything'  => ['isDefault' => false, 'permissionLevel' => 'view_only', 'targetGroups' => ['a', 'b'], 'description' => 'x', 'name' => 'y'],
		];

		$actual = [];
		foreach ($cases as $case => $args) {
			$log    = [];
			$mapper = $this->createMock(\OCA\LaunchPad\Db\DashboardMapper::class);
			$mapper->method('clearDefaultTemplates')->willReturnCallback(
				static function () use (&$log): int {
					$log[] = 'clearDefaultTemplates';
					return 0;
				}
			);
			$mapper->method('insert')->willReturnCallback(
				static function (Dashboard $dashboard) use (&$log): Dashboard {
					$row = $dashboard->jsonSerialize();
					unset($row['uuid'], $row['createdAt'], $row['updatedAt']);
					$log[] = ['insert' => $row, 'uuid' => strlen((string) $dashboard->getUuid()), 'stamped' => $dashboard->getCreatedAt() === $dashboard->getUpdatedAt()];
					return $dashboard;
				}
			);
			$service = new AdminTemplateService(
				stores: new \OCA\LaunchPad\Service\AdminTemplateStores(
					dashboardMapper: $mapper,
					placementMapper: $this->createMock(\OCA\LaunchPad\Db\WidgetPlacementMapper::class)
				),
				settingsService: $this->createMock(AdminSettingsService::class),
				groupManager: $this->createMock(IGroupManager::class),
				userManager: $this->createMock(\OCP\IUserManager::class),
			);

			$response      = $this->controller(templates: $service)->createTemplate(...$args);
			$actual[$case] = ['status' => $response->getStatus(), 'log' => $log];
		}

		$fixture = __DIR__.'/fixtures/admin-create-template.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$lines = [];
			foreach ($actual as $case => $row) {
				$lines[] = json_encode($case).': '.json_encode($row, JSON_UNESCAPED_SLASHES);
			}

			file_put_contents($fixture, "{\n".implode(",\n", $lines)."\n}\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents($fixture))), json_encode($actual));
	}//end testCreateTemplateRowsMatchTheRecordedFixture()

	/**
	 * A template created with only a name gets the add-only level, no
	 * groups and is not the default.
	 *
	 * @return void
	 */
	public function testServiceDefaults(): void {
		$mapper = $this->createMock(\OCA\LaunchPad\Db\DashboardMapper::class);
		$mapper->expects($this->never())->method('clearDefaultTemplates');
		$mapper->method('insert')->willReturnArgument(0);
		$service = new AdminTemplateService(
			stores: new \OCA\LaunchPad\Service\AdminTemplateStores(
				dashboardMapper: $mapper,
				placementMapper: $this->createMock(\OCA\LaunchPad\Db\WidgetPlacementMapper::class)
			),
			settingsService: $this->createMock(AdminSettingsService::class),
			groupManager: $this->createMock(IGroupManager::class),
			userManager: $this->createMock(\OCP\IUserManager::class),
		);

		$template = $service->createTemplate(name: 'T');

		$this->assertSame([Dashboard::PERMISSION_ADD_ONLY, [], 0, null], [$template->getPermissionLevel(), $template->getTargetGroupsArray(), $template->getIsDefault(), $template->getDescription()]);
	}//end testServiceDefaults()
}//end class
