<?php

/**
 * Contract of AcknowledgementService
 *
 * The public surface of AcknowledgementService that other components call, so they depend
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

use OCA\LaunchPad\Db\Acknowledgement;

/**
 * Contract of AcknowledgementService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AcknowledgementServiceInterface {
	/**
	 * Record an acknowledgement receipt for `$userId` at `$contentVersion`.
	 * Idempotent (REQ-ACK-003): a repeated acknowledgement of the same
	 * `(announcementKey, userId, contentVersion)` never inserts a second
	 * row and returns the original receipt. An activity event is emitted
	 * for the first insert only (REQ-ACK-006).
	 *
	 * The caller (controller) is responsible for enforcing that `$userId`
	 * is the authenticated user — this service never derives the acting
	 * user itself (ADR-005, no IDOR).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The acknowledging user ID.
	 * @param int $contentVersion The content version being acknowledged.
	 *
	 * @return Acknowledgement The stored (or pre-existing) receipt.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function acknowledge(
		string $announcementKey,
		string $userId,
		int $contentVersion,
	): Acknowledgement;

	/**
	 * Whether a user may acknowledge an announcement: only its live
	 * audience may, that is the members of the groups its template routes
	 * to, or, without group routing, the recipients holding a copy, and in
	 * both cases the recipients of a share of a personal dashboard holding
	 * it. An unknown announcement has no audience. Backs the acknowledge guard
	 * (REQ-ACK-003, ADR-005).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId          The user to look for.
	 *
	 * @return bool True when the user is in the audience.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function isAllowedToAcknowledge(string $announcementKey, string $userId): bool;

	/**
	 * Return the current user's outstanding mandatory items across their
	 * own dashboards and the personal dashboards shared with them, one per
	 * announcement, limited to announcements still addressed to them: a
	 * copy kept after leaving the audience cannot be acknowledged, so it is
	 * not pending. REQ-ACK-002 (outstanding-count indicator).
	 *
	 * @param string $userId The current user ID.
	 *
	 * @return array{count: int, items: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function getPending(string $userId): array;

	/**
	 * The current content version of an announcement for a user: the
	 * version on its template placement, or, when the announcement has no
	 * template (any more), the version on the user's own copy. Null when no
	 * placement carries the key.
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId          The user whose copy counts without a template.
	 *
	 * @return int|null The current content version, or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function currentContentVersion(string $announcementKey, string $userId): ?int;

	/**
	 * Build the audience-scoped read-receipt report for an announcement.
	 * The audience is resolved LIVE from the source template's group
	 * routing via {@see IGroupManager} (design D5) so newly added members
	 * automatically appear as pending. Pending is computed as
	 * `audience − acknowledged(currentVersion)`. REQ-ACK-004.
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return array{
	 *   announcementKey: string,
	 *   contentVersion: int,
	 *   deadline: string|null,
	 *   overdue: bool,
	 *   acknowledgedCount: int,
	 *   pendingCount: int,
	 *   pending: array<int, string>,
	 *   acknowledged: array<int, array{userId: string, acknowledgedAt: string|null}>,
	 *   rows: array<int, array{userId: string, status: string, acknowledgedAt: string|null}>
	 * }
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When no placement carries the key.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function report(string $announcementKey): array;

	/**
	 * Resolve the owning user id of the announcement's blueprint dashboard,
	 * used by the controller to authorize the requirement-setting and the
	 * read-receipt report to the template owner (REQ-ACK-004, ADR-005).
	 * Returns null when the announcement is unknown or its dashboard row
	 * is missing.
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return string|null The owning user id, or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function resolveOwnerUserId(string $announcementKey): ?string;
}//end interface
