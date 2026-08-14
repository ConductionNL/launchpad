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
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\InitialState\Page;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;
use Psr\Log\LoggerInterface;
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
	 * @param IRequest $request The request.
	 * @param IManager $dashboardManager Nextcloud dashboard widget manager.
	 * @param IInitialState $initialState The Nextcloud initial-state service.
	 * @param IUserSession $userSession Active user session.
	 * @param WidgetService $widgetService Available-widgets descriptor formatter.
	 * @param DashboardService $dashboardService Dashboard listing + resolver
	 *                                           (also exposes the
	 *                                           `allow_user_dashboards` flag
	 *                                           — REQ-ASET-003).
	 * @param AdminTemplateService $adminTemplateService Primary-group routing
	 *                                                   resolver (REQ-TMPL-012,
	 *                                                   REQ-TMPL-013).
	 * @param RoleFeaturePermissionService $roleFeaturePerm Per-user widget
	 *                                                      allow-list source
	 *                                                      (REQ-RFP-009..010).
	 * @param DashboardTreeService $treeService Slug-chain
	 *                                          resolver used by the
	 *                                          deep-link route.
	 * @param LoggerInterface $logger Used to record
	 *                                silent fallback
	 *                                when a deep-link
	 *                                path doesn't
	 *                                resolve to a
	 *                                visible dashboard.
	 * @param AdminSettingsService $adminSettingsService Source of the
	 *                                                   quick-search
	 *                                                   no-match
	 *                                                   fallback-target
	 *                                                   admin setting
	 *                                                   (tile-quick-search
	 *                                                   REQ-QSEARCH-004).
	 */
	public function __construct(
		IRequest $request,
		private readonly IManager $dashboardManager,
		private readonly IInitialState $initialState,
		private readonly IUserSession $userSession,
		private readonly WidgetService $widgetService,
		private readonly DashboardService $dashboardService,
		private readonly AdminTemplateService $adminTemplateService,
		private readonly RoleFeaturePermissionService $roleFeaturePerm,
		private readonly DashboardTreeService $treeService,
		private readonly LoggerInterface $logger,
		private readonly AdminSettingsService $adminSettingsService,
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
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(string $deepLink = ''): TemplateResponse {
		Util::addScript(application: Application::APP_ID, file: 'launchpad-main');
		Util::addStyle(application: Application::APP_ID, file: 'launchpad');

		// Load all widget scripts so legacy widgets can register their callbacks.
		$this->loadWidgetScripts();

		$userId = $this->resolveUserId();

		$routingGroup = $this->resolveRoutingGroup(userId: $userId);
		$primaryGroupId = $routingGroup['id'];
		$primaryGroupName = $routingGroup['name'];

		$isAdmin = false;
		$visible = [];
		$active = null;
		if ($userId !== '') {
			$isAdmin = $this->dashboardService->isAdmin(userId: $userId);
			$visible = $this->dashboardService->getVisibleToUser(userId: $userId);
			$active = $this->resolveActive(
				userId: $userId,
				deepLink: $deepLink,
				primaryGroupId: $primaryGroupId
			);
		}

		$descriptors = $this->splitDescriptors(visible: $visible);
		$activeState = $this->describeActive(active: $active);

		$allowUserDashboards = $this->dashboardService->getAllowUserDashboards();

		$builder = new InitialStateBuilder(
			initialState: $this->initialState,
			page: Page::WORKSPACE
		);

		$builder
			->setWidgets($this->widgetService->getAvailableWidgets())
			->setLayout($activeState['layout'])
			->setPrimaryGroup($primaryGroupId)
			->setPrimaryGroupName($primaryGroupName)
			->setIsAdmin($isAdmin)
			->setActiveDashboardId($activeState['activeDashboardId'])
			->setDashboardSource($activeState['dashboardSource'])
			->setGroupDashboards($descriptors['group'])
			->setUserDashboards($descriptors['user'])
			->setAllowUserDashboards($allowUserDashboards);

		// PR #95 (role-based-content): per-user widget allow-list.
		// `null` = no admin policy for this user (unlimited).
		$allowedWidgets = null;
		if ($userId !== '') {
			$allowedWidgets = $this->roleFeaturePerm->getAllowedWidgetIds(
				userId: $userId
			);
		}

		// Tile-quick-search REQ-QSEARCH-004: read the admin-configured
		// no-match fallback target. `getSettings()` already resolves the
		// safe 'none' default when unset/invalid, so this never throws.
		$quicksearchFallback = (string)(
			$this->adminSettingsService->getSettings()['quicksearchFallbackTarget'] ?? AdminSettingsService::DEFAULT_QUICKSEARCH_FALLBACK_TARGET
		);

		$builder
			->setAllowedWidgets($allowedWidgets)
			->setDeepLinkPath($activeState['deepLinkPath'])
			->setQuicksearchFallbackTarget($quicksearchFallback)
			->apply();

		// REQ-SHELL-001: pass the chrome slot ids so Nextcloud treats
		// `#app-workspace` as the main content slot and allocates no left
		// navigation panel (the runtime shell renders its own slide-in
		// sidebar via `dashboard-switcher-sidebar`). Renderer parameter
		// names match the Nextcloud chrome conventions.
		$response = new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'index',
			params: [
				'id-app-content' => '#app-workspace',
				'id-app-navigation' => null,
			]
		);

		$response->setContentSecurityPolicy(csp: $this->buildWorkspaceCsp());

		return $response;
	}//end index()

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
		$primaryGroupName = $this->adminTemplateService->resolvePrimaryGroupDisplayName(
			groupId: Dashboard::DEFAULT_GROUP_ID
		);
		if ($userId !== '') {
			$primaryGroupId = $this->adminTemplateService->resolvePrimaryGroup(
				userId: $userId
			);
			$primaryGroupName = $this->adminTemplateService->resolvePrimaryGroupDisplayName(
				groupId: $primaryGroupId
			);
		}

		return [
			'id' => $primaryGroupId,
			'name' => $primaryGroupName,
		];
	}//end resolveRoutingGroup()

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

		return $this->dashboardService->resolveActiveDashboard(
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
			$resolved = $this->treeService->resolvePath(path: $deepLink);
			if ($resolved !== null) {
				$active = $this->dashboardService->getDashboardForUser(
					dashboardId: $resolved->getId(),
					userId: $userId
				);
			}
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: deep-link resolution failed for path "{path}": {message}',
				context: [
					'path' => $deepLink,
					'message' => $t->getMessage(),
				]
			);
		}

		if ($active === null) {
			$this->logger->info(
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
	 * @param array{dashboard: Dashboard, source: string}|null $active The active entry.
	 *
	 * @return array{activeDashboardId: string, dashboardSource: string, layout: array<int, mixed>, deepLinkPath: string}
	 */
	private function describeActive(?array $active): array {
		if ($active === null) {
			return [
				'activeDashboardId' => '',
				'dashboardSource' => Dashboard::SOURCE_GROUP,
				'layout' => [],
				'deepLinkPath' => '',
			];
		}

		$activeDashboard = $active['dashboard'];
		$placements = $this->widgetService->getDashboardPlacements(
			dashboardId: $activeDashboard->getId()
		);

		return [
			'activeDashboardId' => (string)$activeDashboard->getUuid(),
			'dashboardSource' => (string)$active['source'],
			'layout' => array_map(
				callback: function ($placement) {
					return $placement->jsonSerialize();
				},
				array: $placements
			),
			'deepLinkPath' => $this->computeDeepLinkPath(dashboard: $activeDashboard),
		];
	}//end describeActive()

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
			return $this->treeService->computePath(
				uuid: (string)$dashboard->getUuid()
			);
		} catch (Throwable $t) {
			$this->logger->warning(
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
	 * @return TemplateResponse The public page response.
	 *
	 * @spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	// The public share page — one of only four rendered public pages in the
	// fleet (ADR-081). The share TOKEN is checked by PublicShareController,
	// which already has brute-force protection wired through
	// PublicShareService::registerAttempt(); this is just the shell that hosts
	// it, so a volume ceiling is the right control and it is deliberately
	// generous — a recipient reloading the page must not be what trips it.
	#[AnonRateLimit(limit: 120, period: 60)]
	public function publicShare(): TemplateResponse {
		Util::addScript(application: Application::APP_ID, file: 'launchpad-public');
		Util::addStyle(application: Application::APP_ID, file: 'launchpad');

		$response = new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'public',
			params: [],
			renderAs: TemplateResponse::RENDER_AS_PUBLIC
		);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain(domain: 'data:');
		// A shared dashboard may contain a map widget, which falls back to
		// OpenStreetMap tiles when no basemap is configured (see index()).
		$csp->addAllowedImageDomain(domain: 'https://*.tile.openstreetmap.org');
		$response->setContentSecurityPolicy(csp: $csp);

		return $response;
	}//end publicShare()

	/**
	 * Load scripts for all available dashboard widgets.
	 *
	 * This ensures legacy widgets can register their callbacks via
	 * OCA.Dashboard.register.
	 *
	 * @return array<string, \OCP\Dashboard\IWidget> Map of widget id to widget.
	 */
	private function loadWidgetScripts(): array {
		$widgets = $this->dashboardManager->getWidgets();

		foreach ($widgets as $widget) {
			// Call the widget's load() method to inject its scripts.
			$widget->load();
		}

		return $widgets;
	}//end loadWidgetScripts()
}//end class
