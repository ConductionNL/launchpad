<?php

/**
 * AnnouncementReadController
 *
 * Seen and confirmed receipts of announcements (REQ-ANN-007, REQ-ANN-009):
 * - GET  /api/announcements/read-state       which ones the reader has seen, confirmed and closed
 * - POST /api/announcements/seen             the ones that came into view, batched after render
 * - POST /api/announcements/{uuid}/confirm   the check or the x (`via`), once
 * - POST /api/announcements/{uuid}/read      the check (kept for older clients)
 * - GET  /api/announcements/{uuid}/receipts  Seen and confirmed, for managers
 * - GET  /api/announcements/receipt-counts   seen and confirmed per announcement, for the tab's list
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The receipt endpoints of announcements.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReadController extends Controller {
	use AnswersAnnouncementRequests;

	/**
	 * Constructor.
	 *
	 * @param IRequest                      $request       The request.
	 * @param AnnouncementReadConfirmations $confirmations The reader's receipts.
	 * @param AnnouncementReceiptReport     $report        Seen and confirmed for managers.
	 * @param RequestGuard                  $guard         The session user and the action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementReadConfirmations $confirmations,
		private readonly AnnouncementReceiptReport $report,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/announcements/read-state: among what the reader sees, the
	 * uuids seen, confirmed and closed (x), and those whose receipts the
	 * reader may open. Asked beside the list, not after it.
	 *
	 * @return JSONResponse `{seen, confirmed, closed, receipts}`.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
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
	 * POST /api/announcements/seen: record `uuids` as seen by the reader,
	 * once each; the client sends it after render, never before.
	 *
	 * @return JSONResponse `{recorded}`, or 400.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	#[NoAdminRequired]
	public function seen(): JSONResponse {
		$uuids = $this->request->getParam(key: 'uuids', default: []);
		if (is_array($uuids) === false) {
			$uuids = [];
		}

		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				['recorded' => $this->confirmations->markSeen(userId: $userId, uuids: $uuids)]
			)
		);
	}//end seen()

	/**
	 * POST /api/announcements/{uuid}/confirm: the check (`via=check`) or
	 * the x (`via=x`) for the signed-in reader, once.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse `{uuid, confirmed, confirmedAt, confirmedVia}`, or 400 / 403 / 404.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	#[NoAdminRequired]
	public function confirmVia(string $uuid): JSONResponse {
		$via = (string) $this->request->getParam(key: 'via', default: AnnouncementReceipt::VIA_CHECK);

		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->confirmations->confirm(userId: $userId, uuid: $uuid, via: $via)
			)
		);
	}//end confirmVia()

	/**
	 * POST /api/announcements/{uuid}/read: the check, for older clients.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse `{uuid, confirmed, confirmedAt, confirmedVia}`, or 400 / 403 / 404.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	#[NoAdminRequired]
	public function confirm(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->confirmations->confirm(userId: $userId, uuid: $uuid, via: AnnouncementReceipt::VIA_CHECK)
			)
		);
	}//end confirm()

	/**
	 * GET /api/announcements/{uuid}/receipts: Seen and confirmed for one
	 * announcement, with names and departments; managers only.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse The report, or 403 / 404.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function receipts(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (string $userId): JSONResponse => new JSONResponse(
				$this->report->report(userId: $userId, uuid: $uuid)
			)
		);
	}//end receipts()

	/**
	 * GET /api/announcements/receipt-counts: per announcement of `uuids`,
	 * how many have seen and confirmed it; managers only (REQ-ANN-008).
	 *
	 * @return JSONResponse `{counts: {uuid: {seen, confirmed}}}`.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function counts(): JSONResponse {
		$uuids = $this->request->getParam(key: 'uuids', default: []);
		if (is_array($uuids) === false) {
			$uuids = [];
		}

		$uuids = array_slice(array_values(array_map('strval', $uuids)), 0, 500);

		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(
			run: fn (): JSONResponse => new JSONResponse(['counts' => (object) $this->report->countsFor(uuids: $uuids)])
		);
	}//end counts()
}//end class
