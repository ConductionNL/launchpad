<?php

/**
 * DashboardDraft
 *
 * One edit-mode Save: who saves which dashboard, with which grid columns and
 * which full placement list (edit-mode-draft-and-save, design D2).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * One edit-mode Save.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
class DashboardDraft {
	/**
	 * Constructor.
	 *
	 * @param int                        $dashboardId The dashboard.
	 * @param string                     $userId      The person saving.
	 * @param int|null                   $gridColumns The draft's grid columns, or null to keep them.
	 * @param list<array<string, mixed>> $placements  The full placement list of the draft.
	 */
	public function __construct(
		public readonly int $dashboardId,
		public readonly string $userId,
		public readonly ?int $gridColumns,
		public readonly array $placements,
	) {
	}//end __construct()
}//end class
