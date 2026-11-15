<?php

/**
 * WidgetPlatform
 *
 * The dashboard widget manager and the session of the widget service.
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
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Dashboard\IManager;
use OCP\IUserSession;

/**
 * The dashboard widget manager and the session of the widget service.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class WidgetPlatform {
	/**
	 * Constructor.
	 *
	 * @param IManager     $dashboardManager Dashboard manager interface.
	 * @param IUserSession $userSession User session interface.
	 */
	public function __construct(
		public readonly IManager $dashboardManager,
		public readonly IUserSession $userSession,
	) {
	}//end __construct()
}//end class
