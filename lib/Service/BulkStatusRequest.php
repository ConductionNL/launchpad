<?php

/**
 * BulkStatusRequest
 *
 * The caller-supplied part of a bulk publication status change
 * (REQ-BULK-003): the target status, the requested publish time and the
 * preview flag.
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
 * Target status, requested publish time and preview flag of a bulk status
 * change. BulkOperationService validates the status and parses the time.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkStatusRequest {
	/**
	 * Constructor
	 *
	 * @param string      $publicationStatus The target status enum value.
	 * @param string|null $publishAt         Requested publish time (required for `scheduled`).
	 * @param bool        $dryRun            When true, preview only.
	 */
	public function __construct(
		public readonly string $publicationStatus,
		public readonly ?string $publishAt,
		public readonly bool $dryRun,
	) {
	}//end __construct()
}//end class
