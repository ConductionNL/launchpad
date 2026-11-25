<?php

/**
 * AdminControllerSettingsValidationTest
 *
 * REQ-ASET-002: `PUT /api/admin/settings` refuses a boolean setting whose raw
 * value is not a boolean with 400 and stores nothing. Nextcloud casts a bool
 * parameter before the controller sees it ("maybe" arrives as true), so the
 * controller reads the raw request value.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
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
use PHPUnit\Framework\TestCase;
use OCA\LaunchPad\Controller\ResponseHelper;
use Psr\Log\NullLogger;
use OCA\LaunchPad\Controller\AdminContext;

class AdminControllerSettingsValidationTest extends TestCase {
	/**
	 * The controller under test.
	 *
	 * @var AdminController
	 */
	private AdminController $controller;

	/** @var AdminSettingsService&MockObject */
	private $settingsService;

	/**
	 * The raw request parameters the mocked request answers.
	 *
	 * @var array<string, mixed>
	 */
	private array $params = [];

	/**
	 * Build the controller over a request that answers $this->params.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->settingsService = $this->createMock(originalClassName: AdminSettingsService::class);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->params[$key] ?? $default
		);

		$this->controller = new AdminController(
			request: $request,
			templates: new AdminTemplateTools(
				templateService: $this->createMock(originalClassName: AdminTemplateService::class),
				exportService: $this->createMock(originalClassName: ExportService::class),
				importService: $this->createMock(originalClassName: ImportService::class),
				resyncService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\TemplateResyncService::class)
			),
			settings: new AdminSettingsTools(
				settingsService: $this->settingsService,
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				footerService: $this->createMock(originalClassName: FooterService::class),
				setupWizardService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\SetupWizardService::class)
			),
			context: new AdminContext(caller: new AdminCallerAndRoles(
				roleService: $this->createMock(originalClassName: \OCA\LaunchPad\Service\RoleService::class),
				feedRefresh: $this->createMock(originalClassName: \OCA\LaunchPad\Service\FeedRefreshService::class),
				userSession: $this->createMock(originalClassName: IUserSession::class),
				actionAuth: $this->createMock(originalClassName: ActionAuthService::class)
			), responseHelper: new ResponseHelper(logger: new NullLogger()))
		);
	}//end setUp()

	/**
	 * "maybe" for a boolean setting answers 400 and nothing is written.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
	 */
	public function testANonBooleanValueIsRefused(): void {
		$this->settingsService->expects($this->never())->method('updateSettings');
		foreach (['allowUserDash', 'allowMultiDash', 'allowUserDashboards', 'defaultAppEnabled'] as $name) {
			$this->params = [$name => 'maybe'];
			$response = $this->controller->updateSettings(allowUserDash: true);
			$this->assertSame(expected: 400, actual: $response->getStatus(), message: $name);
			$this->assertStringContainsString(needle: $name, haystack: (string)$response->getData()['error']);
		}
	}//end testANonBooleanValueIsRefused()

	/**
	 * Booleans and their usual spellings pass through to the service.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
	 */
	public function testBooleanSpellingsAreAccepted(): void {
		$this->settingsService->expects($this->exactly(count: 6))->method('updateSettings');
		foreach ([true, false, 0, 1, '1', 'false'] as $value) {
			$this->params = ['allowUserDash' => $value];
			$response = $this->controller->updateSettings(allowUserDash: (bool)$value);
			$this->assertSame(expected: 200, actual: $response->getStatus());
		}
	}//end testBooleanSpellingsAreAccepted()

	/**
	 * The service's refusal of a level or a column count answers 400.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
	 */
	public function testAServiceRefusalAnswers400(): void {
		$this->settingsService->method('updateSettings')
			->willThrowException(new \InvalidArgumentException('defaultPermLevel must be one of view_only, add_only, full'));
		$response = $this->controller->updateSettings(defaultPermLevel: 'super_admin');
		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertStringContainsString(needle: 'defaultPermLevel', haystack: (string)$response->getData()['error']);
	}//end testAServiceRefusalAnswers400()

	/**
	 * REQ-ASET-011: the store failing answers 500 with a generic message, never the database's own words.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-settings-api-error-handling-req-aset-011
	 */
	public function testAStoreFailureAnswers500WithoutDetails(): void {
		$this->settingsService->method('updateSettings')
			->willThrowException(new \RuntimeException('SQLSTATE[42S02]: Base table oc_launchpad_admin_settings not found'));
		$response = $this->controller->updateSettings(allowUserDash: true);
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertStringNotContainsString(needle: 'SQLSTATE', haystack: json_encode(value: $response->getData()));
	}//end testAStoreFailureAnswers500WithoutDetails()
}//end class
