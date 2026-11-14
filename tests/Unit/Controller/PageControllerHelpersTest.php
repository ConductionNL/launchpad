<?php

/**
 * Unit tests for the PageController helpers that shape the workspace initial state.
 *
 * `index()` itself registers scripts through `OCP\Util`, which needs a booted
 * Nextcloud, so the decisions it delegates are exercised here directly.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PageController;
use OCA\LaunchPad\Controller\PageSettingsSources;
use OCA\LaunchPad\Controller\PageSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\PageController
 * @uses   \OCA\LaunchPad\Controller\PageSources
 * @uses   \OCA\LaunchPad\Controller\PageSettingsSources
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 */
class PageControllerHelpersTest extends TestCase {
	private WidgetService&MockObject $widgets;
	private DashboardService&MockObject $dashboards;
	private AdminTemplateService&MockObject $templates;
	private IUserSession&MockObject $session;
	private DashboardTreeService&MockObject $tree;
	private LoggerInterface&MockObject $logger;
	private PageController $controller;

	protected function setUp(): void {
		$this->widgets = $this->createMock(WidgetService::class);
		$this->dashboards = $this->createMock(DashboardService::class);
		$this->templates = $this->createMock(AdminTemplateService::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->tree = $this->createMock(DashboardTreeService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->controller = new PageController(
			request: $this->createMock(IRequest::class),
			sources: new PageSources(
				initialState: $this->createMock(IInitialState::class),
				widgetService: $this->widgets,
				dashboardService: $this->dashboards,
				adminTemplateService: $this->templates
			),
			userSession: $this->session,
			settings: new PageSettingsSources(
				roleFeaturePerm: $this->createMock(RoleFeaturePermissionService::class),
				treeService: $this->tree,
				logger: $this->logger,
				adminSettingsService: $this->createMock(AdminSettingsService::class)
			)
		);
	}

	private function call(string $method, mixed ...$args): mixed {
		return (new ReflectionMethod(PageController::class, $method))->invoke($this->controller, ...$args);
	}

	private function dashboard(int $id, string $uuid, string $name = 'Ops'): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		return $dashboard;
	}

	public function testUserIdIsEmptyWithoutASession(): void {
		$this->session->method('getUser')->willReturn(null);

		$this->assertSame('', $this->call('resolveUserId'));
	}

	public function testUserIdComesFromTheSession(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->session->method('getUser')->willReturn($user);

		$this->assertSame('alice', $this->call('resolveUserId'));
	}

	public function testAnonymousRoutingUsesTheDefaultGroup(): void {
		$this->templates->expects($this->never())->method('resolvePrimaryGroup');
		$this->templates->expects($this->once())
			->method('resolvePrimaryGroupDisplayName')
			->with(Dashboard::DEFAULT_GROUP_ID)
			->willReturn('Default');

		$this->assertSame(['id' => Dashboard::DEFAULT_GROUP_ID, 'name' => 'Default'], $this->call('resolveRoutingGroup', ''));
	}

	public function testUserRoutingUsesTheResolvedPrimaryGroup(): void {
		$this->templates->expects($this->once())->method('resolvePrimaryGroup')->with('alice')->willReturn('sales');
		$this->templates->method('resolvePrimaryGroupDisplayName')
			->willReturnMap([[Dashboard::DEFAULT_GROUP_ID, 'Default'], ['sales', 'Sales team']]);

		$this->assertSame(['id' => 'sales', 'name' => 'Sales team'], $this->call('resolveRoutingGroup', 'alice'));
	}

	public function testDescriptorsSplitUserFromGroupDashboards(): void {
		$visible = [
			['dashboard' => $this->dashboard(1, 'u-1', 'Mine'), 'source' => Dashboard::SOURCE_USER],
			['dashboard' => $this->dashboard(2, 'g-2', 'Team'), 'source' => Dashboard::SOURCE_GROUP],
			['dashboard' => $this->dashboard(3, 's-3', 'Shared'), 'source' => Dashboard::SOURCE_SHARED],
		];

		$this->assertSame([
			'group' => [
				['id' => 'g-2', 'name' => 'Team', 'icon' => '', 'source' => Dashboard::SOURCE_GROUP],
				['id' => 's-3', 'name' => 'Shared', 'icon' => '', 'source' => Dashboard::SOURCE_SHARED],
			],
			'user' => [
				['id' => 'u-1', 'name' => 'Mine', 'icon' => ''],
			],
		], $this->call('splitDescriptors', $visible));
	}

	public function testNoActiveDashboardDescribesAnEmptyGroupWorkspace(): void {
		$this->widgets->expects($this->never())->method('getDashboardPlacements');

		$this->assertSame([
			'activeDashboardId' => '',
			'dashboardSource' => Dashboard::SOURCE_GROUP,
			'layout' => [],
			'deepLinkPath' => '',
		], $this->call('describeActive', null));
	}

	public function testActiveDashboardCarriesItsLayoutAndPath(): void {
		$placement = new WidgetPlacement();
		$placement->setId(70);
		$placement->setWidgetId('notes');
		$this->widgets->expects($this->once())->method('getDashboardPlacements')->with(5)->willReturn([$placement]);
		$this->tree->expects($this->once())->method('computePath')->with('uuid-5')->willReturn('team/ops');

		$state = $this->call('describeActive', ['dashboard' => $this->dashboard(5, 'uuid-5'), 'source' => Dashboard::SOURCE_USER]);

		$this->assertSame('uuid-5', $state['activeDashboardId']);
		$this->assertSame(Dashboard::SOURCE_USER, $state['dashboardSource']);
		$this->assertEquals([$placement->jsonSerialize()], $state['layout']);
		$this->assertSame('notes', $state['layout'][0]['widgetId']);
		$this->assertSame('team/ops', $state['deepLinkPath']);
	}

	public function testPathFailureIsLoggedAndGivesAnEmptyPath(): void {
		$this->tree->method('computePath')->willThrowException(new RuntimeException('cycle'));
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'launchpad: failed to compute path for active dashboard {uuid}: {message}',
				['uuid' => 'uuid-5', 'message' => 'cycle']
			);

		$this->assertSame('', $this->call('computeDeepLinkPath', $this->dashboard(5, 'uuid-5')));
	}

