<?php

/**
 * BookmarkImportController
 *
 * Imports chosen browser bookmarks onto a dashboard as containers and tiles.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\BookmarkImportPlan;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * `POST /api/dashboard/{dashboardId}/tiles/import`.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) {@see BookmarkImportPlan::fromRequest()}
 *  is a named constructor of a value.
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */
class BookmarkImportController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest          $request           The request.
	 * @param WidgetService     $widgetService     Creates the placements.
	 * @param PermissionService $permissionService Whether the user may add to the dashboard.
	 * @param RequestActor      $actor             The session and the action check.
	 * @param string|null       $userId            The current user ID.
	 * @param ResponseHelper $responseHelper Builds the JSON response envelopes.
	 *
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		IRequest $request,
		private readonly WidgetService $widgetService,
		private readonly PermissionService $permissionService,
		private readonly RequestActor $actor,
		private readonly ?string $userId,
		private readonly ResponseHelper $responseHelper,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Import chosen browser bookmarks onto a dashboard: each folder becomes a
	 * container of link tiles and each loose bookmark a tile, appended at the
	 * bottom. Addresses that are not web addresses are skipped and listed back.
	 * The quota is checked for the whole import, and nothing is created when it
	 * does not fit (409).
	 *
	 * @param int $dashboardId Dashboard ID.
	 *
	 * @return JSONResponse 201 with `placements` and `skipped`; 400, 401, 403 or 409.
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function import(int $dashboardId): JSONResponse {
		$user = $this->actor->userSession->getUser();
		if ($user === null || $this->userId === null) {
			return $this->responseHelper->unauthorized();
		}

		$this->actor->actionAuth->requireAction($user, 'widget.import-bookmarks');

		if ($this->permissionService->canAddWidget(userId: $this->userId, dashboardId: $dashboardId) === false) {
			return $this->responseHelper->forbidden();
		}

		try {
			$plan = BookmarkImportPlan::fromRequest(
				folders: $this->request->getParam('folders', []),
				bookmarks: $this->request->getParam('bookmarks', [])
			);
			$created = $this->widgetService->importBookmarks(dashboardId: $dashboardId, plan: $plan);
		} catch (InvalidArgumentException $e) {
			return $this->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (QuotaExceededException $e) {
			return $this->responseHelper->quotaExceeded(exception: $e);
		} catch (\Exception $e) {
			return $this->responseHelper->error(exception: $e);
		}

		return $this->responseHelper->success(
			data: [
				'placements' => $this->responseHelper->serializeList(entities: $created),
				'skipped' => $plan->skipped(),
			],
			statusCode: 201
		);
	}//end import()
}//end class
