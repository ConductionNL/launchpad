<?php

/**
 * OrgNavigationSources
 *
 * The org navigation service and the admin settings it reads.
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
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\OrgNavigationService;

/**
 * The org navigation service and the admin settings it reads.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class OrgNavigationSources {
	/**
	 * Constructor.
	 *
	 * @param OrgNavigationService $service Tree storage + filter service.
	 * @param AdminSettingMapper   $settings Persistence layer for the position scalar.
	 */
	public function __construct(
		public readonly OrgNavigationService $service,
		public readonly AdminSettingMapper $settings,
	) {
	}//end __construct()

	/**
	 * The persisted rail position, or the default when it is unset or not
	 * one of the allowed positions (REQ-ONAV-004).
	 *
	 * @return string Always one of {@see AdminOrgNavigationController::ALLOWED_POSITIONS}.
	 *
	 * @spec openspec/specs/navigation-editor-org/spec.md
	 */
	public function position(): string {
		$raw = $this->settings->getValue(
			key: AdminOrgNavigationController::SETTING_KEY_POSITION,
			default: AdminOrgNavigationController::DEFAULT_POSITION
		);

		if (is_string($raw) === false
			|| in_array(needle: $raw, haystack: AdminOrgNavigationController::ALLOWED_POSITIONS, strict: true) === false
		) {
			return AdminOrgNavigationController::DEFAULT_POSITION;
		}

		return $raw;
	}//end position()
}//end class