	public function testWithoutDeepLinkTheDefaultResolverPicksTheDashboard(): void {
		$active = ['dashboard' => $this->dashboard(5, 'uuid-5'), 'source' => 'group'];
		$this->tree->expects($this->never())->method('resolvePath');
		$this->dashboards->expects($this->once())
			->method('resolveActiveDashboard')
			->with('alice', 'sales')
			->willReturn($active);

		$this->assertSame($active, $this->call('resolveActive', 'alice', '', 'sales'));
	}

	public function testVisibleDeepLinkWinsOverTheDefaultResolver(): void {
		$target = $this->dashboard(8, 'uuid-8');
		$active = ['dashboard' => $target, 'source' => 'user'];
		$this->tree->expects($this->once())->method('resolvePath')->with('team/ops')->willReturn($target);
		$this->dashboards->expects($this->once())->method('getDashboardForUser')->with(8, 'alice')->willReturn($active);
		$this->dashboards->expects($this->never())->method('resolveActiveDashboard');
		$this->logger->expects($this->never())->method('info');

		$this->assertSame($active, $this->call('resolveActive', 'alice', 'team/ops', 'sales'));
	}

	public function testDeepLinkTheUserCannotSeeFallsBackAndIsLogged(): void {
		$fallback = ['dashboard' => $this->dashboard(5, 'uuid-5'), 'source' => 'group'];
		$this->tree->method('resolvePath')->willReturn($this->dashboard(8, 'uuid-8'));
		$this->dashboards->method('getDashboardForUser')->willReturn(null);
		$this->dashboards->expects($this->once())->method('resolveActiveDashboard')->willReturn($fallback);
		$this->logger->expects($this->once())
			->method('info')
			->with(
				'launchpad: deep-link path "{path}" not visible — falling back to default resolver',
				['path' => 'secret/board']
			);

		$this->assertSame($fallback, $this->call('resolveActive', 'alice', 'secret/board', 'sales'));
	}

	public function testUnknownDeepLinkPathDoesNotLookUpADashboard(): void {
		$this->tree->method('resolvePath')->willReturn(null);
		$this->dashboards->expects($this->never())->method('getDashboardForUser');

		$this->assertNull($this->call('resolveDeepLink', 'alice', 'nowhere'));
	}

	public function testDeepLinkResolutionFailureIsLoggedAndYieldsNull(): void {
		$this->tree->method('resolvePath')->willThrowException(new RuntimeException('bad path'));
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'launchpad: deep-link resolution failed for path "{path}": {message}',
				['path' => '../x', 'message' => 'bad path']
			);

		$this->assertNull($this->call('resolveDeepLink', 'alice', '../x'));
	}

	public function testWorkspacePolicyAllowsTheEmbedAndTileHosts(): void {
		$csp = $this->call('buildWorkspaceCsp');

		$this->assertInstanceOf(ContentSecurityPolicy::class, $csp);
		$policy = $csp->buildPolicy();
		foreach (['https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'] as $frame) {
			$this->assertMatchesRegularExpression('/frame-src[^;]*' . preg_quote($frame, '/') . '/', $policy);
		}
		foreach (['https://i.ytimg.com', 'https://i.vimeocdn.com', 'https://*.tile.openstreetmap.org'] as $image) {
			$this->assertMatchesRegularExpression('/img-src[^;]*' . preg_quote($image, '/') . '/', $policy);
		}
	}
}
