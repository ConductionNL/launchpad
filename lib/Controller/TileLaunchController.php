<?php

/**
 * TileLaunchController
 *
 * Starts what a launch tile opens when the browser cannot do it from a plain
 * link: the `.rdp` file of a remote-desktop tile in RDP mode.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RdpFileWriter;
use OCA\LaunchPad\Service\TileLinkValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * `GET /api/tiles/{placementId}/rdp` (design D3).
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */
class TileLaunchController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest              $request     The request.
	 * @param PermissionService     $permissions Decides who may view the tile's dashboard.
	 * @param SessionLog            $session     The signed-in user.
	 * @param WidgetPlacementMapper $placements  Reads the tile.
	 * @param TileLinkValidator     $links       Checks the tile's RDP connection again before it is written.
	 * @param RdpFileWriter         $rdpFiles    Writes the file.
	 */
	public function __construct(
		IRequest $request,
		private readonly PermissionService $permissions,
		private readonly SessionLog $session,
		private readonly WidgetPlacementMapper $placements,
		private readonly TileLinkValidator $links,
		private readonly RdpFileWriter $rdpFiles,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The `.rdp` file of a remote-desktop tile in RDP mode.
	 *
	 * 401 when signed out; 403 when the caller may not view the tile's
	 * dashboard (checked before the tile is read); 404 when the placement
	 * is not a remote-desktop tile with a valid RDP connection.
	 *
	 * @param int $placementId The tile's placement id.
	 *
	 * @return Response The file, or the refusal.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function rdp(int $placementId): Response {
		$viewer = $this->session->placementViewer(permissions: $this->permissions, placementId: $placementId);
		if ($viewer instanceof JSONResponse) {
			return $viewer;
		}

		try {
			$placement = $this->placements->find(id: $placementId);
			if ($placement->getTileLinkType() !== TileLinkValidator::TYPE_REMOTE) {
				return $this->notFound();
			}

			$connection = $this->links->rdpConnection(content: $placement->getContentArray());
		} catch (DoesNotExistException | InvalidArgumentException) {
			return $this->notFound();
		}

		$response = new DataDisplayResponse(
			data: $this->rdpFiles->contents(connection: $connection),
			statusCode: Http::STATUS_OK
		);
		// After construction: DataDisplayResponse sets an inline disposition of its own.
		$response->addHeader(name: 'Content-Type', value: RdpFileWriter::MEDIA_TYPE);
		$response->addHeader(
			name: 'Content-Disposition',
			value: $this->rdpFiles->disposition(fileName: $this->rdpFiles->fileName(title: $placement->getTileTitle()))
		);
		return $response;
	}//end rdp()

	/**
	 * The answer for a tile that has no RDP file.
	 *
	 * @return JSONResponse
	 */
	private function notFound(): JSONResponse {
		return new JSONResponse(
			data: ['status' => 'error', 'error' => 'not_found'],
			statusCode: Http::STATUS_NOT_FOUND
		);
	}//end notFound()
}//end class
