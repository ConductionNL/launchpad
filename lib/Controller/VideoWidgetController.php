<?php

/**
 * VideoWidgetController
 *
 * `GET /api/widgets/video/{placementId}/file`: the streaming address of a
 * `video` placement's Nextcloud file for the logged-in viewer, or why it
 * cannot play (REQ-VID-006). Only a viewer who may see the placement is
 * answered, so a file id cannot be probed through it.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\VideoFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Resolves the file of a video placement for its viewer.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-006-check-file-access-control-for-nextcloud-files
 */
class VideoWidgetController extends Controller {
	/**
	 * HTTP status per refusal of the file service.
	 */
	private const REFUSAL_STATUS = [
		VideoFileService::NO_FILE        => Http::STATUS_BAD_REQUEST,
		VideoFileService::NOT_FOUND      => Http::STATUS_NOT_FOUND,
		VideoFileService::NOT_VIDEO      => Http::STATUS_UNPROCESSABLE_ENTITY,
		VideoFileService::NOT_ACCESSIBLE => Http::STATUS_FORBIDDEN,
	];

	/**
	 * Constructor.
	 *
	 * @param IRequest          $request     HTTP request.
	 * @param VideoFileService  $videoFiles  Resolves the placement's file.
	 * @param PermissionService $permissions Decides who may see the placement.
	 * @param UserResponses     $context     The user id and the response helper.
	 */
	public function __construct(
		IRequest $request,
		private readonly VideoFileService $videoFiles,
		private readonly PermissionService $permissions,
		private readonly UserResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/widgets/video/{placementId}/file
	 *
	 * 200 `{streamingUrl}` for a video the viewer can read; otherwise
	 * `{error}` with `not_found` (404), `not_video` (422),
	 * `not_accessible` (403) or `no_file` (400), never an address.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-006-check-file-access-control-for-nextcloud-files
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function file(int $placementId): JSONResponse {
		$userId = $this->context->userId;
		if ($userId === null) {
			return $this->context->responseHelper->unauthorized();
		}

		if ($this->permissions->canViewPlacement(userId: $userId, placementId: $placementId) === false) {
			return $this->context->responseHelper->forbidden();
		}

		$result = $this->videoFiles->resolve(viewerId: $userId, placementId: $placementId);
		if ($result['status'] === VideoFileService::OK) {
			return new JSONResponse(data: ['streamingUrl' => ($result['streamingUrl'] ?? '')]);
		}

		return new JSONResponse(
			data: ['error' => $result['status']],
			statusCode: (self::REFUSAL_STATUS[$result['status']] ?? Http::STATUS_BAD_REQUEST)
		);
	}//end file()
}//end class
