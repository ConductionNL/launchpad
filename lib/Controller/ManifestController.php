<?php

/**
 * ManifestController
 *
 * Serves the v2 app manifest for the current user. The manifest is assembled
 * at runtime from the user's dashboard objects stored in OpenRegister
 * (ADR-036 Decision 8). Each OR dashboard object becomes one `type: "dashboard"`
 * page entry and one menu entry. When the user has no dashboards the manifest
 * returns empty pages/menu so the frontend renders its empty-state CTA.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for the v2 runtime manifest endpoint (ADR-036 Decision 8).
 *
 * Assembles a per-user app manifest from the user's dashboard objects
 * stored in OpenRegister and returns it as a JSON document that validates
 * against the v2 schema.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ManifestController extends Controller
{
    /**
     * OpenRegister register slug for launchpad dashboards.
     *
     * @var string
     */
    private const REGISTER = 'launchpad';

    /**
     * OpenRegister schema slug for dashboard objects.
     *
     * @var string
     */
    private const SCHEMA = 'dashboard';

    /**
     * V2 manifest schema URL.
     *
     * @var string
     */
    private const SCHEMA_URL = 'https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json';

    /**
     * Constructor.
     *
     * @param IRequest           $request     The HTTP request.
     * @param ContainerInterface $container   The Nextcloud DI container; used to
     *                                        lazy-load ObjectService so that launchpad
     *                                        degrades gracefully when OpenRegister
     *                                        is not yet active.
     * @param ActionAuthService  $actionAuth  ADR-023 action authorization.
     * @param IUserSession       $userSession User session (IUser resolution).
     * @param LoggerInterface    $logger      PSR logger.
     * @param string|null        $userId      The authenticated user ID, injected
     *                                        by the DI container.
     */
    public function __construct(
        IRequest $request,
        private readonly ContainerInterface $container,
        private readonly ActionAuthService $actionAuth,
        private readonly IUserSession $userSession,
        private readonly LoggerInterface $logger,
        private readonly ?string $userId,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * Build and return the v2 app manifest for the authenticated user.
     *
     * Reads the user's dashboard objects from OpenRegister. Each object with
     * a `slug` and `title` property becomes one page entry and one menu entry.
     * Objects the user owns, plus objects explicitly granted to them via
     * OpenRegister's per-object sharing primitive, are included.
     *
     * Route: GET /apps/launchpad/api/manifest
     *
     * @return JSONResponse A JSON document conforming to the v2 manifest
     *                      schema. Returns HTTP 401 when no user is
     *                      authenticated, HTTP 503 when OpenRegister is
     *                      unavailable.
     *
     * @spec manifest-v2-runtime:REQ-MVR-001
     * @spec openspec/specs/runtime-shell/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse
    {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'manifest.index');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        // Retrieve ObjectService lazily — OpenRegister may not be enabled on
        // every instance. Returning an empty manifest (not an error) lets the
        // frontend render its "no dashboards yet" CTA without a red alert.
        try {
            /*
             * @var \OCA\OpenRegister\Service\ObjectService $objectService
             */

            $objectService = $this->container->get('OCA\OpenRegister\Service\ObjectService');
        } catch (\Throwable $e) {
            $this->logger->warning(
                'LaunchPad: OpenRegister ObjectService unavailable — returning empty manifest. '.$e->getMessage(),
                ['app' => Application::APP_ID]
            );

            return new JSONResponse($this->buildManifest(dashboards: [], userId: $this->userId));
        }//end try

        // Fetch all dashboard objects owned by or shared with the current user.
        $dashboards = $this->fetchUserDashboards(objectService: $objectService, userId: $this->userId);

        return new JSONResponse($this->buildManifest(dashboards: $dashboards, userId: $this->userId));

    }//end index()

    /**
     * Maximum number of granted dashboards folded into one manifest.
     *
     * A bound, not a policy: the grant lookup is cheap but the follow-up load is
     * one IN(...) query whose parameter list should not grow without limit. If a
     * user ever exceeds this, the manifest is truncated rather than slow — and
     * the truncation is logged rather than silent, so it cannot be mistaken for
     * "the user has no shared dashboards".
     *
     * @var int
     */
    private const MAX_GRANTED = 200;

    /**
     * Fetch dashboard objects from OpenRegister for the given user.
     *
     * C5 fix (REQ-MVR-001): replaces the non-existent `findObjects()` call
     * (which caused a BadMethodCallException silently swallowed by Throwable)
     * with the real `ObjectService::findAll()` API. The `owner` filter
     * constrains results to the calling user's records — without it every
     * user would receive the full dataset (latent IDOR on top of the API drift).
     *
     * Two sources, deduplicated: dashboards the user OWNS, and dashboards
     * explicitly GRANTED to them through OpenRegister's per-object sharing
     * primitive. The owner filter is deliberately kept on the first query rather
     * than replaced by "let RBAC decide" — see fetchGrantedDashboards() for why
     * the additive shape is the safe one.
     *
     * @param object $objectService The OpenRegister ObjectService instance.
     * @param string $userId        The authenticated Nextcloud user ID.
     *
     * @return array<int, array<string, mixed>> Flat list of dashboard data arrays.
     */
    private function fetchUserDashboards(object $objectService, string $userId): array
    {
        $dashboards = [];
        $seen       = [];

        try {
            // C5 fix: use the real ObjectService::findAll() API.
            // `findObjects()` does not exist; the old call threw
            // BadMethodCallException that was silently swallowed, causing the
            // manifest to always return empty pages/menu arrays.
            //
            // The owner filter MUST be NESTED under `@self`. OpenRegister splits
            // filters into metadata filters (the magic table's `_`-prefixed
            // columns, addressed as a nested `@self` array) and property filters
            // (matched against the schema's own properties). A bare `owner` is
            // therefore read as a property filter on an `owner` property, which
            // the dashboard schema does not have — so it matched nothing and
            // this endpoint returned an EMPTY MANIFEST to every user, including
            // the owner of the dashboards.
            //
            // Measured on a live instance, admin owning two dashboards:
            //   no owner filter at all                    -> 2
            //   'owner'          => 'admin'               -> 0
            //   '@self.owner'    => 'admin'  (dotted)     -> 0
            //   '@self' => ['owner' => 'admin'] (nested)  -> 2
            //   '@self' => ['owner' => 'nobody-xyz']      -> 0   <- control
            // The last row is the control: it proves the nested form actually
            // filters rather than being silently ignored, which is the failure
            // mode that produced the always-empty manifest in the first place.
            $ownedResults = $objectService->findAll(
                config: [
                    'filters' => [
                        'register' => self::REGISTER,
                        'schema'   => self::SCHEMA,
                        '@self'    => ['owner' => $userId],
                    ],
                    'limit'   => 500,
                ]
            );

            if (is_array($ownedResults) === true) {
                foreach ($ownedResults as $item) {
                    $data = $this->extractData(item: $item);
                    if (empty($data) === true) {
                        continue;
                    }

                    $id = $data['id'] ?? $data['uuid'] ?? $data['slug'] ?? null;
                    if ($id !== null && isset($seen[$id]) === false) {
                        $seen[$id]    = true;
                        $dashboards[] = $data;
                    }
                }
            }
        } catch (DoesNotExistException $e) {
            // The 'mydash' register or 'dashboard' schema has not been
            // provisioned in OpenRegister on this instance yet. That simply
            // means the user has no dashboards — degrade to an empty manifest
            // so the frontend renders its "no dashboards yet" CTA instead of a
            // 500 (OpenRegister surfaces this as DoesNotExistException, which
            // extends \Exception and so is not a RuntimeException).
            $this->logger->info(
                'MyDash: OpenRegister register/schema not provisioned — returning empty manifest. '.$e->getMessage(),
                ['app' => Application::APP_ID, 'userId' => $userId]
            );
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            // Narrow catch: only handle recoverable OR API errors. Let
            // unexpected errors propagate so they are visible in the logs.
            $this->logger->error(
                'LaunchPad: failed to fetch dashboards from OpenRegister: '.$e->getMessage(),
                ['app' => Application::APP_ID, 'userId' => $userId]
            );
        }//end try

        // Second source: dashboards explicitly granted to this user. Additive,
        // and folded through the same $seen map so a dashboard the user both
        // owns and was granted appears once.
        foreach ($this->fetchGrantedDashboards(objectService: $objectService, userId: $userId) as $data) {
            $id = ($data['id'] ?? $data['uuid'] ?? $data['slug'] ?? null);
            if ($id !== null && isset($seen[$id]) === false) {
                $seen[$id]    = true;
                $dashboards[] = $data;
            }
        }

        return $dashboards;

    }//end fetchUserDashboards()

    /**
     * Dashboard objects explicitly granted to this user, via OpenRegister.
     *
     * WHY THIS IS ADDITIVE, rather than "drop the owner filter and let RBAC
     * decide". Letting RBAC decide is the tidier design and it is what the
     * OpenRegister `private` scope exists for — but it is only safe once the
     * `dashboard` schema actually carries `scope: private`. A register-descriptor
     * change lands through a repair step on upgrade, so there is necessarily a
     * window (and, on any instance where that import did not apply, an
     * indefinite one) in which the schema is still unscoped. An unfiltered
     * findAll() against an unscoped schema returns EVERY user's dashboards. So
     * the owner filter stays, and grants only ever ADD rows. The failure mode of
     * this shape is a missing dashboard; the failure mode of the other is a
     * cross-tenant leak in the manifest.
     *
     * `read` is the verb, because appearing in someone's manifest is exactly a
     * read. The resolver answers only for the five core permission verbs and
     * refuses anything else, so this cannot silently widen.
     *
     * Fails soft and empty: OpenRegister may be present without the sharing
     * primitive (an older release), in which case the class is simply absent and
     * the manifest degrades to owned-only — the behaviour before this change.
     *
     * @param object $objectService The OpenRegister ObjectService instance.
     * @param string $userId        The authenticated Nextcloud user ID.
     *
     * @return array<int, array<string, mixed>> Granted dashboard data arrays.
     */
    private function fetchGrantedDashboards(object $objectService, string $userId): array
    {
        try {
            $grantResolver = $this->container->get('OCA\OpenRegister\Service\Rbac\ObjectGrantResolver');
        } catch (\Throwable $e) {
            // OpenRegister without the per-object sharing primitive. Not an
            // error: degrade to owned-only, which is the pre-existing behaviour.
            $this->logger->debug(
                'LaunchPad: OpenRegister object-grant resolver unavailable — manifest is owned-only. '.$e->getMessage(),
                ['app' => Application::APP_ID]
            );

            return [];
        }

        try {
            // Keys, not values: the resolver returns uuid => permission
            // bitmask, so array_values() would yield the bitmasks.
            $grantedUuids = array_keys($grantResolver->grantedObjectUuidsFor($userId, 'read'));
            if (empty($grantedUuids) === true) {
                return [];
            }

            if (count($grantedUuids) > self::MAX_GRANTED) {
                // Logged, never silent: a truncated manifest must not be
                // indistinguishable from "nothing is shared with this user".
                $this->logger->warning(
                    sprintf(
                        'LaunchPad: %d granted dashboards exceeds the %d cap — manifest truncated.',
                        count($grantedUuids),
                        self::MAX_GRANTED
                    ),
                    ['app' => Application::APP_ID, 'userId' => $userId]
                );
                $grantedUuids = array_slice($grantedUuids, 0, self::MAX_GRANTED);
            }

            // `ids` is a first-class config key that matches `_uuid` OR `_slug`.
            // A `filters['uuid']` entry would instead be read as a property
            // filter on a `uuid` property — the same trap that made the owner
            // query above return nothing.
            $results = $objectService->findAll(
                config: [
                    'filters' => [
                        'register' => self::REGISTER,
                        'schema'   => self::SCHEMA,
                    ],
                    'ids'     => $grantedUuids,
                    'limit'   => self::MAX_GRANTED,
                ]
            );

            if (is_array($results) === false) {
                return [];
            }

            $granted = [];
            foreach ($results as $item) {
                $data = $this->extractData(item: $item);
                if (empty($data) === false) {
                    $granted[] = $data;
                }
            }

            return $granted;
        } catch (DoesNotExistException $e) {
            // Register/schema not provisioned — same benign case the owned
            // query already handles.
            return [];
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            $this->logger->error(
                'LaunchPad: failed to fetch granted dashboards from OpenRegister: '.$e->getMessage(),
                ['app' => Application::APP_ID, 'userId' => $userId]
            );

            return [];
        }//end try

    }//end fetchGrantedDashboards()

    /**
     * Normalise a raw ObjectService result item to a plain data array.
     *
     * OpenRegister items may be returned as objects with a `getObject()`
     * method or as plain associative arrays.
     *
     * @param mixed $item A single result from ObjectService::findAll().
     *
     * @return array<string, mixed> The plain data array, or [] on failure.
     */
    private function extractData(mixed $item): array
    {
        if (is_array($item) === true) {
            return $item;
        }

        if (is_object($item) === true && method_exists($item, 'getObject') === true) {
            $data = $item->getObject();
            if (is_array($data) === true) {
                return $data;
            }

            return [];
        }

        if (is_object($item) === true && method_exists($item, 'jsonSerialize') === true) {
            $data = $item->jsonSerialize();
            if (is_array($data) === true) {
                return $data;
            }

            return [];
        }

        return [];

    }//end extractData()

    /**
     * Build the v2 manifest array from a list of dashboard data arrays.
     *
     * @param array<int, array<string, mixed>> $dashboards Flat list of dashboard data.
     * @param string                           $userId     The current user ID.
     *
     * @return array<string, mixed> The v2 manifest document.
     */
    private function buildManifest(array $dashboards, string $userId): array
    {
        $pages = [];
        $menu  = [];
        $order = 0;

        foreach ($dashboards as $data) {
            $slug  = $data['slug'] ?? null;
            $title = $data['title'] ?? null;

            if (empty($slug) === true || empty($title) === true) {
                continue;
            }

            $pageId = 'dashboard-'.$slug;

            $pages[] = [
                'id'      => $pageId,
                'route'   => '/'.$slug,
                'type'    => 'dashboard',
                'title'   => $title,
                'widgets' => $data['widgets'] ?? [],
            ];

            $menu[] = [
                'id'    => 'menu-'.$slug,
                'label' => $title,
                'route' => $pageId,
                'order' => $order,
                'icon'  => 'icon-home',
            ];

            $order++;
        }//end foreach

        return [
            '$schema'      => self::SCHEMA_URL,
            'version'      => '1.0.0',
            'dependencies' => ['openregister'],
            'menu'         => $menu,
            'pages'        => $pages,
            'runtime'      => [
                'user' => [
                    'id' => $userId,
                ],
            ],
        ];

    }//end buildManifest()
}//end class
