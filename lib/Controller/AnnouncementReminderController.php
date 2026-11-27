<?php

/**
 * AnnouncementReminderController
 *
 * POST /api/announcements/{uuid}/remind: Send reminder on Seen and confirmed
 * (REQ-ANN-009), for announcement managers, at most once a day.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AnnouncementReminder;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The reminder endpoint of announcements.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReminderController extends Controller {
	use AnswersAnnouncementRequests;

	/**
	 * Constructor.
	 *
	 * @param IRequest             $request  The request.
	 * @param AnnouncementReminder $reminder Sends the reminder.
	 * @param RequestGuard         $guard    The session user and the action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementReminder $reminder,
		private readonly RequestGuard $guard,
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
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
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
}//end class
