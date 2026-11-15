<?php

/**
 * MetadataAdminController
 *
 * Admin-only HTTP entry points for the dashboard-metadata-fields
 * registry (REQ-MDFL-001..003). All endpoints gate on
 * `\OCP\IGroupManager::isAdmin` at runtime and return HTTP 403 otherwise
 * (the `#[NoAdminRequired]` attribute is omitted so the framework
 * already requires authentication; the runtime check adds the admin
 * gate on top — same pattern as `AdminSettingsController`).
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
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Exception\MetadataFieldHasValuesException;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin field-definition CRUD controller.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Constructor wires
 *  the standard Nextcloud admin trio (group manager + session +
 *  request) plus the capability service.
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */
class MetadataAdminController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request The HTTP request.
	 * @param MetadataService $metadataService The metadata service facade.
	 * @param RequestGuard $guard The administrator check.
	 */
	public function __construct(
		IRequest $request,
		private readonly MetadataService $metadataService,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/admin/metadata-fields` — list all field definitions
	 * (REQ-MDFL-001).
	 *
	 * @return JSONResponse The fields array + count, or 403.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listFields(): JSONResponse {
		$refusal = $this->guard->refusal() ?? $this->guard->actionRefusal(action: 'metadata-admin.list-fields');
		if ($refusal !== null) {
			return $refusal;
		}

		$fields = $this->metadataService->listFields();

		return ResponseHelper::success(
			data: [
				'fields' => ResponseHelper::serializeList(entities: $fields),
				'count' => count($fields),
			]
		);
	}//end listFields()

	/**
	 * `POST /api/admin/metadata-fields` — create a new field definition
	 * (REQ-MDFL-001).
	 *
	 * @param string $key The slug.
	 * @param string $label The display label.
	 * @param string $type The field type.
	 * @param array<int, string>|null $options Option set (select types).
	 * @param int $required 0 / 1.
	 * @param int $sortOrder UI sort order.
	 *
	 * @return JSONResponse 201 + field, 400 on validation failure,
	 *                      403 for non-admins.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function createField(
		string $key = '',
		string $label = '',
		string $type = '',
		array|null $options = null,
		int $required = 0,
		int $sortOrder = 0,
	): JSONResponse {
		$refusal = $this->guard->refusal() ?? $this->guard->actionRefusal(action: 'metadata-admin.create-field');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$field = $this->metadataService->createFieldDefinition(
				key: $key,
				label: $label,
				type: $type,
				settings: ['options' => $options, 'required' => $required, 'sortOrder' => $sortOrder]
			);
		} catch (InvalidMetadataFieldException $exception) {
			return self::badRequest(message: $exception->getMessage());
		}

		return new JSONResponse(
			data: $field->jsonSerialize(),
			statusCode: Http::STATUS_CREATED
		);
	}//end createField()

	/**
	 * `GET /api/admin/metadata-fields/{id}` — fetch a single field
	 * definition (REQ-MDFL-001).
	 *
	 * @param int $id The field id.
	 *
	 * @return JSONResponse 200 + field, 404 when missing, 403 for non-admins.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getField(int $id): JSONResponse {
		$refusal = $this->guard->refusal() ?? $this->guard->actionRefusal(action: 'metadata-admin.get-field');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$field = $this->metadataService->getField(id: $id);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Field not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		return ResponseHelper::success(data: $field->jsonSerialize());
	}//end getField()

	/**
	 * `PUT /api/admin/metadata-fields/{id}` — update label / sortOrder
	 * / required / options. Forbids `key` rename (REQ-MDFL-002).
	 *
	 * @param int $id The field id.
	 * @param string|null $label The new label.
	 * @param int|null $sortOrder The new sort order.
	 * @param int|null $required The new required flag.
	 * @param array<int, string>|null $options The new option set.
	 * @param string|null $key Forbidden — triggers 400.
	 *
	 * @return JSONResponse 200 + field, 400 on validation failure,
	 *                      404 when missing, 403 for non-admins.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateField(
		int $id,
		string|null $label = null,
		int|null $sortOrder = null,
		int|null $required = null,
		array|null $options = null,
		string|null $key = null,
	): JSONResponse {
		$refusal = $this->guard->refusal() ?? $this->guard->actionRefusal(action: 'metadata-admin.update-field');
		if ($refusal !== null) {
			return $refusal;
		}

		$patch = self::buildPatch(
			fields: [
				'key'       => $key,
				'label'     => $label,
				'sortOrder' => $sortOrder,
				'required'  => $required,
				'options'   => $options,
			]
		);

		try {
			$field = $this->metadataService->updateFieldDefinition(
				id: $id,
				patch: $patch
			);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Field not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidMetadataFieldException $exception) {
			return self::badRequest(message: $exception->getMessage());
		}

		return ResponseHelper::success(data: $field->jsonSerialize());
	}//end updateField()

	/**
	 * `DELETE /api/admin/metadata-fields/{id}?cascade=true` —
	 * REQ-MDFL-003.
	 *
	 * @param int $id The field id.
	 * @param bool $cascade Whether to cascade-delete dependent values.
	 *
	 * @return JSONResponse 200 on success, 409 when soft-deletion blocked,
	 *                      404 when missing, 403 for non-admins.
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function deleteField(int $id, bool $cascade = false): JSONResponse {
		$refusal = $this->guard->refusal() ?? $this->guard->actionRefusal(action: 'metadata-admin.delete-field');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->metadataService->deleteFieldDefinition(
				id: $id,
				cascade: $cascade
			);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Field not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (MetadataFieldHasValuesException $exception) {
			return self::fieldInUse(exception: $exception);
		}

		return ResponseHelper::success(data: ['status' => 'ok']);
	}//end deleteField()

	/**
	 * The 409 for deleting a field that still has values.
	 *
	 * @param MetadataFieldHasValuesException $exception The refusal.
	 *
	 * @return JSONResponse The error response.
	 */
	private static function fieldInUse(MetadataFieldHasValuesException $exception): JSONResponse {
		return new JSONResponse(
			data: [
				'error' => MetadataFieldHasValuesException::ERROR_CODE,
				'message' => $exception->getMessage(),
				'valueCount' => $exception->getValueCount(),
			],
			statusCode: Http::STATUS_CONFLICT
		);
	}//end fieldInUse()

	/**
	 * Build the update patch from the individual nullable parameters.
	 *
	 * `null` means "not supplied" and the key is left out entirely, so
	 * the service can distinguish an omitted field from an explicit
	 * value. The router delivers `null` when the body omits the key;
	 * any explicit array (including an empty one) counts as "set
	 * options", so admins can clear an option set on non-select types.
	 *
	 * The forbidden `key` rename is deliberately forwarded rather than
	 * dropped — the service rejects it with the documented 400.
	 *
	 * @param array<string, mixed> $fields The nullable `key` (forbidden
	 *                                     new slug), `label`, `sortOrder`,
	 *                                     `required` and `options`.
	 *
	 * @return array<string, mixed> The patch payload.
	 */
	private static function buildPatch(array $fields): array {
		return array_filter(
			array: $fields,
			callback: static function ($value): bool {
				return $value !== null;
			}
		);
	}//end buildPatch()

	/**
	 * Build a 400-with-error envelope.
	 *
	 * @param string $message The validation message.
	 *
	 * @return JSONResponse The 400 response.
	 */
	private static function badRequest(string $message): JSONResponse {
		return new JSONResponse(
			data: [
				'error' => InvalidMetadataFieldException::ERROR_CODE,
				'message' => $message,
			],
			statusCode: Http::STATUS_BAD_REQUEST
		);
	}//end badRequest()
}//end class
