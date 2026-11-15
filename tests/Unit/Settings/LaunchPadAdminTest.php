<?php

/**
 * LaunchPadAdminTest
 *
 * Pins the admin settings form: the initial state it provides (groups,
 * configured group order with its legacy fallback, widgets, the user
 * dashboard switch and link file extensions), the template it renders and
 * the section it registers under.
 *
 * getForm() registers its script through OCP\Util, which reaches the
 * Nextcloud server container. Without a booted Nextcloud those tests run
 * in a separate process with a minimal container standing in, so nothing
 * leaks into the rest of the suite.
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
use OCA\LaunchPad\Settings\AdminPageSources;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAdminSection;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Tests for LaunchPadAdmin.
 *
 * @covers \OCA\LaunchPad\Settings\LaunchPadAdmin
 * @covers \OCA\LaunchPad\Settings\AdminPageSources
 * @uses   \OCA\LaunchPad\Settings\LaunchPadAdminSection
 * @uses   \OCA\LaunchPad\Service\InitialStateBuilder
 * @uses   \OCA\LaunchPad\Service\InitialState\Page
 */
class LaunchPadAdminTest extends TestCase {

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
	 * Build the form with mocked sources.
	 *
	 * @return LaunchPadAdmin
	 */
	private function form(): LaunchPadAdmin {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			function (string $key, mixed $value): void {
				$this->provided[$key] = $value;
			}
		);

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->settingsService = $this->createMock(AdminSettingsService::class);
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);

		$widgets = $this->createMock(WidgetService::class);
		$widgets->method('getAvailableWidgets')->willReturn([['id' => 'weather']]);
		$dashboards = $this->createMock(DashboardService::class);
		$dashboards->method('getAllowUserDashboards')->willReturn(false);
		$files = $this->createMock(FileService::class);
		$files->method('getAllowedExtensions')->willReturn(['docx', 'md']);

		return new LaunchPadAdmin(
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
	}//end form()

	/**
	 * Stand in for the Nextcloud server container that OCP\Util::addScript()
	 * reaches, when no Nextcloud is booted. Only ever called in a separate
	 * process.
	 *
	 * @return void
	 */
	private function standInForTheServer(): void {
		if (class_exists('OC') === true) {
			return;
		}

		eval('class OC { public static $server; }');
		if (class_exists('OC\\AppScriptDependency') === false) {
			eval(
				'namespace OC; class AppScriptDependency {'
				. ' public function __construct(public string $id, public array $deps = []) {}'
				. ' public function addDep(string $dep): void { $this->deps[] = $dep; } }'
			);
		}

		$factory = $this->createMock(IFactory::class);
		$factory->method('findLanguage')->willReturn('en');
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(IFactory::class)->willReturn($factory);
		\OC::$server = $container;
	}//end standInForTheServer()

	/**
	 * A group mock.
	 *
	 * @param string $gid  The group id.
	 * @param string $name The display name.
	 *
	 * @return IGroup
	 */
	private function group(string $gid, string $name): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($gid);
		$group->method('getDisplayName')->willReturn($name);
		return $group;
	}//end group()

	/**
	 * The form provides every group, the configured order, the widgets, the
	 * user-dashboard switch and the file extensions, and renders the admin template.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testTheFormProvidesTheAdminInitialState(): void {
		$this->standInForTheServer();
		$admin = $this->form();
		$this->groupManager->expects($this->once())
			->method('search')
			->with('')
			->willReturn([$this->group('admin', 'Admins'), $this->group('staff', 'Staff')]);
		$this->settingsService->method('getGroupOrder')->willReturn(['staff', 'admin']);
		$this->settingMapper->expects($this->never())->method('getValue');

		$response = $admin->getForm();

		$this->assertSame('launchpad', $response->getApp());
		$this->assertSame('settings/admin', $response->getTemplateName());
		$schema = $this->provided;
		$this->assertArrayHasKey(InitialStateBuilder::KEY_SCHEMA_VERSION, $schema);
		unset($schema[InitialStateBuilder::KEY_SCHEMA_VERSION]);
		$this->assertSame(
			[
				'allGroups' => [
					['id' => 'admin', 'displayName' => 'Admins'],
					['id' => 'staff', 'displayName' => 'Staff'],
				],
				'configuredGroups' => ['staff', 'admin'],
				'widgets' => [['id' => 'weather']],
				'allowUserDashboards' => false,
				'linkCreateFileExtensions' => ['docx', 'md'],
			],
			$schema
		);
	}//end testTheFormProvidesTheAdminInitialState()

	/**
	 * Without a group order the legacy setting is used, keeping only
	 * non-empty strings and reindexing them.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testWithoutAGroupOrderTheLegacySettingIsFiltered(): void {
		$this->standInForTheServer();
		$admin = $this->form();
		$this->groupManager->method('search')->willReturn([]);
		$this->settingsService->method('getGroupOrder')->willReturn([]);
		$this->settingMapper->expects($this->once())
			->method('getValue')
			->with('configured_groups', [])
			->willReturn(['', 'staff', 7, null, 'admin']);

		$admin->getForm();

		$this->assertSame(['staff', 'admin'], $this->provided['configuredGroups']);
		$this->assertSame([], $this->provided['allGroups']);
	}//end testWithoutAGroupOrderTheLegacySettingIsFiltered()

	/**
	 * A legacy setting that is not a list leaves the configured groups empty.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testANonListLegacySettingGivesNoConfiguredGroups(): void {
		$this->standInForTheServer();
		$admin = $this->form();
		$this->groupManager->method('search')->willReturn([]);
		$this->settingsService->method('getGroupOrder')->willReturn([]);
		$this->settingMapper->method('getValue')->willReturn('staff');

		$admin->getForm();

		$this->assertSame([], $this->provided['configuredGroups']);
	}//end testANonListLegacySettingGivesNoConfiguredGroups()

	/**
	 * The form registers under the LaunchPad section, with no name of its own
	 * and no delegated app config.
	 *
	 * @return void
	 */
	public function testTheFormRegistersUnderTheLaunchPadSection(): void {
		$admin = $this->form();
		$section = new LaunchPadAdminSection(
			l: $this->createMock(IL10N::class),
			urlGenerator: $this->createMock(IURLGenerator::class)
		);

		$this->assertSame($section->getID(), $admin->getSection());
		$this->assertSame(10, $admin->getPriority());
		$this->assertNull($admin->getName());
		$this->assertSame([], $admin->getAuthorizedAppConfig());
	}//end testTheFormRegistersUnderTheLaunchPadSection()
}//end class
