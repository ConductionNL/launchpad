<?php

/**
 * SummaryMailController
 *
 * The caller's own summary email choice, for the section Samenvatting per
 * e-mail of the personal settings dialog (personal-settings REQ-PSET-002):
 *   - GET /api/summary-mail/me   `{frequency, hasEmail}`
 *   - PUT /api/summary-mail/me   store `frequency` (off, daily, weekly)
 *
 * Both read the caller from the session; no route takes a user id.
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
use OCA\LaunchPad\Service\SummaryMailPreference;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Read and write the caller's summary email frequency.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request     The request.
	 * @param IUserSession          $userSession Names the caller.
	 * @param SummaryMailPreference $preference  Reads and writes the frequency.
	 */
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly SummaryMailPreference $preference,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The caller's frequency, and whether they have an email address.
	 *
	 * @return JSONResponse `{frequency, hasEmail}`, or 401 signed out.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[NoAdminRequired]
	public function show(): JSONResponse {
		return $this->forCaller(frequency: null);
	}//end show()

	/**
	 * Store the caller's frequency.
	 *
	 * @param string $frequency Off, daily or weekly.
	 *
	 * @return JSONResponse `{frequency, hasEmail}`; 400 for an unknown frequency, 401 signed out.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[NoAdminRequired]
	public function update(string $frequency = SummaryMailPreference::OFF): JSONResponse {
		if (in_array($frequency, SummaryMailPreference::FREQUENCIES, true) === false) {
			return new JSONResponse(data: ['message' => 'Unknown frequency'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return $this->forCaller(frequency: $frequency);
	}//end update()

	/**
	 * Answer for the signed-in caller, storing a new frequency first when given.
	 *
	 * @param string|null $frequency A valid frequency to store, or null to only read.
	 *
	 * @return JSONResponse
	 */
	private function forCaller(?string $frequency): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['message' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		if ($frequency !== null) {
			$this->preference->setFrequency(userId: $uid, frequency: $frequency);
		}

		return new JSONResponse(
			data: [
				'frequency' => $this->preference->frequency(userId: $uid),
				'hasEmail' => ((string)$user->getEMailAddress() !== ''),
			]
		);
	}//end forCaller()
}//end class
