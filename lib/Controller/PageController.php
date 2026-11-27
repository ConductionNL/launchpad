<?php

/**
 * PageController
 *
 * Controller for rendering the main LaunchPad workspace page (REQ-INIT-001,
 * REQ-INIT-002). The page-render path constructs an
 * {@see \OCA\LaunchPad\Service\InitialStateBuilder} for
 * {@see \OCA\LaunchPad\Service\InitialState\Page::WORKSPACE}, populates every
 * key declared in the spec's Data Model, and applies — direct calls to
 * {@see \OCP\AppFramework\Services\IInitialState::provideInitialState()}
 * are forbidden here (and any other controller) and enforced by the
 * `lint:initial-state` CI guard.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\InitialState\Page;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCA\LaunchPad\Support\FleetAppId;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;
use Throwable;

/**
 * Workspace page controller — wires the typed initial-state contract.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Boot path needs widget,
 *                                                  dashboard, settings and
 *                                                  group services to fill
 *                                                  the contract.
 */
class PageController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest            $request The request.
	 * @param PageSources         $sources The initial state, widget, dashboard and admin template services.
	 * @param IUserSession        $userSession Active user session.
	 * @param PageSettingsSources $settings The role feature, tree and admin settings services and the logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly PageSources $sources,
		private readonly IUserSession $userSession,
		private readonly PageSettingsSources $settings,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Deep-link entry point — `/apps/launchpad/{deepLink}`.
	 *
	 * Symfony binds the captured slug-chain into `$deepLink`. Delegating
	 * to {@see self::index()} keeps the workspace render path single-
	 * sourced; the optional path argument merely overrides the active
	 * dashboard before initial-state assembly.
	 *
	 * @param string $deepLink Slug-chain captured from the URL (may
	 *                         contain `/` separators).
	 *
	 * @return TemplateResponse The workspace template response.
	 *
	 * @spec openspec/specs/runtime-shell/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deepLink(string $deepLink = ''): TemplateResponse {
		return $this->index(deepLink: $deepLink);
	}//end deepLink()

	/**
	 * Render the workspace page.
	 *
	 * Wires the full workspace initial-state contract into the template via
	 * {@see InitialStateBuilder}. Every key declared in REQ-INIT-002 is set
	 * before `apply()` runs; missing keys raise
	 * {@see \OCA\LaunchPad\Exception\MissingInitialStateException} so the page
	 * never renders with a partial payload.
	 *
	 * Deep-link path: when `$deepLink` resolves through the tree service
	 * to a dashboard the user can read, that dashboard is used as the
	 * active one (overriding the resolver's seven-step fallback). When
	 * the path doesn't resolve (renamed, deleted, never existed, or not
	 * visible to the caller), the controller logs a warning and falls
	 * back silently — bookmarks of stale slug chains still land on
	 * something instead of 404'ing.
	 *
	 * @param string $deepLink Optional slug-chain selecting the active
	 *                         dashboard. Empty string ⇒ default resolver.
	 *
	 * @return TemplateResponse The template response.
	 *
	 * @spec openspec/specs/runtime-shell/spec.md
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-25-org-navigation-from-initial-state/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-25-manifest-cached-per-user/tasks.md#task-2
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(string $deepLink = ''): TemplateResponse {
		$this->addAssets(script: 'launchpad-main');

		// Settings are read once and reused below; getSettings() resolves every
		// safe default, so neither read throws on an unset key.
		$settings = $this->settings->adminSettingsService->getSettings();
		$userId = $this->resolveUserId();
		$allowedWidgets = $this->allowedWidgets(userId: $userId);
		$widgets = AllowedWidgetFilter::permitted(
			widgets: $this->resolveWidgetCatalogue(settings: $settings),
			allowed: $allowedWidgets
		);

		$routingGroup = $this->resolveRoutingGroup(userId: $userId);

		$isAdmin = false;
		$visible = [];
		$active = null;
		if ($userId !== '') {
			$isAdmin = $this->sources->dashboards->dashboardService->isAdmin(userId: $userId);
			// Active first: resolving it can hand a first-time user their copy
			// of an admin template (REQ-TMPL-019), and the list read after it
			// must hold that copy or the switcher opens without it.
			$active = $this->resolveActive(
				userId: $userId,
				deepLink: $deepLink,
				primaryGroupId: $routingGroup['id']
			);
			$visible = $this->sources->dashboards->dashboardService->getVisibleToUser(userId: $userId);
		}

		$descriptors = $this->splitDescriptors(visible: $visible);
		$activeState = $this->describeActive(active: $active, userId: $userId);

		$builder = new InitialStateBuilder(
			initialState: $this->sources->initialState,
			page: Page::WORKSPACE
		);

		$builder
			->setWidgets($widgets)
			->setLayout($activeState['layout'])
			->setPrimaryGroup($routingGroup['id'])
			->setPrimaryGroupName($routingGroup['name'])
			->setIsAdmin($isAdmin)
			->setActiveDashboardId($activeState['activeDashboardId'])
			->setDashboardSource($activeState['dashboardSource'])
			->setGroupDashboards($descriptors['group'])
			->setUserDashboards($descriptors['user'])
			->setAllowUserDashboards($this->sources->dashboards->dashboardService->getAllowUserDashboards());

		// Tile-quick-search REQ-QSEARCH-004: the admin-configured no-match
		// fallback target; `getSettings()` already resolves the safe 'none'
		// default when unset/invalid, so this never throws.
		$builder
			->setAllowedWidgets($allowedWidgets)
			->setDeepLinkPath($activeState['deepLinkPath'])
			->setQuicksearchFallbackTarget((string)($settings['quicksearchFallbackTarget'] ?? AdminSettingsService::DEFAULT_QUICKSEARCH_FALLBACK_TARGET))
			->setSearchShortcuts($this->settings->searchShortcuts->getShortcuts())
			->setCustomCss($this->settings->customCss?->getGlobalCss() ?? '')
			->setTelemetry(filters: ($settings['filterTelemetryEnabled'] ?? false) === true, views: $this->settings->analytics?->isGloballyEnabled() ?? true)
			->setSharingPolicy(...$this->sources->dashboards->sharingPolicy(userId: $userId))
			->setTileLaunchOptions(schemes: (array)($settings['tileAllowedSchemes'] ?? []), templates: (array)($settings['ssoLaunchTemplates'] ?? []))
			->setOnOfficeNetwork(($settings['currentAddressInOfficeNetworks'] ?? false) === true)
			->setTalkEnabled($this->isAppEnabledForViewer(appId: 'spreed'))
			->setIntegrationApps($this->integrationApps())
			->setUserTimezone($this->userTimezone(userId: $userId))
			->setFooter(...$this->sources->dashboards->footerState(dashboard: $active['dashboard'] ?? null, userId: $userId))
			->setPinnedCommunities($settings['pinnedCommunities'] ?? [])
			->setFavouriteDashboards($this->favouriteDashboards(userId: $userId, visible: $visible))
			->setDashboardFirstLoad($this->sources->dashboards->payloads?->firstLoad(userId: $userId, visible: $visible))
			->setOrgNavigation($this->settings->orgNavigation?->firstLoad(userId: $userId))
			->setWorkspaceBootReads($this->sources->bootReads?->firstLoad(userId: $userId))
			->setShellReads(reads: $this->sources->shell->firstLoad(userId: $userId))
			->setWorkspaceApiReads($this->sources->dashboards->payloads?->apiReads?->firstLoad(userId: $userId, opening: ($active ?? []) + $activeState))
			->apply();

		// 🔴 NO CHROME SLOT IDS. This used to pass
		// `'id-app-navigation' => null` (REQ-SHELL-001), which suppressed
		// Nextcloud's left navigation panel because the app rendered its own
		// slide-in sidebar and nothing else.
		//
		// `launchpad-manifest-tier-3` roots the app on `CnAppRoot`, which
		// renders `NcContent` — and `NcContent` allocates that panel for
		// `CnAppNav` itself. Suppressing it here would leave the shared
		// chrome, ADR-114's four footer destinations included, with nowhere to
		// render: not an error, an empty rail. Every other app in the fleet
		// passes no slot ids and lets `NcContent` do it.
		//
		// The slide-in sidebar is unaffected; it lives inside the workspace
		// view, not in the chrome slot.
		return $this->workspaceResponse();
	}//end index()

	/**
	 * The viewer's favourite dashboards, checked against the dashboards the
	 * page already read, so a gone or hidden one drops out without another
	 * query (more-menu-favourites, REQ-SHELL-014).
	 *
	 * @param string $userId  The viewer, or '' when nobody is logged in.
	 * @param array  $visible The entries of getVisibleToUser().
	 *
	 * @return array<int, string> The favourite uuids in starring order.
	 *
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-2
	 */
	private function favouriteDashboards(string $userId, array $visible): array {
		if ($userId === '' || $this->sources->favourites === null) {
			return [];
		}

		$uuids = [];
		foreach ($visible as $entry) {
			$uuids[] = (string) $entry['dashboard']->getUuid();
		}

		return $this->sources->favourites->listFor(userId: $userId, visibleUuids: $uuids);
	}//end favouriteDashboards()

	/**
	 * The workspace page, without the Nextcloud layout for `kiosk=1`
	 * (REQ-KIOSK-001).
	 *
	 * @return TemplateResponse The page.
	 */
	private function workspaceResponse(): TemplateResponse {
		$response = new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'index',
			params: [],
			renderAs: $this->kioskRenderAs(default: TemplateResponse::RENDER_AS_USER)
		);
		$response->setContentSecurityPolicy(csp: $this->buildWorkspaceCsp());

		return $response;
	}//end workspaceResponse()

	/**
	 * The per-user widget allow-list (PR #95, role-based-content).
	 *
	 * @param string $userId The user, or '' when nobody is logged in.
	 *
	 * @return array|null The allowed widget ids, or null when no admin policy
	 *                    applies to this user (unlimited).
	 */
	private function allowedWidgets(string $userId): ?array {
		if ($userId === '') {
			return null;
		}

		return $this->settings->roleFeaturePerm->getAllowedWidgetIds(
			userId: $userId
		);
	}//end allowedWidgets()

	/**
	 * The Nextcloud dashboard widgets the workspace page carries in its
	 * `widgets` initial state, so the card library lists them without a
	 * `GET /api/widgets` call (REQ-RFP-010).
	 *
	 * `IManager::getWidgets()` is not a getter: it calls loadLazyPanels(),
	 * which calls load() on EVERY widget of every app enabled for the user,
	 * and load() is where widgets call `Util::addScript()`. Measured on this
	 * instance that is ~147 MB of widget JS injected into a page rendering no
	 * Nextcloud dashboard, every one of which then throws because
	 * `OCA.Dashboard` exists only on `/apps/dashboard`.
	 *
	 * So the registry is loaded only when the legacy widget bridge is on and
	 * needs those scripts. When it is off the widgets are read without loading
	 * them; when even that is not possible the list is empty and the card
	 * library falls back to `GET /api/widgets`.
	 *
	 * @param array $settings Resolved admin settings (see AdminSettingsService).
	 *
	 * @return array The widget descriptors, not yet narrowed to the user's role.
	 *
	 * @spec openspec/specs/runtime-shell/spec.md
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
	 */
	private function resolveWidgetCatalogue(array $settings): array {
		$bridgeEnabled = (bool)($settings['legacyWidgetBridgeEnabled'] ?? false);
		if ($bridgeEnabled === true) {
			return $this->sources->widgetService->getAvailableWidgets();
		}

		return $this->sources->widgetService->getWidgetCatalogue() ?? [];
	}//end resolveWidgetCatalogue()

	/**
	 * Resolve the primary group this request routes through.
	 *
	 * Routing resolver — REQ-TMPL-012 / REQ-TMPL-013. The
	 * `AdminTemplateService` walks the admin-configured `group_order`
	 * priority list and returns the first group the user belongs to, OR
	 * the literal `'default'` sentinel when nothing matches. The display
	 * name comes from the same service so the lookup lives in exactly one
	 * place.
	 *
	 * @param string $userId The caller's uid, or '' when anonymous.
	 *
	 * @return array{id: string, name: string} The group id and its
	 *                                         display name.
	 */
	private function resolveRoutingGroup(string $userId): array {
		$primaryGroupId = Dashboard::DEFAULT_GROUP_ID;
		$primaryGroupName = $this->sources->adminTemplateService->resolvePrimaryGroupDisplayName(
			groupId: Dashboard::DEFAULT_GROUP_ID
		);
		if ($userId !== '') {
			$primaryGroupId = $this->sources->adminTemplateService->resolvePrimaryGroup(
				userId: $userId
			);
			$primaryGroupName = $this->sources->adminTemplateService->resolvePrimaryGroupDisplayName(
				groupId: $primaryGroupId
			);
		}

		return [
			'id' => $primaryGroupId,
			'name' => $primaryGroupName,
		];
	}//end resolveRoutingGroup()

	/**
	 * The viewer's Nextcloud timezone, which a clock without its own zone
	 * shows (REQ-CLOCK-002), or '' when it is unknown.
	 *
	 * @param string $userId The user, or '' when nobody is logged in.
	 *
	 * @return string The IANA timezone name, or ''.
	 *
	 * @spec openspec/specs/clock-weather-widgets/spec.md
	 */
	private function userTimezone(string $userId): string {
		if ($userId === '' || $this->settings->config === null) {
			return '';
		}

		return $this->settings->config->getUserValue(userId: $userId, appName: 'core', key: 'timezone', default: '');
	}//end userTimezone()

	/**
	 * Which apps behind the integration widgets are enabled for the
	 * signed-in user, by app id (wiki-widget REQ-WIKI-004).
	 *
	 * @return array<string, bool|string> Whether each app is enabled, or the id it is installed under.
	 *
	 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-picker-offers-the-wiki-only-where-it-can-work-req-wiki-004
	 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
	 */
	private function integrationApps(): array {
		return [
			'openregister'            => $this->isAppEnabledForViewer(appId: 'openregister'),
			'integration_openproject' => $this->isAppEnabledForViewer(appId: 'integration_openproject'),
			'dossiq'                  => $this->enabledFleetAppId(canonical: 'dossiq'),
			'integriq'                => $this->isAppEnabledForViewer(appId: 'integriq'),
		];
	}//end integrationApps()

	/**
	 * Whether an app is enabled for the signed-in user. Talk and the apps
	 * behind the integration widgets are optional: when one is absent,
	 * disabled for this user, or the check fails, its widget explains itself
	 * or stays out of the picker (engagement-communities REQ-COMM-004,
	 * wiki-widget REQ-WIKI-004).
	 *
	 * @param string $appId The app id.
	 *
	 * @return bool True when the app is enabled for the viewer.
	 *
	 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-without-talk-the-widget-explains-itself-req-comm-004
	 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-picker-offers-the-wiki-only-where-it-can-work-req-wiki-004
	 */
	private function isAppEnabledForViewer(string $appId): bool {
		$user = $this->userSession->getUser();
		if ($user === null || $this->settings->appManager === null) {
			return false;
		}

		try {
			return $this->settings->appManager->isEnabledForUser(appId: $appId, user: $user);
		} catch (Throwable) {
			return false;
		}
	}//end isAppEnabledForViewer()

	/**
	 * The id under which a renamed fleet app is installed and enabled for the
	 * signed-in user, or false. The app is looked up by every name it has had
	 * ({@see FleetAppId}), never by one spelling (assigned-cases-widget REQ-CASES-004).
	 *
	 * @param string $canonical The app's current name.
	 *
	 * @return string|false The installed id, or false.
	 *
	 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-is-offered-only-with-dossiq-req-cases-004
	 */
	private function enabledFleetAppId(string $canonical): string|false {
		if ($this->settings->appManager === null) {
			return false;
		}

		$installed = FleetAppId::resolve(appManager: $this->settings->appManager, canonical: $canonical);
		if ($installed === null || $this->isAppEnabledForViewer(appId: $installed) === false) {
			return false;
		}

		return $installed;
	}//end enabledFleetAppId()

	/**
	 * Resolve the active session's user id.
	 *
	 * @return string The uid, or an empty string for an anonymous caller.
	 */
	private function resolveUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}

		return $user->getUID();
	}//end resolveUserId()

	/**
	 * Split the visible dashboards into the group and user descriptor
	 * lists the initial-state contract expects (REQ-INIT-002).
	 *
	 * User-sourced entries drop the `source` key — the frontend infers it
	 * from the list the descriptor arrived in.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The visible entries.
	 *
	 * @return array{group: list<array<string, string>>, user: list<array<string, string>>}
	 */
	private function splitDescriptors(array $visible): array {
		$groupDashboards = [];
		$userDashboards = [];
		foreach ($visible as $entry) {
			$dashboard = $entry['dashboard'];
			// Dashboard entity has no icon column today — surface an empty
			// string so the frontend descriptor shape matches REQ-INIT-002.
			$descriptor = [
				'id' => (string)$dashboard->getUuid(),
				'name' => (string)$dashboard->getName(),
				'icon' => '',
				'source' => $entry['source'],
			];

			if ($entry['source'] === Dashboard::SOURCE_USER) {
				unset($descriptor['source']);
				$userDashboards[] = $descriptor;
				continue;
			}

			$groupDashboards[] = $descriptor;
		}

		return [
			'group' => $groupDashboards,
			'user' => $userDashboards,
		];
	}//end splitDescriptors()

	/**
	 * Resolve the dashboard that should open for this request.
	 *
	 * Deep-link override: when the URL carries a slug-chain we try to land
	 * the user on that dashboard before consulting the seven-step
	 * resolver. Failures (path doesn't resolve, not visible, throws) are
	 * swallowed so a stale bookmark still opens *something* instead of
	 * breaking.
	 *
	 * @param string $userId The caller's uid (never empty here).
	 * @param string $deepLink The slug-chain from the URL, or ''.
	 * @param string $primaryGroupId The resolved primary group id.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null The active
	 *                                                          entry, or
	 *                                                          null.
	 */
	private function resolveActive(
		string $userId,
		string $deepLink,
		string $primaryGroupId,
	): ?array {
		$active = null;
		if ($deepLink !== '') {
			$active = $this->resolveDeepLink(userId: $userId, deepLink: $deepLink);
		}

		if ($active !== null) {
			return $active;
		}

		return $this->sources->dashboards->dashboardService->resolveActiveDashboard(
			userId: $userId,
			primaryGroupId: $primaryGroupId
		);
	}//end resolveActive()

	/**
	 * Resolve a slug-chain to a dashboard the caller may read.
	 *
	 * Every failure mode — unresolvable path, dashboard not visible, or a
	 * throwing resolver — returns null after logging, so the caller can
	 * fall back to the default resolver.
	 *
	 * @param string $userId The caller's uid.
	 * @param string $deepLink The slug-chain from the URL.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null The matched
	 *                                                          entry, or
	 *                                                          null.
	 */
	private function resolveDeepLink(string $userId, string $deepLink): ?array {
		$active = null;
		try {
			$resolved = $this->settings->treeService->resolvePath(path: $deepLink);
			if ($resolved !== null) {
				$active = $this->sources->dashboards->dashboardService->getDashboardForUser(
					dashboardId: $resolved->getId(),
					userId: $userId
				);
			}
		} catch (Throwable $t) {
			$this->settings->logger->warning(
				message: 'launchpad: deep-link resolution failed for path "{path}": {message}',
				context: [
					'path' => $deepLink,
					'message' => $t->getMessage(),
				]
			);
		}

		if ($active === null) {
			$this->settings->logger->info(
				message: 'launchpad: deep-link path "{path}" not visible — falling back to default resolver',
				context: ['path' => $deepLink]
			);
		}

		return $active;
	}//end resolveDeepLink()

	/**
	 * Derive the initial-state keys that describe the active dashboard.
	 *
	 * Returns the documented empty defaults when nothing is active, so
	 * the contract is fully populated either way.
	 *
	 * The layout leaves out nothing but marks a widget the user's role does
	 * not permit with `isVisible` 0, as the dashboard reads do (REQ-RFP-001).
	 *
	 * @param array{dashboard: Dashboard, source: string}|null $active The active entry.
	 * @param string                                           $userId The user, or '' when nobody is logged in.
	 *
	 * @return array{activeDashboardId: string, dashboardSource: string, layout: array<int, mixed>, deepLinkPath: string}
	 */
	private function describeActive(?array $active, string $userId): array {
		if ($active === null) {
			return [
				'activeDashboardId' => '',
				'dashboardSource' => Dashboard::SOURCE_GROUP,
				'layout' => [],
				'deepLinkPath' => '',
			];
		}

		$activeDashboard = $active['dashboard'];
		$placements = $this->sources->widgetService->getDashboardPlacements(
			dashboardId: $activeDashboard->getId()
		);

		return [
			'activeDashboardId' => (string)$activeDashboard->getUuid(),
			'dashboardSource' => (string)$active['source'],
			'layout' => $this->hideUnpermitted(
				placements: array_map(
					callback: function ($placement) {
						return $placement->jsonSerialize();
					},
					array: $placements
				),
				userId: $userId
			),
			'deepLinkPath' => $this->computeDeepLinkPath(dashboard: $activeDashboard),
		];
	}//end describeActive()

	/**
	 * The serialised placements with the ones the user's role does not
	 * permit marked `isVisible` 0; unchanged when nobody is logged in.
	 *
	 * @param array<int, array<string, mixed>> $placements The serialised placements.
	 * @param string                           $userId     The user, or ''.
	 *
	 * @return array<int, array<string, mixed>> The placements.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 */
	private function hideUnpermitted(array $placements, string $userId): array {
		if ($userId === '') {
			return $placements;
		}

		return $this->settings->roleFeaturePerm->hideUnpermittedPlacements(
			placements: $placements,
			userId: $userId
		);
	}//end hideUnpermitted()

	/**
	 * Compute the canonical slug-chain for the active dashboard.
	 *
	 * The frontend reads this to keep the URL in sync (e.g. after a
	 * parent rename, a stale bookmarked path is normalised in-place via
	 * `history.replaceState`). A failure is logged and degrades to an
	 * empty path rather than breaking the render.
	 *
	 * @param Dashboard $dashboard The active dashboard.
	 *
	 * @return string The slug-chain, or '' when it could not be computed.
	 */
	private function computeDeepLinkPath(Dashboard $dashboard): string {
		try {
			return $this->settings->treeService->computePath(
				uuid: (string)$dashboard->getUuid()
			);
		} catch (Throwable $t) {
			$this->settings->logger->warning(
				message: 'launchpad: failed to compute path for active dashboard {uuid}: {message}',
				context: [
					'uuid' => (string)$dashboard->getUuid(),
					'message' => $t->getMessage(),
				]
			);
		}

		return '';
	}//end computeDeepLinkPath()

	/**
	 * Build the workspace content-security policy.
	 *
	 * REQ-VID: the video widget embeds YouTube/Vimeo players in an
	 * `<iframe>`; Nextcloud's default `frame-src 'self'` blocks them, so
	 * the page must explicitly allow the player origins (and their
	 * poster/thumbnail CDNs) or the widget renders an empty/broken frame.
	 * REQ-MMW: the map widget (CnMapWidget) falls back to OpenStreetMap
	 * tiles when no basemap is configured; Nextcloud's default `img-src`
	 * blocks external images, so allow the OSM tile hosts or the map
	 * paints white.
	 *
	 * @return ContentSecurityPolicy The workspace policy.
	 */
	private function buildWorkspaceCsp(): ContentSecurityPolicy {
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain(domain: 'https://www.youtube.com');
		$csp->addAllowedFrameDomain(domain: 'https://www.youtube-nocookie.com');
		$csp->addAllowedFrameDomain(domain: 'https://player.vimeo.com');
		$csp->addAllowedImageDomain(domain: 'https://i.ytimg.com');
		$csp->addAllowedImageDomain(domain: 'https://i.vimeocdn.com');
		$csp->addAllowedImageDomain(domain: 'https://*.tile.openstreetmap.org');

		return $csp;
	}//end buildWorkspaceCsp()

	/**
	 * Render the anonymous read-only public-share page.
	 *
	 * Boots the standalone `launchpad-public` SPA (renderAs public — no
	 * Nextcloud login chrome). The SPA reads the token from the `/s/{token}`
	 * URL itself, fetches the shared dashboard from `/s/{token}/data`, and
	 * renders it read-only. The route is #[PublicPage] so no login is required;
	 * the token is validated server-side by the data endpoint (invalid/revoked/
	 * expired ⇒ 404 there, password ⇒ 401). The token is therefore not a method
	 * parameter here — it needs no server-side handling on the page route.
	 *
	 * The image CSP is widened to `data:` so the bundled NL Design tile icons
	 * (base64 SVG data URIs) render for anonymous visitors too.
	 *
	 * Rate-limit rationale: this is the public share page — one of only four
	 * rendered public pages in the fleet (ADR-081). The share TOKEN is checked by
	 * PublicShareController, which already has brute-force protection wired
	 * through PublicShareService::registerAttempt(); this is just the shell that
	 * hosts it, so a volume ceiling is the right control and it is deliberately
	 * generous — a recipient reloading the page must not be what trips it.
	 *
	 * @return TemplateResponse The public page response.
	 *
	 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function publicShare(): TemplateResponse {
		return $this->publicPage();
	}//end publicShare()

	/**
	 * Render the public start page, `/apps/launchpad/start` (sharing-public-start-page D3).
	 *
	 * Signed-out visitors on the plain login page are sent here by
	 * {@see \OCA\LaunchPad\Listener\PublicStartRedirectListener}. The page is
	 * the public-share page for the link an administrator chose, with its
	 * token handed over as initial state and a "Sign in" bar on top; the data
	 * still comes from `/s/{token}/data`, so the read-only guards of REQ-PSHR
	 * apply unchanged. When the start page is off, or its link was revoked,
	 * protected or has expired, the visitor goes to the login form with
	 * `direct=1`, which never sends them back here.
	 *
	 * Rate limit: the same generous volume ceiling as {@see self::publicShare()}.
	 *
	 * @return TemplateResponse|RedirectResponse The start page, or the login form.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 60)]
	public function start(): TemplateResponse|RedirectResponse {
		$share = $this->settings->publicStartPage->active();
		if ($share === null) {
			return new RedirectResponse(
				redirectURL: $this->settings->urlGenerator->linkToRoute(routeName: 'core.login.showLoginForm', arguments: ['direct' => 1])
			);
		}

		(new InitialStateBuilder(initialState: $this->sources->initialState, page: Page::PUBLIC_START))
			->setPublicStartToken((string)$share->getToken())
			->apply();

		return $this->publicPage();
	}//end start()

	/**
	 * The anonymous read-only page that boots `launchpad-public`.
	 *
	 * @return TemplateResponse The public page response.
	 */
	private function publicPage(): TemplateResponse {
		$this->addAssets(script: 'launchpad-public');

		$response = new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'public',
			params: [],
			renderAs: $this->kioskRenderAs(default: TemplateResponse::RENDER_AS_PUBLIC)
		);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain(domain: 'data:');
		// A shared dashboard may contain a map widget, which falls back to
		// OpenStreetMap tiles when no basemap is configured (see index()).
		$csp->addAllowedImageDomain(domain: 'https://*.tile.openstreetmap.org');
		$response->setContentSecurityPolicy(csp: $csp);

		return $response;
	}//end publicPage()

	/**
	 * The page layout: without any Nextcloud chrome (header, navigation)
	 * when the request carries `kiosk=1` (REQ-KIOSK-001), else the default.
	 * The flag only changes the layout; every access check runs unchanged.
	 *
	 * @param string $default The layout without the flag.
	 *
	 * @return string The layout to render as.
	 */
	private function kioskRenderAs(string $default): string {
		if ($this->request->getParam(key: 'kiosk') === '1') {
			return TemplateResponse::RENDER_AS_BASE;
		}

		return $default;
	}//end kioskRenderAs()

	/**
	 * Add an app script and the app stylesheet to the page.
	 *
	 * @param string $script The script, `launchpad-main` or `launchpad-public`.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/runtime-shell/spec.md
	 */
	protected function addAssets(string $script): void {
		Util::addScript(application: Application::APP_ID, file: $script);
		Util::addStyle(application: Application::APP_ID, file: 'launchpad');
	}//end addAssets()

}//end class
