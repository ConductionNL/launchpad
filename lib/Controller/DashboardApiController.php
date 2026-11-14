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
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCA\LaunchPad\Exception\QuotaExceededException;
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
 */
class DashboardApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest             $request The request.
	 * @param DashboardApiServices $services The dashboard service and the optional quota, translation and
	 *                                       metadata services.
	 * @param PermissionService    $permissionService The permission service.
	 * @param DashboardApiHistory  $history The tree, version and analytics services and the logger.
	 * @param RequestActor         $actor The user session and the action authorisation service.
	 * @param string|null          $userId The user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly DashboardApiServices $services,
		private readonly PermissionService $permissionService,
		private readonly DashboardApiHistory $history,
		private readonly RequestActor $actor,
		private readonly ?string $userId,
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
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-17
	 */
	#[NoAdminRequired]
	public function list(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.list');
		if ($refusal !== null) {
			return $refusal;
		}

		$dashboards = $this->services->dashboardService->getUserDashboards(
			userId: $this->userId
		);

		$serialized = ResponseHelper::serializeList(entities: $dashboards);

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
	 * @spec openspec/specs/dashboards/spec.md
	 */
	#[NoAdminRequired]
	public function visible(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.visible');
		if ($refusal !== null) {
			return $refusal;
		}

		$items = $this->filterByMetadata(
			items: $this->services->dashboardService->getVisibleToUser(
				userId: $this->userId
			)
		);

		$serialized = [];
		foreach ($items as $entry) {
			$row = $entry['dashboard']->jsonSerialize();
			$row['source'] = $entry['source'];
			// Tag ownership so the frontend can route activation correctly:
			// only personal `user`-type rows owned by the caller take the
			// legacy id-based `is_active` path; group/default rows (user_id
			// NULL) are activated via the UUID preference instead.
			$row['isOwner'] = ($entry['dashboard']->getUserId() === $this->userId);
			$serialized[] = $row;
		}

		// Dashboard-quota-limits REQ-QUOTA-006: carry the additive quota
		// envelope on the unioned listing the store consumes, so the
		// frontend can disable create affordances at the limit without an
		// extra round-trip. The response shape is now
		// `{items: [...], quota: {...}}`; clients that read the bare array
		// are handled by the store's shape-tolerant unwrap. When the quota
		// service is absent (legacy test doubles) fall back to the
		// bare-array contract.
		return $this->withQuota(serialized: $serialized);
	}//end visible()

	/**
	 * Get the user's active dashboard with placements.
	 *
	 * @return JSONResponse The active dashboard data.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-18
	 */
	#[NoAdminRequired]
	public function getActive(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.get-active');
		if ($refusal !== null) {
			return $refusal;
		}

		$result = $this->services->dashboardService->getEffectiveDashboard(
			userId: $this->userId
		);

		if ($result === null) {
			return ResponseHelper::success(
				data: ['error' => 'No dashboard available'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		// The effective dashboard can now be a group/default (showcase)
		// dashboard the user does not own (resolved via the last-used
		// preference), so tag ownership the same way show() does rather
		// than letting the client assume the caller owns it.
		$activeDashboard = $result['dashboard'];
		$isOwner = ($activeDashboard->getUserId() === $this->userId);

		$sharedBy = null;
		if ($isOwner === false) {
			$sharedBy = $activeDashboard->getUserId();
		}

		return ResponseHelper::success(
			data: [
				'dashboard' => $activeDashboard->jsonSerialize(),
				'placements' => ResponseHelper::serializeList(
					entities: $result['placements']
				),
				'permissionLevel' => $result['permissionLevel'],
				'isOwner' => $isOwner,
				'sharedBy' => $sharedBy,
				'hasVariants' => $this->hasVariants(uuid: (string)$activeDashboard->getUuid()),
			]
		);
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
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-21
	 */
	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.show');
		if ($refusal !== null) {
			return $refusal;
		}

		$result = $this->services->dashboardService->getDashboardForUser(
			dashboardId: $id,
			userId: $this->userId
		);

		if ($result === null) {
			return ResponseHelper::success(
				data: ['error' => 'Not found'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		}

		$dashboard = $result['dashboard'];
		$isOwner = ($dashboard->getUserId() === $this->userId);
		$sharedBy = null;
		if ($isOwner === false) {
			$sharedBy = $dashboard->getUserId();
		}

		return ResponseHelper::success(
			data: [
				'dashboard' => $dashboard->jsonSerialize(),
				'placements' => ResponseHelper::serializeList(
					entities: $result['placements']
				),
				'permissionLevel' => $result['permissionLevel'],
				'isOwner' => $isOwner,
				'sharedBy' => $sharedBy,
				'hasVariants' => $this->hasVariants(uuid: (string)$dashboard->getUuid()),
				'breadcrumbs' => $this->visibleBreadcrumbs(dashboard: $dashboard, userId: $this->userId),
			]
		);
	}//end show()

	/**
	 * Whether a dashboard has more than one language version, so the page
	 * asks for the resolved variant only when it can differ.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return bool True with two or more variants.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 */
	private function hasVariants(string $uuid): bool {
		if ($this->services->translationService === null || $uuid === '') {
			return false;
		}

		return count($this->services->translationService->listVariants(dashboardUuid: $uuid)) > 1;
	}//end hasVariants()

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
		$filters = $this->request->getParam('metadata');
		if ($this->services->metadataService === null || is_array($filters) === false || $filters === []) {
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
	 */
	private function visibleBreadcrumbs(Dashboard $dashboard, string $userId): array {
		$parent = $dashboard->getParentUuid();
		if ($parent === null || $parent === '') {
			return [];
		}

		$visible = $this->visibleUuids(userId: $userId);

		$crumbs = [];
		foreach ($this->history->treeService->computeBreadcrumbs(uuid: (string)$dashboard->getUuid()) as $crumb) {
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
	 * @param mixed $name The dashboard name.
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
			]
		);

		return $this->createFromParams(resolved: $resolved);
	}//end create()

	/**
	 * The refusal of a create request: the guard chain of
	 * {@see self::denyCreate()}, then the create permissions.
	 *
	 * @return JSONResponse|null The refusal, or null to proceed.
	 */
	private function denyCreateRequest(): ?JSONResponse {
		$denial = $this->denyCreate();
		if ($denial !== null) {
			return $denial;
		}

		return $this->checkCreatePermissions(
			userId: $this->userId
		);
	}//end denyCreateRequest()

	/**
	 * Create the dashboard from resolved parameters and answer 201, or map
	 * the service's refusal to its response.
	 *
	 * @param array<string, mixed> $resolved The resolved create parameters.
	 *
	 * @return JSONResponse The created dashboard, or the error.
	 */
	private function createFromParams(array $resolved): JSONResponse {
		try {
			return $this->createdResponse(dashboard: $this->createDashboardFrom(resolved: $resolved));
		} catch (QuotaExceededException $e) {
			// Dashboard-quota-limits REQ-QUOTA-002: the user is at their
			// dashboard limit — HTTP 409 with the structured body.
			return ResponseHelper::quotaExceeded(exception: $e);
		} catch (InvalidArgumentException $e) {
			// REQ-DASH-023..029: parent / slug / depth / cycle violations
			// surface as HTTP 400 with the validation message verbatim.
			return ResponseHelper::invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end createFromParams()

	/**
	 * Create the signed-in user's dashboard, seeded with the default
	 * widgets, from resolved parameters.
	 *
	 * @param array<string, mixed> $resolved The resolved create parameters.
	 *
	 * @return Dashboard The created dashboard.
	 */
	private function createDashboardFrom(array $resolved): Dashboard {
		return $this->services->dashboardService->createDashboard(
			userId: $this->userId,
			name: $resolved['name'],
			options: ['seedDefaults' => true] + array_diff_key($resolved, ['name' => true])
		);
	}//end createDashboardFrom()

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
	 */
	private function createdResponse(Dashboard $dashboard): JSONResponse {
		$placements = $this->services->dashboardService->findPlacements(
			dashboardId: $dashboard->getId()
		);

		return ResponseHelper::success(
			data: [
				'dashboard' => $dashboard->jsonSerialize(),
				'placements' => ResponseHelper::serializeList(
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
	 * @param array|null $placements The placements.
	 * @param string|null $icon The icon registry key, URL, or NULL to leave unchanged.
	 * @param string|null $parentUuid Optional new parent UUID (REQ-DASH-023);
	 *                                explicit empty string clears the
	 *                                parent (re-roots the dashboard).
	 * @param string|null $slug Optional new slug (REQ-DASH-024).
	 * @param int|null $sortOrder Optional new sort order (REQ-DASH-029).
	 *
	 * @return JSONResponse The updated dashboard.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-19
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
		$denial = $this->denyUpdate(dashboardId: $id, metadataOnly: $placements === null);
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
			]
		);
	}//end update()

	/**
	 * Resolve the guard chain for {@see self::update()}.
	 *
	 * Order is load-bearing: the session, then the action check, then the
	 * injected user id, then the permission for the kind of update.
	 *
	 * @param int  $dashboardId  The dashboard ID.
	 * @param bool $metadataOnly Whether the update leaves the placements alone.
	 *
	 * @return JSONResponse|null The refusal, or null to proceed.
	 */
	private function denyUpdate(int $dashboardId, bool $metadataOnly): ?JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.update');
		if ($refusal !== null) {
			return $refusal;
		}

		if ($this->mayUpdate(dashboardId: $dashboardId, metadataOnly: $metadataOnly) === false) {
			return ResponseHelper::forbidden();
		}

		return null;
	}//end denyUpdate()

	/**
	 * Whether the user may make this kind of update.
	 *
	 * REQ-PERM-007: Metadata-only updates (name, description, icon) are
	 * allowed for all permission levels. Widget/tile/layout changes
	 * require add_only or full permission.
	 *
	 * @param int  $dashboardId  The dashboard ID.
	 * @param bool $metadataOnly Whether the update leaves the placements alone.
	 *
	 * @return bool True when the update is allowed.
	 */
	private function mayUpdate(int $dashboardId, bool $metadataOnly): bool {
		if ($metadataOnly === true) {
			return $this->permissionService->canEditDashboardMetadata(
				userId: $this->userId,
				dashboardId: $dashboardId
			) !== false;
		}

		return $this->permissionService->canEditDashboard(
			userId: $this->userId,
			dashboardId: $dashboardId
		) !== false;
	}//end mayUpdate()

	/**
	 * Apply an allowed update and answer with the saved dashboard.
	 *
	 * Captures an automatic version snapshot after a successful save
	 * (REQ-VERS-001). Parent, slug, depth and cycle violations answer 400
	 * (REQ-DASH-023..029).
	 *
	 * @param int                  $dashboardId The dashboard ID.
	 * @param array<string, mixed> $fields      The nullable update fields by name, in patch order.
	 *
	 * @return JSONResponse The updated dashboard, or the error.
	 */
	private function applyUpdate(int $dashboardId, array $fields): JSONResponse {
		try {
			$data = $this->buildUpdateData(fields: $fields);

			$dashboard = $this->services->dashboardService->updateDashboard(
				dashboardId: $dashboardId,
				userId: $this->userId,
				data: $data
			);

			// REQ-VERS-001: capture an automatic snapshot after the
			// PUT succeeds. The version service enforces its own
			// debounce window (60 s) so rapid drag-and-drop edits do
			// not flood the table. Failures are swallowed so they do
			// not surface to the dashboard PUT response.
			$this->captureAutomaticSnapshot(dashboard: $dashboard);

			return ResponseHelper::success(
				data: ['dashboard' => $dashboard->jsonSerialize()]
			);
		} catch (InvalidArgumentException $e) {
			// REQ-DASH-023..029: parent / slug / depth / cycle violations
			// surface as HTTP 400 with the validation message verbatim.
			return ResponseHelper::invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
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
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-20
	 */
	#[NoAdminRequired]
	public function delete(int $id): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.delete');
		if ($refusal !== null) {
			return $refusal;
		}

		$cascade = $this->resolveCascadeFlag();

		try {
			$this->services->dashboardService->deleteDashboard(
				dashboardId: $id,
				userId: $this->userId,
				cascade: $cascade
			);

			return ResponseHelper::success(data: ['status' => 'ok']);
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
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
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
			userId: $this->userId
		);
		$visibleUuids = [];
		foreach ($visible as $entry) {
			$uuid = $entry['dashboard']->getUuid();
			if ($uuid !== null && $uuid !== '') {
				$visibleUuids[$uuid] = true;
			}
		}

		$tree = $this->history->treeService->getFilteredTree(
			visibleUuids: $visibleUuids
		);

		return ResponseHelper::success(data: $tree);
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

		$dashboard = $this->history->treeService->resolvePath(path: $path);
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
		if ($this->permissionService->canViewDashboard(
			userId: $this->userId,
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
		$serialised['path'] = $this->history->treeService->computePath(uuid: $uuid);
		$serialised['breadcrumbs'] = $this->history->treeService->computeBreadcrumbs(
			uuid: $uuid
		);

		return ResponseHelper::success(
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

		return ResponseHelper::success(
			data: ['path' => $this->history->treeService->computePath(uuid: $uuid)]
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
				userId: $this->userId
			);

			return ResponseHelper::success(
				data: ['dashboard' => $dashboard->jsonSerialize()]
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
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
			userId: $this->userId,
			groupId: $groupId
		) === false
		) {
			return ResponseHelper::forbidden();
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

		return ResponseHelper::success(data: $viewerData);
	}//end listGroup()

	/**
	 * Create a new group-shared dashboard.
	 *
	 * Admin-only — enforced by the `#[AuthorizedAdminSetting]` attribute
	 * (gate-route-auth / gate-semantic-auth both pass since the
	 * framework-level check is the actual authorization point).
	 * REQ-DASH-014.
	 *
	 * @param string $groupId The group ID.
	 * @param mixed $name The dashboard name (or {name,...}
	 *                    dict as the body).
	 * @param string|null $description The dashboard description.
	 *
	 * @return JSONResponse The created dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function createGroup(
		string $groupId,
		$name = null,
		?string $description = null,
	): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		$resolved = $this->resolveCreateParams(
			name: $name,
			flat: ['description' => $description]
		);

		try {
			$dashboard = $this->services->dashboardService->createGroupShared(
				actorUserId: $this->userId,
				groupId: $groupId,
				fields: ['name' => $resolved['name'], 'description' => $resolved['description']]
			);

			return ResponseHelper::success(
				data: ['dashboard' => $dashboard->jsonSerialize()],
				statusCode: Http::STATUS_CREATED
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
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
			userId: $this->userId,
			groupId: $groupId
		) === false
		) {
			return ResponseHelper::forbidden();
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
		return ResponseHelper::success(
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
	 */
	private function updateGroupDashboard(GroupDashboardTarget $target, array $patch): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		return $this->runGroupMutation(
			mutation: fn (): JSONResponse => $this->dashboardResponse(
				dashboard: $this->services->dashboardService->updateGroupShared(
					actorUserId: $this->userId,
					groupId: $target->groupId,
					uuid: $target->uuid,
					patch: $patch
				)
			)
		);
	}//end updateGroupDashboard()

	/**
	 * Run a group-shared mutation, answering an unknown dashboard with 404
	 * and any other failure with the generic error.
	 *
	 * @param callable(): JSONResponse $mutation The mutation and its answer.
	 *
	 * @return JSONResponse The mutation's answer, or the error.
	 */
	private function runGroupMutation(callable $mutation): JSONResponse {
		try {
			return $mutation();
		} catch (DoesNotExistException) {
			// ADR-005: do not leak raw exception messages to clients.
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end runGroupMutation()

	/**
	 * Answer 200 with one dashboard.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return JSONResponse The response.
	 */
	private function dashboardResponse(Dashboard $dashboard): JSONResponse {
		return ResponseHelper::success(
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
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteGroup(
		string $groupId,
		string $uuid,
	): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		try {
			$this->services->dashboardService->deleteGroupShared(
				actorUserId: $this->userId,
				groupId: $groupId,
				uuid: $uuid
			);

			return ResponseHelper::success(data: ['status' => 'ok']);
		} catch (DoesNotExistException) {
			// ADR-005: do not leak raw exception messages to clients.
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
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
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function setGroupDefault(
		string $groupId,
		?string $uuid = null,
	): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
		}

		if ($uuid === null || $uuid === '') {
			return self::missingUuid();
		}

		return $this->runGroupMutation(
			mutation: fn (): JSONResponse => $this->markGroupDefault(target: new GroupDashboardTarget(groupId: $groupId, uuid: $uuid))
		);
	}//end setGroupDefault()

	/**
	 * The 400 answer for a group-default request without a UUID.
	 *
	 * @return JSONResponse The response.
	 */
	private static function missingUuid(): JSONResponse {
		return ResponseHelper::error(
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
	 */
	private function markGroupDefault(GroupDashboardTarget $target): JSONResponse {
		$this->services->dashboardService->setGroupDefault(
			actorUserId: $this->userId,
			groupId: $target->groupId,
			uuid: $target->uuid
		);

		return ResponseHelper::success(
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
	 */
	#[NoAdminRequired]
	public function setActiveDashboard(?string $uuid = null): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.set-active-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		$this->services->dashboardService->setActivePreference(
			userId: $this->userId,
			uuid: ($uuid ?? '')
		);

		return ResponseHelper::success(data: ['status' => 'success']);
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
	 */
	#[NoAdminRequired]
	public function setDefaultDashboard(?string $uuid = null): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.set-default-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		$this->services->dashboardService->setDefaultPreference(
			userId: $this->userId,
			uuid: ($uuid ?? '')
		);

		return ResponseHelper::success(data: ['status' => 'success']);
	}//end setDefaultDashboard()

	/**
	 * Read the user's EXPLICIT default-dashboard pin (wave3.7).
	 *
	 * @return JSONResponse 200 `{uuid: string}` — empty string when no
	 *                      pin set; 401 when the session has no user.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	#[NoAdminRequired]
	public function getDefaultDashboard(): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard.get-default-dashboard');
		if ($refusal !== null) {
			return $refusal;
		}

		return ResponseHelper::success(
			data: [
				'uuid' => $this->services->dashboardService->getDefaultPreference(
					userId: $this->userId
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
	 */
	#[NoAdminRequired]
	public function fork(
		string $uuid,
		?string $name = null,
	): JSONResponse {
		if ($this->userId === null) {
			return ResponseHelper::unauthorized();
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
	 */
	private function forkFor(string $uuid, ?string $name): JSONResponse {
		try {
			$fork = $this->services->dashboardService->forkAsPersonal(
				userId: $this->userId,
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
	 */
	private function forkFailure(\Throwable $throwable): JSONResponse {
		return match (true) {
			$throwable instanceof PersonalDashboardsDisabledException => $this->personalDashboardsDisabled(exception: $throwable),
			$throwable instanceof QuotaExceededException => ResponseHelper::quotaExceeded(exception: $throwable),
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
	 */
	private function internalForkError(\Throwable $throwable): JSONResponse {
		$this->history->logger->error(
			message: 'launchpad: fork failed for user {user}: {message}',
			context: [
				'user' => $this->userId,
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
				userId: $this->userId
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
				userId: $this->userId
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
	 */
	private function withQuota(array $serialized): JSONResponse {
		if ($this->services->quotaService === null) {
			return ResponseHelper::success(data: $serialized);
		}

		return ResponseHelper::success(
			data: [
				'items' => $serialized,
				'quota' => $this->services->quotaService->getQuotaStatus(
					userId: $this->userId
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
	 */
	private function publicationResponse(\Closure $operation): JSONResponse {
		try {
			$dashboard = $operation();

			return ResponseHelper::success(
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
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		return $this->actor->refusal(userId: $this->userId, action: $action);
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
	 */
	private function scheduleDated(DashboardScheduleRequest $request): JSONResponse {
		// A take-down time alone is a valid schedule (REQ-SCHEDUI-002).
		if ($this->isBlank(value: $request->publishAt) === true && $this->isBlank(value: $request->unpublishAt) === true) {
			return ResponseHelper::invalidArgument(message: DashboardService::ERR_SCHEDULE_PAST_DATE);
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
	 */
	private function scheduled(DashboardScheduleRequest $request): JSONResponse {
		try {
			return $this->dashboardResponse(dashboard: $this->scheduleFor(request: $request));
		} catch (DoesNotExistException) {
			return $this->errorCode(code: 'not_found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return ResponseHelper::invalidArgument(message: $e->getMessage());
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
	 */
	private function scheduleFor(DashboardScheduleRequest $request): Dashboard {
		return $this->services->dashboardService->schedule(
			uuid: $request->uuid,
			publishAt: $request->publishAt,
			userId: $this->userId,
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

		if ($this->permissionService->canViewDashboard(
			userId: $this->userId,
			dashboardId: $dashboard->getId()
		) === false
		) {
			return ResponseHelper::forbidden();
		}

		try {
			$this->history->analyticsService->recordViewEvent(
				dashboardUuid: $uuid,
				userId: $this->userId
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
	 * generic ResponseHelper::error path.
	 *
	 * @param \Exception $exception The thrown exception.
	 *
	 * @return JSONResponse The mapped error envelope.
	 */
	private function mapPublicationError(\Exception $exception): JSONResponse {
		if ($exception->getMessage() === DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
		) {
			return ResponseHelper::forbidden(
				message: DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
			);
		}

		return ResponseHelper::error(exception: $exception);
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
	 *                    `slug` and `sortOrder` parameters, each optional.
	 *
	 * @return array{name: string, description: ?string, icon: ?string, parentUuid: ?string, slug: ?string, sortOrder: int}
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
		];
	}//end resolveCreateBody()

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
	 */
	private function checkCreatePermissions(string $userId): ?JSONResponse {
		if ($this->permissionService->canCreateDashboard(
			userId: $userId
		) === false
		) {
			return ResponseHelper::forbidden(
				message: 'Dashboard creation not allowed'
			);
		}

		$existing = $this->services->dashboardService->getUserDashboards(
			userId: $userId
		);
		if (empty($existing) === false
			&& $this->permissionService->canHaveMultipleDashboards() === false
		) {
			return ResponseHelper::forbidden(
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
	 */
	private function buildUpdateData(array $fields): array {
		$data = array_filter(
			array: $fields,
			callback: function ($value) {
				return $value !== null;
			}
		);

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
	 * Capture an automatic version snapshot after a successful update
	 * (REQ-VERS-001). The version service enforces a 60-second debounce
	 * window so a flurry of drag-and-drop saves does not flood the
	 * table.
	 *
	 * Failures here MUST NOT surface to the dashboard PUT response —
	 * the user's edit succeeded; missing one snapshot is a quality of
	 * life regression, not a data-integrity bug. We log + swallow.
	 *
	 * @param \OCA\LaunchPad\Db\Dashboard $dashboard The dashboard that was
	 *                                               just updated.
	 *
	 * @return void
	 */
	private function captureAutomaticSnapshot(
		\OCA\LaunchPad\Db\Dashboard $dashboard,
	): void {
		if ($this->userId === null) {
			return;
		}

		try {
			$this->history->versionService->captureSnapshot(
				dashboard: $dashboard,
				createdBy: $this->userId
			);
		} catch (\Throwable $t) {
			$this->history->logger->warning(
				message: 'launchpad: automatic version snapshot failed',
				context: ['exception' => $t]
			);
		}
	}//end captureAutomaticSnapshot()
}//end class
