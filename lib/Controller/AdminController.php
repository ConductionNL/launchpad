<?php

/**
 * AdminController
 *
 * Controller for admin dashboard template management.
 *
 * @category  Controller
 * @package   OCA\MyDash\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\MyDash\Controller;

use InvalidArgumentException;
use OCA\MyDash\AppInfo\Application;
use OCA\MyDash\Db\Dashboard;
use OCA\MyDash\Service\AdminTemplateService;
use OCA\MyDash\Service\AdminSettingsService;
use OCA\MyDash\Service\ExportService;
use OCA\MyDash\Service\ImportService;
use OCA\MyDash\Service\SetupWizardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for admin dashboard template management.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      Mirrors aggregated services.
 * @SuppressWarnings(PHPMD.Superglobals)
 *      $_FILES is the only multipart entry point.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 *      The controller is the single admin endpoint surface for templates,
 *      settings, exports, imports, and the setup wizard. Splitting would
 *      create a parallel routing surface admins must remember.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *      Same; complexity is a function of the number of guarded admin
 *      endpoints, not nested branching inside any single method.
 */
class AdminController extends Controller
{
    /**
     * Constructor
     *
     * @param IRequest             $request            The request.
     * @param AdminTemplateService $templateService    The admin template service.
     * @param AdminSettingsService $settingsService    The admin settings service.
     * @param IGroupManager        $groupManager       The Nextcloud group manager.
     * @param IUserSession         $userSession        The current user session.
     * @param ExportService        $exportService      ZIP export service
     *                                                 (REQ-EXIM-001..003).
     * @param ImportService        $importService      ZIP import service
     *                                                 (REQ-EXIM-004..008).
     * @param SetupWizardService   $setupWizardService Setup-wizard
     *                                                 orchestrator
     *                                                 (REQ-WIZ-001..011).
     */
    public function __construct(
        IRequest $request,
        private readonly AdminTemplateService $templateService,
        private readonly AdminSettingsService $settingsService,
        private readonly IGroupManager $groupManager,
        private readonly IUserSession $userSession,
        private readonly ExportService $exportService,
        private readonly ImportService $importService,
        private readonly SetupWizardService $setupWizardService,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * List all admin dashboard templates.
     *
     * @return JSONResponse The list of templates.
     */
    public function listTemplates(): JSONResponse
    {
        $templates = $this->templateService->listTemplates();

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
     */
    public function getTemplate(int $id): JSONResponse
    {
        try {
            $result     = $this->templateService->getTemplateWithPlacements(
                id: $id
            );
            $placements = ResponseHelper::serializeList(
                entities: $result['placements']
            );

            return ResponseHelper::success(
                data: [
                    'template'   => $result['template']->jsonSerialize(),
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
     * @param string      $name            The template name.
     * @param string|null $description     The description.
     * @param array|null  $targetGroups    The target groups.
     * @param string      $permissionLevel The permission level.
     * @param bool        $isDefault       Whether default.
     *
     * @return JSONResponse The created template.
     */
    public function createTemplate(
        string $name,
        ?string $description=null,
        ?array $targetGroups=null,
        string $permissionLevel=Dashboard::PERMISSION_ADD_ONLY,
        bool $isDefault=false
    ): JSONResponse {
        try {
            $template = $this->templateService->createTemplate(
                name: $name,
                description: $description,
                targetGroups: $targetGroups,
                permissionLevel: $permissionLevel,
                isDefault: $isDefault
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
     * @param int         $id              The template ID.
     * @param string|null $name            The name.
     * @param string|null $description     The description.
     * @param array|null  $targetGroups    The target groups.
     * @param string|null $permissionLevel The permission level.
     * @param bool|null   $isDefault       Whether default.
     * @param int|null    $gridColumns     The grid columns.
     *
     * @return JSONResponse The updated template.
     */
    public function updateTemplate(
        int $id,
        ?string $name=null,
        ?string $description=null,
        ?array $targetGroups=null,
        ?string $permissionLevel=null,
        ?bool $isDefault=null,
        ?int $gridColumns=null
    ): JSONResponse {
        try {
            $data = $this->buildUpdateData(
                name: $name,
                description: $description,
                targetGroups: $targetGroups,
                permissionLevel: $permissionLevel,
                isDefault: $isDefault,
                gridColumns: $gridColumns
            );

            $template = $this->templateService->updateTemplate(
                id: $id,
                data: $data
            );

            return ResponseHelper::success(
                data: $template->jsonSerialize()
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end updateTemplate()

    /**
     * Delete an admin template.
     *
     * @param int $id The template ID.
     *
     * @return JSONResponse The deletion confirmation.
     */
    public function deleteTemplate(int $id): JSONResponse
    {
        try {
            $this->templateService->deleteTemplate(id: $id);

            return ResponseHelper::success(data: ['status' => 'ok']);
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end deleteTemplate()

    /**
     * Get admin settings.
     *
     * @return JSONResponse The admin settings.
     */
    public function getSettings(): JSONResponse
    {
        return ResponseHelper::success(
            data: $this->settingsService->getSettings()
        );
    }//end getSettings()

    /**
     * Update admin settings.
     *
     * @param string|null $defaultPermLevel   Default permission level.
     * @param bool|null   $allowUserDash      Allow user dashboards.
     * @param bool|null   $allowMultiDash     Allow multiple dashboards.
     * @param int|null    $defaultGridCols    Default grid columns.
     * @param array|null  $linkCreateFileExts link-button-widget createFile
     *                                        extension allow-list
     *                                        (REQ-LBN-004).
     *
     * @return JSONResponse The update confirmation.
     */
    public function updateSettings(
        ?string $defaultPermLevel=null,
        ?bool $allowUserDash=null,
        ?bool $allowMultiDash=null,
        ?int $defaultGridCols=null,
        ?array $linkCreateFileExts=null
    ): JSONResponse {
        try {
            $this->settingsService->updateSettings(
                defaultPermLevel: $defaultPermLevel,
                allowUserDash: $allowUserDash,
                allowMultiDash: $allowMultiDash,
                defaultGridCols: $defaultGridCols,
                linkCreateFileExts: $linkCreateFileExts
            );

            return ResponseHelper::success(data: ['status' => 'ok']);
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end updateSettings()

    /**
     * List all Nextcloud groups partitioned for the admin UI (REQ-ASET-013).
     *
     * Returns `{active, inactive, allKnown}`:
     * - `active`  — the persisted `group_order` list, in admin-chosen order.
     *               Stale IDs (no longer in Nextcloud) are preserved so the
     *               admin can see and remove them.
     * - `inactive` — every Nextcloud group ID NOT in `active`, sorted by
     *                display name (case-insensitive).
     * - `allKnown` — full descriptor list `{id, displayName}` for every group
     *                currently known to Nextcloud, so the UI can render
     *                display names without a second round-trip. Stale IDs are
     *                NOT included here (no display name available).
     *
     * Admin-only (REQ-ASET-014): non-admins receive HTTP 403.
     *
     * @return JSONResponse The grouped descriptor payload, or 403.
     *
     * @NoAdminRequired
     */
    public function listGroups(): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        $allKnown    = [];
        $allKnownIds = [];
        foreach ($this->groupManager->search(search: '') as $group) {
            $id         = $group->getGID();
            $allKnown[] = [
                'id'          => $id,
                'displayName' => $group->getDisplayName(),
            ];
            $allKnownIds[$id] = $group->getDisplayName();
        }

        // Stable sort `allKnown` by displayName (case-insensitive).
        usort(
            array: $allKnown,
            callback: static function (array $a, array $b): int {
                return strcasecmp(
                    string1: $a['displayName'],
                    string2: $b['displayName']
                );
            }
        );

        $active     = $this->settingsService->getGroupOrder();
        $activeKeys = array_flip($active);

        $inactive = [];
        foreach (array_keys($allKnownIds) as $id) {
            if (isset($activeKeys[$id]) === false) {
                $inactive[] = $id;
            }
        }

        // Sort `inactive` by displayName (case-insensitive) per REQ-ASET-013.
        usort(
            array: $inactive,
            callback: static function (string $a, string $b) use ($allKnownIds): int {
                return strcasecmp(
                    string1: $allKnownIds[$a] ?? $a,
                    string2: $allKnownIds[$b] ?? $b
                );
            }
        );

        return ResponseHelper::success(
            data: [
                'active'   => $active,
                'inactive' => $inactive,
                'allKnown' => $allKnown,
            ]
        );
    }//end listGroups()

    /**
     * Replace the persisted group priority order wholesale (REQ-ASET-014).
     *
     * Accepts a JSON body `{groups: string[]}`:
     * - 403 if the caller is not a Nextcloud admin (no side effects).
     * - 400 if `groups` is missing or contains a non-string element (no
     *   side effects on the persisted setting).
     * - Duplicates are deduplicated (first occurrence wins, order preserved).
     * - Unknown IDs are tolerated (per REQ-ASET-014 "Unknown IDs accepted").
     * - 200 with `{status: 'ok', groupOrder: string[]}` on success.
     *
     * @param mixed $groups The new ordered list (validated to `string[]`).
     *
     * @return JSONResponse The status response.
     *
     * @NoAdminRequired
     */
    public function updateGroupOrder(mixed $groups=null): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        if (is_array($groups) === false) {
            return new JSONResponse(
                data: ['error' => 'Field "groups" must be an array of strings.'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $this->settingsService->setGroupOrder(groupIds: $groups);
        } catch (InvalidArgumentException) {
            // ADR-005: do not leak raw exception messages.
            return new JSONResponse(
                data: ['error' => 'Invalid groups payload'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        return ResponseHelper::success(
            data: [
                'status'     => 'ok',
                'groupOrder' => $this->settingsService->getGroupOrder(),
            ]
        );
    }//end updateGroupOrder()

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
     * @param string      $scope         The export scope.
     * @param string|null $dashboardUuid The dashboard UUID for scope=dashboard.
     *
     * @return StreamResponse|JSONResponse The streamed ZIP, or a JSON error.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function export(
        string $scope='site',
        ?string $dashboardUuid=null
    ): StreamResponse|JSONResponse {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        if (in_array(needle: $scope, haystack: ['site', 'dashboard'], strict: true) === false) {
            return new JSONResponse(
                data: ['error' => 'Unsupported scope: '.$scope],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        $userId = (string) $this->userSession->getUser()?->getUID();

        if ($scope === 'site') {
            return $this->exportService->exportSite(currentUserId: $userId);
        }

        if ($dashboardUuid === null || $dashboardUuid === '') {
            return new JSONResponse(
                data: ['error' => 'dashboardUuid parameter is required when scope=dashboard'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        if (preg_match(pattern: '/^[A-Za-z0-9\-]{8,}$/', subject: $dashboardUuid) !== 1) {
            return new JSONResponse(
                data: ['error' => 'Invalid dashboard UUID format'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        try {
            return $this->exportService->exportDashboard(
                dashboardUuid: $dashboardUuid,
                currentUserId: $userId
            );
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: ['error' => 'Dashboard not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }
    }//end export()

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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function import(bool $preserveUuids=false): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        // Multipart uploads bind to $_FILES; PHP only populates this for
        // POST requests, which is what the route declares (REQ-EXIM-004).
        $upload = $_FILES['file'] ?? null;
        if (is_array($upload) === false
            || isset($upload['tmp_name']) === false
            || (string) $upload['tmp_name'] === ''
        ) {
            return new JSONResponse(
                data: ['error' => 'No file uploaded under field "file".'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        $tmpName = (string) $upload['tmp_name'];

        $userId = (string) $this->userSession->getUser()?->getUID();

        try {
            $result = $this->importService->import(
                zipPath: $tmpName,
                preserveUuids: $preserveUuids,
                currentUserId: $userId
            );
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        if ($result['status'] === ImportService::ERR_UUID_COLLISION) {
            return new JSONResponse(
                data: [
                    'importedDashboardCount' => 0,
                    'skippedDashboardCount'  => 0,
                    'errors'                 => $result['errors'],
                ],
                statusCode: Http::STATUS_CONFLICT
            );
        }

        return ResponseHelper::success(
            data: [
                'importedDashboardCount' => $result['importedDashboardCount'],
                'skippedDashboardCount'  => $result['skippedDashboardCount'],
                'errors'                 => $result['errors'],
            ]
        );
    }//end import()

    /**
     * Get the setup-wizard state (REQ-WIZ-008).
     *
     * Admin-only — non-admins receive HTTP 403. Returns
     * `{complete, currentRecommendedStep, stepStatuses}`.
     *
     * @return JSONResponse The wizard state, or 401/403.
     *
     * @NoAdminRequired
     */
    public function getWizardState(): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        return ResponseHelper::success(
            data: $this->setupWizardService->getWizardState()
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
     * @NoAdminRequired
     */
    public function completeWizard(): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        return ResponseHelper::success(
            data: $this->setupWizardService->markWizardComplete()
        );
    }//end completeWizard()

    /**
     * Persist the storage backend choice from Step 2 (REQ-WIZ-003).
     *
     * Validates the selection and writes `mydash.content_storage`. The
     * GroupFolder option is server-side gated by the `groupfolders` app
     * dependency — selecting it without the app installed returns 400.
     * Admin-only.
     *
     * @param string|null $storage The chosen backend.
     *
     * @return JSONResponse The post-write wizard state, or 400/401/403.
     *
     * @NoAdminRequired
     */
    public function setWizardStorage(?string $storage=null): JSONResponse
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) {
            return $guard;
        }

        if ($storage === null || $storage === '') {
            return new JSONResponse(
                data: ['error' => 'Field "storage" is required.'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        if ($storage === SetupWizardService::STORAGE_GROUPFOLDER
            && $this->setupWizardService->hasGroupfolderApp() === false
        ) {
            return new JSONResponse(
                data: ['error' => 'GroupFolder app is not installed.'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $this->setupWizardService->setContentStorage(value: $storage);
        } catch (InvalidArgumentException) {
            return new JSONResponse(
                data: ['error' => 'Unsupported storage backend.'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        return ResponseHelper::success(
            data: $this->setupWizardService->getWizardState()
        );
    }//end setWizardStorage()

    /**
     * Verify the current session belongs to a Nextcloud admin.
     *
     * Returns `null` when the caller is an admin (proceed). Returns a 401
     * JSONResponse when no user is logged in, and a 403 JSONResponse when
     * the user is not an admin. REQ-ASET-014.
     *
     * @return JSONResponse|null The error response when the guard fails;
     *                           `null` when the caller is an admin.
     */
    private function requireAdmin(): ?JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return ResponseHelper::unauthorized();
        }

        if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
            return ResponseHelper::forbidden(
                message: 'Administrator privileges required.'
            );
        }

        return null;
    }//end requireAdmin()

    /**
     * Build the update data array from nullable parameters.
     *
     * @param string|null $name            The name.
     * @param string|null $description     The description.
     * @param array|null  $targetGroups    The target groups.
     * @param string|null $permissionLevel The permission level.
     * @param bool|null   $isDefault       Whether default.
     * @param int|null    $gridColumns     The grid columns.
     *
     * @return array The non-null update data.
     */
    private function buildUpdateData(
        ?string $name,
        ?string $description,
        ?array $targetGroups,
        ?string $permissionLevel,
        ?bool $isDefault,
        ?int $gridColumns
    ): array {
        $fields = [
            'name'            => $name,
            'description'     => $description,
            'targetGroups'    => $targetGroups,
            'permissionLevel' => $permissionLevel,
            'isDefault'       => $isDefault,
            'gridColumns'     => $gridColumns,
        ];

        return array_filter(
            array: $fields,
            callback: function ($value) {
                return $value !== null;
            }
        );
    }//end buildUpdateData()
}//end class
