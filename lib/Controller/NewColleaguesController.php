<?php

/**
 * NewColleaguesController
 *
 * GET /api/people/new: the "New colleagues" selection of the people widget
 * (REQ-PPL-014), for whoever may list people.
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
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\NewColleagues;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;

/**
 * The new colleagues the caller may see.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
class NewColleaguesController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest       $request   HTTP request.
	 * @param NewColleagues  $colleagues Who started recently or starts soon.
	 * @param ActorResponses $context    The caller and the action check.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	public function __construct(
		IRequest $request,
		private readonly NewColleagues $colleagues,
		private readonly ActorResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/people/new
	 *
	 * `{configured, since, until, users: [{id, displayName, startDate, starts}]}`,
	 * newest start first; the windows are clamped to 1..90 days back and
	 * 0..30 days ahead. Guarded by the same action as the people list.
	 *
	 * @param int|null $since Days back (default 30).
	 * @param int|null $until Days ahead (default 14).
	 *
	 * @return JSONResponse
	 *
	 * @no-admin-idor-exempt Addresses no object: it lists people through the
	 *   same visibility checks as the people widget, for the caller.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getNew(?int $since = NewColleagues::SINCE_DEFAULT, ?int $until = NewColleagues::UNTIL_DEFAULT): JSONResponse {
		$user = $this->context->actor->userSession->getUser();
		if ($this->context->userId === null || $user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->context->actor->actionAuth->requireAction($user, 'people-widget.get-users');
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(
			data: $this->colleagues->listNew(
				viewerId: $this->context->userId,
				window: [
					'since' => ($since ?? NewColleagues::SINCE_DEFAULT),
					'until' => ($until ?? NewColleagues::UNTIL_DEFAULT),
					'today' => gmdate(format: 'Y-m-d'),
				]
			)
		);
	}//end getNew()
}//end class
