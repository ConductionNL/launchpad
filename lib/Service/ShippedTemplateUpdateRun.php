<?php

/**
 * ShippedTemplateUpdateRun
 *
 * One update of an installed shipped template.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * One ShippedTemplateUpdateService::update(): the shipped template id and
 * its installed template. Built by update() and never handed out.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
final class ShippedTemplateUpdateRun {
	/**
	 * Constructor
	 *
	 * @param string                   $templateId The shipped template id.
	 * @param InstalledShippedTemplate $installed  Its definition and installed dashboard.
	 */
	public function __construct(
		public readonly string $templateId,
		public readonly InstalledShippedTemplate $installed,
	) {
	}//end __construct()
}//end class
