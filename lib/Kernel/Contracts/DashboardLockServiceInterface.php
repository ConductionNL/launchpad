<?php

/**
 * Contract of DashboardLockService
 *
 * The public surface of DashboardLockService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

use OCA\LaunchPad\Db\DashboardLock;

/**
 * Contract of DashboardLockService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface DashboardLockServiceInterface {
	/**
	 * Acquire (or refresh) the editing lock on a dashboard.
	 *
	 * Re-entrant for the same user — calling `acquireLock` while the
	 * caller already owns the lock returns the existing row with its
	 * heartbeat bumped, instead of throwing a conflict (REQ-LOCK-001
	 * "Same user with two browser tabs").
	 *
	 * Stale locks (whose `updatedAt + 15 min` is in the past) are
	 * deleted inline before the conflict check, so an expired lock
	 * never blocks a fresh acquire.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $userId The acquiring user's ID.
	 *
	 * @return DashboardLock The active lock owned by `$userId`.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the dashboard UUID does not
	 *                               resolve to an existing dashboard —
	 *                               propagated unchanged so the
	 *                               controller can map it to HTTP 404.
	 * @throws \OCA\LaunchPad\Exception\LockConflictException When another user already holds an
	 *                               active lock on the dashboard.
	 * @throws \OCA\LaunchPad\Exception\LockForbiddenException When the caller has no view access
	 *                                to the dashboard (C3 fix).
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	public function acquireLock(
		string $dashboardUuid,
		string $userId,
	): DashboardLock;

	/**
	 * Refresh the lock by bumping `updatedAt` (the "heartbeat").
	 *
	 * Owner-only — a non-owner caller MUST be rejected with
	 * `LockForbiddenException`. A heartbeat against an expired or
	 * missing lock raises `LockNotFoundException` so the client's UI
	 * can fall back to the "lock lost" path.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $userId The calling user's ID.
	 *
	 * @return DashboardLock The refreshed lock.
	 *
	 * @throws \OCA\LaunchPad\Exception\LockNotFoundException When no active lock exists.
	 * @throws \OCA\LaunchPad\Exception\LockForbiddenException When the caller is not the owner, or
	 *                                when the caller has no view access
	 *                                (C3 fix).
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	public function heartbeat(
		string $dashboardUuid,
		string $userId,
	): DashboardLock;

	/**
	 * Release the editing lock on a dashboard.
	 *
	 * Owner-only by default. Admins MAY release any user's lock when
	 * `$allowAdminOverride` is `true` — this is wired by the
	 * controller for the standard `DELETE` verb so admins can clear
	 * stuck sessions without going through the dedicated
	 * `force-release` endpoint.
	 *
	 * Idempotent: releasing a non-existent lock is a no-op (returns
	 * silently). REQ-LOCK-003 "Release non-existent lock".
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $userId The calling user's ID.
	 * @param bool $allowAdminOverride When true, an admin caller may
	 *                                 release any user's lock.
	 *
	 * @return void
	 *
	 * @throws \OCA\LaunchPad\Exception\LockForbiddenException When the caller is neither the
	 *                                owner nor an admin (when allowed).
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) The contract mirrors the existing signature.
	 */
	public function releaseLock(
		string $dashboardUuid,
		string $userId,
		bool $allowAdminOverride = true,
	): void;

	/**
	 * Get the current active lock state for a dashboard.
	 *
	 * Performs inline cleanup before the read so stale rows are never
	 * leaked to clients. Returns `null` when no active lock exists.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 *
	 * @return DashboardLock|null The active lock or null.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	public function getLockState(string $dashboardUuid): ?DashboardLock;

	/**
	 * Admin-only: release the lock for whoever holds it.
	 *
	 * After this call the dashboard is in an unlocked state — the
	 * admin may then call `acquireLock()` if they want to take
	 * ownership themselves (REQ-LOCK-006 design D4).
	 *
	 * Idempotent: if no lock exists the call still succeeds and is
	 * still logged (so admins always see their action recorded).
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $adminUserId The admin's user ID.
	 *
	 * @return void
	 *
	 * @throws \OCA\LaunchPad\Exception\LockForbiddenException When the caller is not an admin.
	 *
	 * @spec openspec/specs/dashboard-locking/spec.md
	 */
	public function forceRelease(
		string $dashboardUuid,
		string $adminUserId,
	): void;
}//end interface
