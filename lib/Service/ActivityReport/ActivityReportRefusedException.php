<?php

/**
 * ActivityReportRefusedException
 *
 * Why an activity report was not given (REQ-DWMS-005).
 *
 * @category Exception
 * @package  OCA\LaunchPad\Service\ActivityReport
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

namespace OCA\LaunchPad\Service\ActivityReport;

use RuntimeException;

/**
 * An activity report the caller may not have, with the HTTP status it answers.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-005-activity-reporting-names-its-purpose
 */
class ActivityReportRefusedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message    Why, in a sentence the screen shows.
	 * @param int    $statusCode The HTTP status.
	 * @param bool   $disabled   Whether the refusal is that activity reporting is off.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.4
	 */
	public function __construct(
		string $message,
		public readonly int $statusCode=403,
		public readonly bool $disabled=false,
	) {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
