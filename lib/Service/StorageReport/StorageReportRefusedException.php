<?php

/**
 * StorageReportRefusedException
 *
 * A storage report the caller may not have (REQ-DWMS-010).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StorageReport
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
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\StorageReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use RuntimeException;

/**
 * Refuses the storage report, carrying the HTTP status to answer with.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageReportRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message    Why.
	 * @param int    $statusCode The HTTP status.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.4
	 */
	public function __construct(string $message, public readonly int $statusCode=403) {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
