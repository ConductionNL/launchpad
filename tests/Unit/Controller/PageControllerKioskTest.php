<?php

/**
 * PageControllerKioskTest
 *
 * `?kiosk=1` renders the workspace and the public share page without any
 * Nextcloud chrome (REQ-KIOSK-001); without the flag they keep their layout.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PageController;
use OCA\LaunchPad\Controller\PageSettingsSources;
use OCA\LaunchPad\Controller\PageSources;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PublicStartPage;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\SearchShortcutService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The kiosk flag picks the page layout.
 */
#[CoversClass(PageController::class)]
#[UsesClass(PageSources::class)]
#[UsesClass(PageSettingsSources::class)]
class PageControllerKioskTest extends TestCase {

	/**
	 * The controller over a request whose `kiosk` parameter is given.
	 *
	 * @param string|null $kiosk The `kiosk` query value, or null when absent.
	 *
	 * @return PageController The controller.
	 */
	private function controller(?string $kiosk): PageController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => ($key === 'kiosk' ? $kiosk : $default)
		);

		return new class(
			request: $request,
			sources: new PageSources(
				initialState: $this->createMock(originalClassName: IInitialState::class),
				widgetService: $this->createMock(originalClassName: WidgetService::class),
				dashboardService: $this->createMock(originalClassName: DashboardService::class),
				adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class)
			),
			userSession: $this->createMock(originalClassName: IUserSession::class),
			settings: new PageSettingsSources(
				roleFeaturePerm: $this->createMock(originalClassName: RoleFeaturePermissionService::class),
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				logger: $this->createMock(originalClassName: LoggerInterface::class),
				adminSettingsService: $this->createMock(originalClassName: AdminSettingsService::class),
				publicStartPage: $this->createMock(originalClassName: PublicStartPage::class),
				urlGenerator: $this->createMock(originalClassName: IURLGenerator::class),
				searchShortcuts: $this->createMock(originalClassName: SearchShortcutService::class)
			)
		) extends PageController {
			/**
			 * Nextcloud's asset registry is not loaded in a unit test.
			 *
			 * @return void
			 */
			protected function addPublicAssets(): void {
			}
		};
	}//end controller()

	/**
	 * The flag values and the public page layout they give.
	 *
	 * @return array<string, array{0: string|null, 1: string}>
	 */
	public static function flags(): array {
		return [
			'kiosk=1'   => ['1', TemplateResponse::RENDER_AS_BASE],
			'no flag'   => [null, TemplateResponse::RENDER_AS_PUBLIC],
			'kiosk=0'   => ['0', TemplateResponse::RENDER_AS_PUBLIC],
			'kiosk=yes' => ['yes', TemplateResponse::RENDER_AS_PUBLIC],
		];
	}//end flags()

	/**
	 * The public share page drops its chrome only for `kiosk=1`.
	 *
	 * @param string|null $kiosk    The `kiosk` query value.
	 * @param string      $renderAs The expected layout.
	 *
	 * @return void
	 */
	#[DataProvider('flags')]
	public function testThePublicSharePageFollowsTheFlag(?string $kiosk, string $renderAs): void {
		$response = $this->controller(kiosk: $kiosk)->publicShare();

		$this->assertSame(expected: $renderAs, actual: $response->getRenderAs());
		$this->assertSame(expected: 'public', actual: $response->getTemplateName());
	}//end testThePublicSharePageFollowsTheFlag()

	/**
	 * The workspace layout is the user layout without the flag, and bare with it.
	 *
	 * @return void
	 */
	public function testTheWorkspaceLayoutFollowsTheFlag(): void {
		$method = new ReflectionMethod(objectOrMethod: PageController::class, method: 'kioskRenderAs');

		$this->assertSame(
			expected: TemplateResponse::RENDER_AS_BASE,
			actual: $method->invoke($this->controller(kiosk: '1'), TemplateResponse::RENDER_AS_USER)
		);
		$this->assertSame(
			expected: TemplateResponse::RENDER_AS_USER,
			actual: $method->invoke($this->controller(kiosk: null), TemplateResponse::RENDER_AS_USER)
		);
	}//end testTheWorkspaceLayoutFollowsTheFlag()
}//end class
