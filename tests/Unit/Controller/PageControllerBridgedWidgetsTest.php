<?php

/**
 * PageControllerBridgedWidgetsTest
 *
 * Covers the legacy-widget-bridge decision on the workspace render path.
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
			initialState: $this->createMock(IInitialState::class),
			userSession: $this->createMock(IUserSession::class),
			widgetService: $widgetService,
			dashboardService: $this->createMock(DashboardService::class),
			adminTemplateService: $this->createMock(AdminTemplateService::class),
			roleFeaturePerm: $this->createMock(RoleFeaturePermissionService::class),
			treeService: $this->createMock(DashboardTreeService::class),
			logger: $this->createMock(LoggerInterface::class),
			adminSettingsService: $this->createMock(AdminSettingsService::class),
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
			'resolveBridgedWidgets'
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
	 * Bridge OFF: the registry is NOT touched at all.
	 *
	 * `never()` is the assertion that matters — the point of this change is not
	 * that the returned array is empty, it is that `getAvailableWidgets()` (and
	 * through it `IManager::getWidgets()`) is never called, so no widget's
	 * `load()` runs and no scripts are injected. An assertion on the return
	 * value alone would still pass if the registry were read and discarded.
	 *
	 * @return void
	 */
	public function testBridgeDisabledNeverTouchesTheRegistry(): void {
		$widgetService = $this->createMock(WidgetService::class);
		$widgetService->expects($this->never())
			->method('getAvailableWidgets');

		$result = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: ['legacyWidgetBridgeEnabled' => false]
		);

		$this->assertSame([], $result);
	}//end testBridgeDisabledNeverTouchesTheRegistry()

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

		$resolved = $this->resolve(
			controller: $this->makeController(widgetService: $widgetService),
			settings: []
		);

		$this->assertSame([], $resolved);
	}//end testMissingSettingDefaultsToBridgeOff()
}//end class
