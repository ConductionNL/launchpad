<?php

/**
 * WidgetProposalController
 *
 * The assistant line of Add widget (REQ-WDG-044):
 *
 * - `GET /api/widget-picker/assistant` — `{available}`: whether the server
 *   has a text-to-text assistant for the signed-in user, so the picker shows
 *   the line only when it can answer.
 * - `POST /api/widget-picker/proposal` — `{need, cards}` in, `{proposal}`
 *   out: one of the offered cards (`{key, name, reason}`) or null.
 *
 * Signed-in users only; a proposal runs an assistant task, so it is rate
 * limited per user. Nothing is stored.
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
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\WidgetProposalService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Serves the widget picker's assistant line.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
 */
class WidgetProposalController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request   HTTP request.
	 * @param WidgetProposalService $proposals Asks the assistant.
	 * @param SessionLog            $session   The signed-in user and the 401 answer.
	 */
	public function __construct(
		IRequest $request,
		private readonly WidgetProposalService $proposals,
		private readonly SessionLog $session,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/widget-picker/assistant`: whether the assistant line can answer.
	 *
	 * @return JSONResponse `{available: bool}`; 401 when not signed in.
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	#[NoAdminRequired]
	public function assistant(): JSONResponse {
		$userId = $this->session->userId();
		if ($userId === null) {
			return $this->session->unauthorized();
		}

		return new JSONResponse(data: ['available' => $this->proposals->isAvailable(userId: $userId)]);
	}//end assistant()

	/**
	 * `POST /api/widget-picker/proposal`: propose one offered card for a need.
	 *
	 * @return JSONResponse `{proposal: {key, name, reason}|null}`; 401 when not
	 *                      signed in, 400 without a need, 503 without an assistant.
	 *
	 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-044
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 300)]
	public function propose(): JSONResponse {
		$userId = $this->session->userId();
		if ($userId === null) {
			return $this->session->unauthorized();
		}

		$need  = $this->request->getParam(key: 'need');
		$cards = $this->request->getParam(key: 'cards');
		if (is_string(value: $need) === false || trim(string: $need) === '') {
			return new JSONResponse(data: ['message' => 'Describe what you need'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($this->proposals->isAvailable(userId: $userId) === false) {
			return new JSONResponse(data: ['message' => 'No assistant is available'], statusCode: Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$offers = [];
		if (is_array(value: $cards) === true) {
			$offers = $cards;
		}

		return new JSONResponse(
			data: ['proposal' => $this->proposals->propose(userId: $userId, need: $need, cards: $offers)]
		);
	}//end propose()
}//end class
