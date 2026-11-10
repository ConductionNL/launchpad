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

use OCA\LaunchPad\Db\Dashboard;

/**
 * The values one ShippedTemplateUpdateService::update() works with: the
 * template id, its shipped definition, the installed template dashboard,
 * the dry-run flag and the acting user. Built by update() and never
 * handed out.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
final class ShippedTemplateUpdateRun {
	/**
	 * Constructor
	 *
	 * @param string               $templateId The shipped template id.
	 * @param array<string, mixed> $definition The shipped definition.
	 * @param Dashboard            $template   The installed template dashboard.
	 * @param boolean              $dryRun     Whether only to report.
	 * @param string               $userId     The acting user.
	 */
	public function __construct(
		public readonly string $templateId,
		public readonly array $definition,
		public readonly Dashboard $template,
		public readonly bool $dryRun,
		public readonly string $userId,
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
