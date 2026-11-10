<?php

/**
 * BulkMoveChange
 *
 * One validated bulk re-parent, applied to every dashboard of the batch
 * (REQ-BULK-002).
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
 * Target parent, the batch timestamp and the preview flag shared by every
 * dashboard of one bulk move.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkMoveChange {
	/**
	 * Constructor.
	 *
	 * @param string|null $parentUuid The new parent UUID (NULL ⇒ root).
	 * @param string $now The batch `Y-m-d H:i:s` timestamp.
	 * @param bool $dryRun When true, preview only.
	 */
	public function __construct(
		public readonly string|null $parentUuid,
		public readonly string $now,
		public readonly bool $dryRun,
	) {
	}//end __construct()
}//end class
