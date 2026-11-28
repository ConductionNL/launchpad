<?php

/**
 * AnswersAnnouncementRequests
 *
 * Runs an announcement request as the session user and turns the refusals
 * of the announcement services into their HTTP status: not found 404, not
 * allowed 403, bad input 400. Shared by the announcement controllers.
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
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * The session user and the refusal mapping of the announcement endpoints.
 *
 * The using class holds the request guard as `$this->guard`.
 *
 * @property-read RequestGuard $guard
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
trait AnswersAnnouncementRequests {

	/**
	 * Run a step as the session user, answering a refusal with its status.
	 *
	 * @param callable $run `(string $userId): JSONResponse`.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	private function asUser(callable $run): JSONResponse {
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
}//end trait
