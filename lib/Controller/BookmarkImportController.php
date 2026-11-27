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
	 * @param ActorResponses $context Holds the actor, userId and responseHelper.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		IRequest $request,
		private readonly WidgetService $widgetService,
		private readonly PermissionService $permissionService,
		private readonly ActorResponses $context,
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
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	public function import(int $dashboardId): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($user === null || $this->context->userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		$this->context->actor->actionAuth->requireAction($user, 'widget.import-bookmarks');

		if ($this->permissionService->canAddWidget(userId: $this->context->userId, dashboardId: $dashboardId) === false) {
			return $this->context->responseHelper->forbidden();
		}

		try {
			$plan = BookmarkImportPlan::fromRequest(
				folders: $this->request->getParam('folders', []),
				bookmarks: $this->request->getParam('bookmarks', [])
			);
			return $this->answer(dashboardId: $dashboardId, plan: $plan);
		} catch (InvalidArgumentException $e) {
			return $this->context->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (QuotaExceededException $e) {
			return $this->context->responseHelper->quotaExceeded(exception: $e);
		} catch (\Exception $e) {
			return $this->context->responseHelper->error(exception: $e);
		}
	}//end import()

	/**
	 * Create the import, or, for an edit-mode draft (`draft: true`), answer
	 * the placements it would create below the draft's lowest edge (`bottom`)
	 * without writing anything: the draft adds them and its Save stores them
	 * (edit-mode-draft-and-save REQ-EDIT-001). The quota is checked either way.
	 *
	 * @param int                $dashboardId The dashboard.
	 * @param BookmarkImportPlan $plan        What to create.
	 *
	 * @return JSONResponse 201 with the created placements, or 200 with the draft's; `skipped` either way.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
	 */
	private function answer(int $dashboardId, BookmarkImportPlan $plan): JSONResponse {
		if ($this->request->getParam('draft') === true) {
			$this->widgetService->assertImportFits(dashboardId: $dashboardId, plan: $plan);
			return $this->context->responseHelper->success(
				data: [
					'placements' => $plan->draftPlacements(bottom: max(0, (int)$this->request->getParam('bottom', 0))),
					'skipped'    => $plan->skipped(),
				]
			);
		}

		$created = $this->widgetService->importBookmarks(dashboardId: $dashboardId, plan: $plan);
		return $this->context->responseHelper->success(
			data: [
				'placements' => $this->context->responseHelper->serializeList(entities: $created),
				'skipped' => $plan->skipped(),
			],
			statusCode: 201
		);
	}//end answer()
}//end class
