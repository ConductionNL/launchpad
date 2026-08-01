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
class PageController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest                     $request              The request.
     * @param IManager                     $dashboardManager     Nextcloud dashboard widget manager.
     * @param IInitialState                $initialState         The Nextcloud initial-state service.
     * @param IUserSession                 $userSession          Active user session.
     * @param WidgetService                $widgetService        Available-widgets descriptor formatter.
     * @param DashboardService             $dashboardService     Dashboard listing + resolver
     *                                                           (also exposes the
     *                                                           `allow_user_dashboards` flag
     *                                                           — REQ-ASET-003).
     * @param AdminTemplateService         $adminTemplateService Primary-group routing
     *                                                           resolver (REQ-TMPL-012,
     *                                                           REQ-TMPL-013).
     * @param RoleFeaturePermissionService $roleFeaturePerm      Per-user widget
     *                                                           allow-list source
     *                                                           (REQ-RFP-009..010).
     * @param DashboardTreeService         $treeService          Slug-chain
     *                                                           resolver used by the
     *                                                           deep-link route.
     * @param LoggerInterface              $logger               Used to record
     *                                                           silent fallback
     *                                                           when a deep-link
     *                                                           path doesn't
     *                                                           resolve to a
     *                                                           visible dashboard.
     * @param AdminSettingsService         $adminSettingsService Source of the
     *                                                           quick-search
     *                                                           no-match
     *                                                           fallback-target
     *                                                           admin setting
     *                                                           (tile-quick-search
     *                                                           REQ-QSEARCH-004).
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
    public function deepLink(string $deepLink=''): TemplateResponse
    {
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
    public function index(string $deepLink=''): TemplateResponse
    {
        Util::addScript(application: Application::APP_ID, file: 'launchpad-main');
        Util::addStyle(application: Application::APP_ID, file: 'launchpad');

        // Load all widget scripts so legacy widgets can register their callbacks.
        $this->loadWidgetScripts();

        $user   = $this->userSession->getUser();
        $userId = '';
        if ($user !== null) {
            $userId = $user->getUID();
        }

        // Routing resolver — REQ-TMPL-012 / REQ-TMPL-013. The
        // `AdminTemplateService` walks the admin-configured `group_order`
        // priority list and returns the first group the user belongs to,
        // OR the literal `'default'` sentinel when nothing matches. The
        // display name comes from the same service so the lookup lives in
        // exactly one place.
        $primaryGroupId   = Dashboard::DEFAULT_GROUP_ID;
        $primaryGroupName = $this->adminTemplateService->resolvePrimaryGroupDisplayName(
            groupId: Dashboard::DEFAULT_GROUP_ID
        );
        if ($userId !== '') {
            $primaryGroupId   = $this->adminTemplateService->resolvePrimaryGroup(
                userId: $userId
            );
            $primaryGroupName = $this->adminTemplateService->resolvePrimaryGroupDisplayName(
                groupId: $primaryGroupId
            );
        }

        $isAdmin = false;
        if ($userId !== '') {
            $isAdmin = $this->dashboardService->isAdmin(userId: $userId);
        }

        $visible = [];
        if ($userId !== '') {
            $visible = $this->dashboardService->getVisibleToUser(userId: $userId);
        }

        $groupDashboards = [];
        $userDashboards  = [];
        foreach ($visible as $entry) {
            $dashboard = $entry['dashboard'];
            // Dashboard entity has no icon column today — surface an empty
            // string so the frontend descriptor shape matches REQ-INIT-002.
            $descriptor = [
                'id'     => (string) $dashboard->getUuid(),
                'name'   => (string) $dashboard->getName(),
                'icon'   => '',
                'source' => $entry['source'],
            ];

            if ($entry['source'] === Dashboard::SOURCE_USER) {
                unset($descriptor['source']);
                $userDashboards[] = $descriptor;
                continue;
            }

            $groupDashboards[] = $descriptor;
        }

        $active = null;
        if ($userId !== '') {
            // Deep-link override: when the URL carries a slug-chain we
            // try to land the user on that dashboard before consulting
            // the seven-step resolver. Failures (path doesn't resolve,
            // not visible, throws) are swallowed so a stale bookmark
            // still opens *something* instead of breaking.
            if ($deepLink !== '') {
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
                            'path'    => $deepLink,
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
            }//end if

            if ($active === null) {
                $active = $this->dashboardService->resolveActiveDashboard(
                    userId: $userId,
                    primaryGroupId: $primaryGroupId
                );
            }
        }//end if

        $activeDashboardId = '';
        $dashboardSource   = Dashboard::SOURCE_GROUP;
        $layout            = [];
        $deepLinkPath      = '';
        if ($active !== null) {
            $activeDashboard   = $active['dashboard'];
            $activeDashboardId = (string) $activeDashboard->getUuid();
            $dashboardSource   = (string) $active['source'];
            $placements        = $this->widgetService->getDashboardPlacements(
                dashboardId: $activeDashboard->getId()
            );
            $layout            = array_map(
                callback: function ($placement) {
                    return $placement->jsonSerialize();
                },
                array: $placements
            );
            // Canonical slug-chain for whatever dashboard ended up active —
            // the frontend reads this to keep the URL in sync (e.g. after
            // a parent rename, a stale bookmarked path is normalised
            // in-place via `history.replaceState`).
            try {
                $deepLinkPath = $this->treeService->computePath(
                    uuid: (string) $activeDashboard->getUuid()
                );
            } catch (Throwable $t) {
                $this->logger->warning(
                    message: 'launchpad: failed to compute path for active dashboard {uuid}: {message}',
                    context: [
                        'uuid'    => (string) $activeDashboard->getUuid(),
                        'message' => $t->getMessage(),
                    ]
                );
            }
        }//end if

        $allowUserDashboards = $this->dashboardService->getAllowUserDashboards();

        $builder = new InitialStateBuilder(
            initialState: $this->initialState,
            page: Page::WORKSPACE
        );

        $builder
            ->setWidgets($this->widgetService->getAvailableWidgets())
            ->setLayout($layout)
            ->setPrimaryGroup($primaryGroupId)
            ->setPrimaryGroupName($primaryGroupName)
            ->setIsAdmin($isAdmin)
            ->setActiveDashboardId($activeDashboardId)
            ->setDashboardSource($dashboardSource)
            ->setGroupDashboards($groupDashboards)
            ->setUserDashboards($userDashboards)
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
        $quicksearchFallback = (string) (
            $this->adminSettingsService->getSettings()['quicksearchFallbackTarget'] ?? AdminSettingsService::DEFAULT_QUICKSEARCH_FALLBACK_TARGET
        );

        $builder
            ->setAllowedWidgets($allowedWidgets)
            ->setDeepLinkPath($deepLinkPath)
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
                'id-app-content'    => '#app-workspace',
                'id-app-navigation' => null,
            ]
        );

        // REQ-VID: the video widget embeds YouTube/Vimeo players in an
        // <iframe>; Nextcloud's default `frame-src 'self'` blocks them, so the
        // page must explicitly allow the player origins (and their poster/
        // thumbnail CDNs) or the widget renders an empty/broken frame.
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedFrameDomain(domain: 'https://www.youtube.com');
        $csp->addAllowedFrameDomain(domain: 'https://www.youtube-nocookie.com');
        $csp->addAllowedFrameDomain(domain: 'https://player.vimeo.com');
        $csp->addAllowedImageDomain(domain: 'https://i.ytimg.com');
        $csp->addAllowedImageDomain(domain: 'https://i.vimeocdn.com');
        // REQ-MMW: the map widget (CnMapWidget) falls back to OpenStreetMap tiles
        // when no basemap is configured; Nextcloud's default `img-src` blocks
        // external images, so allow the OSM tile hosts or the map paints white.
        $csp->addAllowedImageDomain(domain: 'https://*.tile.openstreetmap.org');
        $response->setContentSecurityPolicy(csp: $csp);

        return $response;
    }//end index()

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
    public function publicShare(): TemplateResponse
    {
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
    private function loadWidgetScripts(): array
    {
        $widgets = $this->dashboardManager->getWidgets();

        foreach ($widgets as $widget) {
            // Call the widget's load() method to inject its scripts.
            $widget->load();
        }

        return $widgets;
    }//end loadWidgetScripts()
}//end class
