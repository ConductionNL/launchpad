<?php

/**
 * AnnouncementReadController
 *
 * "I have read this" on an announcement and the author's read
 * confirmations (REQ-ANN-007):
 * - POST /api/announcements/{uuid}/read      the signed-in reader confirms, once
 * - GET  /api/announcements/{uuid}/receipts  who confirmed and who has not yet
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Closure;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The read confirmation endpoints of announcements.
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class AnnouncementReadController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                      $request       The request.
	 * @param AnnouncementReadConfirmations $confirmations The read confirmations.
	 * @param RequestGuard                  $guard         The session user and the action check.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementReadConfirmations $confirmations,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

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
				$this->confirmations->report(userId: $userId, uuid: $uuid)
			)
		);
	}//end receipts()

	/**
	 * Run a step as the session user, mapping a refusal to its status.
	 *
	 * @param Closure $run Takes the user id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	private function asUser(Closure $run): JSONResponse {
		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		try {
			return $run($user->getUID());
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Announcement not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['status' => 'error', 'error' => 'invalid_argument', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end asUser()
}//end class
