<?php

/**
 * PageControllerIndexTest
 *
 * Unit tests for PageController::index(), the workspace page: the initial
 * state it hands the frontend for a signed-in user and for a guest, which
 * dashboard becomes active (deep link or the resolver), how the visible
 * dashboards are split, and which optional apps are reported enabled.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\DashboardPayloads;
use OCA\LaunchPad\Controller\PageDashboards;
use OCA\LaunchPad\Controller\PageController;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Controller\PageSettingsSources;
use OCA\LaunchPad\Controller\PageSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\CustomCssService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PageFooter;
use OCA\LaunchPad\Service\PublicStartPage;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\SearchShortcutService;
use OCA\LaunchPad\Service\SharingLimits;
use OCA\LaunchPad\Service\WidgetService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\PageController
 * @uses   \OCA\LaunchPad\Controller\AllowedWidgetFilter
 * @uses   \OCA\LaunchPad\Controller\PageSettingsSources
 * @uses   \OCA\LaunchPad\Controller\PageSources
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\InitialStateBuilder
 * @uses   \OCA\LaunchPad\Support\FleetAppId
 */
class PageControllerIndexTest extends TestCase {

	/**
	 * The initial state the page provided, by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $state = [];

	/**
	 * Timezone preference per user.
	 *
	 * @var array<string, string>
	 */
	private array $timezones = [];

	/**
	 * Dashboard service double.
	 *
	 * @var DashboardService&MockObject
	 */
	private $dashboards;

	/**
	 * Widget service double.
	 *
	 * @var WidgetService&MockObject
	 */
	private $widgets;

	/**
	 * Template service double.
	 *
	 * @var AdminTemplateService&MockObject
	 */
	private $templates;

	/**
	 * Role permission double.
	 *
	 * @var RoleFeaturePermissionService&MockObject
	 */
	private $permissions;

	/**
	 * Dashboard tree double.
	 *
	 * @var DashboardTreeService&MockObject
	 */
	private $tree;

	/**
	 * Admin settings double.
	 *
	 * @var AdminSettingsService&MockObject
	 */
	private $adminSettings;

	/**
	 * App manager double.
	 *
	 * @var IAppManager&MockObject
	 */
	private $appManager;

	/**
	 * Config double.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * Logger double.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * Session double.
	 *
	 * @var IUserSession&MockObject
	 */
	private $session;

	/**
	 * Build the doubles with a capturing initial state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboards    = $this->createMock(originalClassName: DashboardService::class);
		$this->widgets       = $this->createMock(originalClassName: WidgetService::class);
		$this->templates     = $this->createMock(originalClassName: AdminTemplateService::class);
		$this->permissions   = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
		$this->tree          = $this->createMock(originalClassName: DashboardTreeService::class);
		$this->adminSettings = $this->createMock(originalClassName: AdminSettingsService::class);
		$this->appManager    = $this->createMock(originalClassName: IAppManager::class);
		$this->config        = $this->createMock(originalClassName: IConfig::class);
		$this->logger        = $this->createMock(originalClassName: LoggerInterface::class);
		$this->session       = $this->createMock(originalClassName: IUserSession::class);

		$this->widgets->method('getWidgetCatalogue')->willReturn([['id' => 'notes'], ['id' => 'calendar']]);
		$this->config->method('getUserValue')->willReturnCallback(
			callback: fn (string $userId, string $app, string $key, mixed $default): mixed => match ([$app, $key]) {
				['core', 'timezone'] => ($this->timezones[$userId] ?? $default),
				default => $default,
			}
		);
		$this->templates->method('resolvePrimaryGroupDisplayName')->willReturnCallback(
			callback: static fn (string $groupId): string => 'Group '.$groupId
		);
	}//end setUp()

	/**
	 * The controller over the doubles, with the asset loading left out.
	 *
	 * @param DashboardPayloads|null $payloads The first-load builder, if the page has one.
	 * @param SharingLimits|null     $sharing  The sharing policy, if the page has one.
	 * @param PageFooter|null        $footer    The footer source, if the page has one.
	 * @param AnalyticsService|null  $analytics The view analytics setting, if the page has one.
	 *
	 * @return PageController
	 */
	private function controller(
		?DashboardPayloads $payloads=null,
		?SharingLimits $sharing=null,
		?PageFooter $footer=null,
		?AnalyticsService $analytics=null
	): PageController {
		$initialState = $this->createMock(originalClassName: IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			callback: function (string $key, mixed $value): void {
				$this->state[$key] = json_decode((string) json_encode($value), true);
			}
		);
		$css = $this->createMock(originalClassName: CustomCssService::class);
		$css->method('getGlobalCss')->willReturn('.x{}');

		return new class(
			request: $this->createMock(originalClassName: IRequest::class),
			sources: new PageSources(
				initialState: $initialState,
				widgetService: $this->widgets,
				dashboards: new PageDashboards(dashboardService: $this->dashboards, payloads: $payloads, sharing: $sharing, footer: $footer),
				adminTemplateService: $this->templates
			),
			userSession: $this->session,
			settings: new PageSettingsSources(
				roleFeaturePerm: $this->permissions,
				treeService: $this->tree,
				logger: $this->logger,
				adminSettingsService: $this->adminSettings,
				publicStartPage: $this->createMock(originalClassName: PublicStartPage::class),
				urlGenerator: $this->createMock(originalClassName: IURLGenerator::class),
				searchShortcuts: $this->createMock(originalClassName: SearchShortcutService::class),
				customCss: $css,
				appManager: $this->appManager,
				config: $this->config,
				analytics: $analytics
			)
		) extends PageController {
			/**
			 * The scripts the page asked for.
			 *
			 * @var list<string>
			 */
			public array $scripts = [];

			/**
			 * Asset loading needs a running Nextcloud, so the script is only recorded.
			 *
			 * @param string $script The script.
			 *
			 * @return void
			 */
			protected function addAssets(string $script): void {
				$this->scripts[] = $script;
			}
		};
	}//end controller()

