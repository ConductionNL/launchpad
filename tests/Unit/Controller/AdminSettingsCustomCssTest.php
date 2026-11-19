<?php

/**
 * AdminSettingsController administrator CSS test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AdminSettingsController;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Kernel\Contracts\SearchShortcutServiceInterface;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\CustomCssService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(AdminSettingsController::class)]
#[UsesClass(RequestGuard::class)]
#[UsesClass(ResponseHelper::class)]
class AdminSettingsCustomCssTest extends TestCase {

	/** @var CustomCssService&MockObject */
	private $css;

	/** @var IUserSession&MockObject */
	private $userSession;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	private AdminSettingsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->css          = $this->createMock(CustomCssService::class);
		$this->userSession  = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->controller   = new AdminSettingsController(
			request: $this->createMock(IRequest::class),
			settingsService: $this->createMock(AdminSettingsService::class),
			groupManager: $this->groupManager,
			guard: new RequestGuard(
				userSession: $this->userSession,
				groupManager: $this->groupManager,
				actionAuth: $this->createMock(ActionAuthService::class)
			),
			shortcuts: $this->createMock(SearchShortcutServiceInterface::class),
			css: $this->css,
			responseHelper: new ResponseHelper(logger: $this->createMock(LoggerInterface::class)),
		);
	}

	private function loginAs(string $uid, bool $admin): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with($uid)->willReturn($admin);
	}

	public function testANonAdministratorIsRefusedOnEveryRoute(): void {
		$this->loginAs(uid: 'ella', admin: false);
		$this->css->expects($this->never())->method('setGlobalCss');
		$this->css->expects($this->never())->method('setDashboardCss');
		$this->css->expects($this->never())->method('getGlobalCss');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller->showCustomCss()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller->updateCustomCss(customCss: 'p {}')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller->updateDashboardCustomCss(uuid: 'u', customCss: 'p {}')->getStatus());
	}

	public function testNobodySignedInIsRefused(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->css->expects($this->never())->method('setGlobalCss');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller->updateCustomCss(customCss: 'p {}')->getStatus());
	}

	public function testAnAdministratorReadsTheGlobalCss(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->method('getGlobalCss')->willReturn('.launchpad-workspace p {}');

		$response = $this->controller->showCustomCss();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['customCss' => '.launchpad-workspace p {}'], $response->getData());
	}

	public function testAnAdministratorsSaveAnswersWithWhatWasDropped(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->expects($this->once())->method('setGlobalCss')->with('@import url(x); p { margin: 0 }')
			->willReturn(['customCss' => '.launchpad-workspace p { margin: 0; }', 'removed' => ['@import']]);

		$response = $this->controller->updateCustomCss(customCss: '@import url(x); p { margin: 0 }');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['customCss' => '.launchpad-workspace p { margin: 0; }', 'removed' => ['@import']], $response->getData());
	}

	public function testAValueThatIsNoStringAnswers400(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->expects($this->never())->method('setGlobalCss');

		$response = $this->controller->updateCustomCss(customCss: ['p' => 'x']);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'customCss must be a string'], $response->getData());
	}

	public function testOversizedCssAnswers400WithTheMessage(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->method('setGlobalCss')->willThrowException(new InvalidArgumentException('Custom CSS may be at most 20 KB.'));

		$response = $this->controller->updateCustomCss(customCss: 'x');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Custom CSS may be at most 20 KB.'], $response->getData());
	}

	public function testADashboardsCssIsSavedForThatDashboard(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->expects($this->once())->method('setDashboardCss')->with('uuid-7', 'body { display: none }')
			->willReturn(['customCss' => 'scoped', 'removed' => []]);

		$response = $this->controller->updateDashboardCustomCss(uuid: 'uuid-7', customCss: 'body { display: none }');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['customCss' => 'scoped', 'removed' => []], $response->getData());
	}

	public function testAnUnknownDashboardAnswers404(): void {
		$this->loginAs(uid: 'noor', admin: true);
		$this->css->method('setDashboardCss')->willThrowException(new DoesNotExistException('none'));

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->updateDashboardCustomCss(uuid: 'nope', customCss: 'p {}')->getStatus());
	}
}
