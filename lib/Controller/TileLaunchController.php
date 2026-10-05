<?php

/**
 * TileLaunchController
 *
 * `GET /api/tiles/{placementId}/rdp` hands out the remote desktop connection
 * file of a tile (launcher-tile-launch-types, REQ-TLT-002). The caller must
 * be able to view the tile's dashboard; the file is written from the stored
 * connection after checking it again, and never holds a credential.
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
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RdpFileBuilder;
use OCA\LaunchPad\Service\TileLaunchValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Remote desktop connection files for tiles.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
class TileLaunchController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param PermissionService $permissionService Dashboard view check.
	 * @param WidgetPlacementMapper $placementMapper Loads the tile.
	 * @param TileLaunchValidator $launchValidator Checks the stored connection.
	 * @param RdpFileBuilder $rdpFiles Writes the connection file.
	 * @param IUserSession $userSession Current user.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly PermissionService $permissionService,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly TileLaunchValidator $launchValidator,
		private readonly RdpFileBuilder $rdpFiles,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * The tile's `.rdp` file: 401 signed out, 403 when the caller may not
	 * view the tile's dashboard (or the tile does not exist), 404 when the
	 * tile is no valid RDP connection.
	 *
	 * @param int $placementId The tile's placement id.
	 *
	 * @return Response
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function rdp(int $placementId): Response {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		// REQ-TLT-002: the view check runs before the tile is read.
		if ($this->permissionService->canViewPlacement(userId: $user->getUID(), placementId: $placementId) === false) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		$tile   = $this->placementMapper->find(id: $placementId);
		$remote = ($tile->getContentArray()['remote'] ?? null);
		if ($tile->getTileLinkType() !== 'remote-desktop' || is_array(value: $remote) === false || ($remote['mode'] ?? null) !== 'rdp') {
			return new JSONResponse(data: ['error' => 'This tile has no remote desktop connection'], statusCode: Http::STATUS_NOT_FOUND);
		}

		try {
			$this->launchValidator->assertValidRemote(remote: $remote);
		} catch (InvalidArgumentException) {
			return new JSONResponse(data: ['error' => 'This tile has no remote desktop connection'], statusCode: Http::STATUS_NOT_FOUND);
		}

		// The name holds only letters, digits, spaces and `._()-`, so it is
		// safe between quotes; non-ASCII letters go in `filename*`.
		$fileName = $this->rdpFiles->fileName(title: $tile->getTileTitle());
		$fallback = (string)preg_replace(pattern: '/[^\x20-\x7E]/', replacement: '_', subject: $fileName);
		$response = new DataDisplayResponse(data: $this->rdpFiles->build(remote: $remote));
		$response->addHeader(name: 'Content-Type', value: 'application/x-rdp');
		$response->addHeader(
			name: 'Content-Disposition',
			value: 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode(string: $fileName)
		);

		return $response;
	}//end rdp()
}//end class
