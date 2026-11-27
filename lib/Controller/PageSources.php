<?php

/**
 * PageSources
 *
 * What the main page reads: initial state, widgets, dashboards and admin templates.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Services\IInitialState;

/**
 * What the main page reads: initial state, widgets, dashboards and admin templates.
 *
 * @spec openspec/specs/dashboards/spec.md
 * @spec openspec/changes/archive/2026-11-25-dashboard-first-load-from-initial-state/tasks.md#task-2
 */
class PageSources {
	/**
	 * Constructor.
	 *
	 * @param IInitialState        $initialState The Nextcloud initial-state service.
	 * @param WidgetService        $widgetService Available-widgets descriptor formatter.
	 * @param PageDashboards       $dashboards The dashboard service (listing, resolver and the
	 *                                         `allow_user_dashboards` flag — REQ-ASET-003) and the
	 *                                         first-load builder.
	 * @param AdminTemplateService $adminTemplateService Primary-group routing resolver (REQ-TMPL-012,
	 *                                                   REQ-TMPL-013).
	 * @param WorkspaceBootReads|null $bootReads The tiles, pending acknowledgements and default-dashboard pin
	 *                                           the workspace view takes from the initial state.
	 * @param ShellBootReads          $shell     The kept runtime manifest, setup status and walkthrough
	 *                                           preferences the app shell takes from the initial state.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
	 */
	public function __construct(
		public readonly IInitialState $initialState,
		public readonly WidgetService $widgetService,
		public readonly PageDashboards $dashboards,
		public readonly AdminTemplateService $adminTemplateService,
		public readonly ?WorkspaceBootReads $bootReads=null,
		public readonly ShellBootReads $shell=new ShellBootReads(),
		public readonly ?WorkspaceApiReads $apiReads=null,
	) {
	}//end __construct()
}//end class
