<?php

/**
 * RoleFeaturePermissionApiController
 *
 * Admin-only HTTP API for managing RoleFeaturePermission and
 * RoleLayoutDefault rows. All routes guard with the same `requireAdmin()`
 * pattern as `AdminController` (REQ-RFP-007 / ADR-005).
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

use Closure;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin-only API for role-feature permissions and role-layout defaults.
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-3
 */
class RoleFeaturePermissionApiController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request The HTTP request.
	 * @param RoleFeaturePermissionService $service Permission service.
	 */
	public function __construct(
		IRequest $request,
		private readonly RoleFeaturePermissionService $service,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/role-feature-permissions
	 *
	 * @return JSONResponse The list of permission rows.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listPermissions(): JSONResponse {
		return self::listed(rows: $this->service->listPermissions());
	}//end listPermissions()

	/**
	 * POST /api/role-feature-permissions
	 *
	 * Body: `{name, description?, groupId, allowedWidgets, deniedWidgets?, priorityWeights?}`.
	 * Upsert keyed by `groupId`.
	 *
	 * @return JSONResponse The persisted row.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function savePermission(): JSONResponse {
		return $this->created(save: fn (array $data): Entity => $this->service->savePermission(data: $data));
	}//end savePermission()

	/**
	 * DELETE /api/role-feature-permissions/{id}
	 *
	 * @param int $id The row id.
	 *
	 * @return JSONResponse Empty 204 on success.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deletePermission(int $id): JSONResponse {
		return self::deleted(delete: fn () => $this->service->deletePermission(id: $id));
	}//end deletePermission()

	/**
	 * GET /api/role-layout-defaults
	 *
	 * @return JSONResponse The list of layout default rows.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listLayoutDefaults(): JSONResponse {
		return self::listed(rows: $this->service->listLayoutDefaults());
	}//end listLayoutDefaults()

	/**
	 * POST /api/role-layout-defaults
	 *
	 * @return JSONResponse The persisted row.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function saveLayoutDefault(): JSONResponse {
		return $this->created(save: fn (array $data): Entity => $this->service->saveLayoutDefault(data: $data));
	}//end saveLayoutDefault()

	/**
	 * DELETE /api/role-layout-defaults/{id}
	 *
	 * @param int $id The row id.
	 *
	 * @return JSONResponse Empty 204 on success.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteLayoutDefault(int $id): JSONResponse {
		return self::deleted(delete: fn () => $this->service->deleteLayoutDefault(id: $id));
	}//end deleteLayoutDefault()

	/**
	 * The 200 answer of a list endpoint.
	 *
	 * @param Entity[] $rows The rows.
	 *
	 * @return JSONResponse The serialised rows.
	 */
	private static function listed(array $rows): JSONResponse {
		return ResponseHelper::success(
			data: ResponseHelper::serializeList(entities: $rows)
		);
	}//end listed()

	/**
	 * Save the request body and answer 201 with the saved row; 400 when
	 * the service rejects the payload, the generic error otherwise.
	 *
	 * @param Closure(array): Entity $save Saves the payload.
	 *
	 * @return JSONResponse The saved row, or the error.
	 */
	private function created(Closure $save): JSONResponse {
		try {
			$entity = $save($this->readJsonBody());
			return ResponseHelper::success(
				data: $entity->jsonSerialize(),
				statusCode: Http::STATUS_CREATED
			);
		} catch (\InvalidArgumentException $e) {
			return ResponseHelper::error(
				exception: $e,
				statusCode: Http::STATUS_BAD_REQUEST
			);
		} catch (\Exception $e) {
			return ResponseHelper::error(exception: $e);
		}//end try
	}//end created()

	/**
	 * Delete a row and answer 204; 404 when it does not exist.
	 *
	 * @param Closure(): void $delete Deletes the row.
	 *
	 * @return JSONResponse The empty answer, or the error.
	 */
	private static function deleted(Closure $delete): JSONResponse {
		try {
			$delete();
			return new JSONResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
		} catch (DoesNotExistException $e) {
			return ResponseHelper::error(
				exception: $e,
				statusCode: Http::STATUS_NOT_FOUND
			);
		}//end try
	}//end deleted()

	/**
	 * Read the request body as a JSON object (best-effort).
	 *
	 * @return array The decoded body, or `[]` when not JSON.
	 */
	private function readJsonBody(): array {
		$raw = file_get_contents(filename: 'php://input');
		if ($raw === false || $raw === '') {
			return $this->request->getParams();
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (is_array(value: $decoded) === false) {
			return $this->request->getParams();
		}

		return $decoded;
	}//end readJsonBody()
}//end class
