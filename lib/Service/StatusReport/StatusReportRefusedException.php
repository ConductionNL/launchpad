<?php

/**
 * StatusReportRefusedException
 *
 * A status report that cannot be made as asked (REQ-DWMS-004).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StatusReport
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

namespace OCA\LaunchPad\Service\StatusReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use RuntimeException;

/**
 * Refuses a status report, carrying the HTTP status to answer with.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-004-a-dated-status-report-with-a-health-rating
 */
class StatusReportRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message    Why.
	 * @param int    $statusCode The HTTP status.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.1
	 */
	public function __construct(string $message, public readonly int $statusCode=400) {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
