<?php

/**
 * AdminSettingsTools
 *
 * What the admin API manages settings with: settings, the footer, the setup wizard and groups.
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

use OCA\LaunchPad\Kernel\Contracts\FooterServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\SetupWizardServiceInterface;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCP\IGroupManager;

/**
 * What the admin API manages settings with: settings, the footer, the setup wizard and groups.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class AdminSettingsTools {
	/**
	 * Constructor.
	 *
	 * @param AdminSettingsService $settingsService The admin settings service.
	 * @param FooterServiceInterface        $footerService Global footer settings + sanitiser (REQ-FTR-001..010).
	 * @param SetupWizardServiceInterface   $setupWizardService Setup-wizard orchestrator (REQ-WIZ-001..011).
	 * @param IGroupManager        $groupManager The Nextcloud group manager.
	 */
	public function __construct(
		public readonly AdminSettingsService $settingsService,
		public readonly FooterServiceInterface $footerService,
		public readonly SetupWizardServiceInterface $setupWizardService,
		public readonly IGroupManager $groupManager,
	) {
	}//end __construct()
}//end class
