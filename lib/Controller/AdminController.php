<?php

/**
 * AdminController
 *
 * Controller for admin dashboard template management.
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
use OCA\LaunchPad\Exception\DuplicateRoleAssignmentException;
use OCA\LaunchPad\Exception\InvalidRoleAssignmentException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\ResourceService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;

/**
 * Controller for admin dashboard template management plus role-assignment
 * CRUD (REQ-ROLE-004, REQ-ROLE-006) and feed-refresh admin actions.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)     The admin surface
 *                                                legitimately covers
 *                                                templates, settings,
 *                                                group-priority order,
 *                                                role assignments,
 *                                                export/import,
 *                                                feed-refresh, footer
 *                                                customization,
 *                                                template-preview-image
 *                                                upload (REQ-TMPL-017),
 *                                                setup wizard
 *                                                (REQ-WIZ-008..009),
 *                                                and the self-introspection
 *                                                endpoint in one
 *                                                cohesive admin namespace.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   Admin coordination
 *                                                  spans multiple
 *                                                  injected services
 *                                                  (templates, settings,
 *                                                  roles, export, import,
 *                                                  feed refresh, footer,
 *                                                  resource uploads,
 *                                                  setup wizard,
 *                                                  helpers) by design —
 *                                                  splitting the controller
 *                                                  would fragment the routing
 *                                                  surface.
 * @SuppressWarnings(PHPMD.Superglobals)             $_FILES is the only
 *                                                  multipart entry point.
 * @SuppressWarnings(PHPMD.LongVariable)             `footerBackgroundColor`
 *                                                  / `footerTextColor` mirror
 *                                                  the API contract field
 *                                                  names verbatim — shortening
 *                                                  would diverge from the
 *                                                  documented payload.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Complexity is a function
 *                                                  of the number of guarded
 *                                                  admin endpoints, not
 *                                                  nested branching inside
 *                                                  any single method.
 * @spec                                             openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-4
 */
