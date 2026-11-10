<?php

/**
 * DashboardMetadataController
 *
 * Per-dashboard metadata read / write endpoints
 * (REQ-MDFL-004..006, REQ-MDFL-008). Lives in a dedicated controller
 * (rather than `DashboardApiController`) so the dashboard-metadata-fields
 * capability ships independently of the in-flight dashboard-tree and
 * dashboard-draft-published changes that also touch
 * `DashboardApiController`.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Dashboard metadata read/write controller.
 *
 * All access decisions are delegated to PermissionService — the single
 * source of truth for dashboard ACL (H5, REQ-MDFL-008).
 */
class DashboardMetadataController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest          $request The HTTP request.
	 * @param MetadataApiCaller $caller The metadata sources, session and action check.
	 * @param PermissionService $permissionService Authoritative ACL service (replaces inline canRead /
	 *                                             canWrite helpers — H5).
	 * @param string|null       $userId The active user id.
	 */
	public function __construct(
		IRequest $request,
		private readonly MetadataApiCaller $caller,
		private readonly PermissionService $permissionService,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/dashboards/{uuid}/metadata` — REQ-MDFL-004 / REQ-MDFL-008.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 200 + flat metadata, 404 when missing,
	 *                      403 when the caller cannot see the dashboard.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[NoAdminRequired]
	public function getMetadata(string $uuid): JSONResponse {
		$user = $this->caller->authorise(userId: $this->userId, action: 'dashboard-metadata.get-metadata');
		if ($user instanceof JSONResponse) {
			return $user;
		}

		$dashboard = $this->loadDashboard(uuid: $uuid);
		if ($dashboard === null) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		// H5: delegate to PermissionService — the single ACL source of truth.
		if ($this->permissionService->canViewDashboard(
			userId: $this->userId,
			dashboardId: $dashboard->getId()
		) === false
		) {
			return ResponseHelper::forbidden();
		}

		$metadata = $this->caller->sources->metadataService->getMetadataForDashboard(
			dashboardUuid: $uuid
		);

		return ResponseHelper::success(data: $metadata);
	}//end getMetadata()

	/**
	 * `GET /api/metadata-fields`: the field definitions, readable by any
	 * logged-in user, so a dashboard owner who is not an administrator can
	 * fill in the Details tab. Definitions only (key, label, type,
	 * options); no values of any dashboard. Writing definitions stays
	 * administrator-only in MetadataAdminController.
	 *
	 * @return JSONResponse `{fields: [...]}`, or 401 without a login.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[NoAdminRequired]
	public function fields(): JSONResponse {
		if ($this->userId === null || $this->caller->userSession->getUser() === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		return ResponseHelper::success(
			data: ['fields' => ResponseHelper::serializeList(entities: $this->caller->sources->metadataService->listFields())]
		);
	}//end fields()

	/**
	 * `PUT /api/dashboards/{uuid}/metadata` — REQ-MDFL-005 / REQ-MDFL-008.
	 *
	 * Body: flat key-value object. Omitted keys are NOT removed; only
	 * keys present in the payload are upserted.
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param array<string, mixed> $metadata The patch payload.
	 *
	 * @return JSONResponse 200 + updated metadata, 400 on validation
	 *                      failure, 404 when missing, 403 otherwise.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[NoAdminRequired]
	public function setMetadata(string $uuid, array $metadata = []): JSONResponse {
		$user = $this->caller->authorise(userId: $this->userId, action: 'dashboard-metadata.set-metadata');
		if ($user instanceof JSONResponse) {
			return $user;
		}

		$dashboard = $this->loadDashboard(uuid: $uuid);
		if ($dashboard === null) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		// H5: delegate to PermissionService — canEditDashboardMetadata is
		// owner-only for personal dashboards; admin-only for admin templates.
		// This replaces the previous inline canWrite() which incorrectly
		// allowed any group member to write group-shared metadata.
		if ($this->permissionService->canEditDashboardMetadata(
			userId: $this->userId,
			dashboardId: $dashboard->getId()
		) === false
		) {
			return ResponseHelper::forbidden();
		}

		try {
			$updated = $this->caller->sources->metadataService->setMetadataForDashboard(
				dashboardUuid: $uuid,
				keyValues: $metadata
			);
		} catch (InvalidMetadataFieldException $exception) {
			return new JSONResponse(
				data: [
					'error' => InvalidMetadataFieldException::ERROR_CODE,
					'message' => $exception->getMessage(),
				],
				statusCode: Http::STATUS_BAD_REQUEST
			);
		}

		return ResponseHelper::success(data: $updated);
	}//end setMetadata()

	/**
	 * Resolve a UUID to a dashboard or null.
	 *
	 * @param string $uuid The UUID.
	 *
	 * @return Dashboard|null The dashboard or null.
	 */
	private function loadDashboard(string $uuid): ?Dashboard {
		try {
			return $this->caller->sources->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return null;
		}
	}//end loadDashboard()
}//end class
