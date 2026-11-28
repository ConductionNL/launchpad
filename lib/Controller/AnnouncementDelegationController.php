<?php

/**
 * AnnouncementDelegationController
 *
 * GET /api/announcements/delegation: the groups that hold Launchpad's
 * announcements setting through Nextcloud's admin delegation, for the
 * read-only "Manage announcements" row of the Roles tab
 * (announcements-managed-in-settings D1). Administrators only.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AnnouncementDelegation;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Who holds the announcements setting.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/tasks.md#task-3
 */
class AnnouncementDelegationController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest               $request    The request.
	 * @param AnnouncementDelegation $delegation The delegated groups.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementDelegation $delegation,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The delegated groups, and whether delegation can be read here.
	 *
	 * @return JSONResponse `{available, groups: [{id, displayName}]}`.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/tasks.md#task-3
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		return new JSONResponse($this->delegation->groups());
	}//end index()
}//end class
