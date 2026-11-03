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
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

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
	 * @param RequestGuard $guard The session and administrator check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AttentionSourceService $sources,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The attention items the signed-in user's apps declare.
	 *
	 * Any signed-in user may ask: the answer is what apps enabled for THIS
	 * user declare, and holds no object data.
	 *
	 * @return JSONResponse `{userId, sources, invalid}`, or 401 without a session.
	 *
	 * @spec openspec/specs/attention-feed/spec.md#req-att-002
	 */
	#[NoAdminRequired]
	public function sources(): JSONResponse {
		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		// The widget resolves `@me` in a filter to this id before it counts.
		return new JSONResponse(
			data: (['userId' => $user->getUID()] + $this->sources->collect(user: $user))
		);
	}//end sources()
}//end class
