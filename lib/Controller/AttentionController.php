<?php

/**
 * AttentionController
 *
 * Serves what the user's apps say needs attention, for the "First today"
 * widget. It returns declarations only: the widget runs the counts.
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
use OCA\LaunchPad\Service\AttentionSourceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The attention sources endpoint.
 *
 * @spec openspec/specs/attention-feed/spec.md#req-att-002
 */
class AttentionController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest               $request     The request.
	 * @param AttentionSourceService $sources     Reads the apps' declarations.
	 * @param IUserSession           $userSession The user session.
	 */
	public function __construct(
		IRequest $request,
		private readonly AttentionSourceService $sources,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * The attention items the signed-in user's apps declare.
	 *
	 * Any signed-in user may ask: the answer is what apps enabled for THIS
	 * user declare, and holds no object data.
	 *
	 * @return JSONResponse `{sources, invalid}`, or 401 without a session.
	 *
	 * @spec openspec/specs/attention-feed/spec.md#req-att-002
	 */
	#[NoAdminRequired]
	public function sources(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				data: ['error' => 'Not authenticated'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		return new JSONResponse(data: $this->sources->collect(user: $user));
	}//end sources()
}//end class
