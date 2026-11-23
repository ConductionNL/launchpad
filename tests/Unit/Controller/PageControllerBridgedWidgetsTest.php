<?php

/**
 * PageControllerBridgedWidgetsTest
 *
 * Covers which widget catalogue the workspace page carries: the loaded
 * registry with the legacy widget bridge on, the registry read without
 * loading it otherwise (REQ-RFP-010).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\PageSettingsSources;
use OCA\LaunchPad\Controller\PageSources;
use OCA\LaunchPad\Controller\PageController;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The workspace renders no Nextcloud dashboard, but reading the widget registry
 * to bridge legacy widgets is expensive out of proportion to that: `getWidgets()`
 * is not a getter — it calls `loadLazyPanels()`, which calls `load()` on EVERY
 * widget of every enabled app, and `load()` is where widgets call
 * `Util::addScript()`. Measured at ~147 MB of widget JS on a page that renders
 * no dashboard.
 *
 * So the decision "do we touch the registry at all" is load-bearing, and these
 * tests pin both directions of it.
 */
class PageControllerBridgedWidgetsTest extends TestCase {
	/**
	 * Build a controller whose only meaningful collaborator is the widget
	 * service — every other dependency is an unused mock.
	 *
	 * @param WidgetService $widgetService The widget service double.
	 *
	 * @return PageController The controller under test.
	 */
	private function makeController(WidgetService $widgetService): PageController {
		return new PageController(
			request: $this->createMock(IRequest::class),
			sources: new PageSources(
				initialState: $this->createMock(IInitialState::class),
				widgetService: $widgetService,
				dashboardService: $this->createMock(DashboardService::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			userSession: $this->createMock(IUserSession::class),
			settings: new PageSettingsSources(
				roleFeaturePerm: $this->createMock(RoleFeaturePermissionService::class),
				treeService: $this->createMock(DashboardTreeService::class),
				logger: $this->createMock(LoggerInterface::class),
				adminSettingsService: $this->createMock(AdminSettingsService::class),
				publicStartPage: $this->createMock(\OCA\LaunchPad\Service\PublicStartPage::class),
				urlGenerator: $this->createMock(\OCP\IURLGenerator::class),
				searchShortcuts: $this->createMock(\OCA\LaunchPad\Service\SearchShortcutService::class)
			)
		);
	}//end makeController()

	/**
	 * Invoke the private resolver.
	 *
	 * @param PageController $controller The controller under test.
	 * @param array          $settings   The resolved admin settings.
	 *
	 * @return array The bridged widget descriptors.
	 */
	private function resolve(PageController $controller, array $settings): array {
		$method = new ReflectionMethod(
			PageController::class,
			'resolveWidgetCatalogue'
		);
		$method->setAccessible(accessible: true);

		return $method->invoke($controller, $settings);
	}//end resolve()

	/**
	 * Bridge ON: the registry IS read and its descriptors are returned.
	 *
	 * @return void
	 */
	public function testBridgeEnabledReadsTheWidgetRegistry(): void {
		$widgetService = $this->createMock(WidgetService::class);
		$widgetService->expects($this->once())
			->method('getAvailableWidgets')
			->willReturn([['id' => 'deals'], ['id' => 'leads']]);

		$result = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: ['legacyWidgetBridgeEnabled' => true]
		);

		$this->assertCount(2, $result);
		$this->assertSame('deals', $result[0]['id']);
	}//end testBridgeEnabledReadsTheWidgetRegistry()

	/**
	 * Bridge OFF: the registry is NOT loaded, its widgets are read without
	 * loading them, so the card library needs no `GET /api/widgets`.
	 *
	 * `never()` on getAvailableWidgets() is the assertion that matters: through
	 * it `IManager::getWidgets()` runs every widget's `load()`, which injects
	 * its scripts into the page.
	 *
	 * @return void
	 */
	public function testBridgeDisabledReadsTheCatalogueWithoutLoading(): void {
		$widgetService = $this->createMock(WidgetService::class);
		$widgetService->expects($this->never())
			->method('getAvailableWidgets');
		$widgetService->expects($this->once())
			->method('getWidgetCatalogue')
			->willReturn([['id' => 'activity']]);

		$result = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: ['legacyWidgetBridgeEnabled' => false]
		);

		$this->assertSame([['id' => 'activity']], $result);
	}//end testBridgeDisabledReadsTheCatalogueWithoutLoading()

	/**
	 * A registry that cannot be read without loading gives an empty list, and
	 * the frontend then asks `GET /api/widgets`.
	 *
	 * @return void
	 */
	public function testAnUnreadableCatalogueGivesAnEmptyList(): void {
		$widgetService = $this->createMock(WidgetService::class);
		$widgetService->expects($this->never())
			->method('getAvailableWidgets');
		$widgetService->method('getWidgetCatalogue')->willReturn(null);

		$result = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: ['legacyWidgetBridgeEnabled' => false]
		);

		$this->assertSame([], $result);
	}//end testAnUnreadableCatalogueGivesAnEmptyList()

	/**
	 * An absent key defaults to OFF, matching AdminSettingsService's default.
	 *
	 * This pins the fail-safe direction: a settings row that has never been
	 * written must not opt an instance into the expensive path.
	 *
	 * @return void
	 */
	public function testMissingSettingDefaultsToBridgeOff(): void {
		$widgetService = $this->createMock(WidgetService::class);
		$widgetService->expects($this->never())
			->method('getAvailableWidgets');
		$widgetService->method('getWidgetCatalogue')->willReturn([]);

		$resolved = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: []
		);

		$this->assertSame([], $resolved);
	}//end testMissingSettingDefaultsToBridgeOff()
}//end class
