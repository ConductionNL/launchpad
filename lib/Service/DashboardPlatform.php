<?php

/**
 * DashboardPlatform
 *
 * The Nextcloud services DashboardService runs on: groups, the database, config and l10n.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;

/**
 * The Nextcloud services DashboardService runs on: groups, the database, config and l10n.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardPlatform {
	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groupManager Group manager (used for `isAdmin` only — group membership lookups go
	 *                                    through the routing resolver per REQ-TMPL-013).
	 * @param IDBConnection $db DB connection (for the transactional default flip — REQ-DASH-015).
	 * @param IConfig       $config Nextcloud per-user preference storage.
	 * @param IFactory      $l10nFactory L10N factory used to build the "My copy of {name}" default fork name
	 *                                   (REQ-DASH-020).
	 */
	public function __construct(
		public readonly IGroupManager $groupManager,
		public readonly IDBConnection $db,
		public readonly IConfig $config,
		public readonly IFactory $l10nFactory,
	) {
	}//end __construct()
}//end class
