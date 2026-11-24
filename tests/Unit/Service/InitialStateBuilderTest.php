<?php

/**
 * InitialStateBuilder Test
 *
 * Covers REQ-INIT-001 + REQ-INIT-002:
 *  - Builder rejects missing required keys for each page (one test per page)
 *  - Builder writes all keys with correct values via a stub IInitialState
 *  - Schema version key `_schemaVersion` is always pushed
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Exception\MissingInitialStateException;
use OCA\LaunchPad\Service\InitialState\Page;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\TestCase;

class InitialStateBuilderTest extends TestCase {
	/**
	 * REQ-SPX-002: the search shortcuts reach the workspace page.
	 *
	 * @return void
	 */
	public function testSearchShortcutsReachTheWorkspace(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$shortcuts = [['prefix' => '!t', 'name' => 'TOPdesk', 'urlTemplate' => 'https://topdesk.example.nl/?q={query}']];

		(new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE))
			->setWidgets([])
			->setLayout([])
			->setPrimaryGroup('g')
			->setPrimaryGroupName('G')
			->setIsAdmin(false)
			->setActiveDashboardId('')
			->setDashboardSource('group')
			->setGroupDashboards([])
			->setUserDashboards([])
			->setAllowUserDashboards(false)
			->setAllowedWidgets(null)
			->setSearchShortcuts($shortcuts)
			->apply();

		$this->assertSame($shortcuts, $sink['searchShortcuts']);
	}//end testSearchShortcutsReachTheWorkspace()

	/**
	 * REQ-RFP-010: an unconfigured allow-list reaches the page as JSON null.
	 * Nextcloud's initial-state service drops a plain PHP null, so the value
	 * must be something it accepts that still encodes as `null`.
	 *
	 * @return void
	 */
	public function testANullAllowListIsPushedAsJsonNull(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);

		(new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE))
			->setWidgets([])
			->setLayout([])
			->setPrimaryGroup('g')
			->setPrimaryGroupName('G')
			->setIsAdmin(false)
			->setActiveDashboardId('')
			->setDashboardSource('group')
			->setGroupDashboards([])
			->setUserDashboards([])
			->setAllowUserDashboards(false)
			->setAllowedWidgets(null)
			->apply();

		$this->assertArrayHasKey('allowedWidgets', $sink);
		$this->assertTrue(is_scalar($sink['allowedWidgets']) || is_array($sink['allowedWidgets']) || $sink['allowedWidgets'] instanceof \JsonSerializable);
		$this->assertSame('null', json_encode($sink['allowedWidgets']));
	}//end testANullAllowListIsPushedAsJsonNull()

	/**
	 * The first dashboard load reaches the workspace when given, and is left
	 * out when null, so the store fetches.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-4
	 */
	public function testTheFirstDashboardLoadIsProvidedOnlyWhenGiven(): void {
		$firstLoad = ['visible' => [], 'active' => null];
		foreach ([[$firstLoad, true], [null, false]] as [$value, $provided]) {
			$sink = [];
			(new InitialStateBuilder(initialState: $this->makeRecordingState(sink: $sink), page: Page::WORKSPACE))
				->setWidgets([])
				->setLayout([])
				->setPrimaryGroup('g')
				->setPrimaryGroupName('G')
				->setIsAdmin(false)
				->setActiveDashboardId('')
				->setDashboardSource('group')
				->setGroupDashboards([])
				->setUserDashboards([])
				->setAllowUserDashboards(false)
				->setAllowedWidgets(null)
				->setDashboardFirstLoad($value)
				->apply();

			$this->assertSame(expected: $provided, actual: array_key_exists('dashboardFirstLoad', $sink));
			if ($provided === true) {
				$this->assertSame(expected: $firstLoad, actual: $sink['dashboardFirstLoad']);
			}
		}
	}//end testTheFirstDashboardLoadIsProvidedOnlyWhenGiven()

	/**
	 * In-memory IInitialState stub that records every push.
	 *
	 * @return IInitialState&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function makeRecordingState(array &$sink): IInitialState {
		$stub = $this->createMock(IInitialState::class);
		$stub->method('provideInitialState')
			->willReturnCallback(static function (string $key, mixed $value) use (&$sink): void {
				$sink[$key] = $value;
			});
		return $stub;
	}

	public function testWorkspaceBuilderRejectsMissingRequiredKey(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE);

		$builder
			->setWidgets([])
			// Deliberately omit setLayout() — the spec requires it.
			->setPrimaryGroup('default')
			->setPrimaryGroupName('')
			->setIsAdmin(false)
			->setActiveDashboardId('')
			->setDashboardSource('group')
			->setGroupDashboards([])
			->setUserDashboards([])
			->setAllowUserDashboards(false);

		$this->expectException(MissingInitialStateException::class);
		$this->expectExceptionMessageMatches('/page "workspace".*"layout"/');
		$builder->apply();
	}

	public function testAdminBuilderRejectsMissingRequiredKey(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::ADMIN);

		$builder
			->setAllGroups([])
			->setConfiguredGroups([])
			// Deliberately omit setWidgets()
			->setAllowUserDashboards(false)
			->setLinkCreateFileExtensions(['txt']);

		$this->expectException(MissingInitialStateException::class);
		$this->expectExceptionMessageMatches('/page "admin".*"widgets"/');
		$builder->apply();
	}

	public function testWorkspaceBuilderWritesEveryKeyWithCorrectValues(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE);

		$widgets = [['id' => 'cal', 'title' => 'Calendar']];
		$layout = [['widgetId' => 'cal', 'x' => 0, 'y' => 0]];
		$groupDashboards = [['id' => 'gd1', 'name' => 'Team', 'icon' => '']];
		$userDashboards = [['id' => 'ud1', 'name' => 'Mine', 'icon' => '']];

		$builder
			->setWidgets($widgets)
			->setLayout($layout)
			->setPrimaryGroup('engineering')
			->setPrimaryGroupName('Engineering')
			->setIsAdmin(true)
			->setActiveDashboardId('uuid-1')
			->setDashboardSource('user')
			->setGroupDashboards($groupDashboards)
			->setUserDashboards($userDashboards)
			->setAllowUserDashboards(true)
			->setAllowedWidgets(null)
			->setOnOfficeNetwork(true)
			->setCustomCss(".launchpad-workspace p {\n  margin: 0;\n}")
			->apply();

		$this->assertSame($widgets, $sink['widgets']);
		$this->assertSame($layout, $sink['layout']);
		$this->assertSame('engineering', $sink['primaryGroup']);
		$this->assertSame('Engineering', $sink['primaryGroupName']);
		$this->assertTrue($sink['isAdmin']);
		$this->assertSame('uuid-1', $sink['activeDashboardId']);
		$this->assertSame('user', $sink['dashboardSource']);
		$this->assertSame($groupDashboards, $sink['groupDashboards']);
		$this->assertSame($userDashboards, $sink['userDashboards']);
		$this->assertTrue($sink['allowUserDashboards']);
		// launcher-tile-internal-address REQ-TIA-003, an optional key: pushed when set.
		$this->assertTrue($sink['onOfficeNetwork']);
		// REQ-THM-003, an optional key: the stored administrator CSS.
		$this->assertSame(".launchpad-workspace p {\n  margin: 0;\n}", $sink['customCss']);
	}

	/**
	 * The viewer's Nextcloud timezone is an optional workspace key, pushed only
	 * when set (REQ-CLOCK-002).
	 *
	 * @return void
	 */
	public function testTheUserTimezoneIsPushedOnlyWhenSet(): void {
		$sink = [];
		$this->workspaceBuilder($sink)->setUserTimezone('Asia/Tokyo')->apply();
		$this->assertSame('Asia/Tokyo', $sink['userTimezone']);

		$bare = [];
		$this->workspaceBuilder($bare)->apply();
		$this->assertArrayNotHasKey('userTimezone', $bare);
	}//end testTheUserTimezoneIsPushedOnlyWhenSet()

	/**
	 * Engagement-communities: whether Talk is enabled and the pinned tokens are
	 * optional workspace keys, pushed only when set (REQ-COMM-003, REQ-COMM-004).
	 *
	 * @return void
	 */
	public function testTalkAndPinnedCommunitiesArePushedOnlyWhenSet(): void {
		$sink = [];
		$this->workspaceBuilder($sink)
			->setTalkEnabled(true)
			->setPinnedCommunities([2 => 'vraagict', 5 => 'duurzaam42'])
			->apply();

		$this->assertTrue($sink['talkEnabled']);
		$this->assertSame(['vraagict', 'duurzaam42'], $sink['pinnedCommunities']);

		$bare = [];
		$this->workspaceBuilder($bare)->apply();
		$this->assertArrayNotHasKey('talkEnabled', $bare);
		$this->assertArrayNotHasKey('pinnedCommunities', $bare);
	}

	/**
	 * A workspace builder with every required key set, writing into the sink.
	 *
	 * @param array $sink The recorded state.
	 *
	 * @return InitialStateBuilder
	 */
	private function workspaceBuilder(array &$sink): InitialStateBuilder {
		return (new InitialStateBuilder(initialState: $this->makeRecordingState($sink), page: Page::WORKSPACE))
			->setWidgets([])
			->setLayout([])
			->setPrimaryGroup('default')
			->setPrimaryGroupName('')
			->setIsAdmin(false)
			->setActiveDashboardId('')
			->setDashboardSource('group')
			->setGroupDashboards([])
			->setUserDashboards([])
			->setAllowUserDashboards(false)
			->setAllowedWidgets(null);
	}

	public function testAdminBuilderWritesEveryKeyWithCorrectValues(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::ADMIN);

		$allGroups = [['id' => 'admin', 'displayName' => 'admin']];
		$widgets = [['id' => 'cal', 'title' => 'Calendar']];

		$builder
			->setAllGroups($allGroups)
			->setConfiguredGroups(['admin', 'users'])
			->setWidgets($widgets)
			->setAllowUserDashboards(true)
			->setLinkCreateFileExtensions(['txt', 'md'])
			->apply();

		$this->assertSame($allGroups, $sink['allGroups']);
		$this->assertSame(['admin', 'users'], $sink['configuredGroups']);
		$this->assertSame($widgets, $sink['widgets']);
		$this->assertTrue($sink['allowUserDashboards']);
		$this->assertSame(['txt', 'md'], $sink['linkCreateFileExtensions']);
	}

	/**
	 * The tile editor gets the allowed schemes and the template names, never the template addresses.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
	 */
	public function testTileLaunchOptionsLeaveTheTemplateAddressesOut(): void {
		$sink = [];
		$builder = new InitialStateBuilder(initialState: $this->makeRecordingState($sink), page: Page::ADMIN);

		$builder
			->setAllGroups([])
			->setConfiguredGroups([])
			->setWidgets([])
			->setAllowUserDashboards(false)
			->setLinkCreateFileExtensions(['txt'])
			->setTileLaunchOptions(
				schemes: ['ms-word', 'vscode'],
				templates: [
					['key' => 'entra', 'name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}'],
					'not a template',
				]
			)
			->apply();

		$this->assertSame(['ms-word', 'vscode'], $sink['tileAllowedSchemes']);
		$this->assertSame([['key' => 'entra', 'name' => 'Microsoft Entra ID']], $sink['ssoLaunchTemplates']);
	}

	public function testSchemaVersionAlwaysPushedForWorkspace(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE);

		$builder
			->setWidgets([])
			->setLayout([])
			->setPrimaryGroup('default')
			->setPrimaryGroupName('')
			->setIsAdmin(false)
			->setActiveDashboardId('')
			->setDashboardSource('group')
			->setGroupDashboards([])
			->setUserDashboards([])
			->setAllowUserDashboards(false)
			->setAllowedWidgets(null)
			->apply();

		$this->assertArrayHasKey(InitialStateBuilder::KEY_SCHEMA_VERSION, $sink);
		$this->assertSame(
			InitialStateBuilder::INITIAL_STATE_SCHEMA_VERSION,
			$sink[InitialStateBuilder::KEY_SCHEMA_VERSION]
		);
	}

	public function testSchemaVersionAlwaysPushedForAdmin(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::ADMIN);

		$builder
			->setAllGroups([])
			->setConfiguredGroups([])
			->setWidgets([])
			->setAllowUserDashboards(false)
			->setLinkCreateFileExtensions(['txt'])
			->apply();

		$this->assertArrayHasKey(InitialStateBuilder::KEY_SCHEMA_VERSION, $sink);
		$this->assertSame(3, $sink[InitialStateBuilder::KEY_SCHEMA_VERSION]);
	}

	public function testMissingFirstKeyExceptionMessageNamesTheFirstMissingKey(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::WORKSPACE);

		// Set nothing — apply should fail on the first required key.
		$this->expectException(MissingInitialStateException::class);
		$this->expectExceptionMessage('"widgets"');
		$builder->apply();
	}

	public function testPublicStartBuilderPushesTheTokenAndTheVersion(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::PUBLIC_START);

		$builder->setPublicStartToken('Bib1Noord')->apply();

		$this->assertSame(['publicStartToken' => 'Bib1Noord', InitialStateBuilder::KEY_SCHEMA_VERSION => 3], $sink);
	}

	public function testPublicStartBuilderRefusesToRenderWithoutTheToken(): void {
		$sink = [];
		$state = $this->makeRecordingState($sink);
		$builder = new InitialStateBuilder(initialState: $state, page: Page::PUBLIC_START);

		$this->expectException(MissingInitialStateException::class);
		$this->expectExceptionMessage('"publicStartToken"');
		$builder->apply();
	}
}
