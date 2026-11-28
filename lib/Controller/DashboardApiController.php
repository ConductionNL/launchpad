<?php

/**
 * DashboardApiController
 *
 * Controller for dashboard API endpoints — personal scope, group-shared
 * scope (REQ-DASH-014), and the visible-to-user resolution endpoint
 * (REQ-DASH-013).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\ContrastTooLowException;
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCA\LaunchPad\Exception\DraftConflictException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\ResponseDefinitions;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use OCA\LaunchPad\Service\DashboardDraft;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for dashboard API endpoints.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)     Each public method is one
 *                                                  routed endpoint in
 *                                                  appinfo/routes.php — CRUD,
 *                                                  tree/path, group, default
 *                                                  and publication actions.
 *                                                  The count is set by the
 *                                                  route table, not by logic
 *                                                  that could be extracted.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Class complexity is the
 *                                                  sum over those endpoints;
 *                                                  each one is individually
 *                                                  shallow (decode, authorize,
 *                                                  delegate to a service, map
 *                                                  exceptions to a status
 *                                                  code). No single method
 *                                                  carries the weight.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   The dashboard API
 *                                                  legitimately spans
 *                                                  multiple persistence
 *                                                  and service layers
 *                                                  (dashboard, share,
 *                                                  permission, factory,
 *                                                  versions) plus
 *                                                  personal +
 *                                                  group-shared +
 *                                                  resolution scopes —
 *                                                  splitting would
 *                                                  fragment the routing
 *                                                  surface.
 * @spec                                             openspec/specs/dashboards/spec.md
 *
 * @psalm-import-type LaunchPadDashboard from ResponseDefinitions
 * @psalm-import-type LaunchPadDashboardList from ResponseDefinitions
 * @psalm-import-type LaunchPadDashboardEnvelope from ResponseDefinitions
 * @psalm-import-type LaunchPadCreatedDashboard from ResponseDefinitions
 * @psalm-import-type LaunchPadPlacement from ResponseDefinitions
 * @psalm-import-type LaunchPadStatusOk from ResponseDefinitions
 */
class DashboardApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * The column counts a dashboard grid may have (the edit bar's Columns).
	 */
	private const GRID_COLUMN_OPTIONS = [6, 8, 12];

	/**
	 * Constructor
	 *
	 * @param IRequest             $request The request.
	 * @param DashboardApiServices $services The dashboard service and the optional quota, translation and
	 *                                       metadata services.
	 * @param DashboardApiAccess $access Holds the permissionService and history.
	 * @param ActorResponses $context Holds the actor, userId and responseHelper.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		IRequest $request,
		private readonly DashboardApiServices $services,
		private readonly DashboardApiAccess $access,
		private readonly ActorResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * List all personal dashboards for the current user.
	 *
	 * Backward compatible — this endpoint never returns group-shared
	 * dashboards (REQ-DASH-014). Use {@see self::visible()} for the
	 * unioned listing.
	 *
	 * @return JSONResponse The list of dashboards.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadDashboardList|list<LaunchPadDashboard>, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-17
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function list(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.list');
		if ($refusal !== null) {
			return $refusal;
		}

		$dashboards = $this->services->dashboardService->getUserDashboards(
			userId: $this->context->userId
		);
		$filters    = $this->metadataFilters();
		if ($filters !== [] && $this->services->metadataService !== null) {
			$dashboards = $this->services->metadataService->filterDashboards(
				dashboards: $dashboards,
				metadataFilters: $filters
			);
		}

		$serialized = $this->context->responseHelper->serializeList(entities: $dashboards);

		// Dashboard-quota-limits REQ-QUOTA-006: additive quota envelope on
		// the personal dashboards list. Response shape is
		// `{items: [...], quota: {...}}`. When the quota service is absent
		// (legacy test doubles) fall back to the bare-array contract.
		return $this->withQuota(serialized: $serialized);
	}//end list()

	/**
	 * List the deduplicated union of dashboards visible to the user.
	 *
	 * Returns personal + group-matching + default-group dashboards, each
	 * tagged with `source` (`'user'`, `'group'`, `'default'`).
	 * REQ-DASH-013.
	 *
	 * @return JSONResponse The visible dashboards.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadDashboardList|list<LaunchPadDashboard>, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	#[NoAdminRequired]
	public function visible(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.visible');
		if ($refusal !== null) {
			return $refusal;
		}

		$items = $this->filterByMetadata(
			items: $this->services->dashboardService->getVisibleToUser(
				userId: $this->context->userId
			)
		);

		// The same payload the main page carries in its initial state
		// (`{items, quota}`, or the bare list without the quota service).
		return $this->context->responseHelper->success(
			data: $this->payloads()->visibleList(userId: (string)$this->context->userId, items: $items)
		);
	}//end visible()

	/**
	 * Get the user's active dashboard with placements.
	 *
	 * @return JSONResponse The active dashboard data.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadDashboardEnvelope, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-18
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	#[NoAdminRequired]
	public function getActive(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.get-active');
		if ($refusal !== null) {
			return $refusal;
		}

		$envelope = $this->payloads()->active(userId: (string)$this->context->userId);
		if ($envelope === null) {
			return $this->context->responseHelper->success(
				data: ['error' => 'No dashboard available'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		return $this->context->responseHelper->success(data: $envelope);
	}//end getActive()

	/**
	 * Get a single dashboard by id with its placements + permission level.
	 *
	 * Powers the front-end's `switchDashboard` flow: clicking a row in the
	 * sidebar issues `GET /api/dashboard/{id}` and the response is the
	 * same envelope shape as {@see self::getActive()}, so the store can
	 * write `activeDashboard`, `widgetPlacements`, and `permissionLevel`
	 * with no per-source branching.
	 *
	 * Returns 404 (not 403) when the dashboard exists but is not visible
	 * to the caller — this matches the `getVisibleToUser` policy and
	 * intentionally does not leak existence (REQ-DASH-020 scenario
	 * "Cannot see what you cannot read").
	 *
	 * @param int $id The dashboard ID.
	 *
	 * @return JSONResponse The dashboard envelope (200) or
	 *                      `{'error': 'Not found'}` (404).
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadDashboardEnvelope, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-21
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.show');
		if ($refusal !== null) {
			return $refusal;
		}

		$result = $this->services->dashboardService->getDashboardForUser(
			dashboardId: $id,
			userId: $this->context->userId
		);

		if ($result === null) {
			// REQ-SHARE-003: a dashboard that exists but is neither owned by
			// nor shared with the caller is refused, not reported missing.
			if ($this->services->dashboardService->dashboardExists(dashboardId: $id) === true) {
				return $this->context->responseHelper->success(
					data: ['error' => 'Access denied'],
					statusCode: Http::STATUS_FORBIDDEN
				);
			}

			return $this->context->responseHelper->success(
				data: ['error' => 'Not found'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		$dashboard = $result['dashboard'];
		$isOwner = ($dashboard->getUserId() === $this->context->userId);
		$sharedBy = null;
		if ($isOwner === false) {
			$sharedBy = $dashboard->getUserId();
		}

		return $this->context->responseHelper->success(
			data: [
				'dashboard' => $dashboard->jsonSerialize(),
				'placements' => $this->renderedPlacements(
					placements: $result['placements'],
					permissionLevel: (string)$result['permissionLevel']
				),
				'permissionLevel' => $result['permissionLevel'],
				'isOwner' => $isOwner,
				'sharedBy' => $sharedBy,
				'hasVariants' => $this->hasVariants(uuid: (string)$dashboard->getUuid()),
				'breadcrumbs' => $this->visibleBreadcrumbs(dashboard: $dashboard, userId: $this->context->userId),
			]
		);
	}//end show()

	/**
	 * The placements a dashboard renders for the caller, with the widget
	 * visibility rules applied: a viewer does not get a hidden widget, the
	 * owner and editors get it marked `hiddenForViewers` (REQ-VIS-013). On
	 * every dashboard, the caller's own, a shared one or a group one, a
	 * widget the caller's role does not permit comes back with `isVisible` 0
	 * (REQ-RFP-001, REQ-RFP-003, REQ-RFP-004), so a viewer does not get it.
	 *
	 * @param array  $placements      The dashboard's placement entities.
	 * @param string $permissionLevel The caller's permission level on the dashboard.
	 *
	 * @return array The serialised placements.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	private function renderedPlacements(array $placements, string $permissionLevel): array {
		return $this->payloads()->renderedPlacements(
			placements: $placements,
			permissionLevel: $permissionLevel,
			userId: (string)$this->context->userId
		);
	}//end renderedPlacements()

	/**
	 * Whether a dashboard has more than one language version, so the page
	 * asks for the resolved variant only when it can differ.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return bool True with two or more variants.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	private function hasVariants(string $uuid): bool {
		return $this->payloads()->hasVariants(uuid: $uuid);
	}//end hasVariants()

	/**
	 * The builder of the visible list and the active envelope, shared with
	 * the main page's initial state.
	 *
	 * @return DashboardPayloads The builder.
	 *
	 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	private function payloads(): DashboardPayloads {
		return new DashboardPayloads(services: $this->services);
	}//end payloads()

	/**
	 * Keep only the visible entries whose dashboards match the
	 * `metadata[<key>]` filters of the request (REQ-MDFL-007), through
	 * MetadataService::filterDashboards().
	 *
	 * @param array<int, array{dashboard: \OCA\LaunchPad\Db\Dashboard, source: string}> $items Visible entries.
	 *
	 * @return array<int, array{dashboard: \OCA\LaunchPad\Db\Dashboard, source: string}> The matching entries.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	private function filterByMetadata(array $items): array {
		$filters = $this->metadataFilters();
		if ($this->services->metadataService === null || $filters === []) {
			return $items;
		}

		$kept = $this->services->metadataService->filterDashboards(
			dashboards: array_map(static fn (array $entry) => $entry['dashboard'], $items),
			metadataFilters: $filters
		);

		return array_values(
			array_filter(
				$items,
				static fn (array $entry): bool => in_array($entry['dashboard'], $kept, true)
			)
		);
	}//end filterByMetadata()

	/**
	 * The metadata filters of the request, sent as `metadata[<key>]=` or as
	 * `metadata.<key>=` (REQ-MDFL-007, REQ-MDUI-003).
	 *
	 * @return array<string, mixed> The filters by field key, empty without any.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	private function metadataFilters(): array {
		$filters = $this->request->getParam('metadata');
		if (is_array($filters) === false) {
			$filters = [];
		}

		$query = (string)parse_url($this->request->getRequestUri(), PHP_URL_QUERY);

		return array_replace($filters, self::filtersFromQueryString(query: $query));
	}//end metadataFilters()

	/**
	 * The `metadata.<key>=` and `metadata.<key>.<bound>=` filters of a raw
	 * query string, in the shape MetadataService::filterDashboards() takes
	 * (REQ-MDFL-007).
	 *
	 * PHP rewrites the dots of query parameter names to underscores, so
	 * these cannot be read from the parsed parameters.
	 *
	 * @param string $query The query string, without the leading `?`.
	 *
	 * @return array<string, string|array<string, string>> The filters by field key.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	private static function filtersFromQueryString(string $query): array {
		$filters = [];
		foreach (explode('&', $query) as $pair) {
			$parts = explode('=', $pair, 2);
			$name  = urldecode($parts[0]);
			if (count($parts) !== 2 || str_starts_with($name, 'metadata.') === false) {
				continue;
			}

			$path  = explode('.', substr($name, strlen('metadata.')));
			$value = urldecode($parts[1]);
			if ($path[0] === '' || count($path) > 2) {
				continue;
			}

			if (count($path) === 1) {
				$filters[$path[0]] = $value;
				continue;
			}

			$bounds = [];
			if (is_array($filters[$path[0]] ?? null) === true) {
				$bounds = $filters[$path[0]];
			}

			$bounds[$path[1]]   = $value;
			$filters[$path[0]] = $bounds;
		}//end foreach

		return $filters;
	}//end filtersFromQueryString()

	/**
	 * The breadcrumbs of a child dashboard, root to leaf. An ancestor the
	 * viewer may not see keeps its place but loses its uuid, name and slug,
	 * so the trail never leaks a hidden dashboard's name. A top-level
	 * dashboard has none.
	 *
	 * @param Dashboard $dashboard The dashboard being read.
	 * @param string $userId The reader.
	 *
	 * @return array<int, array<string, mixed>> The crumbs.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function visibleBreadcrumbs(Dashboard $dashboard, string $userId): array {
		$parent = $dashboard->getParentUuid();
		if ($parent === null || $parent === '') {
			return [];
		}

		$visible = $this->visibleUuids(userId: $userId);

		$crumbs = [];
		foreach ($this->access->history->treeService->computeBreadcrumbs(uuid: (string)$dashboard->getUuid()) as $crumb) {
			$crumbs[] = self::crumbFor(crumb: $crumb, visible: $visible);
		}

		return $crumbs;
	}//end visibleBreadcrumbs()

	/**
	 * The UUIDs of every dashboard visible to a user, keyed for `isset()`.
	 *
	 * @param string $userId The viewing user.
	 *
	 * @return array<string, true> The visible UUIDs.
	 */
	private function visibleUuids(string $userId): array {
		$visible = [];
		foreach ($this->services->dashboardService->getVisibleToUser(userId: $userId) as $entry) {
			$visible[(string)$entry['dashboard']->getUuid()] = true;
		}

		return $visible;
	}//end visibleUuids()

	/**
	 * One breadcrumb as the viewer may see it: as is when its dashboard is
	 * visible, otherwise a hidden placeholder.
	 *
	 * @param array<string, mixed> $crumb   The breadcrumb.
	 * @param array<string, true>  $visible The visible UUIDs.
	 *
	 * @return array<string, mixed> The breadcrumb to answer with.
	 */
	private static function crumbFor(array $crumb, array $visible): array {
		if (isset($visible[(string)($crumb['uuid'] ?? '')]) === true) {
			return $crumb + ['hidden' => false];
		}

		return ['uuid' => null, 'name' => null, 'slug' => null, 'hidden' => true];
	}//end crumbFor()

	/**
	 * Create a new dashboard.
	 *
	 * The optional `widgets` request parameter picks what the dashboard starts
	 * with: `starters` for the first-run starter widgets, `none` for no widgets,
	 * anything else for the default bundle.
	 *
	 * @param string|array<string, mixed>|null $name The dashboard name; a legacy client sends the whole body under it.
	 * @param string|null $description The description.
	 * @param string|null $icon The icon registry key (or NULL/empty to use the default).
	 * @param string|null $parentUuid Optional parent dashboard UUID
	 *                                (REQ-DASH-023). NULL ⇒ root.
	 * @param string|null $slug Optional caller-supplied slug
	 *                          (REQ-DASH-024). NULL ⇒ derive from
	 *                          the name.
	 * @param int|null $sortOrder Optional sibling sort order
	 *                            (REQ-DASH-029). NULL ⇒ 0.
	 *
	 * @return JSONResponse The created dashboard.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_CREATED, LaunchPadCreatedDashboard, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-16
	 */
	#[NoAdminRequired]
	public function create(
		$name = null,
		?string $description = null,
		?string $icon = null,
		?string $parentUuid = null,
		?string $slug = null,
		?int $sortOrder = null,
	): JSONResponse {
		$denial = $this->denyCreateRequest();
		if ($denial !== null) {
			return $denial;
		}

		$resolved = $this->resolveCreateParams(
			name: $name,
			flat: [
				'description' => $description,
				'icon'        => $icon,
				'parentUuid'  => $parentUuid,
				'slug'        => $slug,
				'sortOrder'   => $sortOrder,
				'widgets'     => $this->requestedWidgets(),
				'gridColumns' => $this->requestedGridColumns(),
			]
		);

		return $this->createFromParams(resolved: $resolved);
	}//end create()

	/**
	 * The refusal of a create request: the guard chain of
	 * {@see self::denyCreate()}, then the create permissions.
	 *
	 * @return JSONResponse|null The refusal, or null to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyCreateRequest(): ?JSONResponse {
		$denial = $this->denyCreate();
		if ($denial !== null) {
			return $denial;
		}

		return $this->checkCreatePermissions(
			userId: $this->context->userId
		);
	}//end denyCreateRequest()

	/**
	 * Create the dashboard from resolved parameters and answer 201, or map
	 * the service's refusal to its response.
	 *
	 * @param array<string, mixed> $resolved The resolved create parameters.
	 *
	 * @return JSONResponse The created dashboard, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function createFromParams(array $resolved): JSONResponse {
		try {
			return $this->createdResponse(dashboard: $this->createDashboardFrom(resolved: $resolved));
		} catch (QuotaExceededException $e) {
			// Dashboard-quota-limits REQ-QUOTA-002: the user is at their
			// dashboard limit — HTTP 409 with the structured body.
			return $this->context->responseHelper->quotaExceeded(exception: $e);
		} catch (InvalidArgumentException $e) {
			// REQ-DASH-023..029: parent / slug / depth / cycle violations
			// surface as HTTP 400 with the validation message verbatim.
			return $this->context->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end createFromParams()

	/**
	 * Create the signed-in user's dashboard, seeded with the default
	 * widgets, from resolved parameters.
	 *
	 * @param array<string, mixed> $resolved The resolved create parameters.
	 *
	 * @return Dashboard The created dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function createDashboardFrom(array $resolved): Dashboard {
		return $this->services->dashboardService->createDashboard(
			userId: $this->context->userId,
			name: $resolved['name'],
			options: $this->seedOptions(widgets: $resolved['widgets'] ?? null) + array_diff_key($resolved, ['name' => true, 'widgets' => true])
		);
	}//end createDashboardFrom()

	/**
	 * The create request's `widgets` choice, when it is a string.
	 *
	 * @return string|null The choice, or null.
	 *
	 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
	 */
	private function requestedWidgets(): ?string {
		return self::stringOrNull(value: $this->request->getParam(key: 'widgets'));
	}//end requestedWidgets()

	/**
	 * The seed options for a create's `widgets` choice: the starter widgets,
	 * no widgets, or the default bundle.
	 *
	 * @param mixed $widgets The requested choice.
	 *
	 * @return array{seedDefaults: bool, seedSet: string, menuAppIds?: array<int, string>} The seed options;
	 *   the starters carry the user's app menu, which picks their app tiles.
	 *
	 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
	 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-a-first-dashboard-starts-with-a-tile-per-most-used-app-req-apptile-003
	 */
	private function seedOptions(mixed $widgets): array {
		if ($widgets === 'none') {
			return ['seedDefaults' => false, 'seedSet' => DashboardService::SEED_SET_DEFAULT];
		}

		if ($widgets === DashboardService::SEED_SET_STARTERS) {
			$menu = $this->access->appMenu?->discover() ?? [];
			return [
				'seedDefaults' => true,
				'seedSet'      => DashboardService::SEED_SET_STARTERS,
				'menuAppIds'   => array_map(static fn (array $app): string => $app['appId'], $menu),
			];
		}

		return ['seedDefaults' => true, 'seedSet' => DashboardService::SEED_SET_DEFAULT];
	}//end seedOptions()

	/**
	 * Answer 201 with a newly-created dashboard and its placements.
	 *
	 * The newly-created dashboard ships with a default widget
	 * bundle (Conduction + Sendent + Nextcloud tiles + a Files
	 * widget) seeded by the service. Returning the placements
	 * here matches the `getActive()` envelope so the store can
	 * populate `widgetPlacements` without an extra round-trip.
	 *
	 * @param Dashboard $dashboard The created dashboard.
	 *
	 * @return JSONResponse The 201 response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function createdResponse(Dashboard $dashboard): JSONResponse {
		$placements = $this->services->dashboardService->findPlacements(
			dashboardId: $dashboard->getId()
		);

		return $this->context->responseHelper->success(
			data: [
				'dashboard' => $dashboard->jsonSerialize(),
				'placements' => $this->context->responseHelper->serializeList(
					entities: $placements
				),
			],
			statusCode: Http::STATUS_CREATED
		);
	}//end createdResponse()

	/**
	 * Resolve the authentication / authorisation guard chain for
	 * {@see self::create()}.
	 *
	 * Order is load-bearing. REQ-ASET-003 (extended): the admin gating
	 * runs BEFORE any request-body handling so the response envelope is
	 * the stable `personal_dashboards_disabled` shape no matter what the
	 * body looked like.
	 *
	 * @return JSONResponse|NULL The refusal, or NULL to proceed.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-16
	 */
	private function denyCreate(): ?JSONResponse {
		// L3: wire the create action so the matrix entry is enforced —
		// consistent with all other mutation endpoints (ADR-023).
		$refusal = $this->actionRefusal(action: 'dashboard.create');
		if ($refusal !== null) {
			return $refusal;
		}

		// REQ-ASET-003: the switch never restricts a Nextcloud administrator.
		if ($this->services->dashboardService->isAdmin(userId: (string)$this->context->userId) === true) {
			return null;
		}

		try {
			$this->services->dashboardService->assertPersonalDashboardsAllowed();
		} catch (PersonalDashboardsDisabledException $e) {
			return $this->personalDashboardsDisabled(exception: $e);
		}

		return null;
	}//end denyCreate()

	/**
	 * Update a dashboard.
	 *
	 * @param int $id The dashboard ID.
	 * @param string|null $name The name.
	 * @param string|null $description The description.
	 * @param list<array<string, mixed>>|null $placements The placements.
	 * @param string|null $icon The icon registry key, URL, or NULL to leave unchanged.
	 * @param string|null $parentUuid Optional new parent UUID (REQ-DASH-023);
	 *                                explicit empty string clears the
	 *                                parent (re-roots the dashboard).
	 * @param string|null $slug Optional new slug (REQ-DASH-024).
	 * @param int|null $sortOrder Optional new sort order (REQ-DASH-029).
	 *
	 * The optional `defaultLinkTarget` body field sets the default tile link
	 * target; an empty string clears it ({@see self::requestedLinkTarget()}).
	 * The optional `hierarchies` body field sets the hierarchy descriptors of
	 * the filter bus; an empty list clears them (REQ-CWF-011). The optional
	 * `gridColumns` body field sets the number of grid columns, 6, 8 or 12
	 * ({@see self::requestedGridColumns()}, REQ-DASH-038).
	 *
	 * The look travels in the same body and is read by {@see self::lookParams()}:
	 * `themeAccent` and `themeBackgroundColor` (`#rrggbb`, empty to follow the
	 * theme, REQ-THM-001), `themeBackgroundResource` (a resource file name,
	 * empty for none) and `customCss` (administrator CSS, empty to clear,
	 * REQ-THM-003).
	 *
	 * @return JSONResponse The updated dashboard.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, array{dashboard: LaunchPadDashboard}, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-19
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	#[NoAdminRequired]
	public function update(
		int $id,
		?string $name = null,
		?string $description = null,
		?array $placements = null,
		?string $icon = null,
		?string $parentUuid = null,
		?string $slug = null,
		?int $sortOrder = null,
	): JSONResponse {
		// REQ-THM-001: the look is not metadata; it needs the same permission as the layout,
		// and so does the number of grid columns (REQ-DASH-038).
		$look = $this->lookParams() + ['gridColumns' => $this->requestedGridColumns()];
		// REQ-FTR-006: the footer override is the owner's, like the name.
		$footer = $this->footerParams();
		$defaultLinkTarget = $this->requestedLinkTarget();
		// Read raw, so a value that is no list is refused with 400 by the service.
		$hierarchies = $this->request->getParam(key: 'hierarchies');
		$metadataOnly = self::isMetadataOnly(placements: $placements, defaultLinkTarget: $defaultLinkTarget, hierarchies: $hierarchies)
			&& array_filter($look, static fn ($value): bool => $value !== null) === [];
		$touchesMetadata = array_filter(
			[$name, $description, $icon, $parentUuid, $slug, $sortOrder, ...array_values($footer)],
			static fn ($value): bool => $value !== null
		) !== [];
		$denial = $this->denyUpdate(dashboardId: $id, metadataOnly: $metadataOnly, touchesMetadata: $touchesMetadata)
			?? $this->denyCustomCss(customCss: $look['customCss']);
		if ($denial !== null) {
			return $denial;
		}

		return $this->applyUpdate(
			dashboardId: $id,
			fields: [
				'name'        => $name,
				'description' => $description,
				'placements'  => $placements,
				'icon'        => $icon,
				'parentUuid'  => $parentUuid,
				'slug'        => $slug,
				'sortOrder'   => $sortOrder,
				'defaultLinkTarget' => $defaultLinkTarget,
				'hierarchies' => $hierarchies,
			] + $footer + $look
		);
	}//end update()

	/**
	 * Store an edit-mode draft: the grid columns and the full placement list
	 * in one transaction with its version (REQ-EDIT-002, REQ-VERS-010). One
	 * refused change answers 403, a colleague's newer widget 409 with those
	 * widgets as saved now; either way nothing is stored.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param array<string, mixed> $fields      The update fields: `gridColumns` and the full `placements` list.
	 *
	 * @return JSONResponse The stored dashboard and placements, or the refusal.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function saveDraft(int $dashboardId, array $fields): JSONResponse {
		if ($this->context->userId === null || $this->services->widgets->draftSave === null) {
			return $this->context->responseHelper->unauthorized();
		}

		try {
			$saved = $this->services->widgets->draftSave->save(
				draft: new DashboardDraft(
					dashboardId: $dashboardId,
					userId: $this->context->userId,
					gridColumns: $fields['gridColumns'],
					placements: array_values($fields['placements'])
				)
			);
		} catch (DraftConflictException $e) {
			return new JSONResponse(
				data: [
					'status'     => 'error',
					'error'      => 'stale',
					'message'    => $e->getMessage(),
					'placements' => array_map(static fn ($placement): array => $placement->jsonSerialize(), $e->getCurrent()),
				],
				statusCode: Http::STATUS_CONFLICT
			);
		} catch (ForbiddenException $e) {
			return $this->context->responseHelper->forbidden(message: $e->getMessage());
		} catch (InvalidArgumentException $e) {
			return $this->context->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try

		$body = [
			'dashboard'  => $saved['dashboard']->jsonSerialize(),
			'placements' => array_map(static fn ($placement): array => $placement->jsonSerialize(), $saved['placements']),
		];
		$warnings = $this->cleanupRemovedFields(removed: $saved['removed'] ?? []);
		if ($warnings !== []) {
			$body['warnings'] = $warnings;
		}

		return $this->context->responseHelper->success(data: $body);
	}//end saveDraft()

	/**
	 * Take the calculated fields of the widgets a draft Save removed off
	 * their source schema, as the single-widget DELETE does
	 * (widget-calculated-fields, task 28). Runs after the save, so a
	 * failing source leaves a warning instead of undoing the Save.
	 *
	 * @param list<\OCA\LaunchPad\Db\WidgetPlacement> $removed The removed widgets as they were stored.
	 *
	 * @return list<mixed> The cleanup warnings.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function cleanupRemovedFields(array $removed): array {
		$fields = $this->services->widgets->fields;
		if ($fields === null) {
			return [];
		}

		$warnings = [];
		foreach ($removed as $placement) {
			if ($fields->hasServerFields(fields: $placement->getCalculatedFieldsArray()) === false) {
				continue;
			}

			$warnings = [
				...$warnings,
				...$fields->cleanupServerFields(
					widget: [
						'id'     => (string) $placement->getId(),
						'source' => (new CalculatedFieldSources())->sourceKey(content: $placement->getContentArray()),
					]
				),
			];
		}

		return $warnings;
	}//end cleanupRemovedFields()

	/**
	 * The footer override fields of an update body (REQ-FTR-006): a field
	 * that is absent or not a string is null and leaves the dashboard alone.
	 *
	 * @return array{dashboardFooterMode: ?string, dashboardFooterHtml: ?string}
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	private function footerParams(): array {
		$footer = [];
		foreach (['dashboardFooterMode', 'dashboardFooterHtml'] as $field) {
			$value = $this->request->getParam(key: $field);
			$footer[$field] = null;
			if (is_string($value) === true) {
				$footer[$field] = $value;
			}
		}

		return $footer;
	}//end footerParams()

	/**
	 * The look fields of an update body, by name, in patch order.
	 *
	 * A field that is absent, or that is not a string, is null and leaves the
	 * dashboard's value alone; an empty string clears it (REQ-THM-001, REQ-THM-003).
	 *
	 * @return array{themeAccent: ?string, themeBackgroundColor: ?string, themeBackgroundResource: ?string, customCss: ?string}
	 *
	 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	private function lookParams(): array {
		$look = [];
		foreach (['themeAccent', 'themeBackgroundColor', 'themeBackgroundResource', 'customCss'] as $field) {
			$value = $this->request->getParam(key: $field);
			$look[$field] = null;
			if (is_string($value) === true) {
				$look[$field] = $value;
			}
		}

		return $look;
	}//end lookParams()

	/**
	 * The refusal of custom CSS from a user who is no administrator.
	 *
	 * REQ-THM-003: custom CSS is an administrator tool, even on a dashboard
	 * the user may edit.
	 *
	 * @param string|null $customCss The CSS of the update, or null when it sends none.
	 *
	 * @return JSONResponse|null The refusal, or null to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyCustomCss(?string $customCss): ?JSONResponse {
		if ($customCss === null || $this->services->dashboardService->isAdmin(userId: (string)$this->context->userId) === true) {
			return null;
		}

		return $this->context->responseHelper->forbidden();
	}//end denyCustomCss()

	/**
	 * The number of grid columns the update body asks for (the edit bar's Columns).
	 *
	 * @return int|null The count, or null when absent or not a number.
	 *
	 * @spec openspec/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
	 */
	private function requestedGridColumns(): ?int {
		$raw   = $this->request->getParam(key: 'gridColumns', default: 0);
		$count = 0;
		if (is_numeric($raw) === true) {
			$count = (int) $raw;
		}

		if ($count === 0) {
			return null;
		}

		return $count;
	}//end requestedGridColumns()

	/**
	 * The default tile link target the update body asks for.
	 *
	 * @return string|null The value, or null when absent or not a string.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md#requirement-a-dashboard-sets-the-default-target-for-its-tiles-req-tob-002
	 */
	private function requestedLinkTarget(): ?string {
		$raw = $this->request->getParam(key: 'defaultLinkTarget');
		if (is_string($raw) === false) {
			return null;
		}

		return $raw;
	}//end requestedLinkTarget()

	/**
	 * Whether an update leaves the placements, the way tiles open and the
	 * hierarchies alone.
	 *
	 * How tiles open and which hierarchies the filter bus uses are dashboard
	 * edits, not metadata, so they need the full edit permission like a
	 * placement change.
	 *
	 * @param array|null  $placements        The placements, or null.
	 * @param string|null $defaultLinkTarget The default tile link target, or null.
	 * @param mixed       $hierarchies       The hierarchy descriptors as sent, or null.
	 *
	 * @return bool True for a metadata-only update.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md#requirement-a-dashboard-sets-the-default-target-for-its-tiles-req-tob-002
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	private static function isMetadataOnly(?array $placements, ?string $defaultLinkTarget, mixed $hierarchies): bool {
		return $placements === null && $defaultLinkTarget === null && $hierarchies === null;
	}//end isMetadataOnly()

	/**
	 * Resolve the guard chain for {@see self::update()}.
	 *
	 * Order is load-bearing: the session, then the action check, then the
	 * injected user id, then the permission for the kind of update.
	 *
	 * @param int  $dashboardId     The dashboard ID.
	 * @param bool $metadataOnly    Whether the update leaves the placements alone.
	 * @param bool $touchesMetadata Whether the update changes a metadata field.
	 *
	 * @return JSONResponse|null The refusal, or null to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/specs/dashboard-locking/spec.md#requirement-req-lock-008-lock-lifecycle-on-dashboard-deletion
	 */
	private function denyUpdate(int $dashboardId, bool $metadataOnly, bool $touchesMetadata): ?JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.update');
		if ($refusal !== null) {
			return $refusal;
		}

		if ($this->mayUpdate(dashboardId: $dashboardId, metadataOnly: $metadataOnly, touchesMetadata: $touchesMetadata) === false) {
			// REQ-LOCK-008: a save on a dashboard that was deleted meanwhile
			// fails with "Dashboard not found", not with a refusal.
			if ($this->services->dashboardService->dashboardExists(dashboardId: $dashboardId) === false) {
				return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
			}

			return $this->context->responseHelper->forbidden();
		}

		return null;
	}//end denyUpdate()

	/**
	 * Whether the user may make this kind of update.
	 *
	 * REQ-PERM-007: Metadata-only updates (name, description, icon) are
	 * allowed for all permission levels. Widget/tile/layout changes
	 * require add_only or full permission; an update that does both needs both.
	 *
	 * @param int  $dashboardId     The dashboard ID.
	 * @param bool $metadataOnly    Whether the update leaves the placements alone.
	 * @param bool $touchesMetadata Whether the update changes a metadata field.
	 *
	 * @return bool True when the update is allowed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/specs/admin-roles/spec.md#requirement-req-role-002-dashboard-editor-role-definition
	 */
	private function mayUpdate(int $dashboardId, bool $metadataOnly, bool $touchesMetadata): bool {
		$mayEditMetadata = fn (): bool => $this->access->permissionService->canEditDashboardMetadata(
			userId: $this->context->userId,
			dashboardId: $dashboardId
		) !== false;

		if ($metadataOnly === true) {
			return $mayEditMetadata();
		}

		return $this->access->permissionService->canEditDashboard(
			userId: $this->context->userId,
			dashboardId: $dashboardId
		) !== false && ($touchesMetadata === false || $mayEditMetadata());
	}//end mayUpdate()

	/**
	 * Apply an allowed update and answer with the saved dashboard.
	 *
	 * Every successful save makes one version of the dashboard as saved, in
	 * the save's transaction (REQ-VERS-010/011). Parent, slug, depth and cycle
	 * violations answer 400 (REQ-DASH-023..029).
	 *
	 * @param int                  $dashboardId The dashboard ID.
	 * @param array<string, mixed> $fields      The nullable update fields by name, in patch order.
	 *
	 * @return JSONResponse The updated dashboard, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboard-versioning/spec.md#requirement-every-save-makes-one-version-of-the-whole-dashboard-req-vers-010
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function applyUpdate(int $dashboardId, array $fields): JSONResponse {
		// REQ-EDIT-002: an edit-mode Save sends its whole draft, marked as such,
		// so the older position-only `placements` patch keeps its meaning.
		if ($this->request->getParam(key: 'draft') === true && is_array($fields['placements']) === true) {
			return $this->saveDraft(dashboardId: $dashboardId, fields: $fields);
		}

		try {
			$data = $this->buildUpdateData(fields: $fields);
			$save = fn (): Dashboard => $this->services->dashboardService->updateDashboard(
				dashboardId: $dashboardId,
				userId: $this->context->userId,
				data: $data
			);

			// REQ-VERS-010/011: every successful save makes one version of
			// the dashboard as saved, without debounce, in the same
			// transaction; a failed save leaves no version.
			$dashboard = $this->saveWithVersion(dashboardId: $dashboardId, save: $save);

			return $this->context->responseHelper->success(
				data: ['dashboard' => $dashboard->jsonSerialize()]
			);
		} catch (ContrastTooLowException $e) {
			// REQ-THM-002: the measured ratio travels with the refusal.
			return new JSONResponse(
				data: ['status' => 'error', 'error' => 'contrast_too_low', 'ratio' => $e->getRatio(), 'message' => $e->getMessage()],
				statusCode: Http::STATUS_BAD_REQUEST
			);
		} catch (InvalidArgumentException $e) {
			// REQ-DASH-023..029: parent / slug / depth / cycle violations
			// surface as HTTP 400 with the validation message verbatim.
			return $this->context->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (ForbiddenException $e) {
			return $this->context->responseHelper->forbidden();
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end applyUpdate()

	/**
	 * Delete a dashboard.
	 *
	 * Honours the cascade-delete guard from REQ-DASH-030: when the
	 * dashboard has children the request MUST include `?cascade=true`
	 * (case-insensitive) — otherwise the response is HTTP 409 with the
	 * child count so the UI can surface a confirmation.
	 *
	 * @param int $id The dashboard ID.
	 *
	 * @return JSONResponse The deletion confirmation.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadStatusOk, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-20
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function delete(int $id): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.delete');
		if ($refusal !== null) {
			return $refusal;
		}

		if ($this->access->permissionService->canDeleteDashboards(userId: (string)$this->context->userId) === false) {
			return $this->context->responseHelper->forbidden();
		}

		$cascade = $this->resolveCascadeFlag();

		try {
			$this->services->dashboardService->deleteDashboard(
				dashboardId: $id,
				userId: $this->context->userId,
				cascade: $cascade
			);

			return $this->context->responseHelper->success(data: ['status' => 'ok']);
		} catch (DashboardHasChildrenException $e) {
			// REQ-DASH-030: stable 409 envelope with the child count so
			// the frontend can render "Delete N children?" before
			// retrying with cascade=true.
			return new JSONResponse(
				data: [
					'status' => 'error',
					'error' => DashboardHasChildrenException::ERROR_CODE,
					'message' => $e->getMessage(),
					'childCount' => $e->getChildCount(),
				],
				statusCode: Http::STATUS_CONFLICT
			);
		} catch (ForbiddenException $e) {
			return $this->context->responseHelper->forbidden();
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end delete()

	/**
	 * GET /api/dashboards/tree — return the nested dashboard tree scoped
	 * to the calling user's visible dashboards (REQ-DASH-026).
	 *
	 * Each node carries `{uuid, name, slug, sortOrder, children: [...]}`.
	 * Only nodes for dashboards that `DashboardService::getVisibleToUser`
	 * resolves for the caller are included — personal drafts owned by
	 * other users are not enumerable (C1 fix: REQ-DASH-026 + REQ-PERM-001).
	 *
	 * @return JSONResponse The nested tree.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function tree(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.tree');
		if ($refusal !== null) {
			return $refusal;
		}

		// C1 fix: build the visibility set for the calling user, then ask
		// the tree service for the structural tree filtered to those UUIDs.
		// This prevents cross-user IDOR via UUID enumeration through the tree.
		$visible = $this->services->dashboardService->getVisibleToUser(
			userId: $this->context->userId
		);
		$visibleUuids = [];
		foreach ($visible as $entry) {
			$uuid = $entry['dashboard']->getUuid();
			if ($uuid !== null && $uuid !== '') {
				$visibleUuids[$uuid] = true;
			}
		}

		$tree = $this->access->history->treeService->getFilteredTree(
			visibleUuids: $visibleUuids
		);

		return $this->context->responseHelper->success(data: $tree);
	}//end tree()

	/**
	 * GET /api/dashboards/by-path/{path} — resolve a slug-chain path
	 * (REQ-DASH-027).
	 *
	 * Returns the matching dashboard with its computed `path` and
	 * `breadcrumbs` (REQ-DASH-025) attached. Responds with 404 (not 403)
	 * on any miss — including visibility misses — to avoid confirming that
	 * a given slug exists to an unauthorised caller.
	 *
	 * C2 fix (REQ-DASH-027 + REQ-PERM-001): after slug resolution the
	 * resolved dashboard is checked via PermissionService; callers with no
	 * view access receive the same 404 they would get for an unknown slug.
	 *
	 * @param string $path The slug-joined path captured from the URL
	 *                     (the `{path}` placeholder is regex-allowed
	 *                     to include slashes — see `appinfo/routes.php`).
	 *
	 * @return JSONResponse The dashboard payload, or a 404 envelope.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function byPath(string $path = ''): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.by-path');
		if ($refusal !== null) {
			return $refusal;
		}

		if ($path === '') {
			$path = (string)$this->request->getParam(key: 'path', default: '');
		}

		$dashboard = $this->access->history->treeService->resolvePath(path: $path);
		if ($dashboard === null) {
			return new JSONResponse(
				data: [
					'status' => 'error',
					'error' => 'not_found',
					'message' => 'Dashboard not found at path',
				],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		// C2 fix: verify the caller can see this dashboard. Return 404
		// (not 403) to avoid leaking that the slug exists at all.
		$dashboardId = (int)$dashboard->getId();
		if ($this->access->permissionService->canViewDashboard(
			userId: $this->context->userId,
			dashboardId: $dashboardId
		) === false
		) {
			return new JSONResponse(
				data: [
					'status' => 'error',
					'error' => 'not_found',
					'message' => 'Dashboard not found at path',
				],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		$uuid = (string)$dashboard->getUuid();
		$serialised = $dashboard->jsonSerialize();
		$serialised['path'] = $this->access->history->treeService->computePath(uuid: $uuid);
		$serialised['breadcrumbs'] = $this->access->history->treeService->computeBreadcrumbs(
			uuid: $uuid
		);

		return $this->context->responseHelper->success(
			data: ['dashboard' => $serialised]
		);
	}//end byPath()

	/**
	 * GET /api/dashboards/{uuid}/path — return a dashboard's canonical
	 * slug-chain path.
	 *
	 * Used by the frontend after every sidebar switch to keep the
	 * browser URL in sync with the active dashboard. The path is the
	 * leading-slash slug-chain returned by
	 * {@see DashboardTreeService::computePath()}; an empty string means
	 * the UUID does not resolve OR the dashboard has no slug (legal —
	 * NULL slugs are simply unaddressable by path), and the frontend
	 * treats either case as "leave the URL alone".
	 *
	 * @param string $uuid Dashboard UUID captured from the URL.
	 *
	 * @return JSONResponse `{path: string}` envelope (always 200 when
	 *                      authorised — the empty-path case is a valid
	 *                      response shape the caller distinguishes
	 *                      client-side).
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function computePath(string $uuid = ''): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.compute-path');
		if ($refusal !== null) {
			return $refusal;
		}

		if ($uuid === '') {
			return $this->badRequestWithCode(code: 'missing_uuid', message: 'UUID is required');
		}

		return $this->context->responseHelper->success(
			data: ['path' => $this->access->history->treeService->computePath(uuid: $uuid)]
		);
	}//end computePath()

	/**
	 * Activate a dashboard.
	 *
	 * @param int $id The dashboard ID.
	 *
	 * @return JSONResponse The activated dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function activate(int $id): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.activate');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$dashboard = $this->services->dashboardService->activateDashboard(
				dashboardId: $id,
				userId: $this->context->userId
			);

			return $this->context->responseHelper->success(
				data: ['dashboard' => $dashboard->jsonSerialize()]
			);
		} catch (ForbiddenException $e) {
			// REQ-DASH-004: another user's dashboard is refused with 403.
			return $this->context->responseHelper->forbidden();
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}
	}//end activate()

	/**
	 * List the group-shared dashboards in a single group.
	 *
	 * Any logged-in user may list. REQ-DASH-014.
	 *
	 * @param string $groupId The group ID.
	 *
	 * @return JSONResponse The list of group-shared dashboards.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function listGroup(string $groupId): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.list-group');
		if ($refusal !== null) {
			return $refusal;
		}

		// H1: verify the caller is a member of the requested group (or
		// admin) before returning its dashboards — mirrors the group-
		// membership check in PermissionService::resolveAccessLevel.
		if ($this->services->dashboardService->userCanAccessGroup(
			userId: $this->context->userId,
			groupId: $groupId
		) === false
		) {
			return $this->context->responseHelper->forbidden();
		}

		$dashboards = $this->services->dashboardService->listGroupDashboards(
			groupId: $groupId
		);

		// M5: strip internal identity fields (userId, groupId, targetGroups)
		// from group-shared dashboard payloads returned to non-owner viewers.
		$viewerData = array_map(
			static fn ($dashboard) => $dashboard->toViewerArray(),
			$dashboards
		);

		return $this->context->responseHelper->success(data: $viewerData);
	}//end listGroup()

	/**
	 * Create a new group-shared dashboard.
	 *
	 * For a Nextcloud administrator, a LaunchPad Admin, or an Editor who is
	 * in the group ({@see GroupDashboardCreation::allows()},
	 * REQ-DASH-014); anyone else gets HTTP 403 before the request body is
	 * read.
	 *
	 * @param string $groupId The group ID.
	 * @param mixed $name The dashboard name (or {name,...}
	 *                    dict as the body).
	 * @param string|null $description The dashboard description.
	 *
	 * @return JSONResponse The created dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function createGroup(
		string $groupId,
		$name = null,
		?string $description = null,
	): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		if ($this->access->groupCreation?->allows(
			userId: $this->context->userId,
			groupId: $groupId
		) !== true
		) {
			return $this->context->responseHelper->forbidden();
		}

		$resolved = $this->resolveCreateParams(
			name: $name,
			flat: ['description' => $description]
		);

		try {
			$dashboard = $this->services->dashboardService->createGroupShared(
				actorUserId: $this->context->userId,
				groupId: $groupId,
				fields: ['name' => $resolved['name'], 'description' => $resolved['description']]
			);

			return $this->context->responseHelper->success(
				data: ['dashboard' => $dashboard->jsonSerialize()],
				statusCode: Http::STATUS_CREATED
			);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}
	}//end createGroup()

	/**
	 * Get a single group-shared dashboard with placements.
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return JSONResponse The dashboard payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function getGroup(
		string $groupId,
		string $uuid,
	): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.get-group');
		if ($refusal !== null) {
			return $refusal;
		}

		// H1: verify the caller is a member of the requested group (or
		// admin) before fetching the dashboard payload.
		if ($this->services->dashboardService->userCanAccessGroup(
			userId: $this->context->userId,
			groupId: $groupId
		) === false
		) {
			return $this->context->responseHelper->forbidden();
		}

		try {
			$dashboard = $this->services->dashboardService->findGroupDashboard(
				groupId: $groupId,
				uuid: $uuid
			);
		} catch (DoesNotExistException) {
			// ADR-005: do not leak raw exception messages to clients.
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		// M5: strip internal identity fields from viewer-facing payload.
		return $this->context->responseHelper->success(
			data: ['dashboard' => $dashboard->toViewerArray()]
		);
	}//end getGroup()

	/**
	 * Update a group-shared dashboard. Admin-only.
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 * @param string|null $name The new name.
	 * @param string|null $description The new description.
	 * @param int|null $gridColumns The new grid column count.
	 * @param array|null $placements Updated placements.
	 *
	 * @return JSONResponse The updated dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateGroup(
		string $groupId,
		string $uuid,
		?string $name = null,
		?string $description = null,
		?int $gridColumns = null,
		?array $placements = null,
	): JSONResponse {
		$patch = $this->buildGroupUpdateData(name: $name, description: $description, gridColumns: $gridColumns, placements: $placements);

		return $this->updateGroupDashboard(target: new GroupDashboardTarget(groupId: $groupId, uuid: $uuid), patch: $patch);
	}//end updateGroup()

	/**
	 * Apply a group-shared patch for the signed-in user.
	 *
	 * @param GroupDashboardTarget $target The group and dashboard UUID.
	 * @param array<string, mixed> $patch  The non-null patch fields.
	 *
	 * @return JSONResponse The updated dashboard, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function updateGroupDashboard(GroupDashboardTarget $target, array $patch): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		return $this->runGroupMutation(
			mutation: fn (): JSONResponse => $this->dashboardResponse(
				dashboard: $this->saveGroupWithVersion(target: $target, patch: $patch)
			)
		);
	}//end updateGroupDashboard()

	/**
	 * Save a group-shared patch with its version (REQ-VERS-010/011): the
	 * group dashboard's settings save makes one version, in the save's
	 * transaction, like the personal dashboard's.
	 *
	 * @param GroupDashboardTarget $target The group and dashboard UUID.
	 * @param array<string, mixed> $patch  The non-null patch fields.
	 *
	 * @return Dashboard The saved dashboard.
	 *
	 * @throws DoesNotExistException When the group has no dashboard with that UUID.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboard-versioning/spec.md#requirement-every-save-makes-one-version-of-the-whole-dashboard-req-vers-010
	 */
	private function saveGroupWithVersion(GroupDashboardTarget $target, array $patch): Dashboard {
		$service = $this->services->dashboardService;
		$dashboardId = (int) $service->findGroupDashboard(groupId: $target->groupId, uuid: $target->uuid)->getId();

		return $this->saveWithVersion(
			dashboardId: $dashboardId,
			save: fn (): Dashboard => $service->updateGroupShared(
				actorUserId: (string) $this->context->userId,
				groupId: $target->groupId,
				uuid: $target->uuid,
				patch: $patch
			)
		);
	}//end saveGroupWithVersion()

	/**
	 * Run a group-shared mutation, answering an unknown dashboard with 404
	 * and any other failure with the generic error.
	 *
	 * @param callable(): JSONResponse $mutation The mutation and its answer.
	 *
	 * @return JSONResponse The mutation's answer, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function runGroupMutation(callable $mutation): JSONResponse {
		try {
			return $mutation();
		} catch (DoesNotExistException) {
			// ADR-005: do not leak raw exception messages to clients.
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end runGroupMutation()

	/**
	 * Answer 200 with one dashboard.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return JSONResponse The response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function dashboardResponse(Dashboard $dashboard): JSONResponse {
		return $this->context->responseHelper->success(
			data: ['dashboard' => $dashboard->jsonSerialize()]
		);
	}//end dashboardResponse()

	/**
	 * Delete a group-shared dashboard. Admin-only.
	 *
	 * Returns HTTP 400 when the last-in-group guard rejects the delete
	 * (REQ-DASH-014).
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return JSONResponse The status payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteGroup(
		string $groupId,
		string $uuid,
	): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		try {
			$this->services->dashboardService->deleteGroupShared(
				actorUserId: $this->context->userId,
				groupId: $groupId,
				uuid: $uuid
			);

			return $this->context->responseHelper->success(data: ['status' => 'ok']);
		} catch (DoesNotExistException) {
			// ADR-005: do not leak raw exception messages to clients.
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			// REQ-DASH-014: the last-in-group guard answers with its reason.
			if ($e->getMessage() === DashboardService::ERR_LAST_IN_GROUP) {
				return $this->failure(message: DashboardService::ERR_LAST_IN_GROUP, statusCode: Http::STATUS_BAD_REQUEST);
			}

			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end deleteGroup()

	/**
	 * Promote a single group-shared dashboard to the group's default.
	 *
	 * Admin-only — enforced by the `#[AuthorizedAdminSetting]` attribute.
	 * The body payload is `{"uuid": "..."}`. Returns 404 when the uuid
	 * does not belong to the given groupId. REQ-DASH-015.
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string|null $uuid The dashboard UUID from the body.
	 *
	 * @return JSONResponse The status payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function setGroupDefault(
		string $groupId,
		?string $uuid = null,
	): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		if ($uuid === null || $uuid === '') {
			return $this->missingUuid();
		}

		return $this->runGroupMutation(
			mutation: fn (): JSONResponse => $this->markGroupDefault(target: new GroupDashboardTarget(groupId: $groupId, uuid: $uuid))
		);
	}//end setGroupDefault()

	/**
	 * The 400 answer for a group-default request without a UUID.
	 *
	 * @return JSONResponse The response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function missingUuid(): JSONResponse {
		return $this->context->responseHelper->error(
			exception: new InvalidArgumentException(
				'Missing required field: uuid'
			),
			statusCode: Http::STATUS_BAD_REQUEST
		);
	}//end missingUuid()

	/**
	 * Make a group-shared dashboard its group's default and answer 200.
	 *
	 * @param GroupDashboardTarget $target The group and dashboard UUID.
	 *
	 * @return JSONResponse The response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function markGroupDefault(GroupDashboardTarget $target): JSONResponse {
		$this->services->dashboardService->setGroupDefault(
			actorUserId: $this->context->userId,
			groupId: $target->groupId,
			uuid: $target->uuid
		);

		return $this->context->responseHelper->success(
			data: [
				'status' => 'ok',
				'groupId' => $target->groupId,
				'uuid' => $target->uuid,
			]
		);
	}//end markGroupDefault()

	/**
	 * Persist the user's active-dashboard preference.
	 *
	 * Accepts any UUID string (including non-existent UUIDs — the resolver's
	 * stale-pref path handles invalid values on next render). Empty string
	 * clears the preference. REQ-DASH-019.
	 *
	 * @param string|null $uuid The dashboard UUID from the request body, or
	 *                          empty string to clear.
	 *
	 * @return JSONResponse HTTP 200 `{status: 'success'}` on success; 401
	 *                      when the session has no user.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function setActiveDashboard(?string $uuid = null): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.set-active-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		$this->services->dashboardService->setActivePreference(
			userId: $this->context->userId,
			uuid: ($uuid ?? '')
		);

		return $this->context->responseHelper->success(data: ['status' => 'success']);
	}//end setActiveDashboard()

	/**
	 * Pin (or clear) the user's EXPLICIT default-dashboard choice
	 * (wave3.7).
	 *
	 * Distinct from {@see self::setActiveDashboard()} — this pref is
	 * only ever written when the user explicitly clicks "Set as
	 * default" on a row's cog menu, and is NOT auto-overwritten on
	 * every switch. The resolver checks it before the active pref so
	 * the pin survives across switches.
	 *
	 * Body shape: `{uuid: string}` — empty string clears the pin.
	 *
	 * @param string|null $uuid The dashboard UUID, or empty string to clear.
	 *
	 * @return JSONResponse 200 `{status: 'success'}` on success; 401
	 *                      when the session has no user.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function setDefaultDashboard(?string $uuid = null): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.set-default-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		$this->services->dashboardService->setDefaultPreference(
			userId: $this->context->userId,
			uuid: ($uuid ?? '')
		);

		return $this->context->responseHelper->success(data: ['status' => 'success']);
	}//end setDefaultDashboard()

	/**
	 * Read the user's EXPLICIT default-dashboard pin (wave3.7).
	 *
	 * @return JSONResponse 200 `{uuid: string}` — empty string when no
	 *                      pin set; 401 when the session has no user.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function getDefaultDashboard(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.get-default-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->context->responseHelper->success(
			data: [
				'uuid' => $this->services->dashboardService->getDefaultPreference(
					userId: $this->context->userId
				),
			]
		);
	}//end getDefaultDashboard()

	/**
	 * Fork any visible dashboard into a brand-new personal copy.
	 *
	 * REQ-DASH-020 / REQ-DASH-021 / REQ-DASH-022. Body shape:
	 * `{name?: string}` — when `name` is absent the system applies the
	 * default `t('My copy of {name}', source.name)` translated via the
	 * caller's active language.
	 *
	 * Status mapping:
	 *  - HTTP 201 with the full new dashboard payload on success.
	 *  - HTTP 401 when the session has no user.
	 *  - HTTP 403 with stable error code `personal_dashboards_disabled`
	 *    when the admin flag `allow_user_dashboards` is off — REQ-ASET-003
	 *    runtime gating runs FIRST so the envelope shape is stable
	 *    regardless of body contents.
	 *  - HTTP 404 when the source UUID is not visible to the caller —
	 *    do not leak existence (REQ-DASH-020 scenario "Cannot fork a
	 *    dashboard you cannot read").
	 *  - HTTP 500 when a partial-failure rollback fires — REQ-DASH-021.
	 *    ADR-005: the response carries a stable error code and a generic
	 *    user-facing message; the underlying exception is logged for ops.
	 *
	 * @param string $uuid The source dashboard UUID from the URL.
	 * @param string|null $name Optional explicit fork name from the body.
	 *
	 * @return JSONResponse The new dashboard payload (201) or an
	 *                      appropriate error envelope.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function fork(
		string $uuid,
		?string $name = null,
	): JSONResponse {
		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		return $this->forkFor(uuid: $uuid, name: $name);
	}//end fork()

	/**
	 * Fork a dashboard for the signed-in user and answer 201, or map the
	 * failure to its response.
	 *
	 * @param string      $uuid The source dashboard UUID.
	 * @param string|null $name Optional name for the copy.
	 *
	 * @return JSONResponse The fork, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function forkFor(string $uuid, ?string $name): JSONResponse {
		try {
			$fork = $this->services->dashboardService->forkAsPersonal(
				userId: $this->context->userId,
				sourceUuid: $uuid,
				name: $name
			);

			return self::forkCreated(fork: $fork);
		} catch (\Throwable $t) {
			return $this->forkFailure(throwable: $t);
		}
	}//end forkFor()

	/**
	 * Answer 201 with a fork.
	 *
	 * @param Dashboard $fork The personal copy.
	 *
	 * @return JSONResponse The response.
	 */
	private static function forkCreated(Dashboard $fork): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'success',
				'dashboard' => $fork->jsonSerialize(),
			],
			statusCode: Http::STATUS_CREATED
		);
	}//end forkCreated()

	/**
	 * Map a failed fork to its response.
	 *
	 * Personal dashboards switched off answer 403; dashboard-quota-limits
	 * REQ-QUOTA-002: a fork is bound by the per-user dashboard quota —
	 * HTTP 409 with the structured body; REQ-DASH-020: a source that is
	 * not visible answers 404 without leaking existence (the canonical
	 * message rather than the exception detail); anything else is
	 * logged and answered with a stable, generic envelope (REQ-DASH-021
	 * + ADR-005).
	 *
	 * @param \Throwable $throwable What the fork threw.
	 *
	 * @return JSONResponse The error response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function forkFailure(\Throwable $throwable): JSONResponse {
		return match (true) {
			$throwable instanceof PersonalDashboardsDisabledException => $this->personalDashboardsDisabled(exception: $throwable),
			$throwable instanceof QuotaExceededException => $this->context->responseHelper->quotaExceeded(exception: $throwable),
			$throwable instanceof DoesNotExistException => $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND),
			default => $this->internalForkError(throwable: $throwable),
		};
	}//end forkFailure()

	/**
	 * Log the real cause of a failed fork and answer 500 with the generic
	 * envelope.
	 *
	 * @param \Throwable $throwable What the fork threw.
	 *
	 * @return JSONResponse The 500 response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function internalForkError(\Throwable $throwable): JSONResponse {
		$this->access->history->logger->error(
			message: 'launchpad: fork failed for user {user}: {message}',
			context: [
				'user' => $this->context->userId,
				'message' => $throwable->getMessage(),
			]
		);

		return new JSONResponse(
			data: ['status' => 'error', 'error' => 'internal_error', 'message' => 'An unexpected error occurred'],
			statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
		);
	}//end internalForkError()

	/**
	 * Publish a dashboard. REQ-DASH-032.
	 *
	 * Owner-or-admin gated at the service boundary; the route attribute
	 * is `#[NoAdminRequired]` because the in-body owner check is the
	 * actual authorization point (gate-semantic-auth).
	 *
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return JSONResponse The updated dashboard payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function publish(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.publish');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->publicationResponse(
			operation: fn (): Dashboard => $this->services->dashboardService->publishDashboard(
				uuid: $uuid,
				userId: $this->context->userId
			)
		);
	}//end publish()

	/**
	 * Unpublish a dashboard. REQ-DASH-033.
	 *
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return JSONResponse The updated dashboard payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function unpublish(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.unpublish');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->publicationResponse(
			operation: fn (): Dashboard => $this->services->dashboardService->unpublish(
				uuid: $uuid,
				userId: $this->context->userId
			)
		);
	}//end unpublish()

	/**
	 * The serialised dashboards, with the user's quota status when the quota service is wired.
	 *
	 * Dashboard-quota-limits REQ-QUOTA-006: additive quota envelope on the
	 * personal dashboards list. Response shape is `{items: [...], quota: {...}}`.
	 * When the quota service is absent (legacy test doubles) fall back to the
	 * bare-array contract.
	 *
	 * @param array<int, mixed> $serialized The serialised dashboards.
	 *
	 * @return JSONResponse The success response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function withQuota(array $serialized): JSONResponse {
		if ($this->services->quotaService === null) {
			return $this->context->responseHelper->success(data: $serialized);
		}

		return $this->context->responseHelper->success(
			data: [
				'items' => $serialized,
				'quota' => $this->services->quotaService->getQuotaStatus(
					userId: $this->context->userId
				),
			]
		);
	}//end withQuota()

	/**
	 * The 403 answer when personal dashboards are switched off.
	 *
	 * @param PersonalDashboardsDisabledException $exception The refusal.
	 *
	 * @return JSONResponse The error envelope.
	 */
	private function personalDashboardsDisabled(PersonalDashboardsDisabledException $exception): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'error',
				'error' => $exception->getErrorCode(),
				'message' => $exception->getMessage(),
			],
			statusCode: Http::STATUS_FORBIDDEN
		);
	}//end personalDashboardsDisabled()

	/**
	 * Run a publication change and answer with the dashboard, 404 when unknown,
	 * or the mapped publication error.
	 *
	 * @param \Closure $operation Runs the change and returns the updated dashboard.
	 *
	 * @return JSONResponse The response.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function publicationResponse(\Closure $operation): JSONResponse {
		try {
			$dashboard = $operation();

			return $this->context->responseHelper->success(
				data: ['dashboard' => $dashboard->jsonSerialize()]
			);
		} catch (DoesNotExistException) {
			return $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return $this->mapPublicationError(exception: $e);
		}//end try
	}//end publicationResponse()

	/**
	 * The caller guard: the refusal for a caller who may not perform the action, or NULL.
	 *
	 * @param string $action The action identifier to require.
	 *
	 * @return JSONResponse|null The refusal, or NULL to proceed.
	 *
	 * @see RequestActor::refusal()
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		return $this->context->actor->refusal(userId: $this->context->userId, action: $action);
	}//end actionRefusal()

	/**
	 * Schedule a dashboard for automatic publication. REQ-DASH-034.
	 *
	 * Body: `{"publishAt": "2026-04-01T10:00:00Z"}`. Returns 400 with an
	 * i18n-friendly error message when `publishAt` is missing,
	 * unparseable, or in the past; 403 when the actor is neither owner
	 * nor admin.
	 *
	 * @param string $uuid The dashboard UUID from the URL.
	 * @param string|null $publishAt The future ISO-8601 timestamp from
	 *                               the request body.
	 * @param string|null $unpublishAt Optional take-down time (REQ-SCHEDUI-002).
	 *
	 * @return JSONResponse The updated dashboard payload.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	#[NoAdminRequired]
	public function schedule(
		string $uuid,
		?string $publishAt = null,
		?string $unpublishAt = null,
	): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.schedule');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->scheduleDated(
			request: new DashboardScheduleRequest(uuid: $uuid, publishAt: $publishAt, unpublishAt: $unpublishAt)
		);
	}//end schedule()

	/**
	 * Refuse a schedule without any time, then schedule.
	 *
	 * @param DashboardScheduleRequest $request The dashboard and its times.
	 *
	 * @return JSONResponse The updated dashboard payload, or the refusal.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function scheduleDated(DashboardScheduleRequest $request): JSONResponse {
		// A take-down time alone is a valid schedule (REQ-SCHEDUI-002).
		if ($this->isBlank(value: $request->publishAt) === true && $this->isBlank(value: $request->unpublishAt) === true) {
			return $this->context->responseHelper->invalidArgument(message: DashboardService::ERR_SCHEDULE_PAST_DATE);
		}

		return $this->scheduled(request: $request);
	}//end scheduleDated()

	/**
	 * Schedule the dashboard and answer with it, mapping the service's
	 * refusals to their responses.
	 *
	 * @param DashboardScheduleRequest $request The dashboard and its times.
	 *
	 * @return JSONResponse The updated dashboard payload, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function scheduled(DashboardScheduleRequest $request): JSONResponse {
		try {
			return $this->dashboardResponse(dashboard: $this->scheduleFor(request: $request));
		} catch (DoesNotExistException) {
			return $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return $this->context->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return $this->mapPublicationError(exception: $e);
		}//end try
	}//end scheduled()

	/**
	 * Schedule the dashboard on behalf of the signed-in user.
	 *
	 * @param DashboardScheduleRequest $request The dashboard and its times.
	 *
	 * @return Dashboard The scheduled dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function scheduleFor(DashboardScheduleRequest $request): Dashboard {
		return $this->services->dashboardService->schedule(
			uuid: $request->uuid,
			publishAt: $request->publishAt,
			userId: $this->context->userId,
			unpublishAt: $request->unpublishAt
		);
	}//end scheduleFor()

	/**
	 * Whether a request value is missing or empty.
	 *
	 * @param string|null $value The request value.
	 *
	 * @return bool True for null or an empty string.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function isBlank(?string $value): bool {
		return $value === null || $value === '';
	}//end isBlank()

	/**
	 * Record a dashboard view event (REQ-ANLT-002).
	 *
	 * Authenticated users only — POST `{}` body. Returns HTTP 204
	 * after the daily counter has been incremented. Short-circuits
	 * silently to 204 when the user has opted out (REQ-ANLT-004) or
	 * when global analytics is disabled (REQ-ANLT-005). Returns 404
	 * when the dashboard does not exist.
	 *
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return JSONResponse An empty 204 response on success, 401
	 *                      when unauthenticated, 404 when the
	 *                      dashboard does not exist.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function viewEvent(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.view-event');
		if ($refusal !== null) {
			return $refusal;
		}

		// H4: resolve the dashboard and assert the caller can view it
		// before recording any counter increment (REQ-ANLT-002).
		try {
			$dashboard = $this->services->dashboardService->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND);
		}

		if ($this->access->permissionService->canViewDashboard(
			userId: $this->context->userId,
			dashboardId: $dashboard->getId()
		) === false
		) {
			return $this->context->responseHelper->forbidden();
		}

		try {
			$this->access->history->analyticsService->recordViewEvent(
				dashboardUuid: $uuid,
				userId: $this->context->userId
			);
		} catch (DoesNotExistException) {
			return $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(
			data: [],
			statusCode: Http::STATUS_NO_CONTENT
		);
	}//end viewEvent()

	/**
	 * Map publication-related service exceptions onto the right HTTP
	 * status. REQ-DASH-032..034.
	 *
	 * The service raises `Exception` with the sentinel message
	 * {@see DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN} when
	 * the actor is not allowed; everything else falls through to the
	 * generic $this->context->responseHelper->error path.
	 *
	 * @param \Exception $exception The thrown exception.
	 *
	 * @return JSONResponse The mapped error envelope.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function mapPublicationError(\Exception $exception): JSONResponse {
		if ($exception->getMessage() === DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
		) {
			return $this->context->responseHelper->forbidden(
				message: DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
			);
		}

		return $this->context->responseHelper->error(exception: $exception);
	}//end mapPublicationError()

	/**
	 * Resolve create parameters from JSON body or individual params.
	 *
	 * Forwards the new hierarchy fields (`parentUuid`, `slug`,
	 * `sortOrder`) introduced by REQ-DASH-023..029. When the caller
	 * sends a JSON body the helper inspects the array form first; when
	 * positional / typed params come via the framework binding it
	 * falls back to those.
	 *
	 * @param mixed $name The name parameter.
	 * @param array $flat The flat-form `description`, `icon`, `parentUuid`,
	 *                    `slug`, `sortOrder` and `widgets` parameters, each optional.
	 *
	 * @return array{name: string, description: ?string, icon: ?string, parentUuid: ?string, slug: ?string, sortOrder: int, widgets: ?string}
	 *                                                                                                                      The resolved values.
	 */
	private function resolveCreateParams($name, array $flat=[]): array {
		if (is_array($name) === true) {
			return self::resolveCreateBody(body: $name);
		}

		return [
			'name' => $name ?? 'My Dashboard',
			'description' => ($flat['description'] ?? null),
			'icon' => ($flat['icon'] ?? null),
			'parentUuid' => ($flat['parentUuid'] ?? null),
			'slug' => ($flat['slug'] ?? null),
			'sortOrder' => ($flat['sortOrder'] ?? 0),
			'widgets' => ($flat['widgets'] ?? null),
			'gridColumns' => self::offeredGridColumns(value: ($flat['gridColumns'] ?? null)),
		];
	}//end resolveCreateParams()

	/**
	 * Resolve create parameters from a JSON body: string fields keep only
	 * strings, the sort order only numbers.
	 *
	 * @param array<string, mixed> $body The decoded request body.
	 *
	 * @return array<string, mixed> The resolved parameters.
	 */
	private static function resolveCreateBody(array $body): array {
		return [
			'name' => $body['name'] ?? 'My Dashboard',
			'description' => $body['description'] ?? null,
			'icon' => self::stringOrNull(value: ($body['icon'] ?? null)),
			'parentUuid' => self::stringOrNull(value: ($body['parentUuid'] ?? null)),
			'slug' => self::stringOrNull(value: ($body['slug'] ?? null)),
			'sortOrder' => self::intOrZero(value: ($body['sortOrder'] ?? null)),
			'widgets' => self::stringOrNull(value: ($body['widgets'] ?? null)),
			'gridColumns' => self::offeredGridColumns(value: ($body['gridColumns'] ?? null)),
		];
	}//end resolveCreateBody()

	/**
	 * A requested column count when it is one of the offered counts, null otherwise.
	 *
	 * @param mixed $value The requested value.
	 *
	 * @return int|null The column count, or null for the administrator's default.
	 */
	private static function offeredGridColumns(mixed $value): ?int {
		if (is_numeric($value) === true && in_array((int)$value, self::GRID_COLUMN_OPTIONS, true) === true) {
			return (int)$value;
		}

		return null;
	}//end offeredGridColumns()

	/**
	 * A string value as is, anything else as null.
	 *
	 * @param mixed $value The body value.
	 *
	 * @return string|null The string, or null.
	 */
	private static function stringOrNull(mixed $value): ?string {
		if (is_string($value) === true) {
			return $value;
		}

		return null;
	}//end stringOrNull()

	/**
	 * A numeric value as an integer, anything else as 0.
	 *
	 * @param mixed $value The body value.
	 *
	 * @return int The integer.
	 */
	private static function intOrZero(mixed $value): int {
		if (is_numeric($value) === true) {
			return (int)$value;
		}

		return 0;
	}//end intOrZero()

	/**
	 * Read the case-insensitive `cascade` query param (REQ-DASH-030).
	 *
	 * `?cascade=true|TRUE|True|1|yes|on|cascade` → true; anything else
	 * (including the param being absent) → false.
	 *
	 * @return bool Whether cascade-delete was explicitly requested.
	 */
	private function resolveCascadeFlag(): bool {
		$raw = $this->request->getParam(key: 'cascade');
		if ($raw === null) {
			return false;
		}

		$lower = strtolower((string)$raw);
		return in_array(
			$lower,
			['true', '1', 'yes', 'on', 'cascade'],
			true
		);
	}//end resolveCascadeFlag()

	/**
	 * Check creation permissions and return error if denied.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return JSONResponse|null Error response or null if allowed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function checkCreatePermissions(string $userId): ?JSONResponse {
		if ($this->access->permissionService->canCreateDashboard(
			userId: $userId
		) === false
		) {
			return $this->context->responseHelper->forbidden(
				message: 'Dashboard creation not allowed'
			);
		}

		$existing = $this->services->dashboardService->getUserDashboards(
			userId: $userId
		);
		if (empty($existing) === false
			&& $this->access->permissionService->canHaveMultipleDashboards() === false
		) {
			return $this->context->responseHelper->forbidden(
				message: 'Multiple dashboards not allowed'
			);
		}

		return null;
	}//end checkCreatePermissions()

	/**
	 * Build update data from the nullable update fields.
	 *
	 * A null field is "not in the payload" and is left out. The parent
	 * UUID sentinel `__null__` and the empty string both clear the parent
	 * (re-root the dashboard, REQ-DASH-023), because the framework's typed
	 * string binding cannot represent an explicit NULL.
	 *
	 * @param array<string, mixed> $fields The update fields by name, in patch order.
	 *
	 * @return array<string, mixed> The non-null update data.
	 *
	 * @throws InvalidArgumentException When the grid column count is not 6, 8 or 12.
	 */
	private function buildUpdateData(array $fields): array {
		$data = array_filter(
			array: $fields,
			callback: function ($value) {
				return $value !== null;
			}
		);

		if (isset($data['gridColumns']) === true
			&& in_array(needle: $data['gridColumns'], haystack: self::GRID_COLUMN_OPTIONS, strict: true) === false
		) {
			throw new InvalidArgumentException(message: 'Grid columns must be 6, 8 or 12');
		}

		if (isset($data['parentUuid']) === true
			&& ($data['parentUuid'] === '__null__' || $data['parentUuid'] === '')
		) {
			$data['parentUuid'] = null;
		}

		return $data;
	}//end buildUpdateData()

	/**
	 * Build the patch payload for the group-shared update endpoint.
	 *
	 * @param string|null $name The new name.
	 * @param string|null $description The new description.
	 * @param int|null $gridColumns The new grid columns.
	 * @param array|null $placements Updated placements.
	 *
	 * @return array The non-null patch fields.
	 */
	private function buildGroupUpdateData(
		?string $name,
		?string $description,
		?int $gridColumns,
		?array $placements,
	): array {
		$fields = [
			'name' => $name,
			'description' => $description,
			'gridColumns' => $gridColumns,
			'placements' => $placements,
		];

		return array_filter(
			array: $fields,
			callback: function ($value) {
				return $value !== null;
			}
		);
	}//end buildGroupUpdateData()

	/**
	 * Run a dashboard save with its version (REQ-VERS-010/011): one version
	 * of the dashboard as saved, in the save's transaction, the first one
	 * preceded by the state before. Without a user there is no author, so
	 * the save runs without a version.
	 *
	 * @param int      $dashboardId The dashboard being saved.
	 * @param \Closure $save        Runs the save and returns the saved dashboard.
	 *
	 * @return Dashboard The saved dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboard-versioning/spec.md#requirement-every-save-makes-one-version-of-the-whole-dashboard-req-vers-010
	 */
	private function saveWithVersion(int $dashboardId, \Closure $save): Dashboard {
		if ($this->context->userId === null) {
			return $save();
		}

		return $this->access->history->versionService->saveWithVersion(
			dashboardId: $dashboardId,
			createdBy: $this->context->userId,
			save: $save
		);
	}//end saveWithVersion()
}//end class
