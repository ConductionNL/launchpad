<?php

/**
 * PageControllerStartTest
 *
 * Covers design D3 of sharing-public-start-page: `/start` renders the public
 * page with the chosen link's token as initial state, falls back to the login
 * form with `direct=1` when the start page is off or its link went bad, and
 * is reachable without signing in under the public-share rate limit.
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
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PageController;
use OCA\LaunchPad\Controller\PageSettingsSources;
use OCA\LaunchPad\Controller\PageSources;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCA\LaunchPad\Service\PublicStartPage;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The start page route.
 */
#[CoversClass(PageController::class)]
#[UsesClass(PageSources::class)]
#[UsesClass(PageSettingsSources::class)]
#[UsesClass(InitialStateBuilder::class)]
#[UsesClass(PublicShare::class)]
class PageControllerStartTest extends TestCase {

	/**
	 * Initial state the controller provided.
	 *
	 * @var array<string, mixed>
	 */
	private array $state = [];

	/**
	 * The controller over a start page that shows the given link.
	 *
	 * @param PublicShare|null $active The link, or null when the start page is off.
	 *
	 * @return PageController
	 */
	private function controller(?PublicShare $active): PageController {
		$initialState = $this->createMock(originalClassName: IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(function (string $key, mixed $value): void {
			$this->state[$key] = $value;
		});

		$startPage = $this->createMock(originalClassName: PublicStartPage::class);
		$startPage->method('active')->willReturn($active);

		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $arguments = []): string => '/index.php/' . $route . '?' . http_build_query($arguments)
		);

		return new class(
			request: $this->createMock(originalClassName: IRequest::class),
			sources: new PageSources(
				initialState: $initialState,
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
				publicStartPage: $startPage,
				urlGenerator: $urls
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
	 * With the start page on, `/start` renders the public page for the chosen link.
	 *
	 * @return void
	 */
	public function testTheStartPageRendersTheChosenLink(): void {
		$share = new PublicShare();
		$share->setToken('Bib1Noord');

		$response = $this->controller(active: $share)->start();

		self::assertInstanceOf(TemplateResponse::class, $response);
		self::assertSame('public', $response->getTemplateName());
		self::assertSame(TemplateResponse::RENDER_AS_PUBLIC, $response->getRenderAs());
		self::assertSame('Bib1Noord', $this->state['publicStartToken']);
		self::assertSame(InitialStateBuilder::INITIAL_STATE_SCHEMA_VERSION, $this->state[InitialStateBuilder::KEY_SCHEMA_VERSION]);
	}//end testTheStartPageRendersTheChosenLink()

	/**
	 * With the start page off, or its link gone bad, the visitor goes to the login form with `direct=1`.
	 *
	 * @return void
	 */
	public function testAStartPageThatIsOffFallsBackToTheLoginForm(): void {
		$response = $this->controller(active: null)->start();

		self::assertInstanceOf(RedirectResponse::class, $response);
		self::assertSame('/index.php/core.login.showLoginForm?direct=1', $response->getRedirectURL());
		self::assertSame([], $this->state);
	}//end testAStartPageThatIsOffFallsBackToTheLoginForm()

	/**
	 * `/start` opens without signing in, without a CSRF token, under the public-share rate limit.
	 *
	 * @return void
	 */
	public function testTheStartPageIsPublicAndRateLimited(): void {
		$method = new ReflectionMethod(PageController::class, 'start');

		self::assertCount(1, $method->getAttributes(PublicPage::class));
		self::assertCount(1, $method->getAttributes(NoCSRFRequired::class));
		$limit = $method->getAttributes(AnonRateLimit::class);
		self::assertCount(1, $limit);
		self::assertSame(['limit' => 120, 'period' => 60], $limit[0]->getArguments());
	}//end testTheStartPageIsPublicAndRateLimited()

	/**
	 * `/start` is a route, before the deep-link catch-all.
	 *
	 * @return void
	 */
	public function testTheStartRouteComesBeforeTheDeepLinkCatchAll(): void {
		$source = (string)file_get_contents(dirname(__DIR__, 3) . '/appinfo/routes.php');
		$start = strpos($source, "'page#start', 'url' => '/start', 'verb' => 'GET'");
		$deepLink = strpos($source, "'page#deepLink'");

		self::assertNotFalse($start);
		self::assertNotFalse($deepLink);
		self::assertLessThan($deepLink, $start);
	}//end testTheStartRouteComesBeforeTheDeepLinkCatchAll()
}//end class
