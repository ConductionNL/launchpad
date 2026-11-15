<?php

/**
 * BulkStatusChange
 *
 * One validated bulk publication status change, applied to every
 * dashboard of the batch (REQ-BULK-003).
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
 * Target status, parsed publish time, the batch timestamp and the preview
 * flag shared by every dashboard of one bulk status change.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkStatusChange {
	/**
	 * Constructor
	 *
	 * @param string      $publicationStatus The target status enum value.
	 * @param string|null $publishAt         The parsed publish time, for `scheduled`.
	 * @param string      $now               The batch timestamp (`Y-m-d H:i:s`).
	 * @param bool        $dryRun            When true, preview only.
	 */
	public function __construct(
		public readonly string $publicationStatus,
		public readonly ?string $publishAt,
		public readonly string $now,
		public readonly bool $dryRun,
	) {
	}//end __construct()
}//end class
