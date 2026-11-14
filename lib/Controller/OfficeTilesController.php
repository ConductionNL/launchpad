<?php

/**
 * OfficeTilesController
 *
 * `GET /api/office-tiles` (the pack for the signed-in person) and
 * `POST /api/office-tiles/create` (a new file from an office app's blank
 * template, never overwriting). integrations-office-tiles REQ-OFT-001/002.
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

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Service\OfficeTilesService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The office tile pack and file creation from it.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
 */
class OfficeTilesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request The request.
	 * @param OfficeTilesService $tiles   The pack and file creation.
	 * @param RequestGuard       $guard   The session and the ADR-023 action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly OfficeTilesService $tiles,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The pack's tiles for the signed-in person.
	 *
	 * Every "@all" caller may read their own pack; it lists only creators and
	 * apps this person can use.
	 *
	 * @return JSONResponse `{tiles}`, or 401/403.
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
	 */
	#[NoAdminRequired]
	public function list(): JSONResponse {
		$refusal = $this->guard->actionRefusal(action: 'office-tiles.list');
		if ($refusal !== null) {
			return $refusal;
		}

		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		return new JSONResponse(['tiles' => $this->tiles->packFor(userId: $user->getUID())]);
	}//end list()

	/**
	 * Create a file in the signed-in person's own files from a creator's blank template.
	 *
	 * @param string $creatorId The creator, as the pack names it.
	 * @param string $name      The file name without extension.
	 * @param string $folder    The folder (default /Documents).
	 *
	 * @return JSONResponse `{fileId, path, url}`; 400 on an unknown creator or a bad name or folder.
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
	 */
	#[NoAdminRequired]
	public function create(string $creatorId = '', string $name = '', string $folder = OfficeTilesService::DEFAULT_FOLDER): JSONResponse {
		$refusal = $this->guard->actionRefusal(action: 'office-tiles.create');
		if ($refusal !== null) {
			return $refusal;
		}

		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		try {
			$file = $this->tiles->create(userId: $user->getUID(), creatorId: $creatorId, name: $name, folder: $folder);
		} catch (ResourceException $exception) {
			return new JSONResponse(
				['error' => $exception->getErrorCode(), 'message' => $exception->getDisplayMessage()],
				$exception->getHttpStatus()
			);
		}

		return new JSONResponse($file, Http::STATUS_CREATED);
	}//end create()
}//end class
