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
			->setStartPageWithoutNavigation(true)
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
		// runtime-shell REQ-SHELL-009, an optional key: pushed when set.
		$this->assertTrue($sink['startPageWithoutNavigation']);
	}

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
		$this->assertSame(2, $sink[InitialStateBuilder::KEY_SCHEMA_VERSION]);
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
}
