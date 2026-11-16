<?php

/**
 * Contract of TemplateResyncService
 *
 * The public surface of TemplateResyncService that other components call, so they depend
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

/**
 * Contract of TemplateResyncService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TemplateResyncServiceInterface {
	/**
	 * Controller-facing entry point (REQ-RESYNC-001).
	 *
	 * Validates the template + strategy up front (before any dry-run or
	 * mutation work), then dispatches to the dry-run report, the
	 * synchronous apply, or the async job enqueue depending on `$dryRun`
	 * and the provisioned-copy count.
	 *
	 * @param int $templateId The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'` or `'merge'`.
	 * @param bool $dryRun When true, compute and return the plan
	 *                     without mutating anything.
	 * @param string $actingAdminId The acting admin's NC user ID (for the
	 *                              audit record).
	 *
	 * @return array<string, mixed> The dry-run report, the applied result,
	 *                              or the async-accepted envelope.
	 *
	 * @throws \InvalidArgumentException When the strategy is invalid or the
	 *                                  dashboard is not an admin template.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
	 */
	public function resync(
		int $templateId,
		string $strategy,
		bool $dryRun,
		string $actingAdminId,
	): array;

	/**
	 * Recompute the plan and apply it, per copy, inside its own
	 * transaction (REQ-RESYNC-005). Writes exactly one audit record for
	 * the whole run and notifies every user whose copy actually changed.
	 *
	 * Idempotent: when a copy's diff has no add/update/remove entries the
	 * copy is left untouched (no transaction opened, no notification
	 * queued) — re-running against an unchanged template is a no-op for
	 * every copy (REQ-RESYNC-005 "Re-sync is idempotent").
	 *
	 * Called both inline (small target groups, from {@see self::resync()})
	 * and from {@see TemplateResyncJob::run()} (large target groups).
	 *
	 * @param int $templateId The admin template's dashboard ID.
	 * @param string $strategy `'overwrite'` or `'merge'`.
	 * @param string $actingAdminId The acting admin's NC user ID.
	 *
	 * @return array<string, mixed> `{templateId, strategy, dryRun: false,
	 *                              async: false, totalCopies,
	 *                              affectedCount, copies: [...]}`.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-005-re-sync-is-idempotent-audited-async-capable-and-notifies-users
	 */
	public function applyResync(
		int $templateId,
		string $strategy,
		string $actingAdminId,
	): array;
}//end interface
