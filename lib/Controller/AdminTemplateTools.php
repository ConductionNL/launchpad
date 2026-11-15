<?php

/**
 * AdminTemplateTools
 *
 * What the admin API manages templates with: templates, resync, export and import.
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

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\TemplateResyncService;

/**
 * What the admin API manages templates with: templates, resync, export and import.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class AdminTemplateTools {
	/**
	 * Constructor.
	 *
	 * @param AdminTemplateService  $templateService The admin template service.
	 * @param TemplateResyncService $resyncService Admin template re-sync orchestrator (REQ-RESYNC-001..005).
	 * @param ExportService         $exportService ZIP export service (REQ-EXIM-001..003).
	 * @param ImportService         $importService ZIP import service (REQ-EXIM-004..008).
	 */
	public function __construct(
		public readonly AdminTemplateService $templateService,
		public readonly TemplateResyncService $resyncService,
		public readonly ExportService $exportService,
		public readonly ImportService $importService,
	) {
	}//end __construct()
}//end class
