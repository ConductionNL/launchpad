<?php

/**
 * DemoSeedPreferences
 *
 * The per-user LaunchPad preferences a demo seed sets: which dashboard
 * opens first, and whether the getting-started tour was seen.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\IConfig;

/**
 * Set and clear the demo users' LaunchPad preferences.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedPreferences {
	/**
	 * The user preference that records which version of the getting-started
	 * tour a user completed (manifest `walkthrough.completionConfigKey`, stored
	 * by PreferencesController with its `pref_` prefix).
	 *
	 * @var string
	 */
	public const WALKTHROUGH_PREF_KEY = 'pref_walkthrough_completed_version';

	/**
	 * The user preference holding the dashboard that opens first; the key the
	 * dashboard resolver reads as its pinned default.
	 *
	 * @var string
	 */
	public const DEFAULT_DASHBOARD_PREF_KEY = 'default_dashboard_uuid';

	/**
	 * The user preference holding the dashboard opened last.
	 *
	 * @var string
	 */
	public const ACTIVE_DASHBOARD_PREF_KEY = 'active_dashboard_uuid';

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The user preferences.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Make a dashboard the one that opens first for a user.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The dashboard UUID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function setDefaultDashboard(string $userId, string $uuid): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::DEFAULT_DASHBOARD_PREF_KEY, $uuid);
	}//end setDefaultDashboard()

	/**
	 * Record that a user has seen the getting-started tour, so it does not
	 * cover the demo dashboards.
	 *
	 * @param string $userId  The user.
	 * @param string $version The tour version seen.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function markTourSeen(string $userId, string $version): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::WALKTHROUGH_PREF_KEY, $version);
	}//end markTourSeen()

	/**
	 * Clear a user's dashboard choices and tour progress.
	 *
	 * @param string $userId The user.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function clear(string $userId): void {
		foreach ([self::DEFAULT_DASHBOARD_PREF_KEY, self::ACTIVE_DASHBOARD_PREF_KEY, self::WALKTHROUGH_PREF_KEY] as $key) {
			$this->config->deleteUserValue($userId, Application::APP_ID, $key);
		}
	}//end clear()
}//end class
