<?php

/**
 * TemplateResyncRequest
 *
 * Which template to resync, and how.
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
 * The template whose copies a resync updates, and the strategy (merge or
 * overwrite) it applies.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
final class TemplateResyncRequest {
	/**
	 * Constructor
	 *
	 * @param integer $templateId The template dashboard id.
	 * @param string  $strategy   The resync strategy.
	 */
	public function __construct(
		public readonly int $templateId,
		public readonly string $strategy,
	) {
	}//end __construct()
}//end class
