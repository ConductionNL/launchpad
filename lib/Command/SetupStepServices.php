<?php

/**
 * SetupStepServices
 *
 * The services `occ launchpad:setup` applies Steps 3 to 5 through: the demo
 * showcases (Step 3), the role assignments (Step 4) and the global footer
 * (Step 5). They are the services the wizard modal and the admin page use,
 * so a YAML config stores exactly what the browser would store.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\RoleService;

/**
 * The step services of the setup command, resolved together by DI.
 */
class SetupStepServices {
	/**
	 * Constructor.
	 *
	 * @param DemoShowcasesService $showcases Installs the demo showcases (Step 3).
	 * @param RoleService          $roles     Writes role assignments (Step 4).
	 * @param FooterService        $footer    Stores the global footer (Step 5).
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	public function __construct(
		public readonly DemoShowcasesService $showcases,
		public readonly RoleService $roles,
		public readonly FooterService $footer,
	) {
	}//end __construct()
}//end class
