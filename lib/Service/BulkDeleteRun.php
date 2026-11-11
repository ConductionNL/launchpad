<?php

/**
 * BulkDeleteRun
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
 * Built and filled by {@see BulkOperationService}; never leaves it.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkDeleteRun {

	/**
	 * The acting NC user ID.
	 *
	 * @var string
	 */
	public string $userId;

	/**
	 * When true, preview only.
	 *
	 * @var bool
	 */
	public bool $dryRun;

	/**
	 * When true, recurse into children.
	 *
	 * @var bool
	 */
	public bool $cascade;
}//end class
