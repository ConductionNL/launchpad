<?php

/**
 * AcknowledgedWidgetsController
 *
 * GET /api/acknowledgements/widgets: the "Widgets that ask for confirmation"
 * list of the Announcements tab (REQ-ACK-008), for holders of Launchpad's
 * announcements setting.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AcknowledgedWidgets;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The list of widgets that ask readers to confirm.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 */
class AcknowledgedWidgetsController extends Controller {
	use AnswersAnnouncementRequests;

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request The request.
	 * @param AcknowledgedWidgets $widgets The list.
	 * @param RequestGuard        $guard   The session user and the action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AcknowledgedWidgets $widgets,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Every widget that asks for confirmation, with its dashboard, counts and deadline.
	 *
	 * @return JSONResponse `{widgets: [...]}`, or 401 / 403.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (): JSONResponse => new JSONResponse(['widgets' => $this->widgets->list()])
		);
	}//end index()
}//end class
