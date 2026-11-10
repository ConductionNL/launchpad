<?php

/**
 * BulkDeleteChange
 *
 * One bulk delete, applied to every dashboard of the batch
 * (REQ-BULK-001).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The acting user, the preview flag and the cascade choice shared by every
 * dashboard of one bulk delete.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkDeleteChange {
	/**
	 * Constructor.
	 *
	 * @param string $userId The acting NC user ID.
	 * @param bool $dryRun When true, preview only.
	 * @param bool $cascade When true, recurse into children.
	 */
	public function __construct(
		public readonly string $userId,
		public readonly bool $dryRun,
		public readonly bool $cascade,
	) {
	}//end __construct()
}//end class
