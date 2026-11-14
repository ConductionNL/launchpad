<?php

/**
 * DashboardLockApiController
 *
 * HTTP entry point for dashboard editing-lock management. Implements
 * the four REST verbs on a single lock resource URL plus one admin
 * action, per REQ-LOCK-001..008 and design D3 / D4:
 *
 *  - `POST   /api/dashboards/{uuid}/lock`               acquire (re-entrant for same user)
 *  - `PUT    /api/dashboards/{uuid}/lock`               heartbeat
 *  - `DELETE /api/dashboards/{uuid}/lock`               release
 *  - `GET    /api/dashboards/{uuid}/lock`               query current state
 *  - `POST   /api/dashboards/{uuid}/lock/force-release` admin override
 *
 * All endpoints carry `#[NoAdminRequired]` — the admin guard for
 * `force-release` is enforced inline via `IGroupManager::isAdmin`
 * inside the controller (and again inside the service, defence in
 * depth).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\LockConflictException;
use OCA\LaunchPad\Exception\LockForbiddenException;
use OCA\LaunchPad\Exception\LockNotFoundException;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for dashboard editing-lock endpoints (REQ-LOCK-001..008).
 */
class DashboardLockApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest          $request The HTTP request.
	 * @param LockApiCaller     $caller The lock sources, session and action check.
	 * @param PermissionService $permissionService Dashboard permission resolver.
	 * @param string|null       $userId The calling user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly LockApiCaller $caller,
		private readonly PermissionService $permissionService,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Acquire (or refresh) the lock for the given dashboard.
	 *
	 * Re-entrant for the same user — a second tab MUST receive HTTP
	 * 200 with the refreshed lock instead of HTTP 409 (REQ-LOCK-001).
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 200 with the lock object on success,
	 *                      404 when the dashboard UUID is unknown,
	 *                      409 with the existing lock on conflict.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	#[NoAdminRequired]
	public function acquire(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-lock.acquire');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$lock = $this->caller->sources->lockService->acquireLock(
				dashboardUuid: $uuid,
				userId: $this->userId
			);
			return new JSONResponse(
				data: $lock->jsonSerialize(),
				statusCode: Http::STATUS_OK
			);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (LockForbiddenException $e) {
			// C3 fix: caller lacks view access to this dashboard.
			return $this->lockForbidden(exception: $e);
		} catch (LockConflictException $e) {
			// M2: strip userId from conflict response — callers need the
			// displayName to show "X is editing" but should not receive
			// the internal user identifier of a third party.
			return new JSONResponse(
				data: [
					'error' => $e->getMessage(),
					'code' => LockConflictException::ERROR_CODE,
					'lock' => $e->getExistingLock()->jsonSerializeConflict(),
				],
				statusCode: Http::STATUS_CONFLICT
			);
		}//end try
	}//end acquire()

	/**
	 * Refresh the lock (heartbeat). Owner-only.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 200 with the refreshed lock; 404 when no
	 *                      active lock exists; 403 on owner mismatch.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	#[NoAdminRequired]
	public function heartbeat(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-lock.heartbeat');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$lock = $this->caller->sources->lockService->heartbeat(
				dashboardUuid: $uuid,
				userId: $this->userId
			);
			return new JSONResponse(
				data: $lock->jsonSerialize(),
				statusCode: Http::STATUS_OK
			);
		} catch (LockNotFoundException $e) {
			return new JSONResponse(
				data: [
					'error' => $e->getMessage(),
					'code' => LockNotFoundException::ERROR_CODE,
				],
				statusCode: Http::STATUS_NOT_FOUND
			);
		} catch (LockForbiddenException $e) {
			return $this->lockForbidden(exception: $e);
		}//end try
	}//end heartbeat()

	/**
	 * Release the lock. Owner-or-admin.
	 *
	 * Idempotent — releasing a non-existent lock returns 204 (the
	 * caller's intent "no longer holding the lock" is satisfied).
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 204 on success; 403 on permission mismatch.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	#[NoAdminRequired]
	public function release(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-lock.release');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->caller->sources->lockService->releaseLock(
				dashboardUuid: $uuid,
				userId: $this->userId,
				allowAdminOverride: true
			);
			return new JSONResponse(
				data: [],
				statusCode: Http::STATUS_NO_CONTENT
			);
		} catch (LockForbiddenException $e) {
			return $this->lockForbidden(exception: $e);
		}
	}//end release()

	/**
	 * Query the current lock state.
	 *
	 * Returns the lock object when active, or HTTP 404 when none
	 * exists. Stale rows are scrubbed inline by the service before
	 * the response.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 200 with the lock or 404 when none.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	#[NoAdminRequired]
	public function get(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-lock.get');
		if ($refusal !== null) {
			return $refusal;
		}

		// H1: guard against identity leak — any authed user could enumerate
		// lock holders for arbitrary UUIDs; return 404 on no-view-access
		// (same shape as "no lock") to avoid leaking dashboard existence.
		try {
			$dashboard = $this->caller->sources->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $this->noLockFound();
		}

		if ($this->permissionService->canViewDashboard(
			userId: $this->userId,
			dashboardId: (int)$dashboard->getId()
		) === false
		) {
			// Return 404 not 403 to avoid leaking dashboard existence.
			return $this->noLockFound();
		}

		$lock = $this->caller->sources->lockService->getLockState(dashboardUuid: $uuid);
		if ($lock === null) {
			return $this->noLockFound();
		}

		return new JSONResponse(
			data: $lock->jsonSerialize(),
			statusCode: Http::STATUS_OK
		);
	}//end get()

	/**
	 * Admin-only: force-release any user's lock (REQ-LOCK-006, design
	 * D4). The admin may then `acquire` normally if they want to take
	 * the lock themselves.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse 200 on success; 403 when caller is not admin.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	#[NoAdminRequired]
	public function forceRelease(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-lock.force-release');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->caller->sources->lockService->forceRelease(
				dashboardUuid: $uuid,
				adminUserId: $this->userId
			);
			return new JSONResponse(
				data: ['status' => 'ok'],
				statusCode: Http::STATUS_OK
			);
		} catch (LockForbiddenException $e) {
			return $this->lockForbidden(exception: $e);
		}
	}//end forceRelease()

	/**
	 * The caller guard: no user ID → 401, then the ADR-023 action check; NULL to proceed.
	 *
	 * @param string $action The action identifier to require.
	 *
	 * @return JSONResponse|null The refusal, or NULL.
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		return $this->caller->guard->actionRefusal(action: $action);
	}//end actionRefusal()

	/**
	 * The 403 answer for a lock the caller may not touch.
	 *
	 * @param LockForbiddenException $exception The refusal.
	 *
	 * @return JSONResponse
	 */
	private function lockForbidden(LockForbiddenException $exception): JSONResponse {
		return new JSONResponse(
			data: [
				'error' => $exception->getMessage(),
				'code' => LockForbiddenException::ERROR_CODE,
			],
			statusCode: Http::STATUS_FORBIDDEN
		);
	}//end lockForbidden()

	/**
	 * The 404 answer of the lock query; the same for no lock, an unknown
	 * dashboard and one the caller may not view, so nothing leaks.
	 *
	 * @return JSONResponse
	 */
	private function noLockFound(): JSONResponse {
		return new JSONResponse(
			data: [
				'error' => 'Lock not found',
				'code' => LockNotFoundException::ERROR_CODE,
			],
			statusCode: Http::STATUS_NOT_FOUND
		);
	}//end noLockFound()
}//end class
