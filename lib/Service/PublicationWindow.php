<?php

/**
 * PublicationWindow
 *
 * The validated go-live and take-down times of one schedule request
 * (REQ-DASH-034, REQ-SCHEDUI-002), as `Y-m-d H:i:s` strings.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * A validated publication window.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
final class PublicationWindow {
	/**
	 * Constructor.
	 *
	 * @param string|null $publishAt   The go-live time, or null to leave the status as it is.
	 * @param string|null $unpublishAt The take-down time, or null for none.
	 */
	public function __construct(
		public readonly string|null $publishAt,
		public readonly string|null $unpublishAt,
	) {
	}//end __construct()
}//end class
