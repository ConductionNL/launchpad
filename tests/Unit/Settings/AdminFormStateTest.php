<?php

/**
 * AdminFormStateTest
 *
 * Pins the administration page's initial state: every group, the configured
 * group order with its legacy fallback, the widgets, the user dashboard
 * switch, the link file extensions and `isAdmin` for the Launchpad shell
 * (REQ-INIT-002, REQ-ADM-030).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\FileService;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCA\LaunchPad\Service\WidgetService;
use OCA\LaunchPad\Settings\AdminFormState;
use OCA\LaunchPad\Settings\AdminPageSources;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroup;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AdminFormState.
 *
 * @covers \OCA\LaunchPad\Settings\AdminFormState
 * @covers \OCA\LaunchPad\Settings\AdminPageSources
 * @uses   \OCA\LaunchPad\Service\InitialStateBuilder
 * @uses   \OCA\LaunchPad\Service\InitialState\Page
 */
class AdminFormStateTest extends TestCase {

	/** @var AdminSettingsService&MockObject */
	private $settingsService;
	/** @var AdminSettingMapper&MockObject */
	private $settingMapper;
	/** @var IGroupManager&MockObject */
	private $groupManager;

	/**
	 * Initial state provided, key => value, in order.
	 *
	 * @var array<string, mixed>
	 */
	private array $provided = [];

	/**
	 * Build the state with mocked sources.
	 *
	 * @return AdminFormState
	 */
	private function state(): AdminFormState {
		$initialState = $this->createMock(originalClassName: IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			function (string $key, mixed $value): void {
				$this->provided[$key] = $value;
			}
		);

		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->settingsService = $this->createMock(originalClassName: AdminSettingsService::class);
		$this->settingMapper = $this->createMock(originalClassName: AdminSettingMapper::class);

		$widgets = $this->createMock(originalClassName: WidgetService::class);
		$widgets->method('getAvailableWidgets')->willReturn([['id' => 'weather']]);
		$dashboards = $this->createMock(originalClassName: DashboardService::class);
		$dashboards->method('getAllowUserDashboards')->willReturn(false);
		$files = $this->createMock(originalClassName: FileService::class);
		$files->method('getAllowedExtensions')->willReturn(['docx', 'md']);

		return new AdminFormState(
			initialState: $initialState,
			groupManager: $this->groupManager,
			sources: new AdminPageSources(
				widgetService: $widgets,
				settingMapper: $this->settingMapper,
				dashboardService: $dashboards,
				fileService: $files,
			),
			settingsService: $this->settingsService,
		);
	}//end state()

	/**
	 * A group mock.
	 *
	 * @param string $gid  The group id.
	 * @param string $name The display name.
	 *
	 * @return IGroup
	 */
	private function group(string $gid, string $name): IGroup {
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getGID')->willReturn($gid);
		$group->method('getDisplayName')->willReturn($name);
		return $group;
	}//end group()

	/**
	 * The state carries every group, the configured order, the widgets, the
	 * user-dashboard switch, the file extensions and `isAdmin`.
	 *
	 * @return void
	 */
	public function testItProvidesTheAdminInitialState(): void {
		$state = $this->state();
		$this->groupManager->expects($this->once())
			->method('search')
			->with('')
			->willReturn([$this->group(gid: 'admin', name: 'Admins'), $this->group(gid: 'staff', name: 'Staff')]);
		$this->settingsService->method('getGroupOrder')->willReturn(['staff', 'admin']);
		$this->settingMapper->expects($this->never())->method('getValue');

		$state->provide();

		$schema = $this->provided;
		$this->assertArrayHasKey(key: InitialStateBuilder::KEY_SCHEMA_VERSION, array: $schema);
		unset($schema[InitialStateBuilder::KEY_SCHEMA_VERSION]);
		$this->assertSame(
			expected: [
				'allGroups' => [
					['id' => 'admin', 'displayName' => 'Admins'],
					['id' => 'staff', 'displayName' => 'Staff'],
				],
				'configuredGroups' => ['staff', 'admin'],
				'widgets' => [['id' => 'weather']],
				'allowUserDashboards' => false,
				'linkCreateFileExtensions' => ['docx', 'md'],
				'isAdmin' => true,
			],
			actual: $schema
		);
	}//end testItProvidesTheAdminInitialState()

	/**
	 * Without a group order the legacy setting is used, keeping only
	 * non-empty strings and reindexing them.
	 *
	 * @return void
	 */
	public function testWithoutAGroupOrderTheLegacySettingIsFiltered(): void {
		$state = $this->state();
		$this->groupManager->method('search')->willReturn([]);
		$this->settingsService->method('getGroupOrder')->willReturn([]);
		$this->settingMapper->expects($this->once())
			->method('getValue')
			->with('configured_groups', [])
			->willReturn(['', 'staff', 7, null, 'admin']);

		$state->provide();

		$this->assertSame(expected: ['staff', 'admin'], actual: $this->provided['configuredGroups']);
		$this->assertSame(expected: [], actual: $this->provided['allGroups']);
	}//end testWithoutAGroupOrderTheLegacySettingIsFiltered()

	/**
	 * A legacy setting that is not a list leaves the configured groups empty.
	 *
	 * @return void
	 */
	public function testANonListLegacySettingGivesNoConfiguredGroups(): void {
		$state = $this->state();
		$this->groupManager->method('search')->willReturn([]);
		$this->settingsService->method('getGroupOrder')->willReturn([]);
		$this->settingMapper->method('getValue')->willReturn('staff');

		$state->provide();

		$this->assertSame(expected: [], actual: $this->provided['configuredGroups']);
	}//end testANonListLegacySettingGivesNoConfiguredGroups()
}//end class
