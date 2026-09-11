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
use PHPUnit\Framework\TestCase;

class AdminControllerSettingsAliasTest extends TestCase {
	private AdminController $controller;

	/** @var AdminSettingsService&MockObject */
	private $settingsService;

	protected function setUp(): void {
		$this->settingsService = $this->createMock(AdminSettingsService::class);

		$this->controller = new AdminController(
			request: $this->createMock(IRequest::class),
			templateService: $this->createMock(AdminTemplateService::class),
			settingsService: $this->settingsService,
			groupManager: $this->createMock(IGroupManager::class),
			userSession: $this->createMock(IUserSession::class),
			exportService: $this->createMock(ExportService::class),
			importService: $this->createMock(ImportService::class),
			roleService: $this->createMock(\OCA\LaunchPad\Service\RoleService::class),
			feedRefresh: $this->createMock(\OCA\LaunchPad\Service\FeedRefreshService::class),
			footerService: $this->createMock(FooterService::class),
			setupWizardService: $this->createMock(\OCA\LaunchPad\Service\SetupWizardService::class),
			actionAuth: $this->createMock(ActionAuthService::class),
			resyncService: $this->createMock(\OCA\LaunchPad\Service\TemplateResyncService::class),
		);
	}//end setUp()

	/**
	 * A payload spelled the way GET spells it must reach the service.
	 *
	 * @return void
	 */
	public function testReadSideNamesAreAccepted(): void {
		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(
				$this->equalTo('full'),
				$this->isTrue(),
				$this->isFalse(),
				$this->equalTo(8),
				$this->equalTo(['md']),
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
				$this->equalTo('view_only'),
				$this->isTrue(),
				$this->isNull(),
				$this->equalTo(12),
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
			->with($this->anything(), $this->isFalse());

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
				$this->isNull(),
				$this->isNull(),
				$this->isNull(),
				$this->isNull(),
				$this->isNull(),
			);

		$this->controller->updateSettings(allowUserDash: null);
	}//end testUnsuppliedSettingsStayNull()
}//end class
