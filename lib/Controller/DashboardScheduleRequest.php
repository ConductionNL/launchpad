<?php

/**
 * DashboardScheduleRequest
 *
 * The dashboard and the publish / take-down times of one schedule request.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * The dashboard and the publish / take-down times of one schedule request.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
final class DashboardScheduleRequest {
	/**
	 * Constructor.
	 *
	 * @param string      $uuid        The dashboard UUID from the URL.
	 * @param string|null $publishAt   The publication time, if any.
	 * @param string|null $unpublishAt The take-down time, if any (REQ-SCHEDUI-002).
	 */
	public function __construct(
		public readonly string $uuid,
		public readonly ?string $publishAt,
		public readonly ?string $unpublishAt,
	) {
	}//end __construct()
}//end class
