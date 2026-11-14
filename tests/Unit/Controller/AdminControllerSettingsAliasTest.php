<?php

/**
 * AdminControllerSettingsAliasTest
 *
 * `GET /api/admin/settings` returns `allowUserDashboards`; `PUT
 * /api/admin/settings` accepted only `allowUserDash`. Five settings had a
 * read name and a write name, and they did not match.
 *
 * Nextcloud's parameter binding leaves an unrecognised key null, and null
 * here means "not supplied", so a caller that round-tripped GET into PUT
 * wrote NOTHING and was answered `{"status": "ok"}`. Measured on the dev
 * instance: `PUT {"allowUserDashboards": true}` reported success and left the
 * flag off, which reads as "the setting will not stick" rather than as a
 * rejected key.
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
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\ImportService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;

class AdminControllerSettingsAliasTest extends TestCase {
	private AdminController $controller;

	/** @var AdminSettingsService&MockObject */
	private $settingsService;

	protected function setUp(): void {
		$this->settingsService = $this->createMock(AdminSettingsService::class);

		$this->controller = new AdminController(
			request: $this->createMock(IRequest::class),
			templates: new AdminTemplateTools(
				templateService: $this->createMock(AdminTemplateService::class),
				exportService: $this->createMock(ExportService::class),
				importService: $this->createMock(ImportService::class),
				resyncService: $this->createMock(\OCA\LaunchPad\Service\TemplateResyncService::class)
			),
			settings: new AdminSettingsTools(
				settingsService: $this->settingsService,
				groupManager: $this->createMock(IGroupManager::class),
				footerService: $this->createMock(FooterService::class),
				setupWizardService: $this->createMock(\OCA\LaunchPad\Service\SetupWizardService::class)
			),
			caller: new AdminCallerAndRoles(
				roleService: $this->createMock(\OCA\LaunchPad\Service\RoleService::class),
				feedRefresh: $this->createMock(\OCA\LaunchPad\Service\FeedRefreshService::class),
				userSession: $this->createMock(IUserSession::class),
				actionAuth: $this->createMock(ActionAuthService::class)
			)
		);
	}//end setUp()

	/**
	 * A matcher for a full settings map (fourteen entries) that holds the expected entries.
	 *
	 * @param array<string, mixed> $expected The entries the map must hold.
	 *
	 * @return Callback<array<string, mixed>>
	 */
	private function settingsMatching(array $expected): Callback {
		return $this->callback(
			static fn (array $settings): bool => count($settings) === 14 && array_intersect_key($settings, $expected) === $expected
		);
	}//end settingsMatching()

	/**
	 * A payload spelled the way GET spells it must reach the service.
	 *
	 * @return void
	 */
	public function testReadSideNamesAreAccepted(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(
				$this->settingsMatching(
					[
						'defaultPermLevel' => 'full',
						'allowUserDash' => true,
						'allowMultiDash' => false,
						'defaultGridCols' => 8,
						'linkCreateFileExts' => ['md'],
					]
				)
			);

		$this->controller->updateSettings(
			defaultPermissionLevel: 'full',
			allowUserDashboards: true,
			allowMultipleDashboards: false,
			defaultGridColumns: 8,
			linkCreateFileExtensions: ['md'],
		);
	}//end testReadSideNamesAreAccepted()

	/**
	 * The short names AdminSettings.vue sends keep working unchanged.
	 *
	 * @return void
	 */
	public function testWriteSideNamesStillWork(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(
				$this->settingsMatching(
					[
						'defaultPermLevel' => 'view_only',
						'allowUserDash' => true,
						'allowMultiDash' => null,
						'defaultGridCols' => 12,
					]
				)
			);

		$this->controller->updateSettings(
			defaultPermLevel: 'view_only',
			allowUserDash: true,
			defaultGridCols: 12,
		);
	}//end testWriteSideNamesStillWork()

	/**
	 * When both spellings arrive, the short one wins.
	 *
	 * AdminSettings.vue sends the short form, and an explicit short-form
	 * value is the one a form actually submitted, so it must not be
	 * overridden by a long-form key that happened to ride along.
	 *
	 * @return void
	 */
	public function testShortNameWinsWhenBothAreSupplied(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with($this->settingsMatching(['allowUserDash' => false]));

		$this->controller->updateSettings(
			allowUserDash: false,
			allowUserDashboards: true,
		);
	}//end testShortNameWinsWhenBothAreSupplied()

	/**
	 * A setting nobody sent stays null, so it is left alone.
	 *
	 * The aliasing must not turn "not supplied" into a write, or every PUT
	 * would rewrite every setting it did not mention.
	 *
	 * @return void
	 */
	public function testUnsuppliedSettingsStayNull(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(
				$this->settingsMatching(
					[
						'defaultPermLevel' => null,
						'allowUserDash' => null,
						'allowMultiDash' => null,
						'defaultGridCols' => null,
						'linkCreateFileExts' => null,
					]
				)
			);

		$this->controller->updateSettings(allowUserDash: null);
	}//end testUnsuppliedSettingsStayNull()

	/**
	 * runtime-shell REQ-SHELL-009: the start-page option reaches the service
	 * under its own name, and only when it was sent.
	 *
	 * @return void
	 */
	public function testStartPageWithoutNavigationIsPassedThrough(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(
				$this->settingsMatching(
					[
						'defaultPermLevel' => null,
						'allowUserDash' => null,
						'allowMultiDash' => null,
						'defaultGridCols' => null,
						'linkCreateFileExts' => null,
						'defaultSharePermissionLevel' => null,
						'forcedShareGroups' => null,
						'legacyWidgetBridgeEnabled' => null,
						'maxDashboardsPerUser' => null,
						'maxWidgetsPerDashboard' => null,
						'quicksearchFallbackTarget' => null,
						'startPageWithoutNavigation' => true,
						'tileAllowedSchemes' => null,
						'ssoLaunchTemplates' => null,
					]
				)
			);

		$this->controller->updateSettings(startPageWithoutNavigation: true);
	}//end testStartPageWithoutNavigationIsPassedThrough()

	/**
	 * search-ai-prefix-shortcuts REQ-SPX-001: the shortcut list reaches the
	 * service, and only when it was sent.
	 *
	 * @return void
	 */
	public function testSearchShortcutsArePassedThroughOnlyWhenSent(): void {
		$list = [['prefix' => '!t', 'name' => 'TOPdesk', 'urlTemplate' => 'https://t.example/?q={query}']];
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with($this->callback(static fn (array $settings): bool => count($settings) === 15 && $settings['searchShortcuts'] === $list));

		$this->controller->updateSettings(searchShortcuts: $list);
	}//end testSearchShortcutsArePassedThroughOnlyWhenSent()

	/**
	 * Launcher-tile-launch-types: the program schemes and SSO launch templates reach the service under their own names.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
	 */
	public function testTileLaunchSettingsArePassedThrough(): void {
		$templates = [['key' => 'entra', 'name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}']];
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with($this->settingsMatching(['startPageWithoutNavigation' => null, 'tileAllowedSchemes' => ['ms-word'], 'ssoLaunchTemplates' => $templates]));

		$this->controller->updateSettings(tileAllowedSchemes: ['ms-word'], ssoLaunchTemplates: $templates);
	}//end testTileLaunchSettingsArePassedThrough()
}//end class