class AdminController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest            $request The request.
	 * @param AdminTemplateTools  $templates The template, resync, export and import services.
	 * @param AdminSettingsTools  $settings The settings, footer and setup wizard services and the group manager.
	 * @param AdminCallerAndRoles $caller The user session, the action check and the role and feed refresh services.
	 */
	public function __construct(
		IRequest $request,
		private readonly AdminTemplateTools $templates,
		private readonly AdminSettingsTools $settings,
		private readonly AdminCallerAndRoles $caller,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Inline admin guard — checks session and group membership.
	 *
	 * Returns a 401/403 JSONResponse when the caller is not authenticated or
	 * not an NC admin, or null when the guard passes.
	 *
	 * @return JSONResponse|null Non-null means the request must be rejected.
	 */
	private function assertAdmin(): ?JSONResponse {
		$user = $this->caller->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				data: ['error' => 'Not authenticated'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		if ($this->settings->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(
				data: ['error' => 'Admin required'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		return null;
	}//end assertAdmin()

	/**
	 * List all admin dashboard templates.
	 *
	 * @return JSONResponse The list of templates.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-4
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listTemplates(): JSONResponse {
		$templates = $this->templates->templateService->listTemplates();

		return ResponseHelper::success(
			data: ResponseHelper::serializeList(entities: $templates)
		);
	}//end listTemplates()

	/**
	 * Get a specific admin template.
	 *
	 * @param int $id The template ID.
	 *
	 * @return JSONResponse The template data.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getTemplate(int $id): JSONResponse {
		try {
			$result = $this->templates->templateService->getTemplateWithPlacements(
				id: $id
			);
			$placements = ResponseHelper::serializeList(
				entities: $result['placements']
			);

			return ResponseHelper::success(
				data: [
					'template' => $result['template']->jsonSerialize(),
					'placements' => $placements,
				]
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(
				exception: $e,
				statusCode: Http::STATUS_NOT_FOUND
			);
		}//end try
	}//end getTemplate()

	/**
	 * Create a new admin template.
	 *
	 * @param string $name The template name.
	 * @param string|null $description The description.
	 * @param array|null $targetGroups The target groups.
	 * @param string $permissionLevel The permission level.
	 * @param bool $isDefault Whether default.
	 *
	 * @return JSONResponse The created template.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-3
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function createTemplate(
		string $name,
		string|null $description = null,
		array|null $targetGroups = null,
		string $permissionLevel = Dashboard::PERMISSION_ADD_ONLY,
		bool $isDefault = false,
	): JSONResponse {
		try {
			$template = $this->templates->templateService->createTemplate(
				name: $name,
				settings: [
					'description'     => $description,
					'targetGroups'    => $targetGroups,
					'permissionLevel' => $permissionLevel,
					'isDefault'       => $isDefault,
				]
			);

			return ResponseHelper::success(
				data: $template->jsonSerialize(),
				statusCode: Http::STATUS_CREATED
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end createTemplate()

	/**
	 * Update an admin template.
	 *
	 * @param int $id The template ID.
	 * @param string|null $name The name.
	 * @param string|null $description The description.
	 * @param array|null $targetGroups The target groups.
	 * @param string|null $permissionLevel The permission level.
	 * @param bool|null $isDefault Whether default.
	 * @param int|null $gridColumns The grid columns.
	 *
	 * @return JSONResponse The updated template.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-5
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateTemplate(
		int $id,
		string|null $name = null,
		string|null $description = null,
		array|null $targetGroups = null,
		string|null $permissionLevel = null,
		bool|null $isDefault = null,
		int|null $gridColumns = null,
	): JSONResponse {
		return $this->saveTemplate(
			templateId: $id,
			fields: [
				'name'            => $name,
				'description'     => $description,
				'targetGroups'    => $targetGroups,
				'permissionLevel' => $permissionLevel,
				'isDefault'       => $isDefault,
				'gridColumns'     => $gridColumns,
			]
		);
	}//end updateTemplate()

	/**
	 * Save the non-null template fields and answer with the template.
	 *
	 * @param int                  $templateId The template ID.
	 * @param array<string, mixed> $fields     The nullable template fields by name.
	 *
	 * @return JSONResponse The updated template, or the error.
	 */
	private function saveTemplate(int $templateId, array $fields): JSONResponse {
		try {
			$template = $this->templates->templateService->updateTemplate(
				id: $templateId,
				data: $this->buildUpdateData(fields: $fields)
			);

			return ResponseHelper::success(
				data: $template->jsonSerialize()
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end saveTemplate()

	/**
	 * Delete an admin template.
	 *
	 * @param int $id The template ID.
	 *
	 * @return JSONResponse The deletion confirmation.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-6
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteTemplate(int $id): JSONResponse {
		try {
			$this->templates->templateService->deleteTemplate(id: $id);

			return ResponseHelper::success(data: ['status' => 'ok']);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end deleteTemplate()

	/**
	 * Push an updated admin template to its already-provisioned user
	 * copies (REQ-RESYNC-001).
	 *
	 * Body: `{strategy: "overwrite"|"merge", dryRun: bool}`. Dry-run
	 * (the default) computes and returns the plan — affected copies plus
	 * per-copy add/update/remove/preserve counts — without mutating
	 * anything. A real run (`dryRun: false`) applies inline for small
	 * target groups or enqueues {@see \OCA\LaunchPad\BackgroundJob\TemplateResyncJob}
	 * for large ones, writes one audit record, and notifies every
	 * affected user.
	 *
	 * Admin-guarded twice over — the `AuthorizedAdminSetting` attribute
	 * plus the explicit {@see self::assertAdmin()} guard — matching this
	 * controller's other mutating admin actions (export/import/footer).
	 *
	 * @param int $id The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'` or `'merge'`.
	 * @param bool $dryRun When true (default), report without
	 *                     mutating.
	 *
	 * @return JSONResponse The plan, the applied result, or the
	 *                      async-accepted envelope. 400 on an invalid
	 *                      strategy or a non-template dashboard; 401/403
	 *                      on guard failure.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function resyncTemplate(
		int $id,
		string $strategy = '',
		bool $dryRun = true,
	): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		$user = $this->caller->userSession->getUser();
		$actingAdminId = '';
		if ($user !== null) {
			$actingAdminId = $user->getUID();
		}

		try {
			$result = $this->templates->resyncService->resync(
				templateId: $id,
				strategy: $strategy,
				dryRun: $dryRun,
				actingAdminId: $actingAdminId
			);

			return ResponseHelper::success(data: $result);
		} catch (InvalidArgumentException $e) {
			return ResponseHelper::error(
				exception: $e,
				statusCode: Http::STATUS_BAD_REQUEST,
				message: $e->getMessage()
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end resyncTemplate()

	/**
	 * Get admin settings.
	 *
	 * @return JSONResponse The admin settings.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-1
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getSettings(): JSONResponse {
		return ResponseHelper::success(
			data: $this->settings->settingsService->getSettings()
		);
	}//end getSettings()

	/**
	 * Update admin settings.
	 *
	 * Five settings are read under one name and written under another: GET
	 * answers `allowUserDashboards`, this PUT took only `allowUserDash`. An
	 * unrecognised key binds to null, null means "not supplied", so a caller
	 * round-tripping GET into PUT wrote nothing and was told `{"status":
	 * "ok"}`. Both spellings are accepted; the short one wins, since
	 * AdminSettings.vue sends it. Covered by AdminControllerSettingsAliasTest.
	 *
	 * @param string|null $defaultPermLevel Default permission level.
	 * @param bool|null $allowUserDash Allow user dashboards.
	 * @param bool|null $allowMultiDash Allow multiple dashboards.
	 * @param int|null $defaultGridCols Default grid columns.
	 * @param array|null $linkCreateFileExts link-button-widget createFile
	 *                                       extension allow-list
	 *                                       (REQ-LBN-004).
	 * @param string|null $defaultSharePermissionLevel Org-wide default share
	 *                                                 permission level
	 *                                                 (dashboard-sharing spec).
	 * @param array|null $forcedShareGroups Groups every new dashboard
	 *                                      is force-shared with
	 *                                      (dashboard-sharing spec).
	 * @param bool|null $legacyWidgetBridgeEnabled Enable / disable the
	 *                                             legacy widget bridge
	 *                                             (legacy-widget-bridge
	 *                                             spec).
	 * @param int|null $maxDashboardsPerUser Maximum personal
	 *                                       dashboards per user
	 *                                       (`0` = unlimited).
	 *                                       dashboard-quota-limits
	 *                                       REQ-QUOTA-001.
	 * @param int|null $maxWidgetsPerDashboard Maximum placements per
	 *                                         dashboard (`0` =
	 *                                         unlimited).
	 *                                         dashboard-quota-limits
	 *                                         REQ-QUOTA-001.
	 * @param string|null $quicksearchFallbackTarget On-dashboard quick-search
	 *                                               no-match fallback:
	 *                                               `'none'`,
	 *                                               `'unified-search'`, or
	 *                                               an `https` URL
	 *                                               template containing
	 *                                               `{query}`.
	 *                                               tile-quick-search
	 *                                               REQ-QSEARCH-004.
	 * @param string|null $defaultPermissionLevel Alias of `$defaultPermLevel`.
	 * @param bool|null $allowUserDashboards Alias of `$allowUserDash`.
	 * @param bool|null $allowMultipleDashboards Alias of `$allowMultiDash`.
	 * @param int|null $defaultGridColumns Alias of `$defaultGridCols`.
	 * @param array|null $linkCreateFileExtensions Alias of `$linkCreateFileExts`.
	 * @param bool|null $startPageWithoutNavigation Dashboard view without the navigation panel (REQ-SHELL-009).
	 * @param array|null $sharingAllowedGroups The groups whose members may share; empty means everyone (REQ-SHARE-014).
	 * @param string|null $sharingRecipientScope `everyone` or `shared-groups` (REQ-SHARE-014).
	 *
	 * @return JSONResponse The update confirmation.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-2
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateSettings(
		string|null $defaultPermLevel = null,
		bool|null $allowUserDash = null,
		bool|null $allowMultiDash = null,
		int|null $defaultGridCols = null,
		array|null $linkCreateFileExts = null,
		string|null $defaultSharePermissionLevel = null,
		array|null $forcedShareGroups = null,
		bool|null $legacyWidgetBridgeEnabled = null,
		int|null $maxDashboardsPerUser = null,
		int|null $maxWidgetsPerDashboard = null,
		string|null $quicksearchFallbackTarget = null,
		string|null $defaultPermissionLevel = null,
		bool|null $allowUserDashboards = null,
		bool|null $allowMultipleDashboards = null,
		int|null $defaultGridColumns = null,
		array|null $linkCreateFileExtensions = null, bool|null $startPageWithoutNavigation = null,
		array|null $sharingAllowedGroups = null,
		string|null $sharingRecipientScope = null,
	): JSONResponse {
		// Read-side aliases, see the docblock. Short form wins.
		$defaultPermLevel ??= $defaultPermissionLevel;
		$allowUserDash ??= $allowUserDashboards;
		$allowMultiDash ??= $allowMultipleDashboards;
		$defaultGridCols ??= $defaultGridColumns;
		$linkCreateFileExts ??= $linkCreateFileExtensions;

		try {
			$this->settings->settingsService->updateSettings(
				settings: [
					'defaultPermLevel'            => $defaultPermLevel,
					'allowUserDash'               => $allowUserDash,
					'allowMultiDash'              => $allowMultiDash,
					'defaultGridCols'             => $defaultGridCols,
					'linkCreateFileExts'          => $linkCreateFileExts,
					'defaultSharePermissionLevel' => $defaultSharePermissionLevel,
					'forcedShareGroups'           => $forcedShareGroups,
					'legacyWidgetBridgeEnabled'   => $legacyWidgetBridgeEnabled,
					'maxDashboardsPerUser'        => $maxDashboardsPerUser,
					'maxWidgetsPerDashboard'      => $maxWidgetsPerDashboard,
					'quicksearchFallbackTarget'   => $quicksearchFallbackTarget,
					'startPageWithoutNavigation'  => $startPageWithoutNavigation,
					'sharingAllowedGroups'        => $sharingAllowedGroups,
					'sharingRecipientScope'       => $sharingRecipientScope,
				]
			);

			return ResponseHelper::success(data: ['status' => 'ok']);
		} catch (\InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end updateSettings()

	/**
	 * Read the global footer settings (REQ-FTR-001, REQ-FTR-010).
	 *
	 * Returns the five footer keys as a flat camelCase object so the
	 * admin UI can render the form with one round-trip. Admin-only —
	 * non-admins receive HTTP 403 because even the read path discloses
	 * potentially-sensitive draft footer copy.
	 *
	 * @return JSONResponse The settings object, or 401/403 on guard failure.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getFooterSettings(): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		return ResponseHelper::success(
			data: $this->settings->footerService->getGlobalSettings()
		);
	}//end getFooterSettings()

	/**
	 * Patch the global footer settings (REQ-FTR-001..003, REQ-FTR-009,
	 * REQ-FTR-010).
	 *
	 * Body: any subset of `{footerEnabled, footerHtml, footerConfig,
	 * footerBackgroundColor, footerTextColor}`. The service sanitises
	 * HTML, validates the structured-config schema, and validates hex
	 * colour strings before persistence. Validation failures map to
	 * HTTP 400 (or 413 when the HTML exceeds the 8 KB cap).
	 *
	 * @param bool|null $footerEnabled Master toggle.
	 * @param string|array|null $footerHtml Raw HTML or
	 *                                      language-variant
	 *                                      map.
	 * @param array|null $footerConfig Structured config.
	 * @param string|null $footerBackgroundColor Hex (#rrggbb) or null.
	 * @param string|null $footerTextColor Hex (#rrggbb) or null.
	 *
	 * @return JSONResponse Status 200 on success, 400/413 on validation,
	 *                      401/403 on guard failure.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — NC reads params from route declaration;
	 *                                                  body uses getParams() for array_key_exists semantics.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateFooterSettings(
		bool|null $footerEnabled = null,
		mixed $footerHtml = null,
		array|null $footerConfig = null,
		string|null $footerBackgroundColor = null,
		string|null $footerTextColor = null,
	): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		$patch = $this->footerPatch();

		try {
			$this->settings->footerService->updateGlobalSettings(patch: $patch);
		} catch (InvalidArgumentException $e) {
			return $this->footerFailure(exception: $e);
		}

		return ResponseHelper::success(data: ['status' => 'ok']);
	}//end updateFooterSettings()

	/**
	 * The footer patch: only those keys the caller actually supplied.
	 *
	 * `array_key_exists` semantics on the body let admins explicitly
	 * clear a colour by sending `null`.
	 *
	 * @return array<string, mixed> The supplied footer keys and their values.
	 */
	private function footerPatch(): array {
		$body = $this->request->getParams();
		$patch = [];
		foreach (['footerEnabled', 'footerHtml', 'footerConfig', 'footerBackgroundColor', 'footerTextColor'] as $key) {
			if (array_key_exists(key: $key, array: $body) === true) {
				$patch[$key] = $body[$key];
			}
		}

		return $patch;
	}//end footerPatch()

	/**
	 * Answer a refused footer patch: 413 when the HTML exceeds the 8 KB
	 * cap, 400 for every other validation failure.
	 *
	 * @param InvalidArgumentException $exception The validation failure.
	 *
	 * @return JSONResponse The error response.
	 */
	private function footerFailure(InvalidArgumentException $exception): JSONResponse {
		$isOversize = str_contains(
			haystack: $exception->getMessage(),
			needle: '8 KB limit'
		);
		$status = Http::STATUS_BAD_REQUEST;
		if ($isOversize === true) {
			$status = Http::STATUS_REQUEST_ENTITY_TOO_LARGE;
		}

		return new JSONResponse(
			data: ['error' => $exception->getMessage()],
			statusCode: $status
		);
	}//end footerFailure()

	/**
	 * Export a single dashboard or the entire site as a ZIP archive.
	 *
	 * Implements REQ-EXIM-002 (single-dashboard export) and REQ-EXIM-003
	 * (site export). Admin-only — non-admins receive HTTP 403.
	 *
	 * Query parameters:
	 *   - `scope` (string, required): `dashboard` or `site`.
	 *   - `dashboardUuid` (string, required when scope=dashboard).
	 *
	 * @param string $scope The export scope.
	 * @param string|null $dashboardUuid The dashboard UUID for scope=dashboard.
	 *
	 * @return StreamResponse|JSONResponse The streamed ZIP, or a JSON error.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function export(
		string $scope = 'site',
		string|null $dashboardUuid = null,
	): StreamResponse|JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		if (in_array(needle: $scope, haystack: ['site', 'dashboard'], strict: true) === false) {
			return $this->failure(message: 'Unsupported scope: ' . $scope, statusCode: Http::STATUS_BAD_REQUEST);
		}

		$userId = (string)$this->caller->userSession->getUser()?->getUID();

		if ($scope === 'site') {
			return $this->templates->exportService->exportSite(currentUserId: $userId);
		}

		return $this->exportOneDashboard(dashboardUuid: $dashboardUuid, userId: $userId);
	}//end export()

	/**
	 * Export one dashboard after checking its UUID: 400 when it is missing
	 * or malformed, 404 when no dashboard answers to it.
	 *
	 * @param string|null $dashboardUuid The dashboard UUID from the request.
	 * @param string      $userId        The exporting admin.
	 *
	 * @return StreamResponse|JSONResponse The streamed ZIP, or a JSON error.
	 */
	private function exportOneDashboard(string|null $dashboardUuid, string $userId): StreamResponse|JSONResponse {
		if ($dashboardUuid === null || $dashboardUuid === '') {
			return $this->failure(message: 'dashboardUuid parameter is required when scope=dashboard', statusCode: Http::STATUS_BAD_REQUEST);
		}

		if (preg_match(pattern: '/^[A-Za-z0-9\-]{8,}$/', subject: $dashboardUuid) !== 1) {
			return $this->failure(message: 'Invalid dashboard UUID format', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			return $this->templates->exportService->exportDashboard(
				dashboardUuid: $dashboardUuid,
				currentUserId: $userId
			);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		}
	}//end exportOneDashboard()

	/**
	 * Import a previously-exported ZIP archive.
	 *
	 * Implements REQ-EXIM-004..008. Admin-only.
	 *
	 * Multipart body: a `file` field containing the ZIP archive.
	 * Query parameter: `preserveUuids` (default false).
	 *
	 * @param bool $preserveUuids When true, fail on UUID collision.
	 *
	 * @return JSONResponse The import summary, or an error response.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function import(bool $preserveUuids = false): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		$tmpName = $this->uploadedArchivePath();
		if ($tmpName === null) {
			return $this->failure(message: 'No file uploaded under field "file".', statusCode: Http::STATUS_BAD_REQUEST);
		}

		$userId = (string)$this->caller->userSession->getUser()?->getUID();

		try {
			$result = $this->templates->importService->import(
				zipPath: $tmpName,
				preserveUuids: $preserveUuids,
				currentUserId: $userId
			);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		return $this->importSummary(result: $result);
	}//end import()

	/**
	 * The temporary path of the uploaded ZIP archive, or null when no
	 * file was uploaded under the `file` field.
	 *
	 * Multipart uploads bind to $_FILES; PHP only populates this for
	 * POST requests, which is what the route declares (REQ-EXIM-004).
	 *
	 * @return string|null The temporary path, or null.
	 */
	private function uploadedArchivePath(): string|null {
		$upload = $_FILES['file'] ?? null;
		if (is_array($upload) === false
			|| isset($upload['tmp_name']) === false
			|| (string)$upload['tmp_name'] === ''
		) {
			return null;
		}

		return (string)$upload['tmp_name'];
	}//end uploadedArchivePath()

	/**
	 * Answer an import result: 409 with zero counts on a UUID collision,
	 * otherwise the imported and skipped counts.
	 *
	 * @param array<string, mixed> $result The import service result.
	 *
	 * @return JSONResponse The import summary.
	 */
	private function importSummary(array $result): JSONResponse {
		if ($result['status'] === ImportService::ERR_UUID_COLLISION) {
			return new JSONResponse(
				data: [
					'importedDashboardCount' => 0,
					'skippedDashboardCount' => 0,
					'errors' => $result['errors'],
				],
				statusCode: Http::STATUS_CONFLICT
			);
		}

		return ResponseHelper::success(
			data: [
				'importedDashboardCount' => $result['importedDashboardCount'],
				'skippedDashboardCount' => $result['skippedDashboardCount'],
				'errors' => $result['errors'],
			]
		);
	}//end importSummary()

	/**
	 * List every role assignment in the system (REQ-ROLE-006). NC-admin only.
	 *
	 * Returns a JSON array of role-assignment rows with their persisted
	 * fields (id, userId, groupId, role, assignedBy, assignedAt). The
	 * caller MUST be a Nextcloud admin; non-admins receive HTTP 403.
	 *
	 * @return JSONResponse The list of role assignments, or 401/403.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listRoles(): JSONResponse {

		$assignments = $this->caller->roleService->listAssignments();

		return ResponseHelper::success(
			data: ResponseHelper::serializeList(entities: $assignments)
		);
	}//end listRoles()

	/**
	 * Create a new role assignment (REQ-ROLE-004). NC-admin only.
	 *
	 * Accepts a JSON body `{userId?: string, groupId?: string, role: string}`.
	 * Exactly one of `userId` / `groupId` MUST be set. Returns the new
	 * assignment with HTTP 201 on success. Returns 400 on structural
	 * failure, 409 on duplicate, 401/403 on auth failure.
	 *
	 * @param string|null $userId The target user ID (XOR with groupId).
	 * @param string|null $groupId The target group ID (XOR with userId).
	 * @param string|null $role The role name (admin / editor / viewer).
	 *
	 * @return JSONResponse The created assignment, or an error envelope.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function createRole(
		string|null $userId = null,
		string|null $groupId = null,
		string|null $role = null,
	): JSONResponse {

		$assignedBy = (string)$this->caller->userSession->getUser()->getUID();

		try {
			$assignment = $this->caller->roleService->assignRole(
				userId: $userId,
				groupId: $groupId,
				role: (string)$role,
				assignedBy: $assignedBy
			);
		} catch (DuplicateRoleAssignmentException $e) {
			return $this->roleFailure(exception: $e, statusCode: Http::STATUS_CONFLICT);
		} catch (InvalidRoleAssignmentException $e) {
			return $this->roleFailure(exception: $e, statusCode: Http::STATUS_BAD_REQUEST);
		}//end try

		return ResponseHelper::success(
			data: $assignment->jsonSerialize(),
			statusCode: Http::STATUS_CREATED
		);
	}//end createRole()

	/**
	 * The error envelope of a refused role assignment.
	 *
	 * @param ResourceException $exception  The refusal.
	 * @param int               $statusCode The HTTP status to answer with.
	 *
	 * @return JSONResponse The error response.
	 */
	private function roleFailure(ResourceException $exception, int $statusCode): JSONResponse {
		return new JSONResponse(
			data: [
				'error' => $exception->getDisplayMessage(),
				'errorCode' => $exception->getErrorCode(),
			],
			statusCode: $statusCode
		);
	}//end roleFailure()

	/**
	 * Delete a role assignment by ID (REQ-ROLE-004). NC-admin only.
	 *
	 * Returns 204 on success, 404 when no row matches, 401/403 on auth.
	 *
	 * @param int $id The role assignment ID.
	 *
	 * @return JSONResponse Empty success or error envelope.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteRole(int $id): JSONResponse {

		try {
			$this->caller->roleService->removeRole(id: $id);
		} catch (DoesNotExistException) {
			return ResponseHelper::forbidden(
				message: 'Role assignment not found'
			)->setStatus(status: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(
			data: [],
			statusCode: Http::STATUS_NO_CONTENT
		);
	}//end deleteRole()

	/**
	 * Return the calling user's effective LaunchPad role and source
	 * (REQ-ROLE-006). Available to any authenticated user.
	 *
	 * Response shape: `{role: string|null, source: string|null}`.
	 *
	 * @return JSONResponse The role / source envelope, or 401.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[NoAdminRequired]
	public function getMyRole(): JSONResponse {
		$user = $this->caller->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->caller->actionAuth->requireAction($user, 'admin.get-my-role');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		$userId = (string)$user->getUID();

		return ResponseHelper::success(
			data: [
				'role' => $this->caller->roleService->getEffectiveRole(userId: $userId),
				'source' => $this->caller->roleService->getRoleSource(userId: $userId),
			]
		);
	}//end getMyRole()

	/**
	 * Trigger an immediate background feed refresh (REQ-FRJ-010).
	 *
	 * Admin-only — guarded by {@see self::requireAdmin()}. Optionally
	 * scope the refresh to a single feed URL (must be HTTP/HTTPS).
	 * Returns `{processedCount, successCount, failureCount, durationMs}`.
	 *
	 * @param string|null $feedUrl Optional single URL to refresh.
	 *
	 * @return JSONResponse The aggregate refresh summary.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function refreshFeedsNow(?string $feedUrl = null): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		if ($feedUrl !== null && $feedUrl !== '') {
			$scheme = strtolower(
				string: (string)parse_url(
					url: $feedUrl,
					component: PHP_URL_SCHEME
				)
			);
			if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === false) {
				return $this->failure(message: 'feedUrl must use http:// or https:// scheme.', statusCode: Http::STATUS_BAD_REQUEST);
			}
		}

		$summary = $this->caller->feedRefresh->refreshAll(onlyUrl: $feedUrl);

		return new JSONResponse(data: $summary, statusCode: Http::STATUS_OK);
	}//end refreshFeedsNow()

	/**
	 * `POST /api/admin/templates/{uuid}/preview-image` — admin-only
	 * preview-image upload (REQ-TMPL-017).
	 *
	 * Body (JSON): `{base64: 'data:image/<type>;base64,<bytes>'}`. The
	 * payload is delegated to {@see ResourceService::upload()} (the
	 * "custom-icon-upload pattern"); the returned URL is written to the
	 * template's `templatePreviewImage` column. Allowed image types:
	 * PNG, JPG, GIF, WebP, SVG (sanitised). Maximum decoded size: 5 MB.
	 *
	 * @param string $uuid The template UUID.
	 * @param string $base64 The base64 data URL.
	 *
	 * @return JSONResponse `{status: 'success', previewImage: '...'}`
	 *                      on success.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function uploadTemplatePreviewImage(
		string $uuid,
		string $base64 = '',
	): JSONResponse {

		if ($base64 === '') {
			return $this->badRequestWithCode(code: 'invalid_payload', message: 'Field "base64" is required');
		}

		try {
			$url = $this->templates->templateService->uploadPreviewImage(
				templateUuid: $uuid,
				base64DataUrl: $base64
			);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(
				data: [
					'status' => 'error',
					'error' => 'not_found',
					'message' => 'Template not found',
				],
				statusCode: Http::STATUS_NOT_FOUND
			);
		} catch (ResourceException $e) {
			// Catches every typed ResourceException subclass (bad data URL,
			// disallowed image format, oversized payload, SVG sanitiser
			// rejection, storage failure) returned by ResourceService::upload
			// — all collapse to a single 400 envelope per REQ-TMPL-017.
			return $this->badRequestWithCode(code: 'invalid_image', message: $e->getMessage());
		}//end try

		return new JSONResponse(
			data: [
				'status' => 'success',
				'previewImage' => $url,
			],
			statusCode: Http::STATUS_OK
		);
	}//end uploadTemplatePreviewImage()

	/**
	 * Get the setup-wizard state (REQ-WIZ-008).
	 *
	 * Admin-only — non-admins receive HTTP 403. Returns
	 * `{complete, currentRecommendedStep, stepStatuses}`.
	 *
	 * @return JSONResponse The wizard state, or 401/403.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getWizardState(): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		return ResponseHelper::success(
			data: $this->settings->setupWizardService->getWizardState()
		);
	}//end getWizardState()

	/**
	 * Mark the setup-wizard complete (REQ-WIZ-009).
	 *
	 * Idempotent — calling on a completed instance returns 200 with the
	 * same payload. Admin-only.
	 *
	 * @return JSONResponse The post-completion wizard state, or 401/403.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function completeWizard(): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		return ResponseHelper::success(
			data: $this->settings->setupWizardService->markWizardComplete()
		);
	}//end completeWizard()

	/**
	 * Keep the non-null update fields.
	 *
	 * @param array<string, mixed> $fields The nullable update fields by name.
	 *
	 * @return array<string, mixed> The non-null update data.
	 */
	private function buildUpdateData(array $fields): array {
		return array_filter(
			array: $fields,
			callback: function ($value) {
				return $value !== null;
			}
		);
	}//end buildUpdateData()
}//end class
