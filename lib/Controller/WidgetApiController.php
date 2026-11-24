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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\InvalidWidgetContentException;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\ResponseDefinitions;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use OCA\LaunchPad\Service\CalculatedFieldService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
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
 *
 * @psalm-import-type LaunchPadPlacement from ResponseDefinitions
 * @psalm-import-type LaunchPadStatusOk from ResponseDefinitions
 */
class WidgetApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest          $request The request.
	 * @param WidgetApiSources  $sources The widget, news, calendar and placement services.
	 * @param WidgetApiAccess $access Holds the permissionService and policy.
	 * @param ActorResponses $context Holds the actor, userId and responseHelper.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		IRequest $request,
		private readonly WidgetApiSources $sources,
		private readonly WidgetApiAccess $access,
		private readonly ActorResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * List all available Nextcloud widgets, filtered by the caller's
	 * role-feature permissions (REQ-RFP-001 / REQ-RFP-003).
	 *
	 * @return JSONResponse The list of available widgets.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-32
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function listAvailable(): JSONResponse {
		$denial = $this->denyListAvailable();
		if ($denial !== null) {
			return $denial;
		}

		$widgets = $this->sources->widgetService->getAvailableWidgets();

		$filter = new AllowedWidgetFilter(roleFeaturePerm: $this->access->policy->roleFeaturePerm, userId: $this->context->userId);

		return $this->context->responseHelper->success(data: $filter->filter(widgets: $widgets));
	}//end listAvailable()

	/**
	 * The caller guard of {@see self::listAvailable()}: no session user
	 * → 401, a denied ADR-023 action → 403; NULL to proceed.
	 *
	 * @return JSONResponse|null The refusal, or NULL.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyListAvailable(): ?JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->context->actor->actionAuth->requireAction($user, 'widget.list-available');
		} catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end denyListAvailable()

	/**
	 * Get widget items for specified widgets.
	 *
	 * @param array $widgets Array of widget IDs.
	 * @param int $limit Maximum items per widget.
	 *
	 * @return JSONResponse The widget items.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-33
	 * @spec openspec/changes/role-based-content/tasks.md#task-3
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getItems(
		array $widgets = [],
		int $limit = 7,
	): JSONResponse {
		$refusal = $this->actionRefusal(action: 'widget.get-items');
		if ($refusal !== null) {
			return $refusal;
		}

		// REQ-RFP-001 s.3 / REQ-RFP-006 s.2: deny content requests for
		// widgets the user's role does not permit. Log each denied widget
		// as an audit entry (Task 4). If every requested widget is denied,
		// return HTTP 403 so the caller cannot infer widget existence.
		$deniedWidgets = $this->deniedWidgetIds(widgets: $widgets);

		if (count(value: $deniedWidgets) > 0 && count(value: $widgets) === count(value: $deniedWidgets)) {
			return new JSONResponse(
				data: ['message' => 'Not authorized'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		$allowedWidgets = array_values(
			array: array_filter(
				array: $widgets,
				callback: fn ($widgetId) => in_array(needle: $widgetId, haystack: $deniedWidgets, strict: true) === false
			)
		);

		return $this->context->responseHelper->success(
			data: $this->sources->widgetService->getWidgetItems(
				userId: $this->context->userId,
				widgetIds: $allowedWidgets,
				limit: $limit
			)
		);
	}//end getItems()

	/**
	 * Add a widget to a dashboard.
	 *
	 * @param int $dashboardId Dashboard ID.
	 * @param string $widgetId Widget ID.
	 * @param int $gridX Grid X position.
	 * @param int $gridY Grid Y position.
	 * @param int $gridWidth Grid width.
	 * @param int $gridHeight Grid height.
	 *
	 * @return JSONResponse The created widget placement.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_CREATED, LaunchPadPlacement, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-34
	 */
	#[NoAdminRequired]
	public function addWidget(
		int $dashboardId,
		?string $widgetId = null,
		int $gridX = 0,
		int $gridY = 0,
		int $gridWidth = 4,
		int $gridHeight = 4,
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
		$depthDenial = $this->denyContainerDepth(content: $contentParam);
		if ($depthDenial !== null) {
			return $depthDenial;
		}

		$layout = [
			'gridX'      => $gridX,
			'gridY'      => $gridY,
			'gridWidth'  => $gridWidth,
			'gridHeight' => $gridHeight,
			'content'    => self::contentToPersist(content: $contentParam),
		];

		return $this->createPlacement(
			create: fn (): object => $this->sources->widgetService->addWidget(
				dashboardId: $dashboardId,
				widgetId: (string)$widgetId,
				layout: $layout
			)
		);
	}//end addWidget()

	/**
	 * The per-type content payload to persist with a new widget.
	 *
	 * Registry-driven custom widgets carry their config here — `label`,
	 * `text`, `image`, etc. Tolerant of legacy callers that send only
	 * `widgetId` and grid coords with no content blob: the content stays
	 * null and PlacementService leaves the column NULL.
	 *
	 * @param mixed $content The request's `content` parameter.
	 *
	 * @return array<string, mixed>|null The content, or null.
	 */
	private static function contentToPersist(mixed $content): ?array {
		if (is_array($content) === true) {
			return $content;
		}

		return null;
	}//end contentToPersist()

	/**
	 * Create a placement and answer 201 with it, mapping the widget quota
	 * to 409 and any other failure to the generic error.
	 *
	 * @param callable(): object $create Creates the placement.
	 *
	 * @return JSONResponse The placement, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function createPlacement(callable $create): JSONResponse {
		try {
			return $this->context->responseHelper->success(
				data: $create()->jsonSerialize(),
				statusCode: Http::STATUS_CREATED
			);
		} catch (QuotaExceededException $e) {
			// Dashboard-quota-limits REQ-QUOTA-003: the dashboard is at the
			// widget limit (tiles are placements too) — HTTP 409 with the
			// structured body.
			return $this->context->responseHelper->quotaExceeded(exception: $e);
		} catch (InvalidWidgetContentException $e) {
			return self::invalidContentResponse(exception: $e);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}//end try
	}//end createPlacement()

	/**
	 * Answer content that breaks its widget type's rules with 400 and the
	 * rule it broke (REQ-MENU-002), so the editor can say what to change.
	 *
	 * @param InvalidWidgetContentException $exception The validation failure.
	 *
	 * @return JSONResponse The 400 response.
	 *
	 * @spec openspec/specs/menu-widget/spec.md
	 */
	private static function invalidContentResponse(InvalidWidgetContentException $exception): JSONResponse {
		return new JSONResponse(
			data: ['error' => $exception->getMessage()],
			statusCode: Http::STATUS_BAD_REQUEST
		);
	}//end invalidContentResponse()

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
	 * @param int $dashboardId Dashboard ID.
	 * @param string|null $widgetId Widget ID from the request body.
	 *
	 * @return JSONResponse|NULL The refusal, or NULL to proceed.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-34
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyAddWidget(int $dashboardId, ?string $widgetId): ?JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		$this->context->actor->actionAuth->requireAction($user, 'widget.add-widget');

		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		return $this->denyWidgetChoice(dashboardId: $dashboardId, widgetId: $widgetId);
	}//end denyAddWidget()

	/**
	 * Refuse a widget the request does not name, or one the user may not
	 * add to this dashboard.
	 *
	 * Validate the widgetId parameter explicitly so a missing /
	 * empty value returns 400 rather than letting PHP's TypeError
	 * bubble up to a 500. The route declared `string $widgetId`
	 * (non-nullable) before — Newman's drift test sends a body
	 * without the field and used to crash the dispatcher.
	 *
	 * @param int         $dashboardId The dashboard ID.
	 * @param string|null $widgetId    The widget type ID.
	 *
	 * @return JSONResponse|null The refusal, or NULL to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyWidgetChoice(int $dashboardId, ?string $widgetId): ?JSONResponse {
		if (self::isBlankId(id: $widgetId) === true) {
			return $this->context->responseHelper->error(
				exception: new InvalidArgumentException(
					'Missing required field: widgetId'
				),
				statusCode: Http::STATUS_BAD_REQUEST
			);
		}

		if ($this->mayAddWidget(dashboardId: $dashboardId, widgetId: (string)$widgetId) === false) {
			return $this->context->responseHelper->forbidden(
				message: $this->access->permissionService->widgetRefusal(userId: (string)$this->context->userId, dashboardId: $dashboardId)
			);
		}

		return null;
	}//end denyWidgetChoice()

	/**
	 * Whether an id is missing or empty.
	 *
	 * @param string|null $id The id.
	 *
	 * @return bool True when blank.
	 */
	private static function isBlankId(?string $id): bool {
		return $id === null || $id === '';
	}//end isBlankId()

	/**
	 * Whether the user may add this widget to this dashboard: the
	 * dashboard permission, then the role-feature profile.
	 *
	 * REQ-RFP-001 / REQ-RFP-003: a user may only add a widget that their
	 * role-feature-permission profile permits. `isWidgetAllowed` returns
	 * `true` when no restriction is configured (null allowed set), so
	 * unconfigured deployments are unaffected.
	 *
	 * @param int    $dashboardId The dashboard ID.
	 * @param string $widgetId    The widget type ID.
	 *
	 * @return bool True when both permit it.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function mayAddWidget(int $dashboardId, string $widgetId): bool {
		return $this->access->permissionService->canAddWidget(
			userId: $this->context->userId,
			dashboardId: $dashboardId
		) !== false
			&& $this->access->policy->roleFeaturePerm->isWidgetAllowed(
				userId: $this->context->userId,
				widgetId: $widgetId
			) !== false;
	}//end mayAddWidget()

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
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyAcknowledgementChange(int $placementId): ?JSONResponse {
		if ($this->touchesAcknowledgement() === false) {
			return null;
		}

		if ($this->access->permissionService->canManageAcknowledgement(
			userId: (string)$this->context->userId,
			placementId: $placementId
		) === false
		) {
			return $this->context->responseHelper->forbidden(
				message: 'Only an admin or the template owner may set an acknowledgement requirement'
			);
		}

		return null;
	}//end denyAcknowledgementChange()

	/**
	 * Whether the request sets any acknowledgement field.
	 *
	 * @return bool True when at least one acknowledgement field is present.
	 */
	private function touchesAcknowledgement(): bool {
		$ackFields = [
			'requiresAcknowledgement',
			'acknowledgementPrompt',
			'acknowledgementDeadline',
			'reacknowledgeOnChange',
			'acknowledgementContentVersion',
		];

		foreach ($ackFields as $ackField) {
			if ($this->request->getParam(key: $ackField) !== null) {
				return true;
			}
		}

		return false;
	}//end touchesAcknowledgement()

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
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function denyContainerDepth(mixed $content): ?JSONResponse {
		if (is_array($content) === false) {
			return null;
		}

		try {
			$this->sources->placementService->validateContainerDepth(
				content: $content
			);
		} catch (\InvalidArgumentException $depthError) {
			if ($depthError->getMessage() === 'container_depth_exceeded') {
				return $this->containerDepthExceededResponse();
			}

			return $this->context->responseHelper->error(exception: $depthError);
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
	private function containerDepthExceededResponse(): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'error',
				'error' => 'container_depth_exceeded',
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
	 * @psalm-return JSONResponse<Http::STATUS_CREATED, LaunchPadPlacement, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/specs/widgets/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function addTile(int $dashboardId): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		$this->context->actor->actionAuth->requireAction($user, 'widget.add-tile');

		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		if ($this->mayAddTo(dashboardId: $dashboardId) === false) {
			return $this->context->responseHelper->forbidden();
		}

		return $this->createPlacement(
			create: fn (): object => $this->addTileFromRequest(dashboardId: $dashboardId)
		);
	}//end addTile()

	/**
	 * Whether the user may add widgets or tiles to a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return bool True when allowed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function mayAddTo(int $dashboardId): bool {
		return $this->access->permissionService->canAddWidget(
			userId: $this->context->userId,
			dashboardId: $dashboardId
		) !== false;
	}//end mayAddTo()

	/**
	 * Add the tile the request describes to a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return object The created placement.
	 */
	private function addTileFromRequest(int $dashboardId): object {
		$tileData = RequestDataExtractor::extractTileData(
			request: $this->request
		);
		// A program, remote-desktop or sso tile carries its settings in
		// content, checked with the link type on create (launcher-tile-launch-types).
		$content = $this->request->getParam(key: 'content');
		if (is_array($content) === true) {
			$tileData['content'] = $content;
		}

		return $this->sources->widgetService->addTileFromArray(
			dashboardId: $dashboardId,
			tileData: $tileData
		);
	}//end addTileFromRequest()

	/**
	 * Update a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse The updated widget placement.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadPlacement, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-35
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function updatePlacement(int $placementId): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		$this->context->actor->actionAuth->requireAction($user, 'widget.update-placement');

		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		$denial = $this->denyPlacementUpdate(placementId: $placementId);
		if ($denial !== null) {
			return $denial;
		}

		try {
			return $this->updatedPlacement(placementId: $placementId);
		} catch (CalculatedFieldValidationError | CalculatedFieldCycleError $e) {
			// A calculated field that does not check is refused before anything is
			// saved, with the editor's error envelope (widget-calculated-fields, task 25).
			return new JSONResponse($e->getDetail(), \OCP\AppFramework\Http::STATUS_BAD_REQUEST);
		} catch (StalePlacementException $e) {
			// A colleague saved this widget first: refuse, and hand back their
			// version so the editor can make the change again on top of it.
			return new JSONResponse(
				[
					'error'     => 'stale',
					'message'   => $e->getMessage(),
					'placement' => $e->getCurrent()->jsonSerialize(),
				],
				\OCP\AppFramework\Http::STATUS_CONFLICT
			);
		} catch (InvalidWidgetContentException $e) {
			return self::invalidContentResponse(exception: $e);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}
	}//end updatePlacement()

	/**
	 * Refuse a placement update the user may not style, one that sets an
	 * acknowledgement requirement the user may not manage, or one whose
	 * container content nests too deep.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse|null The refusal, or NULL to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private function denyPlacementUpdate(int $placementId): ?JSONResponse {
		if ($this->mayStyle(placementId: $placementId) === false) {
			return $this->context->responseHelper->forbidden();
		}

		$ackDenial = $this->denyAcknowledgementChange(placementId: $placementId)
			?? $this->denyRuleMatchChange(placementId: $placementId);
		if ($ackDenial !== null) {
			return $ackDenial;
		}

		// REQ-CONT-006: validate the container depth invariant on
		// update too — a placement can grow nested children via PUT
		// without ever going through addWidget.
		return $this->denyContainerDepth(
			content: $this->request->getParam(key: 'content')
		);
	}//end denyPlacementUpdate()

	/**
	 * Refuse a `ruleMatch` that is not `any` or `all` (400), or one set by
	 * someone who may not edit the placement's rules (403): the dashboard
	 * owner or an administrator, as for the rules themselves.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse|null The refusal, or NULL to proceed.
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private function denyRuleMatchChange(int $placementId): ?JSONResponse {
		$ruleMatch = $this->request->getParam(key: 'ruleMatch');
		if ($ruleMatch === null) {
			return null;
		}

		if (in_array($ruleMatch, WidgetPlacement::RULE_MATCHES, true) === false) {
			return $this->context->responseHelper->invalidArgument(message: 'ruleMatch must be any or all');
		}

		try {
			$this->access->permissionService->verifyPlacementOwnership(
				userId: (string)$this->context->userId,
				placementId: $placementId
			);
		} catch (\Exception $e) {
			return $this->context->responseHelper->forbidden(
				message: 'Only the dashboard owner or an admin may change how the rules combine'
			);
		}

		return null;
	}//end denyRuleMatchChange()

	/**
	 * Whether the user may restyle a placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return bool True when allowed.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function mayStyle(int $placementId): bool {
		return $this->access->permissionService->canStyleWidget(
			userId: $this->context->userId,
			placementId: $placementId
		) !== false;
	}//end mayStyle()

	/**
	 * Apply the request's placement fields and answer with the placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse The updated placement.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function updatedPlacement(int $placementId): JSONResponse {
		$data     = RequestDataExtractor::extractPlacementData(request: $this->request);
		$warnings = [];
		$before   = null;
		if (isset($data['calculatedFields']) === false && is_array($data['content'] ?? null) === true) {
			// A source change moves the widget's materialised fields with it, so
			// its stored fields are checked again against the new source.
			$before = $this->sources->widgetService->getPlacement(placementId: $placementId);
			if ($this->sources->calculatedFields->hasServerFields(fields: $before->getCalculatedFieldsArray()) === true) {
				$data['calculatedFields'] = $before->getCalculatedFieldsArray();
			}
		}

		$compiled = null;
		if (isset($data['calculatedFields']) === true && is_array($data['calculatedFields']) === true) {
			$compiled = $this->compiledCalculatedFields(placementId: $placementId, data: $data);
			$data['calculatedFields'] = $compiled['fields'];
			$warnings = $compiled['warnings'];
		}

		$placement = $this->sources->widgetService->updatePlacement(
			placementId: $placementId,
			data: $data
		);

		if ($compiled !== null) {
			// Server-mode fields are written to the source schema once the widget
			// is persisted (widget-calculated-fields, task 27); when that fails
			// the widget is saved again with those fields downgraded to client.
			$written = $this->sources->calculatedFields->materializeForOpenRegister(
				widget: [
					'id'               => (string) $placementId,
					'calculatedFields' => $compiled['fields'],
					'source'           => CalculatedFieldService::sourceKey(content: $placement->getContentArray()),
				],
				previous: $compiled['previous']
			);
			$warnings = array_merge($warnings, $written['warnings']);
			if ($written['changed'] === true) {
				$placement = $this->sources->widgetService->updatePlacement(
					placementId: $placementId,
					data: ['calculatedFields' => $written['fields']]
				);
			}
		}

		$body = $placement->jsonSerialize();
		if ($warnings !== []) {
			// A server-mode field that had to be downgraded (REQ-CALC-007).
			$body['warnings'] = $warnings;
		}

		return $this->context->responseHelper->success(data: $body);
	}//end updatedPlacement()

	/**
	 * Validate a placement's new calculated fields against its dashboard and
	 * answer them with their `returnType` set, server-mode fields that read
	 * another source downgraded to client mode with a warning.
	 *
	 * A placement is created without calculated fields; they arrive through
	 * this update, so this is where they are checked before persisting.
	 *
	 * @param int                $placementId The placement ID.
	 * @param array<string,mixed> $data       The update: its `calculatedFields`, and its `content` when it
	 *                                        changes the placement's source.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>, previous: array<string,mixed>}
	 *         The fields to store, the warnings, and the placement's `source` and `calculatedFields` before this save.
	 *
	 * @throws CalculatedFieldValidationError When a field is malformed or does not type-check.
	 * @throws CalculatedFieldCycleError      When the dashboard's fields form a cycle.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-24
	 */
	private function compiledCalculatedFields(int $placementId, array $data): array {
		$current     = $this->sources->widgetService->getPlacement(placementId: $placementId);
		$dashboardId = $current->getDashboardId();
		$others      = [];
		$keys        = [];
		foreach ($this->sources->widgetService->getDashboardPlacements(dashboardId: $dashboardId) as $placement) {
			$others[] = ['id' => (string) $placement->getId(), 'calculatedFields' => $placement->getCalculatedFieldsArray()];
			$keys[(string) $placement->getId()] = CalculatedFieldService::sourceKey(content: $placement->getContentArray());
		}

		if (is_array($data['content'] ?? null) === true) {
			$keys[(string) $placementId] = CalculatedFieldService::sourceKey(content: $data['content']);
		}

		$result = $this->sources->calculatedFields->validateAndCompile(
			widget: ['id' => (string) $placementId, 'calculatedFields' => $data['calculatedFields']],
			others: $others,
			options: ['sources' => $keys]
		);

		$planned = $this->sources->calculatedFields->planServerFields(id: (string) $placementId, fields: $result['fields']);

		return [
			'fields'   => $planned['fields'],
			'warnings' => array_merge($result['warnings'], $planned['warnings']),
			'previous' => [
				'source'           => CalculatedFieldService::sourceKey(content: $current->getContentArray()),
				'calculatedFields' => $current->getCalculatedFieldsArray(),
			],
		];
	}//end compiledCalculatedFields()

	/**
	 * Remove a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse The removal confirmation.
	 *
	 * @psalm-return JSONResponse<Http::STATUS_OK, LaunchPadStatusOk, array<string, mixed>>|
	 *     JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
	 * @openapi-stability public
	 * @spec openspec/specs/rest-api-reference/spec.md
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-36
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function removePlacement(int $placementId): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		$this->context->actor->actionAuth->requireAction($user, 'widget.remove-placement');

		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		if ($this->access->permissionService->canRemoveWidget(
			userId: $this->context->userId,
			placementId: $placementId
		) === false
		) {
			return $this->context->responseHelper->forbidden(
				message: $this->access->permissionService->widgetRefusal(userId: $this->context->userId, placementId: $placementId)
			);
		}

		try {
			$placement = $this->sources->widgetService->getPlacement(placementId: $placementId);
			$warnings  = [];
			if ($this->sources->calculatedFields->hasServerFields(fields: $placement->getCalculatedFieldsArray()) === true) {
				// The widget's materialised fields leave its source schema with it
				// (widget-calculated-fields, task 28).
				$warnings = $this->sources->calculatedFields->cleanupServerFields(
					widget: [
						'id'     => (string) $placementId,
						'source' => CalculatedFieldService::sourceKey(content: $placement->getContentArray()),
					]
				);
			}

			$this->sources->widgetService->removePlacement(
				placementId: $placementId
			);

			$body = ['status' => 'ok'];
			if ($warnings !== []) {
				$body['warnings'] = $warnings;
			}

			return $this->context->responseHelper->success(data: $body);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
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
	 * @param integer $placementId Placement entity id.
	 * @param integer|null $limit Optional caller cap (default 10,
	 *                            rejected when outside [1, 50]).
	 *
	 * @psalm-param int<min, max>|null $limit Unbounded for Nextcloud's
	 *                            dispatcher, which otherwise answers a
	 *                            `limit` below 1 with a 500 before the
	 *                            controller can answer 400.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function newsItems(int $placementId, ?int $limit = 10): JSONResponse {
		$refusal = $this->actionRefusal(action: 'widget.news-items');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->newsFor(placementId: $placementId, limit: self::effectiveNewsLimit(limit: $limit));
	}//end newsItems()

	/**
	 * The news limit to use: 10 when none was given.
	 *
	 * @param int|null $limit The requested limit.
	 *
	 * @return int The limit.
	 */
	private static function effectiveNewsLimit(?int $limit): int {
		if ($limit === null) {
			return 10;
		}

		return $limit;
	}//end effectiveNewsLimit()

	/**
	 * Check the limit and the view permission, then answer with the news
	 * items.
	 *
	 * @param int $placementId The placement ID.
	 * @param int $limit       The effective limit.
	 *
	 * @return JSONResponse The items, or the refusal.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function newsFor(int $placementId, int $limit): JSONResponse {
		if ($limit < 1 || $limit > 50) {
			return $this->failure(message: 'limit out of range (1..50)', statusCode: Http::STATUS_BAD_REQUEST);
		}

		// M1: data-fetch endpoints only need view permission; canStyleWidget
		// blocks VIEW_ONLY users who are legitimate consumers of this data.
		$refusal = $this->placementViewRefusal(placementId: $placementId);
		if ($refusal !== null) {
			return $refusal;
		}

		$payload = $this->sources->newsWidgetService->getItemsForPlacement(
			placementId: $placementId,
			limit: $limit
		);

		return $this->context->responseHelper->success(data: $payload);
	}//end newsFor()

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
	 * @param int $placementId The placement ID.
	 * @param string $from ISO 8601 start.
	 * @param string $to ISO 8601 end.
	 *
	 * @return JSONResponse The aggregated events payload.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function calendarEvents(
		int $placementId,
		string $from = '',
		string $to = '',
	): JSONResponse {
		$denial = $this->denyCalendarEvents();
		if ($denial !== null) {
			return $denial;
		}

		$range = $this->resolveCalendarRange(from: $from, to: $to);
		if ($range['error'] !== null) {
			return $range['error'];
		}

		return $this->calendarEventsIn(placementId: $placementId, range: $range);
	}//end calendarEvents()

	/**
	 * Look the placement up, check the view permission, then answer with
	 * its events inside the range.
	 *
	 * @param int                                                     $placementId The placement ID.
	 * @param array{start: DateTimeImmutable, end: DateTimeImmutable} $range       The resolved range.
	 *
	 * @return JSONResponse The events, or the refusal or error.
	 */
	private function calendarEventsIn(int $placementId, array $range): JSONResponse {
		try {
			$placement = $this->sources->widgetService->getPlacement(placementId: $placementId);
		} catch (Throwable $exception) {
			unset($exception);
			return $this->failure(message: 'Placement not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		// M1: data-fetch endpoint — view permission is sufficient;
		// canStyleWidget would block VIEW_ONLY users.
		$refusal = $this->placementViewRefusal(placementId: $placementId);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->calendarEventsOf(placement: $placement, range: $range);
	}//end calendarEventsIn()

	/**
	 * Answer with a calendar placement's events inside the range.
	 *
	 * @param object                                                  $placement The calendar placement.
	 * @param array{start: DateTimeImmutable, end: DateTimeImmutable} $range     The resolved range.
	 *
	 * @return JSONResponse The events, or the error.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function calendarEventsOf(object $placement, array $range): JSONResponse {
		$config = $this->extractCalendarConfig(placement: $placement);

		try {
			$result = $this->sources->calendarService->getEvents(
				config: $config,
				from: $range['start']->format(format: \DATE_ATOM),
				to: $range['end']->format(format: \DATE_ATOM)
			);
		} catch (\Exception $exception) {
			return $this->context->responseHelper->error(exception: $exception);
		}

		return $this->context->responseHelper->success(data: $result);
	}//end calendarEventsOf()

	/**
	 * Resolve the authentication / authorisation guard chain for
	 * {@see self::calendarEvents()}.
	 *
	 * @return JSONResponse|NULL The refusal, or NULL to proceed.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	private function denyCalendarEvents(): ?JSONResponse {
		$refusal = $this->actionRefusal(action: 'widget.calendar-events');
		if ($refusal !== null) {
			return $refusal;
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
	 * @param string $to ISO 8601 end.
	 *
	 * @return array{error: JSONResponse|null, start: DateTimeImmutable|null, end: DateTimeImmutable|null}
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	private function resolveCalendarRange(string $from, string $to): array {
		$range = (new CalendarRangeParser())->parse(from: $from, to: $to);
		if ($range['refusal'] !== null) {
			return $this->rangeRefusal(message: $range['refusal']);
		}

		return [
			'error' => null,
			'start' => $range['start'],
			'end' => $range['end'],
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
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function calendars(): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null || $this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		try {
			$this->context->actor->actionAuth->requireAction($user, 'widget.calendar-events');
		} catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$calendars = $this->sources->calendarService->listCalendars(userId: $this->context->userId);
		} catch (\Exception $exception) {
			return $this->context->responseHelper->error(exception: $exception);
		}

		return $this->context->responseHelper->success(data: ['calendars' => $calendars]);
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
	private function extractCalendarConfig(object $placement): array {
		$widgetContent = $this->calendarWidgetContent(placement: $placement);

		return [
			'internalCalendars' => $this->nonEmptyStrings(values: (array)($widgetContent['internalCalendars'] ?? [])),
			'externalIcsUrls' => $this->nonEmptyStrings(values: (array)($widgetContent['externalIcsUrls'] ?? [])),
			'viewMode' => (string)($widgetContent['viewMode'] ?? 'agenda'),
			'daysAhead' => (int)($widgetContent['daysAhead'] ?? 14),
			'colorByCalendar' => (bool)($widgetContent['colorByCalendar'] ?? true),
		];
	}//end extractCalendarConfig()

	/**
	 * A range refusal: HTTP 400 with the message, no bounds.
	 *
	 * @param string $message The client message.
	 *
	 * @return array{error: JSONResponse, start: null, end: null}
	 */
	private function rangeRefusal(string $message): array {
		return [
			'error' => $this->failure(message: $message, statusCode: Http::STATUS_BAD_REQUEST),
			'start' => null,
			'end' => null,
		];
	}//end rangeRefusal()

	/**
	 * The placement's widget content as an array (decoded when stored as JSON; empty when unreadable).
	 *
	 * @param object $placement The widget placement.
	 *
	 * @return array<string, mixed>
	 */
	private function calendarWidgetContent(object $placement): array {
		$serialized = [];
		if (method_exists(object_or_class: $placement, method: 'jsonSerialize') === true) {
			$serialized = (array)$placement->jsonSerialize();
		}

		$widgetContent = $serialized['widgetContent'] ?? $serialized['content'] ?? [];

		if (is_string(value: $widgetContent) === true) {
			$decoded = json_decode(json: $widgetContent, associative: true);
			$widgetContent = [];
			if (is_array(value: $decoded) === true) {
				$widgetContent = $decoded;
			}
		}

		return (array)$widgetContent;
	}//end calendarWidgetContent()

	/**
	 * Every entry cast to string, empty strings dropped, re-indexed.
	 *
	 * @param array<int|string, mixed> $values The configured entries.
	 *
	 * @return string[]
	 */
	private function nonEmptyStrings(array $values): array {
		return array_values(
			array: array_filter(
				array: array_map(callback: 'strval', array: $values),
				callback: static fn (string $value): bool => $value !== ''
			)
		);
	}//end nonEmptyStrings()

	/**
	 * The caller guard: no session user → 401, no user ID → 401, a denied
	 * ADR-023 action → 403; NULL to proceed.
	 *
	 * @param string $action The action identifier to require.
	 *
	 * @return JSONResponse|null The refusal, or NULL.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], \OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
		}

		if ($this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		try {
			$this->context->actor->actionAuth->requireAction($user, $action);
		} catch (\OCP\AppFramework\OCS\OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], \OCP\AppFramework\Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end actionRefusal()

	/**
	 * 403 when the caller may not view the placement; NULL to proceed.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return JSONResponse|null The refusal, or NULL.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function placementViewRefusal(int $placementId): ?JSONResponse {
		if ($this->access->permissionService->canViewPlacement(
			userId: (string)$this->context->userId,
			placementId: $placementId
		) === false
		) {
			return $this->context->responseHelper->forbidden();
		}

		return null;
	}//end placementViewRefusal()

	/**
	 * The requested widgets the user's role does not permit, each logged as an audit entry.
	 *
	 * REQ-RFP-001 s.3 / REQ-RFP-006 s.2 (Task 4).
	 *
	 * @param array<int, mixed> $widgets The requested widget IDs.
	 *
	 * @return array<int, mixed> The denied widget IDs, in request order.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function deniedWidgetIds(array $widgets): array {
		$deniedWidgets = [];
		foreach ($widgets as $widgetId) {
			if ($this->access->policy->roleFeaturePerm->isWidgetAllowed(
				userId: (string)$this->context->userId,
				widgetId: (string)$widgetId
			) === false
			) {
				$deniedWidgets[] = $widgetId;
				$this->access->policy->logger->warning(
					message: 'role_permission_denied',
					context: [
						'userId' => $this->context->userId,
						'widgetId' => $widgetId,
						'timestamp' => (new DateTimeImmutable())->format(format: DateTimeInterface::ATOM),
						'reason' => 'role_permission_denied',
						'app' => Application::APP_ID,
					]
				);
			}//end if
		}//end foreach

		return $deniedWidgets;
	}//end deniedWidgetIds()
}//end class
