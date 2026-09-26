<?php

/**
 * PersonalLayerApiController
 *
 * A person's own arrangement of a dashboard somebody else owns
 * (REQ-DWMS-001, REQ-DWMS-002). Three routes:
 *   - GET    /api/dashboards/{dashboardId}/personal-layer
 *   - PUT    /api/dashboards/{dashboardId}/personal-layer
 *   - DELETE /api/dashboards/{dashboardId}/personal-layer   (the reset)
 *
 * Every route reads the caller's own user id from the session and passes it
 * down. No route takes a user id from the request, so one person's
 * arrangement cannot be read or written through another's.
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
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use stdClass;

/**
 * Read, save and reset a personal dashboard layer.
 *
 * Every method is `#[NoAdminRequired]`: arranging a dashboard for yourself is
 * something any user may do on a dashboard they can already see, and the read
 * of the dashboard is what decides whether they can see it.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class PersonalLayerApiController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param PersonalLayerService $layers Applies, saves and resets a layer.
	 * @param PersonalLayerMapper $mapper Reads the layer for the GET.
	 * @param DashboardService $dashboards Decides whether the caller may see it.
	 * @param string|null $userId The acting user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly PersonalLayerService $layers,
		private readonly PersonalLayerMapper $mapper,
		private readonly DashboardService $dashboards,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the caller's own layer on one dashboard.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return JSONResponse The layer, or an empty one.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function show(int $dashboardId): JSONResponse {
		$visible = $this->visible(dashboardId: $dashboardId);
		if ($visible === null) {
			return new JSONResponse(['error' => 'dashboard_not_found'], Http::STATUS_NOT_FOUND);
		}

		$layer = $this->mapper->findForUser(userId: (string)$this->userId, dashboardId: $dashboardId);
		if ($layer === null) {
			// No layer means this person sees what the owner composed, which
			// is a state worth answering plainly rather than with a 404. The
			// answer carries the same keys and the same JSON types as a saved
			// layer, so a client can read `overrides.<placementId>` and
			// `hidden` without first branching on `hasLayer`.
			return new JSONResponse(
				[
					'id' => null,
					'dashboardId' => $dashboardId,
					'overrides' => new stdClass(),
					'hidden' => [],
					'updatedAt' => null,
					'hasLayer' => false,
				]
			);
		}

		return new JSONResponse($layer->jsonSerialize() + ['hasLayer' => true]);
	}//end show()

	/**
	 * PUT the caller's arrangement.
	 *
	 * @param int $dashboardId The dashboard.
	 * @param array<int, array<string, mixed>> $overrides Per placement id, the
	 *                                                    keys they changed.
	 * @param array<int, int> $hidden The placement ids they want hidden.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function save(int $dashboardId, array $overrides = [], array $hidden = []): JSONResponse {
		$visible = $this->visible(dashboardId: $dashboardId);
		if ($visible === null) {
			return new JSONResponse(['error' => 'dashboard_not_found'], Http::STATUS_NOT_FOUND);
		}

		$saved = $this->layers->save(
			userId: (string)$this->userId,
			dashboardId: $dashboardId,
			overrides: $overrides,
			hide: $hidden,
			placements: (array)($visible['placements'] ?? [])
		);
		if (isset($saved['error']) === true) {
			// The refusal names the placement, so the message can say which
			// widget the administrator made compulsory.
			return new JSONResponse($saved, Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse($saved);
	}//end save()

	/**
	 * DELETE the caller's layer: the reset.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function reset(int $dashboardId): JSONResponse {
		if ($this->visible(dashboardId: $dashboardId) === null) {
			return new JSONResponse(['error' => 'dashboard_not_found'], Http::STATUS_NOT_FOUND);
		}

		// A reset with no layer is not an error: the caller asked for the
		// owner's arrangement and that is what they have.
		return new JSONResponse(['reset' => $this->layers->reset(userId: (string)$this->userId, dashboardId: $dashboardId)]);
	}//end reset()

	/**
	 * The dashboard envelope when the caller may read it, else null.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return array<string, mixed>|null
	 */
	private function visible(int $dashboardId): ?array {
		if ($this->userId === null || $this->userId === '') {
			return null;
		}

		return $this->dashboards->getDashboardForUser(dashboardId: $dashboardId, userId: (string)$this->userId);
	}//end visible()
}//end class
