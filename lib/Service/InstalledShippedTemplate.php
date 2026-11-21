<?php

/**
 * InstalledShippedTemplate
 *
 * A shipped template definition and the dashboard it is installed as.
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

use OCA\LaunchPad\Db\Dashboard;

/**
 * The shipped definition of a template together with the template
 * dashboard it is installed as.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
final class InstalledShippedTemplate {
	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $definition The shipped definition.
	 * @param Dashboard            $template   The installed template dashboard.
	 */
	public function __construct(
		public readonly array $definition,
		public readonly Dashboard $template,
	) {
	}//end __construct()

	/**
	 * The installed template's dashboard id.
	 *
	 * @return integer
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	public function dashboardId(): int {
		return (int)$this->template->getId();
	}//end dashboardId()

	/**
	 * The shipped definition's version.
	 *
	 * @return integer
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	public function version(): int {
		return (int)$this->definition['templateVersion'];
	}//end version()
}//end class
