<?php

/**
 * ReactionDirectory
 *
 * The app config, the user manager and the version rows of the reaction
 * service.
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

use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCP\IAppConfig;
use OCP\IUserManager;

/**
 * The app config, the user manager and the version rows of the reaction
 * service.
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */
class ReactionDirectory {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig   $appConfig App config — admin settings.
	 * @param IUserManager $userManager Display name resolution for the reactors-by-emoji endpoint.
	 * @param DashboardVersionMapper|null $versionMapper The saved versions, for who last changed a dashboard.
	 *
	 * @spec openspec/changes/reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly IUserManager $userManager,
		public readonly ?DashboardVersionMapper $versionMapper = null,
	) {
	}//end __construct()
}//end class
