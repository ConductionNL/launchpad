<?php

/**
 * AdminPageControllerTest
 *
 * Pins the administration page (REQ-ADM-030): a reader who is no (delegated)
 * administrator gets a 403 before anything of the page renders, an
 * administrator the Launchpad shell with the administration's initial state,
 * and the tab comes from the route, an unknown one sending the reader to
 * General.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AdminPageController;
use OCA\LaunchPad\Settings\AdminFormState;
use OCA\LaunchPad\Settings\AdminPageAccess;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for AdminPageController.
 *
 * @covers \OCA\LaunchPad\Controller\AdminPageController
 */
class AdminPageControllerTest extends TestCase {

	/** @var AdminFormState&MockObject */
	private $formState;

	/**
	 * Whether the assets were registered.
	 *
	 * @var boolean
	 */
	public bool $assetsAdded = false;

	/**
	 * The controller, for a reader the access check allows or refuses.
	 *
	 * @param bool $allowed Whether the reader is a (delegated) administrator.
	 *
	 * @return AdminPageController
	 */
	private function controller(bool $allowed): AdminPageController {
		$access = $this->createMock(originalClassName: AdminPageAccess::class);
		$access->method('allows')->willReturn($allowed);
		$this->formState = $this->createMock(originalClassName: AdminFormState::class);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $args = []): string => '/index.php/apps/launchpad/admin/' . ($args['tab'] ?? '') . '#' . $route
		);

		$test = $this;
		return new class(
			request: $this->createMock(originalClassName: IRequest::class),
			access: $access,
			formState: $this->formState,
			urlGenerator: $urls,
			test: $test
		) extends AdminPageController {
			/**
			 * Constructor.
			 *
			 * @param IRequest                $request      The request.
			 * @param AdminPageAccess         $access       The access check.
			 * @param AdminFormState          $formState    The initial state.
			 * @param IURLGenerator           $urlGenerator The URL generator.
			 * @param AdminPageControllerTest $test         Records the asset registration.
			 */
			public function __construct(
				IRequest $request,
				AdminPageAccess $access,
				AdminFormState $formState,
				IURLGenerator $urlGenerator,
				private readonly AdminPageControllerTest $test,
			) {
				parent::__construct(request: $request, access: $access, formState: $formState, urlGenerator: $urlGenerator);
			}

			/**
			 * Nextcloud's asset registry is not loaded in a unit test.
			 *
			 * @return void
			 */
			protected function addAssets(): void {
				$this->test->assetsAdded = true;
			}
		};
	}//end controller()

	/**
	 * A reader who is no administrator gets a 403, and nothing of the page:
	 * no initial state, no script.
	 *
	 * @return void
	 */
	public function testANonAdminGets403BeforeAnythingRenders(): void {
		$controller = $this->controller(allowed: false);
		$this->formState->expects($this->never())->method('provide');

		$response = $controller->show(tab: 'general');

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
		$this->assertSame(expected: 'core', actual: $response->getApp());
		$this->assertSame(expected: '403', actual: $response->getTemplateName());
		$this->assertSame(expected: [], actual: $response->getParams());
		$this->assertFalse(condition: $this->assetsAdded);
	}//end testANonAdminGets403BeforeAnythingRenders()

	/**
	 * An unknown tab is refused the same way to a non-admin: the check comes first.
	 *
	 * @return void
	 */
	public function testANonAdminGets403AlsoOnAnUnknownTab(): void {
		$response = $this->controller(allowed: false)->show(tab: 'nonsense');

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}//end testANonAdminGets403AlsoOnAnUnknownTab()

	/**
	 * Every tab the page declares.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function tabs(): array {
		$cases = [];
		foreach (AdminPageController::TABS as $tab) {
			$cases[$tab] = [$tab];
		}

		return $cases;
	}//end tabs()

	/**
	 * A (delegated) administrator gets the Launchpad shell with the
	 * administration's initial state, on the tab the route names.
	 *
	 * @param string $tab The tab.
	 *
	 * @return void
	 */
	#[DataProvider('tabs')]
	public function testAnAdministratorGetsThePageOnTheRoutesTab(string $tab): void {
		$controller = $this->controller(allowed: true);
		$this->formState->expects($this->once())->method('provide');

		$response = $controller->show(tab: $tab);

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $response);
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'launchpad', actual: $response->getApp());
		$this->assertSame(expected: 'index', actual: $response->getTemplateName());
		$this->assertSame(expected: TemplateResponse::RENDER_AS_USER, actual: $response->getRenderAs());
		$this->assertTrue(condition: $this->assetsAdded);
	}//end testAnAdministratorGetsThePageOnTheRoutesTab()

	/**
	 * Without a tab the page opens on General.
	 *
	 * @return void
	 */
	public function testWithoutATabThePageOpensOnGeneral(): void {
		$controller = $this->controller(allowed: true);
		$this->formState->expects($this->once())->method('provide');

		$response = $controller->show();

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $response);
		$this->assertSame(expected: 'general', actual: AdminPageController::DEFAULT_TAB);
	}//end testWithoutATabThePageOpensOnGeneral()

	/**
	 * An unknown tab sends the reader to General, before any state is provided.
	 *
	 * @return void
	 */
	public function testAnUnknownTabRedirectsToGeneral(): void {
		$controller = $this->controller(allowed: true);
		$this->formState->expects($this->never())->method('provide');

		$response = $controller->show(tab: 'settings');

		$this->assertInstanceOf(expected: RedirectResponse::class, actual: $response);
		$this->assertSame(
			expected: '/index.php/apps/launchpad/admin/general#launchpad.adminPage.show',
			actual: $response->getRedirectURL()
		);
		$this->assertFalse(condition: $this->assetsAdded);
	}//end testAnUnknownTabRedirectsToGeneral()

	/**
	 * The route lets a delegated Launchpad administrator past Nextcloud's own
	 * check, and nobody else: `AuthorizedAdminSetting(LaunchPadAdmin)`, no
	 * `NoAdminRequired`, no `PublicPage`.
	 *
	 * @return void
	 */
	public function testTheRouteAdmitsDelegatedAdministrators(): void {
		$reflection = new ReflectionMethod(objectOrMethod: AdminPageController::class, method: 'show');

		$admin = $reflection->getAttributes(name: AuthorizedAdminSetting::class);
		$this->assertCount(expectedCount: 1, haystack: $admin);
		$this->assertSame(expected: LaunchPadAdmin::class, actual: $admin[0]->newInstance()->getSettings());
		$this->assertSame(expected: [], actual: $reflection->getAttributes(name: NoAdminRequired::class));
		$this->assertSame(expected: [], actual: $reflection->getAttributes(name: PublicPage::class));
	}//end testTheRouteAdmitsDelegatedAdministrators()

	/**
	 * The route is registered under `/admin/{tab}`, General by default, before
	 * the deep-link catch-all that would otherwise take it.
	 *
	 * @return void
	 */
	public function testTheRouteIsRegisteredBeforeTheCatchAll(): void {
		$routes = (require dirname(__DIR__, 3) . '/appinfo/routes.php')['routes'];
		$names = array_column($routes, 'name');
		$admin = array_search('adminPage#show', $names, true);
		$catchAll = array_search('page#deepLink', $names, true);

		$this->assertIsInt(actual: $admin);
		$this->assertIsInt(actual: $catchAll);
		$this->assertLessThan(expected: $catchAll, actual: $admin);
		$this->assertSame(expected: '/admin/{tab}', actual: $routes[$admin]['url']);
		$this->assertSame(expected: ['tab' => 'general'], actual: $routes[$admin]['defaults']);
	}//end testTheRouteIsRegisteredBeforeTheCatchAll()
}//end class
