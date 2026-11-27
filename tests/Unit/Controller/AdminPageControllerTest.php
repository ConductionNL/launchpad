<?php

/**
 * AdminPageControllerTest
 *
 * Pins the former administration address (REQ-ADM-033): every
 * `/apps/launchpad/admin/{tab}` answers with a 303 to
 * `/settings/admin/launchpad#{tab}`, a former tab keeps its name as the
 * fragment, an unknown tab opens General, and the embed query survives.
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
use OCA\LaunchPad\Settings\AdminTabs;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for AdminPageController.
 *
 * @covers \OCA\LaunchPad\Controller\AdminPageController
 * @uses   \OCA\LaunchPad\Settings\AdminTabs
 */
class AdminPageControllerTest extends TestCase {

	/**
	 * The controller with a request carrying the given query.
	 *
	 * @param array<string, string> $query The query parameters.
	 *
	 * @return AdminPageController
	 */
	private function controller(array $query = []): AdminPageController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key): ?string => $query[$key] ?? null
		);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $args = []): string => $route === 'settings.AdminSettings.index'
				? '/index.php/settings/admin/' . ($args['section'] ?? '')
				: '/elsewhere'
		);

		return new AdminPageController(request: $request, urlGenerator: $urls);
	}//end controller()

	/**
	 * Each tab of the page, a former tab and an unknown name.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function tabs(): array {
		$cases = [];
		foreach (AdminTabs::TABS as $tab) {
			$cases[$tab] = [$tab, $tab];
		}

		return $cases + [
			'former tab embeds' => ['embeds', 'embeds'],
			'former tab app-catalogue' => ['app-catalogue', 'app-catalogue'],
			'unknown tab' => ['nonsense', 'general'],
			'no tab' => ['', 'general'],
		];
	}//end tabs()

	/**
	 * The former address answers 303 to the settings page on that tab.
	 *
	 * @param string $tab      The tab the former address names.
	 * @param string $fragment The fragment the redirect opens.
	 *
	 * @return void
	 */
	#[DataProvider('tabs')]
	public function testTheFormerAddressRedirectsToTheSettingsPage(string $tab, string $fragment): void {
		$response = $this->controller()->show(tab: $tab);

		$this->assertSame(expected: Http::STATUS_SEE_OTHER, actual: $response->getStatus());
		$this->assertSame(expected: '/index.php/settings/admin/launchpad#' . $fragment, actual: $response->getRedirectURL());
	}//end testTheFormerAddressRedirectsToTheSettingsPage()

	/**
	 * A link into the Embeds section keeps the widget it names, before the
	 * fragment; other query parameters are dropped.
	 *
	 * @return void
	 */
	public function testTheEmbedQuerySurvivesTheRedirect(): void {
		$response = $this->controller(query: ['embedType' => 'widget', 'embedId' => '5', 'other' => 'x'])->show(tab: 'embeds');

		$this->assertSame(
			expected: '/index.php/settings/admin/launchpad?embedType=widget&embedId=5#embeds',
			actual: $response->getRedirectURL()
		);
	}//end testTheEmbedQuerySurvivesTheRedirect()

	/**
	 * The route stays an administrator's route and never a public one.
	 *
	 * @return void
	 */
	public function testTheRouteStaysAnAdministratorsRoute(): void {
		$method = new ReflectionMethod(objectOrMethod: AdminPageController::class, method: 'show');
		$admin = $method->getAttributes(name: AuthorizedAdminSetting::class);

		$this->assertCount(expectedCount: 1, haystack: $admin);
		$this->assertSame(expected: ['settings' => LaunchPadAdmin::class], actual: $admin[0]->getArguments());
		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(name: PublicPage::class));
	}//end testTheRouteStaysAnAdministratorsRoute()
}//end class
