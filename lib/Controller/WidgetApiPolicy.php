<?php

/**
 * WidgetApiPolicy
 *
 * The role feature check and the log of the widget API.
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
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use Psr\Log\LoggerInterface;

/**
 * The role feature check and the log of the widget API.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class WidgetApiPolicy {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionService $roleFeaturePerm Role-feature filter (REQ-RFP-001..010).
	 * @param LoggerInterface              $logger PSR-3 logger for role-denial audit entries (Task 4).
	 */
	public function __construct(
		public readonly RoleFeaturePermissionService $roleFeaturePerm,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
