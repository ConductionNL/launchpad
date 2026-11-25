<?php

/**
 * AnnouncementGroupsController
 *
 * `GET /api/announcements/groups`: the groups the announcement form offers
 * as the target group (announcement-form-board-sync, REQ-ANN-006). Only
 * announcement editors get the list.
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
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementTargetGroups;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The target group picker's list.
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */
class AnnouncementGroupsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                     $request       The request.
	 * @param AnnouncementServiceInterface $announcements Who may author.
	 * @param RequestGuard                 $guard         The session user and the action check.
	 * @param AnnouncementTargetGroups     $targetGroups  The groups.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementServiceInterface $announcements,
		private readonly RequestGuard $guard,
		private readonly AnnouncementTargetGroups $targetGroups,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/announcements/groups`: the groups the form offers as the target group.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		$editor = $this->guard->actionRefusal(action: 'announcement.manage') === null
			&& $this->announcements->isAuthor(userId: $user->getUID()) === true;
		if ($editor === false) {
			return new JSONResponse(['error' => 'Only announcement editors may list the target groups'], 403);
		}

		return new JSONResponse(['groups' => $this->targetGroups->all()]);
	}//end index()
}//end class
