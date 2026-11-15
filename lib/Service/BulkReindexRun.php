<?php

/**
 * BulkReindexRun
 *
 * One bulk reindex, applied to every dashboard of the batch
 * (REQ-BULK-004).
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
 * The batch timestamp and the preview flag shared by every dashboard of
 * one bulk reindex.
 *
 * Built and filled by {@see BulkOperationService}; never leaves it.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkReindexRun {

	/**
	 * The batch `Y-m-d H:i:s` timestamp.
	 *
	 * @var string
	 */
	public string $now;

	/**
	 * When true, preview only.
	 *
	 * @var bool
	 */
	public bool $dryRun;
}//end class
