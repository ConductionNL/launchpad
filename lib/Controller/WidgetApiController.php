<?php

/**
 * WidgetApiController
 *
 * Controller for managing dashboard widgets.
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

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Controller for managing dashboard widgets.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Routes for calendar
 *                                                 events, tiles, and
 *                                                 generic widget CRUD
 *                                                 share this controller.
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-3
 */
class WidgetApiController extends Controller
{
    /**
     * Constructor
     *
     * @param IRequest                     $request           The request.
     * @param WidgetService                $widgetService     The widget service.
     * @param PermissionService            $permissionService The permission service.
     * @param NewsWidgetService            $newsWidgetService The news widget service.
     * @param CalendarWidgetService        $calendarService   The calendar widget service (REQ-CAL-003).
     * @param WidgetPlacementService       $placementService  Placement-payload validators (REQ-CONT-006).
     * @param RoleFeaturePermissionService $roleFeaturePerm   Role-feature filter (REQ-RFP-001..010).
     * @param IUserSession                 $userSession       User session, used to resolve the
     *                                                        authenticated IUser for ADR-023 action checks.
     * @param ActionAuthService            $actionAuth        The ADR-023 action authorization service.
     * @param LoggerInterface              $logger            PSR-3 logger for role-denial audit entries (Task 4).
     * @param string|null                  $userId            The user ID.
     */
    public function __construct(
        IRequest $request,
        private readonly WidgetService $widgetService,
        private readonly PermissionService $permissionService,
        private readonly NewsWidgetService $newsWidgetService,
        private readonly CalendarWidgetService $calendarService,
        private readonly WidgetPlacementService $placementService,
        private readonly RoleFeaturePermissionService $roleFeaturePerm,
        private readonly IUserSession $userSession,
        private readonly ActionAuthService $actionAuth,
        private readonly LoggerInterface $logger,
        private readonly ?string $userId,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * List all available Nextcloud widgets, filtered by the caller's
     * role-feature permissions (REQ-RFP-001 / REQ-RFP-003).
     *
     * @return JSONResponse The list of available widgets.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-32
     */
    #[NoAdminRequired]
    public function listAvailable(): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'widget.list-available');
        } catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
        }

        $widgets = $this->widgetService->getAvailableWidgets();

        if ($this->userId === null) {
            return ResponseHelper::success(data: $widgets);
        }

        $allowed = $this->roleFeaturePerm->getAllowedWidgetIds(
            userId: $this->userId
        );
        if ($allowed === null) {
            // Backwards-compat: nothing configured, return everything.
            return ResponseHelper::success(data: $widgets);
        }

        $filtered = array_values(
            array: array_filter(
                array: $widgets,
                callback: function (array $widget) use ($allowed): bool {
                    $id = (string) ($widget['id'] ?? '');
                    return $id !== ''
                        && in_array(
                            needle: $id,
                            haystack: $allowed,
                            strict: true
                        );
                }
            )
        );

        return ResponseHelper::success(data: $filtered);
    }//end listAvailable()

    /**
     * Get widget items for specified widgets.
     *
     * @param array $widgets Array of widget IDs.
     * @param int   $limit   Maximum items per widget.
     *
     * @return JSONResponse The widget items.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-33
     * @spec openspec/changes/role-based-content/tasks.md#task-3
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getItems(
        array $widgets=[],
        int $limit=7
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        try {
            $this->actionAuth->requireAction($user, 'widget.get-items');
        } catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
        }

        // REQ-RFP-001 s.3 / REQ-RFP-006 s.2: deny content requests for
        // widgets the user's role does not permit. Log each denied widget
        // as an audit entry (Task 4). If every requested widget is denied,
        // return HTTP 403 so the caller cannot infer widget existence.
        $deniedWidgets = [];
        foreach ($widgets as $widgetId) {
            if ($this->roleFeaturePerm->isWidgetAllowed(
                userId: $this->userId,
                widgetId: (string) $widgetId
            ) === false
            ) {
                $deniedWidgets[] = $widgetId;
                $this->logger->warning(
                    message: 'role_permission_denied',
                    context: [
                        'userId'    => $this->userId,
                        'widgetId'  => $widgetId,
                        'timestamp' => (new DateTimeImmutable())->format(format: DateTimeInterface::ATOM),
                        'reason'    => 'role_permission_denied',
                        'app'       => Application::APP_ID,
                    ]
                );
            }//end if
        }//end foreach

        if (count(value: $deniedWidgets) > 0 && count(value: $widgets) === count(value: $deniedWidgets)) {
            return new JSONResponse(
                data: ['message' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        }

        $allowedWidgets = array_values(
            array: array_filter(
                array: $widgets,
                callback: fn($widgetId) => in_array(needle: $widgetId, haystack: $deniedWidgets, strict: true) === false
            )
        );

        return ResponseHelper::success(
            data: $this->widgetService->getWidgetItems(
                userId: $this->userId,
                widgetIds: $allowedWidgets,
                limit: $limit
            )
        );
    }//end getItems()

    /**
     * Add a widget to a dashboard.
     *
     * @param int    $dashboardId Dashboard ID.
     * @param string $widgetId    Widget ID.
     * @param int    $gridX       Grid X position.
     * @param int    $gridY       Grid Y position.
     * @param int    $gridWidth   Grid width.
     * @param int    $gridHeight  Grid height.
     *
     * @return JSONResponse The created widget placement.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-34
     */
    #[NoAdminRequired]
    public function addWidget(
        int $dashboardId,
        ?string $widgetId=null,
        int $gridX=0,
        int $gridY=0,
        int $gridWidth=4,
        int $gridHeight=4
    ): JSONResponse {
        $denial = $this->denyAddWidget(
            dashboardId: $dashboardId,
            widgetId: $widgetId
        );
        if ($denial !== null) {
            return $denial;
        }

        // REQ-CONT-006: reject deeply-nested container payloads BEFORE
        // touching the placement mapper so no rows are inserted on a
        // depth violation. Tolerant of non-container payloads (no-op
        // when the request carries no `content.placements[]` blob).
        $contentParam = $this->request->getParam(key: 'content');
        $depthDenial  = $this->denyContainerDepth(content: $contentParam);
        if ($depthDenial !== null) {
            return $depthDenial;
        }

        // Forward the per-type content payload (registry-driven custom
        // widgets carry their config here — `label`, `text`, `image`, etc.).
        // Tolerant of legacy callers that send only `widgetId` and grid
        // coords with no content blob: $contentParam stays null and
        // PlacementService leaves the column NULL.
        $contentToPersist = null;
        if (is_array($contentParam) === true) {
            $contentToPersist = $contentParam;
        }

        try {
            $placement = $this->widgetService->addWidget(
                dashboardId: $dashboardId,
                widgetId: (string) $widgetId,
                gridX: $gridX,
                gridY: $gridY,
                gridWidth: $gridWidth,
                gridHeight: $gridHeight,
                content: $contentToPersist
            );

            return ResponseHelper::success(
                data: $placement->jsonSerialize(),
                statusCode: Http::STATUS_CREATED
            );
        } catch (QuotaExceededException $e) {
            // Dashboard-quota-limits REQ-QUOTA-003: dashboard is at the
            // widget limit — HTTP 409 with the structured body.
            return ResponseHelper::quotaExceeded(exception: $e);
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end addWidget()

    /**
     * Resolve the guard chain for {@see self::addWidget()}.
     *
     * Returns the JSONResponse that must be sent when the request is
     * refused, or NULL when every guard passes and the caller may
     * proceed. Extracted so `addWidget()` reads as "guard, validate
     * payload, persist" rather than a flat run of early returns.
     *
     * Order is load-bearing: authentication first, then the
     * action-level authorisation (which throws), then payload
     * validation, then the two per-object permission checks.
     *
     * @param int         $dashboardId Dashboard ID.
     * @param string|null $widgetId    Widget ID from the request body.
     *
     * @return JSONResponse|NULL The refusal, or NULL to proceed.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-34
     */
    private function denyAddWidget(int $dashboardId, ?string $widgetId): ?JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        $this->actionAuth->requireAction($user, 'widget.add-widget');

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        // Validate the widgetId parameter explicitly so a missing /
        // empty value returns 400 rather than letting PHP's TypeError
        // bubble up to a 500. The route declared `string $widgetId`
        // (non-nullable) before — Newman's drift test sends a body
        // without the field and used to crash the dispatcher.
        if ($widgetId === null || $widgetId === '') {
            return ResponseHelper::error(
                exception: new InvalidArgumentException(
                    'Missing required field: widgetId'
                ),
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        if ($this->permissionService->canAddWidget(
            userId: $this->userId,
            dashboardId: $dashboardId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        // REQ-RFP-001 / REQ-RFP-003: a user may only add a widget that their
        // role-feature-permission profile permits. `isWidgetAllowed` returns
        // `true` when no restriction is configured (null allowed set), so
        // unconfigured deployments are unaffected.
        if ($this->roleFeaturePerm->isWidgetAllowed(
            userId: $this->userId,
            widgetId: $widgetId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        return null;
    }//end denyAddWidget()

    /**
     * Guard the mandatory-read acknowledgement fields on a placement
     * update.
     *
     * REQ-ACK-001: setting/changing/clearing the acknowledgement
     * requirement is restricted to an admin or the template owner — a
     * non-author who can otherwise style the widget MUST be rejected
     * (ADR-005, no privilege bleed through the styling gate). The guard
     * only engages when the payload actually touches an
     * acknowledgement-requirement field, so ordinary styling updates are
     * unaffected.
     *
     * @param int $placementId The placement being updated.
     *
     * @return JSONResponse|NULL The refusal, or NULL to proceed.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-34
     */
    private function denyAcknowledgementChange(int $placementId): ?JSONResponse
    {
        $ackFields = [
            'requiresAcknowledgement',
            'acknowledgementPrompt',
            'acknowledgementDeadline',
            'reacknowledgeOnChange',
            'acknowledgementContentVersion',
        ];

        $touchesAck = false;
        foreach ($ackFields as $ackField) {
            if ($this->request->getParam(key: $ackField) !== null) {
                $touchesAck = true;
                break;
            }
        }

        if ($touchesAck === false) {
            return null;
        }

        if ($this->permissionService->canManageAcknowledgement(
            userId: (string) $this->userId,
            placementId: $placementId
        ) === false
        ) {
            return ResponseHelper::forbidden(
                message: 'Only an admin or the template owner may set an acknowledgement requirement'
            );
        }

        return null;
    }//end denyAcknowledgementChange()

    /**
     * Validate the container-nesting depth of an inbound content payload.
     *
     * REQ-CONT-006. Returns the refusal response when the payload nests
     * containers deeper than {@see WidgetPlacementService::MAX_CONTAINER_DEPTH},
     * or NULL when the payload is acceptable (including when it is not a
     * container payload at all).
     *
     * @param mixed $content The raw `content` request parameter.
     *
     * @return JSONResponse|NULL The refusal, or NULL to proceed.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-15
     */
    private function denyContainerDepth(mixed $content): ?JSONResponse
    {
        if (is_array($content) === false) {
            return null;
        }

        try {
            $this->placementService->validateContainerDepth(
                content: $content
            );
        } catch (\InvalidArgumentException $depthError) {
            if ($depthError->getMessage() === 'container_depth_exceeded') {
                return $this->containerDepthExceededResponse();
            }

            return ResponseHelper::error(exception: $depthError);
        }

        return null;
    }//end denyContainerDepth()

    /**
     * Build the canonical "container_depth_exceeded" error response
     * (REQ-CONT-006). HTTP 400 with the documented envelope shape:
     * `{status: 'error', error: 'container_depth_exceeded', maxDepth: 3}`.
     *
     * @return JSONResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-15
     */
    private function containerDepthExceededResponse(): JSONResponse
    {
        return new JSONResponse(
            data: [
                'status'   => 'error',
                'error'    => 'container_depth_exceeded',
                'maxDepth' => WidgetPlacementService::MAX_CONTAINER_DEPTH,
            ],
            statusCode: Http::STATUS_BAD_REQUEST
        );
    }//end containerDepthExceededResponse()

    /**
     * Add a tile to a dashboard.
     *
     * @param int $dashboardId Dashboard ID.
     *
     * @return JSONResponse The created tile placement.
     *
      * @spec openspec/specs/widgets/spec.md
      */
    #[NoAdminRequired]
    public function addTile(int $dashboardId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        $this->actionAuth->requireAction($user, 'widget.add-tile');

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        if ($this->permissionService->canAddWidget(
            userId: $this->userId,
            dashboardId: $dashboardId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        try {
            $placement = $this->widgetService->addTileFromArray(
                dashboardId: $dashboardId,
                tileData: RequestDataExtractor::extractTileData(
                    request: $this->request
                )
            );

            return ResponseHelper::success(
                data: $placement->jsonSerialize(),
                statusCode: Http::STATUS_CREATED
            );
        } catch (QuotaExceededException $e) {
            // Dashboard-quota-limits REQ-QUOTA-003: tiles are placements
            // and bound by the widget quota — HTTP 409 structured body.
            return ResponseHelper::quotaExceeded(exception: $e);
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end addTile()

    /**
     * Update a widget placement.
     *
     * @param int $placementId The placement ID.
     *
     * @return JSONResponse The updated widget placement.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-35
     */
    #[NoAdminRequired]
    public function updatePlacement(int $placementId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        $this->actionAuth->requireAction($user, 'widget.update-placement');

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        if ($this->permissionService->canStyleWidget(
            userId: $this->userId,
            placementId: $placementId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        $ackDenial = $this->denyAcknowledgementChange(placementId: $placementId);
        if ($ackDenial !== null) {
            return $ackDenial;
        }

        // REQ-CONT-006: validate the container depth invariant on
        // update too — a placement can grow nested children via PUT
        // without ever going through addWidget.
        $depthDenial = $this->denyContainerDepth(
            content: $this->request->getParam(key: 'content')
        );
        if ($depthDenial !== null) {
            return $depthDenial;
        }

        try {
            $placement = $this->widgetService->updatePlacement(
                placementId: $placementId,
                data: RequestDataExtractor::extractPlacementData(
                    request: $this->request
                )
            );

            return ResponseHelper::success(
                data: $placement->jsonSerialize()
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end updatePlacement()

    /**
     * Remove a widget placement.
     *
     * @param int $placementId The placement ID.
     *
     * @return JSONResponse The removal confirmation.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-36
     */
    #[NoAdminRequired]
    public function removePlacement(int $placementId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        $this->actionAuth->requireAction($user, 'widget.remove-placement');

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        if ($this->permissionService->canRemoveWidget(
            userId: $this->userId,
            placementId: $placementId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        try {
            $this->widgetService->removePlacement(
                placementId: $placementId
            );

            return ResponseHelper::success(data: ['status' => 'ok']);
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }
    }//end removePlacement()

    /**
     * Fetch merged news widget items for a placement (REQ-NEWS-003).
     *
     * Validates the caller, clamps the limit, and delegates to
     * {@see NewsWidgetService::getItemsForPlacement()}. The response
     * shape is `{items: array, feedsFailed: int, failedUrls: array}`
     * — the placement-level metadata filter is applied server-side
     * (REQ-NEWS-007), and a placement that fails the filter responds
     * with an empty items array (no HTTP fetch occurs).
     *
     * @param integer      $placementId Placement entity id.
     * @param integer|null $limit       Optional caller cap (default 10,
     *                                  rejected when outside [1, 50]).
     *
     * @return JSONResponse
     *
     * @spec openspec/specs/widgets/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function newsItems(int $placementId, ?int $limit=10): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        try {
            $this->actionAuth->requireAction($user, 'widget.news-items');
        } catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
        }

        $effectiveLimit = $limit;
        if ($effectiveLimit === null) {
            $effectiveLimit = 10;
        }

        if ($effectiveLimit < 1 || $effectiveLimit > 50) {
            return new JSONResponse(
                data: ['error' => 'limit out of range (1..50)'],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        // M1: data-fetch endpoints only need view permission; canStyleWidget
        // blocks VIEW_ONLY users who are legitimate consumers of this data.
        if ($this->permissionService->canViewPlacement(
            userId: $this->userId,
            placementId: $placementId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        $payload = $this->newsWidgetService->getItemsForPlacement(
            placementId: $placementId,
            limit: $effectiveLimit
        );

        return ResponseHelper::success(data: $payload);
    }//end newsItems()

    /**
     * Get aggregated events for a calendar-widget placement.
     *
     * Returns merged + sorted events from internal NC calendars and
     * external ICS feeds configured on the placement. The date range
     * is mandatory and is capped at one year in the controller as a
     * defensive measure against runaway RRULE expansion.
     *
     * REQ-CAL-003.
     *
     * @param int    $placementId The placement ID.
     * @param string $from        ISO 8601 start.
     * @param string $to          ISO 8601 end.
     *
     * @return JSONResponse The aggregated events payload.
     *
     * @spec openspec/specs/widgets/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function calendarEvents(
        int $placementId,
        string $from='',
        string $to=''
    ): JSONResponse {
        $denial = $this->denyCalendarEvents();
        if ($denial !== null) {
            return $denial;
        }

        $range = $this->resolveCalendarRange(from: $from, to: $to);
        if ($range['error'] !== null) {
            return $range['error'];
        }

        $start = $range['start'];
        $end   = $range['end'];

        try {
            $placement = $this->widgetService->getPlacement(placementId: $placementId);
        } catch (Throwable $exception) {
            unset($exception);
            return new JSONResponse(
                data: ['error' => 'Placement not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        // M1: data-fetch endpoint — view permission is sufficient;
        // canStyleWidget would block VIEW_ONLY users.
        if ($this->permissionService->canViewPlacement(
            userId: $this->userId,
            placementId: $placementId
        ) === false
        ) {
            return ResponseHelper::forbidden();
        }

        $config = $this->extractCalendarConfig(placement: $placement);

        try {
            $result = $this->calendarService->getEvents(
                config: $config,
                from: $start->format(format: \DATE_ATOM),
                to: $end->format(format: \DATE_ATOM)
            );
        } catch (\Exception $exception) {
            return ResponseHelper::error(exception: $exception);
        }

        return ResponseHelper::success(data: $result);
    }//end calendarEvents()

    /**
     * Resolve the authentication / authorisation guard chain for
     * {@see self::calendarEvents()}.
     *
     * @return JSONResponse|NULL The refusal, or NULL to proceed.
     *
     * @spec openspec/specs/widgets/spec.md
     */
    private function denyCalendarEvents(): ?JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
        }

        if ($this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        try {
            $this->actionAuth->requireAction($user, 'widget.calendar-events');
        } catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
        }

        return null;
    }//end denyCalendarEvents()

    /**
     * Parse, validate and cap the mandatory `from`/`to` date range of a
     * calendar-events request.
     *
     * The window is capped at one year (design D1) as a defensive bound
     * on RRULE expansion. On any validation failure the `error` member
     * carries the response the caller must return and `start`/`end` are
     * NULL.
     *
     * @param string $from ISO 8601 start.
     * @param string $to   ISO 8601 end.
     *
     * @return array{error: JSONResponse|null, start: DateTimeImmutable|null, end: DateTimeImmutable|null}
     *
     * @spec openspec/specs/widgets/spec.md
     */
    private function resolveCalendarRange(string $from, string $to): array
    {
        if ($from === '' || $to === '') {
            return [
                'error' => new JSONResponse(
                    data: ['error' => 'Both from and to are required ISO 8601 timestamps'],
                    statusCode: Http::STATUS_BAD_REQUEST
                ),
                'start' => null,
                'end'   => null,
            ];
        }

        try {
            $start = new DateTimeImmutable(datetime: $from);
            $end   = new DateTimeImmutable(datetime: $to);
        } catch (Throwable $exception) {
            unset($exception);
            return [
                'error' => new JSONResponse(
                    data: ['error' => 'Invalid date format'],
                    statusCode: Http::STATUS_BAD_REQUEST
                ),
                'start' => null,
                'end'   => null,
            ];
        }

        if ($end < $start) {
            return [
                'error' => new JSONResponse(
                    data: ['error' => '`to` must be greater than or equal to `from`'],
                    statusCode: Http::STATUS_BAD_REQUEST
                ),
                'start' => null,
                'end'   => null,
            ];
        }

        // Defensive 1-year cap per design D1 to bound RRULE expansion.
        $maxEnd = $start->modify(modifier: '+1 year');
        if ($end > $maxEnd) {
            $end = $maxEnd;
        }

        return [
            'error' => null,
            'start' => $start,
            'end'   => $end,
        ];
    }//end resolveCalendarRange()

    /**
     * List the current user's internal Nextcloud calendars so the calendar
     * widget's config form can offer a picker instead of free-text principal
     * URIs (REQ-CAL-002). Each entry carries the calendar key (the identifier
     * `fetchInternalEvents` filters on), display name, and colour.
     *
     * @return JSONResponse The user's calendars, or 401 when unauthenticated.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function calendars(): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null || $this->userId === null) {
            return ResponseHelper::unauthorized();
        }

        try {
            $this->actionAuth->requireAction($user, 'widget.calendar-events');
        } catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
            return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
        }

        try {
            $calendars = $this->calendarService->listCalendars(userId: $this->userId);
        } catch (\Exception $exception) {
            return ResponseHelper::error(exception: $exception);
        }

        return ResponseHelper::success(data: ['calendars' => $calendars]);
    }//end calendars()

    /**
     * Pull `internalCalendars`/`externalIcsUrls` arrays out of the
     * placement's widgetContent JSON, applying defaults.
     *
     * @param object $placement The placement entity (WidgetPlacement).
     *
     * @return array{
     *     internalCalendars: array<int, string>,
     *     externalIcsUrls: array<int, string>,
     *     viewMode: string,
     *     daysAhead: int,
     *     colorByCalendar: bool
     * }
     */
    private function extractCalendarConfig(object $placement): array
    {
        $serialized = [];
        if (method_exists(object_or_class: $placement, method: 'jsonSerialize') === true) {
            $serialized = (array) $placement->jsonSerialize();
        }

        $widgetContent = $serialized['widgetContent'] ?? $serialized['content'] ?? [];

        if (is_string(value: $widgetContent) === true) {
            $decoded       = json_decode(json: $widgetContent, associative: true);
            $widgetContent = [];
            if (is_array(value: $decoded) === true) {
                $widgetContent = $decoded;
            }
        }

        $widgetContent = (array) $widgetContent;

        $internal = (array) ($widgetContent['internalCalendars'] ?? []);
        $external = (array) ($widgetContent['externalIcsUrls'] ?? []);

        // Cast every entry to string for safety; drop empty strings.
        $internal = array_values(
                array: array_filter(
            array: array_map(callback: 'strval', array: $internal),
            callback: static fn(string $value): bool => $value !== ''
        )
                );
        $external = array_values(
                array: array_filter(
            array: array_map(callback: 'strval', array: $external),
            callback: static fn(string $value): bool => $value !== ''
        )
                );

        return [
            'internalCalendars' => $internal,
            'externalIcsUrls'   => $external,
            'viewMode'          => (string) ($widgetContent['viewMode'] ?? 'agenda'),
            'daysAhead'         => (int) ($widgetContent['daysAhead'] ?? 14),
            'colorByCalendar'   => (bool) ($widgetContent['colorByCalendar'] ?? true),
        ];
    }//end extractCalendarConfig()
}//end class
