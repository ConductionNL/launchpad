<?php

/**
 * AnnouncementReadController
 *
 * "I have read this" on an announcement and the author's read
 * confirmations (REQ-ANN-007):
 * - GET  /api/announcements/read-state       which ones the reader confirmed, and may report on
 * - POST /api/announcements/{uuid}/read      the signed-in reader confirms, once
 * - GET  /api/announcements/{uuid}/receipts  who confirmed and who has not yet
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\ReadReceiptPeople;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The read confirmation endpoints of announcements.
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class AnnouncementReadController extends Controller {
	use AnswersAnnouncementRequests;

	/**
	 * Constructor.
	 *
	 * @param IRequest                      $request       The request.
	 * @param AnnouncementReadConfirmations $confirmations The read confirmations.
	 * @param ReadReceiptPeople             $people        Names and departments on the report rows.
	 * @param RequestGuard                  $guard         The session user and the action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementReadConfirmations $confirmations,
		private readonly ReadReceiptPeople $people,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/announcements/read-state: the uuids among what the reader sees
	 * that the reader confirmed, and those whose read confirmations the
	 * reader may open. Asked beside the list, not after it.
	 *
	 * @return JSONResponse `{confirmed, receipts}`.
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	#[NoAdminRequired]
	public function state(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->confirmations->stateOfVisible(userId: $userId)
			)
		);
	}//end state()

	/**
	 * POST /api/announcements/{uuid}/read: "I have read this" for the
	 * signed-in reader, once.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse `{uuid, confirmed, confirmedAt}`, or 400 / 403 / 404.
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	#[NoAdminRequired]
	public function confirm(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->confirmations->confirm(userId: $userId, uuid: $uuid)
			)
		);
	}//end confirm()

	/**
	 * GET /api/announcements/{uuid}/receipts: who confirmed and who has not
	 * yet, for the author or an administrator.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse The report, or 400 / 403 / 404.
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	#[NoAdminRequired]
	public function receipts(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->people->withPeople(
					report: $this->confirmations->report(userId: $userId, uuid: $uuid),
					viewerId: $userId
				)
			)
		);
	}//end receipts()
}//end class
