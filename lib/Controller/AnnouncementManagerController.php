<?php

/**
 * AnnouncementManagerController
 *
 * Endpoints for the holders of Launchpad's announcements setting:
 * - POST /api/announcements/{uuid}/remind  Send reminder on Seen and confirmed
 *   (REQ-ANN-009), at most once a day
 * - GET  /api/acknowledgements/widgets     the widgets that ask for
 *   confirmation, for the Announcements tab (REQ-ACK-008)
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementReminder;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The announcement managers' reminder and widgets list.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementManagerController extends Controller {
	use AnswersAnnouncementRequests;

	/**
	 * Constructor.
	 *
	 * @param IRequest                        $request          The request.
	 * @param AnnouncementReminder            $reminder         Sends the reminder.
	 * @param RequestGuard                    $guard            The session user and the action check.
	 * @param AcknowledgementServiceInterface $acknowledgements Lists the widgets that ask for confirmation.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementReminder $reminder,
		private readonly RequestGuard $guard,
		private readonly AcknowledgementServiceInterface $acknowledgements,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Send reminder to everyone in the audience who has not confirmed.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse `{uuid, sent, remindedAt}`, or 400 / 403 / 404.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function remind(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->reminder->remind(userId: $userId, uuid: $uuid)
			)
		);
	}//end remind()

	/**
	 * Every widget that asks for confirmation, with its dashboard, counts and deadline.
	 *
	 * @return JSONResponse `{widgets: [...]}`, or 401 / 403.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function widgets(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (): JSONResponse => new JSONResponse(['widgets' => $this->acknowledgements->listAcknowledged()])
		);
	}//end widgets()
}//end class
