<?php

/**
 * Application
 *
 * Main application bootstrap class for LaunchPad.
 *
 * @category  AppInfo
 * @package   OCA\LaunchPad\AppInfo
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\AppInfo;

use OCA\LaunchPad\Activity\DebounceHelper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\GroupDeletedListener;
use OCA\LaunchPad\Listener\LocksListener;
use OCA\LaunchPad\Listener\MetadataValuesListener;
use OCA\LaunchPad\Listener\PublicSharesListener;
use OCA\LaunchPad\Listener\ReactionsListener;
use OCA\LaunchPad\Listener\TranslationsListener;
use OCA\LaunchPad\Listener\TreeListener;
use OCA\LaunchPad\Listener\UserDeletedListener;
use OCA\LaunchPad\Listener\VersionsListener;
use OCA\LaunchPad\Listener\ViewAnalyticsListener;
use OCA\LaunchPad\Listener\WidgetPlacementsListener;
use OCA\LaunchPad\Notification\Notifier;
use OCA\LaunchPad\Search\LaunchPadSearchProvider;
use OCA\LaunchPad\Service\PublicShareContext;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

/**
 * Application bootstrap.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The bootstrap
 *                                                  legitimately wires
 *                                                  every event listener
 *                                                  in the cascade-events
 *                                                  registry (REQ-CSC-002),
 *                                                  which inflates the
 *                                                  coupling count beyond
 *                                                  the default threshold.
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'launchpad';

    /**
     * Constructor
     *
     * @param array $urlParams The URL parameters.
     */
    public function __construct(array $urlParams=[])
    {
        parent::__construct(appName: self::APP_ID, urlParams: $urlParams);
    }//end __construct()

    /**
     * Register services, event listeners, etc.
     *
     * @param IRegistrationContext $context The registration context.
     *
     * @return void
     */
    public function register(IRegistrationContext $context): void
    {
        // Render `dashboard_shared` and `dashboard_ownership_transferred`
        // notifications via our INotifier. REQ-SHARE-011.
        $context->registerNotifierService(notifierClass: Notifier::class);

        // Cascade share cleanup + admin-retention transfer on user deletion.
        // Also fires the role-assignment cleanup. REQ-SHARE-012,
        // REQ-SHARE-013, REQ-ROLE-010. The same listener also satisfies the
        // owned-dashboard enumeration mandated by REQ-CSC-004 — every owned
        // dashboard is routed through the deletion path that dispatches
        // DashboardDeletedEvent below, triggering the full cascade stack.
        $context->registerEventListener(
            event: UserDeletedEvent::class,
            listener: UserDeletedListener::class
        );

        // REQ-ACT-007: register DebounceHelper as a shared singleton
        // so the in-memory fallback store (used when APCu is absent in
        // CLI / test runs) survives across all callers within a single
        // request. ActivityPublisher autowires from the app namespace
        // — no explicit binding needed (referenced here in this
        // docblock for the cross-capability discoverability contract:
        // {@see ActivityPublisher}).
        $context->registerService(
            name: DebounceHelper::class,
            factory: static fn(): DebounceHelper => new DebounceHelper(),
            shared: true
        );

        // Task-7 of dashboard-public-share — request-scoped bearer marker
        // shared across the entire request so mutation services can
        // assert read-only context without middleware plumbing.
        $context->registerService(
            name: PublicShareContext::class,
            factory: static fn(): PublicShareContext => new PublicShareContext(),
            shared: true
        );

        // Role-assignment cascade on group deletion. REQ-ROLE-011.
        // Group lifecycle cleanup. REQ-CSC-005.
        $context->registerEventListener(
            event: GroupDeletedEvent::class,
            listener: GroupDeletedListener::class
        );

        // DashboardDeletedEvent listener registry. REQ-CSC-002.
        // Each listener owns one dependent table (or, for TreeListener,
        // recursive child dispatch). Adding a new listener requires only
        // appending one registration line below — no edits to existing
        // listener classes, the event, or DashboardService.
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: WidgetPlacementsListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: ReactionsListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: LocksListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: VersionsListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: PublicSharesListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: MetadataValuesListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: TranslationsListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: ViewAnalyticsListener::class
        );
        $context->registerEventListener(
            event: DashboardDeletedEvent::class,
            listener: TreeListener::class
        );

        // Surface dashboards, widget content, and metadata values in
        // Nextcloud's unified search (Ctrl+K). REQ-SRCH-001.
        $context->registerSearchProvider(class: LaunchPadSearchProvider::class);

        // Observability (ADR-040): re-point the unchanged /api/health and
        // /api/metrics routes at thin subclasses of the OpenRegister AppHost
        // generic controllers, which render the declarative observability block
        // of src/manifest.json. The factories below are lazy — they reference
        // no OCA\OpenRegister\… symbol until a request resolves the controller,
        // so a disabled/absent OpenRegister never fatals NC bootstrap (the route
        // then surfaces the degraded OR-unavailable state instead). $appId is the
        // runtime app id `launchpad`; the engine reads the manifest under it and
        // emits the launchpad_ Prometheus prefix, preserving the contract.
        $this->registerObservability(context: $context);
    }//end register()

    /**
     * Wire the AppHost observability controllers (ADR-040).
     *
     * Aliases the unchanged `health#index` / `metrics#index` route targets at
     * the OpenRegister AppHost generic controllers, per the documented leaf
     * adoption pattern (docs/Technical/declarative-observability.md). The
     * controller's `$appName` resolves to this leaf's app id, so the engine
     * loads `src/manifest.json`'s `observability` block under `launchpad` and
     * renders the `launchpad_`-prefixed Prometheus output. The generic
     * controllers own the auth posture: health is public (`#[PublicPage]`),
     * metrics admin-only.
     *
     * LaunchPad keeps its own bespoke Dashboard/Preferences/Settings/
     * AdminSettings boilerplate — entangled with the dashboard lifecycle,
     * permission matrix and DoS-guarded preferences (see
     * openspec/changes/adopt-apphost/design.md). The aliases are class-string
     * registrations, so a disabled/absent OpenRegister never fatals NC
     * bootstrap; the first request to an aliased route surfaces the degraded
     * OR-unavailable state instead.
     *
     * @param IRegistrationContext $context The registration context.
     *
     * @return void
     */
    private function registerObservability(IRegistrationContext $context): void
    {
        // Health controller. The generic class is referenced only as a string
        // and instantiated inside the closure, so no OCA\OpenRegister symbol is
        // touched until a request resolves the controller — keeping NC bootstrap
        // fatal-free when OpenRegister is disabled/absent. $appName is pinned to
        // this leaf's runtime app id (`launchpad`) so the engine loads the right
        // manifest and emits the `launchpad_` prefix, exactly as before adoption.
        $context->registerService(
            \OCA\LaunchPad\Controller\HealthController::class,
            static function (\Psr\Container\ContainerInterface $c): \OCA\LaunchPad\Controller\HealthController {
                return new \OCA\LaunchPad\Controller\HealthController(
                    appName: self::APP_ID,
                    request: $c->get(\OCP\IRequest::class),
                    manifestLoader: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader'),
                    executor: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\HealthCheckExecutor')
                );
            }
        );

        // Metrics controller (admin-only — the subclass omits #[NoAdminRequired]).
        $context->registerService(
            \OCA\LaunchPad\Controller\MetricsController::class,
            static function (\Psr\Container\ContainerInterface $c): \OCA\LaunchPad\Controller\MetricsController {
                return new \OCA\LaunchPad\Controller\MetricsController(
                    appName: self::APP_ID,
                    request: $c->get(\OCP\IRequest::class),
                    manifestLoader: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader'),
                    engine: $c->get('OCA\\OpenRegister\\AppHost\\Observability\\MetricsEngine')
                );
            }
        );
    }//end registerObservability()

    /**
     * App initialization after all apps are registered.
     *
     * @param IBootContext $context The boot context.
     *
     * @return void
     */
    public function boot(IBootContext $context): void
    {
        // C2: block all external XML entity resolution at the process level.
        // LIBXML_NOENT in simplexml_load_string / DOMDocument::loadXML does
        // NOT disable entity substitution — it enables it. The only reliable
        // defence is to install a null entity loader here at boot time. This
        // is safe because Nextcloud itself does not rely on external XML
        // entities in its own code.
        if (function_exists('libxml_set_external_entity_loader') === true) {
            // @psalm-suppress UnusedFunctionCall
            libxml_set_external_entity_loader(static fn (): null => null);
        }

        // App initialization after all apps are registered.
        \OCP\Util::addStyle(application: self::APP_ID, file: 'launchpad');

        // The dashboard view-analytics jobs (REQ-ANLT-003 design D2 +
        // REQ-ANLT-009) and the external-feed refresh job (REQ-FRJ-002) are
        // registered once via the RegisterBackgroundJobs repair step (install +
        // post-migration), NOT on every request. Registering them here issued a
        // JobList::has() SELECT against oc_jobs on each web request and tripped
        // Nextcloud's "dirty table reads" diagnostic.
    }//end boot()
}//end class
