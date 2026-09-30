<?php

/**
 * BookmarkImportController
 *
 * `POST /api/dashboard/{dashboardId}/tiles/import` (launcher-bookmark-import,
 * REQ-BMI-001..003): imports the bookmarks a person chose from their browser's
 * bookmarks file onto a dashboard they may add widgets to.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
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
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\BookmarkImportService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Bookmark import endpoint.
 *
 * @spec openspec/specs/tiles/spec.md
 */
class BookmarkImportController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param BookmarkImportService $importer The import.
	 * @param PermissionService $permissionService Add rights on the dashboard.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param LoggerInterface $logger Logger.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly BookmarkImportService $importer,
		private readonly PermissionService $permissionService,
		private readonly ActionAuthService $actionAuth,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * Import the chosen bookmarks. The caller needs add rights on the
	 * dashboard (`add_only` or `full`), like adding one tile.
	 *
	 * @param int $dashboardId The dashboard.
	 * @param array|null $folders `[{name, bookmarks: [{title, url}]}]`.
	 * @param array|null $bookmarks Loose `[{title, url}]`.
	 *
	 * @return JSONResponse 201 with `{placements, containers, tiles, skipped}`, 400, 401, 403, 409 or 500.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	#[NoAdminRequired]
	public function import(int $dashboardId, ?array $folders = null, ?array $bookmarks = null): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'widget.import-bookmarks');
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		if ($this->permissionService->canAddWidget(userId: $user->getUID(), dashboardId: $dashboardId) === false) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$result = $this->importer->import(
				dashboardId: $dashboardId,
				folders: ($folders ?? []),
				bookmarks: ($bookmarks ?? [])
			);
		} catch (QuotaExceededException $e) {
			return ResponseHelper::quotaExceeded(exception: $e);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (Throwable $e) {
			$this->logger->error(message: 'Bookmark import failed: ' . $e->getMessage(), context: ['exception' => $e]);
			return new JSONResponse(data: ['error' => 'The import failed; nothing was added.'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(data: $result, statusCode: Http::STATUS_CREATED);
	}//end import()
}//end class