	/**
	 * Sign in as the given user.
	 *
	 * @param string $uid The user id.
	 *
	 * @return void
	 */
	private function signIn(string $uid): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->session->method('getUser')->willReturn($user);
	}//end signIn()

	/**
	 * A dashboard with an id, uuid and name.
	 *
	 * @param int    $id   The id.
	 * @param string $uuid The uuid.
	 * @param string $name The name.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(int $id, string $uuid, string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		return $dashboard;
	}//end dashboard()

	/**
	 * A guest gets the full catalogue, the default group and no dashboards, without any user lookup.
	 *
	 * @return void
	 */
	public function testAGuestGetsTheDefaultPage(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->adminSettings->method('getSettings')->willReturn([]);
		$this->dashboards->expects($this->never())->method('isAdmin');
		$this->dashboards->expects($this->never())->method('getVisibleToUser');
		$this->permissions->expects($this->never())->method('getAllowedWidgetIds');

		$controller = $this->controller();
		$response   = $controller->index();

		$this->assertSame(expected: ['launchpad-main'], actual: $controller->scripts);
		$this->assertSame(expected: 'index', actual: $response->getTemplateName());
		$this->assertSame(expected: TemplateResponse::RENDER_AS_USER, actual: $response->getRenderAs());
		$this->assertSame(expected: [['id' => 'notes'], ['id' => 'calendar']], actual: $this->state['widgets']);
		$this->assertSame(expected: Dashboard::DEFAULT_GROUP_ID, actual: $this->state['primaryGroup']);
		$this->assertSame(expected: 'Group '.Dashboard::DEFAULT_GROUP_ID, actual: $this->state['primaryGroupName']);
		$this->assertFalse(condition: $this->state['isAdmin']);
		$this->assertSame(expected: '', actual: $this->state['activeDashboardId']);
		$this->assertSame(expected: Dashboard::SOURCE_GROUP, actual: $this->state['dashboardSource']);
		$this->assertSame(expected: [], actual: $this->state['layout']);
		$this->assertNull(actual: $this->state['allowedWidgets']);
		$this->assertSame(expected: '', actual: $this->state['userTimezone']);
		$this->assertSame(
			expected: ['openregister' => false, 'integration_openproject' => false, 'dossiq' => false, 'integriq' => false],
			actual: $this->state['integrationApps']
		);
		$this->assertSame(expected: AdminSettingsService::DEFAULT_QUICKSEARCH_FALLBACK_TARGET, actual: $this->state['quicksearchFallbackTarget']);
	}//end testAGuestGetsTheDefaultPage()

	/**
	 * The page carries whether view analytics is on, so it sends no view-event
	 * while the administrator switched it off; without the service it stays on.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function testThePageCarriesWhetherViewAnalyticsIsOn(): void {
		$this->signIn(uid: 'pieter');
		$analytics = $this->createMock(originalClassName: AnalyticsService::class);
		$analytics->method('isGloballyEnabled')->willReturn(false);

		$this->controller(analytics: $analytics)->index();
		$this->assertFalse(condition: $this->state['analyticsEnabled']);

		$this->controller()->index();
		$this->assertTrue(condition: $this->state['analyticsEnabled']);
	}//end testThePageCarriesWhetherViewAnalyticsIsOn()

	/**
	 * A signed-in user gets their group, their allowed widgets, the active dashboard's permitted layout and both dashboard lists.
	 *
	 * @return void
	 */
	public function testASignedInUserGetsTheirWorkspace(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn(['quicksearchFallbackTarget' => 'unified-search', 'filterTelemetryEnabled' => true]);
		$this->permissions->method('getAllowedWidgetIds')->with('pieter')->willReturn(['notes']);
		$this->templates->method('resolvePrimaryGroup')->with('pieter')->willReturn('medewerkers');
		$this->dashboards->method('isAdmin')->with('pieter')->willReturn(true);

		$active = self::dashboard(id: 7, uuid: 'uuid-7', name: 'Mijn werkdag');
		$this->dashboards->expects($this->once())->method('resolveActiveDashboard')->with('pieter', 'medewerkers')
			->willReturn(['dashboard' => $active, 'source' => Dashboard::SOURCE_USER]);
		$this->dashboards->method('getVisibleToUser')->willReturn([
			['dashboard' => $active, 'source' => Dashboard::SOURCE_USER],
			['dashboard' => self::dashboard(id: 8, uuid: 'uuid-8', name: 'Team'), 'source' => Dashboard::SOURCE_GROUP],
		]);

		$placement = new WidgetPlacement();
		$placement->setId(31);
		$placement->setWidgetId('notes');
		$hidden = new WidgetPlacement();
		$hidden->setId(32);
		$hidden->setWidgetId('calendar');
		$this->widgets->method('getDashboardPlacements')->with(7)->willReturn([$placement, $hidden]);
		$this->permissions->method('hideUnpermittedPlacements')->willReturnCallback(
			callback: static fn (array $placements, string $userId): array => array_values(
				array_filter($placements, static fn (array $row): bool => $row['widgetId'] === 'notes')
			)
		);
		$this->tree->method('computePath')->with('uuid-7')->willReturn('mijn-werkdag');
		$this->timezones['pieter'] = 'Europe/Amsterdam';

		$this->controller()->index();

		$this->assertSame(expected: [['id' => 'notes']], actual: $this->state['widgets']);
		$this->assertSame(expected: ['notes'], actual: $this->state['allowedWidgets']);
		$this->assertSame(expected: 'medewerkers', actual: $this->state['primaryGroup']);
		$this->assertSame(expected: 'Group medewerkers', actual: $this->state['primaryGroupName']);
		$this->assertTrue(condition: $this->state['isAdmin']);
		$this->assertSame(expected: 'uuid-7', actual: $this->state['activeDashboardId']);
		$this->assertSame(expected: Dashboard::SOURCE_USER, actual: $this->state['dashboardSource']);
		$this->assertSame(expected: [31], actual: array_column($this->state['layout'], 'id'));
		$this->assertSame(expected: 'mijn-werkdag', actual: $this->state['deepLinkPath']);
		$this->assertSame(expected: [['id' => 'uuid-7', 'name' => 'Mijn werkdag', 'icon' => '']], actual: $this->state['userDashboards']);
		$this->assertSame(
			expected: [['id' => 'uuid-8', 'name' => 'Team', 'icon' => '', 'source' => Dashboard::SOURCE_GROUP]],
			actual: $this->state['groupDashboards']
		);
		$this->assertSame(expected: 'unified-search', actual: $this->state['quicksearchFallbackTarget']);
		$this->assertTrue(condition: $this->state['filterTelemetryEnabled']);
		$this->assertSame(expected: 'Europe/Amsterdam', actual: $this->state['userTimezone']);
		$this->assertSame(expected: '.x{}', actual: $this->state['customCss']);
	}//end testASignedInUserGetsTheirWorkspace()

	/**
	 * Without the first-load builder the page carries no first load, so the
	 * store asks the API as before.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-4
	 */
	public function testWithoutTheBuilderThereIsNoFirstLoad(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);

		$this->controller()->index();

		$this->assertArrayNotHasKey(key: 'dashboardFirstLoad', array: $this->state);
	}//end testWithoutTheBuilderThereIsNoFirstLoad()

	/**
	 * The page carries the visible list and the active dashboard the store
	 * would fetch first, built as the API builds them.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-4
	 */
	public function testTheWorkspaceCarriesTheFirstDashboardLoad(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);
		$active = self::dashboard(id: 7, uuid: 'uuid-7', name: 'Mijn werkdag');
		$this->dashboards->method('resolveActiveDashboard')
			->willReturn(['dashboard' => $active, 'source' => Dashboard::SOURCE_USER]);
		$this->dashboards->method('getVisibleToUser')->willReturn([
			['dashboard' => $active, 'source' => Dashboard::SOURCE_USER],
		]);
		$this->dashboards->method('getEffectiveDashboard')->with('pieter')->willReturn([
			'dashboard' => $active,
			'placements' => [],
			'permissionLevel' => Dashboard::PERMISSION_FULL,
		]);
		$payloads = new DashboardPayloads(
			services: new DashboardApiServices(dashboardService: $this->dashboards),
			actor: new RequestActor(
				userSession: $this->session,
				actionAuth: $this->createMock(originalClassName: ActionAuthService::class)
			)
		);

		$this->controller(payloads: $payloads)->index();

		$firstLoad = $this->state['dashboardFirstLoad'];
		$this->assertSame(expected: ['uuid-7'], actual: array_column($firstLoad['visible'], 'uuid'));
		$this->assertSame(expected: Dashboard::SOURCE_USER, actual: $firstLoad['visible'][0]['source']);
		$this->assertSame(expected: 'uuid-7', actual: $firstLoad['active']['dashboard']['uuid']);
		$this->assertSame(expected: Dashboard::PERMISSION_FULL, actual: $firstLoad['active']['permissionLevel']);
	}//end testTheWorkspaceCarriesTheFirstDashboardLoad()

	/**
	 * REQ-SHARE-015: the page says whether the viewer may share and whether
	 * public links are on; without the policy it offers both.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function testTheWorkspaceCarriesTheSharingPolicy(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);

		$this->controller()->index();
		$this->assertSame(expected: ['mayShare' => true, 'publicLinksAllowed' => true], actual: $this->state['sharingPolicy']);

		$sharing = $this->createMock(originalClassName: SharingLimits::class);
		$sharing->method('mayShare')->with('pieter')->willReturn(false);
		$sharing->method('publicLinksAllowed')->willReturn(false);
		$this->controller(sharing: $sharing)->index();
		$this->assertSame(expected: ['mayShare' => false, 'publicLinksAllowed' => false], actual: $this->state['sharingPolicy']);
	}//end testTheWorkspaceCarriesTheSharingPolicy()

	/**
	 * REQ-FTR-004 / REQ-FTR-007: the page carries the footer of the active
	 * dashboard and the viewer's language; without the resolver, no footer.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function testTheWorkspaceCarriesTheFooterAndTheViewersLanguage(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);

		$this->controller()->index();
		$this->assertNull(actual: $this->state['effectiveFooter']);
		$this->assertSame(expected: 'en', actual: $this->state['viewerLocale']);

		$footer = $this->createMock(originalClassName: PageFooter::class);
		$footer->method('footerFor')->willReturn(['mode' => 'global', 'html' => '<p>F</p>']);
		$footer->method('viewerLocale')->with('pieter')->willReturn('nl');
		$this->controller(footer: $footer)->index();
		$this->assertSame(expected: ['mode' => 'global', 'html' => '<p>F</p>'], actual: $this->state['effectiveFooter']);
		$this->assertSame(expected: 'nl', actual: $this->state['viewerLocale']);
	}//end testTheWorkspaceCarriesTheFooterAndTheViewersLanguage()

	/**
	 * The legacy widget bridge switches the catalogue to every available widget.
	 *
	 * @return void
	 */
	public function testTheLegacyBridgeUsesTheAvailableWidgets(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->adminSettings->method('getSettings')->willReturn(['legacyWidgetBridgeEnabled' => true]);
		$this->widgets->method('getAvailableWidgets')->willReturn([['id' => 'bridged']]);

		$this->controller()->index();

		$this->assertSame(expected: [['id' => 'bridged']], actual: $this->state['widgets']);
	}//end testTheLegacyBridgeUsesTheAvailableWidgets()

	/**
	 * A deep link the user can read becomes the active dashboard, and the resolver is not asked.
	 *
	 * @return void
	 */
	public function testAReadableDeepLinkWins(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);
		$this->templates->method('resolvePrimaryGroup')->willReturn('medewerkers');
		$linked = self::dashboard(id: 9, uuid: 'uuid-9', name: 'Projecten');
		$this->tree->method('resolvePath')->with('team/projecten')->willReturn($linked);
		$this->dashboards->method('getDashboardForUser')->with(9, 'pieter')->willReturn(['dashboard' => $linked, 'source' => Dashboard::SOURCE_GROUP]);
		$this->dashboards->expects($this->never())->method('resolveActiveDashboard');
		$this->widgets->method('getDashboardPlacements')->willReturn([]);
		$this->permissions->method('hideUnpermittedPlacements')->willReturnArgument(0);
		$this->tree->method('computePath')->willReturn('team/projecten');

		$this->controller()->deepLink(deepLink: 'team/projecten');

		$this->assertSame(expected: 'uuid-9', actual: $this->state['activeDashboardId']);
		$this->assertSame(expected: 'team/projecten', actual: $this->state['deepLinkPath']);
	}//end testAReadableDeepLinkWins()

	/**
	 * A deep link that does not resolve, or that throws, falls back to the resolver with a log line.
	 *
	 * @return void
	 */
	public function testABrokenDeepLinkFallsBackToTheResolver(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);
		$this->templates->method('resolvePrimaryGroup')->willReturn('medewerkers');
		$broken = $this->throwException(exception: new RuntimeException(message: 'tree broken'));
		$this->tree->method('resolvePath')->willReturnOnConsecutiveCalls(null, $broken);
		$this->dashboards->expects($this->exactly(count: 2))->method('resolveActiveDashboard')->willReturn(null);
		$this->logger->expects($this->once())->method('warning');
		$this->logger->expects($this->exactly(count: 2))->method('info');

		$this->controller()->index(deepLink: 'weg');
		$this->assertSame(expected: '', actual: $this->state['activeDashboardId']);

		$this->controller()->index(deepLink: 'kapot');
		$this->assertSame(expected: '', actual: $this->state['activeDashboardId']);
	}//end testABrokenDeepLinkFallsBackToTheResolver()

	/**
	 * A path that cannot be computed leaves the deep-link path empty and logs a warning.
	 *
	 * @return void
	 */
	public function testAnUncomputablePathIsEmpty(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);
		$this->templates->method('resolvePrimaryGroup')->willReturn('medewerkers');
		$this->dashboards->method('resolveActiveDashboard')->willReturn(
			['dashboard' => self::dashboard(id: 7, uuid: 'uuid-7', name: 'X'), 'source' => Dashboard::SOURCE_GROUP]
		);
		$this->widgets->method('getDashboardPlacements')->willReturn([]);
		$this->permissions->method('hideUnpermittedPlacements')->willReturnArgument(0);
		$this->tree->method('computePath')->willThrowException(new RuntimeException(message: 'cycle'));
		$this->logger->expects($this->once())->method('warning');

		$this->controller()->index();

		$this->assertSame(expected: 'uuid-7', actual: $this->state['activeDashboardId']);
		$this->assertSame(expected: '', actual: $this->state['deepLinkPath']);
	}//end testAnUncomputablePathIsEmpty()

	/**
	 * Talk and the integrations are reported per app, using the installed id of a renamed fleet app.
	 *
	 * @return void
	 */
	public function testEnabledAppsAreReportedForTheViewer(): void {
		$this->signIn(uid: 'pieter');
		$this->adminSettings->method('getSettings')->willReturn([]);
		$this->templates->method('resolvePrimaryGroup')->willReturn('medewerkers');
		$this->appManager->method('isInstalled')->willReturnCallback(callback: static fn (string $app): bool => $app === 'procest');
		$this->appManager->method('isEnabledForUser')->willReturnCallback(
			callback: static function (string $appId): bool {
				if ($appId === 'integration_openproject') {
					throw new RuntimeException(message: 'app store down');
				}

				return in_array($appId, ['spreed', 'procest', 'integriq'], true);
			}
		);

		$this->controller()->index();

		$this->assertTrue(condition: $this->state['talkEnabled']);
		$this->assertSame(
			expected: ['openregister' => false, 'integration_openproject' => false, 'dossiq' => 'procest', 'integriq' => true],
			actual: $this->state['integrationApps']
		);
	}//end testEnabledAppsAreReportedForTheViewer()

	/**
	 * The workspace allows the video and map hosts its widgets embed.
	 *
	 * @return void
	 */
	public function testTheWorkspaceAllowsTheEmbeddedMediaHosts(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->adminSettings->method('getSettings')->willReturn([]);

		$policy = $this->controller()->index()->getContentSecurityPolicy()->buildPolicy();

		$this->assertStringContainsString(needle: 'https://www.youtube-nocookie.com', haystack: $policy);
		$this->assertStringContainsString(needle: 'https://player.vimeo.com', haystack: $policy);
		$this->assertStringContainsString(needle: 'https://*.tile.openstreetmap.org', haystack: $policy);
	}//end testTheWorkspaceAllowsTheEmbeddedMediaHosts()
}//end class
