<?php

/**
 * HiddenWidgetsApiController
 *
 * The overview of every widget the caller hid on shared dashboards, for the
 * section Verborgen widgets of the personal settings dialog
 * (personal-settings REQ-PSET-003). Three routes:
 *   - GET    /api/personal-layer/hidden                 the list
 *   - DELETE /api/personal-layer/hidden/{placementId}   show one again
 *   - DELETE /api/personal-layer/hidden                 show all again
 *
 * Every route reads the caller's own user id from the session. No route
 * takes a user id from the request, so one person's layer cannot be read or
 * changed through another's.
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
use OCA\LaunchPad\Service\HiddenWidgetsOverview;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * List and show again the caller's hidden widgets.
 *
 * Every method is `#[NoAdminRequired]`: it acts on the caller's own layer only.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class HiddenWidgetsApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request  The request.
	 * @param HiddenWidgetsOverview $overview Reads and changes the caller's layers.
	 * @param string|null           $userId   The acting user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly HiddenWidgetsOverview $overview,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the widgets the caller hid, on every dashboard they may see.
	 *
	 * @return JSONResponse `{hidden: [...]}`.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		if ($this->caller() === '') {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['hidden' => $this->overview->listFor(userId: $this->caller())]);
	}//end index()

	/**
	 * DELETE one hidden widget from the caller's layer: show it again.
	 *
	 * @param int $placementId The hidden placement.
	 *
	 * @return JSONResponse `{shown: true}`, or 404 when the caller did not hide it.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[NoAdminRequired]
	public function showAgain(int $placementId): JSONResponse {
		if ($this->caller() === '') {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->overview->showAgain(userId: $this->caller(), placementId: $placementId) === false) {
			return $this->failure(message: 'not_hidden', statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(['shown' => true]);
	}//end showAgain()

	/**
	 * DELETE every hidden widget from the caller's layers: show all again.
	 *
	 * @return JSONResponse `{shown: n}`.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[NoAdminRequired]
	public function showAll(): JSONResponse {
		if ($this->caller() === '') {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['shown' => $this->overview->showAll(userId: $this->caller())]);
	}//end showAll()

	/**
	 * The caller's user id, or an empty string when nobody is signed in.
	 *
	 * @return string
	 */
	private function caller(): string {
		return (string)($this->userId ?? '');
	}//end caller()
}//end class
