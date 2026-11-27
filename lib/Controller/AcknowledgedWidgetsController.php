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
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
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

	/**
	 * Constructor.
	 *
	 * @param IRequest                        $request          The request.
	 * @param AcknowledgementServiceInterface $acknowledgements Lists the widgets with their counts.
	 */
	public function __construct(
		IRequest $request,
		private readonly AcknowledgementServiceInterface $acknowledgements,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Every widget that asks for confirmation, with its dashboard, counts and deadline.
	 *
	 * @return JSONResponse `{widgets: [...]}`; Nextcloud answers 403 without the setting.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		return new JSONResponse(['widgets' => $this->acknowledgements->listAcknowledged()]);
	}//end index()
}//end class
