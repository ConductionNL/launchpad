<?php

/**
 * ReactionDirectory
 *
 * The app config and the user manager of the reaction service.
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
 * @spec openspec/specs/dashboard-reactions/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;
use OCP\IUserManager;

/**
 * The app config and the user manager of the reaction service.
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */
class ReactionDirectory {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig   $appConfig App config — admin settings.
	 * @param IUserManager $userManager Display name resolution for the reactors-by-emoji endpoint.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly IUserManager $userManager,
	) {
	}//end __construct()
}//end class
