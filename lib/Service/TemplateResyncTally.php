<?php

/**
 * TemplateResyncTally
 *
 * The per-copy outcome of one applied template resync.
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
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * One applied resync of TemplateResyncService: the template's placements
 * and the strategy every copy is diffed with, the report per copy, and the
 * copies that took the change. Built by the service for one run and never
 * handed out.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
final class TemplateResyncTally {
	/**
	 * One report per copy, in copy order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $reports = [];

	/**
	 * The copies that took the change, in copy order.
	 *
	 * @var array<int, Dashboard>
	 */
	public array $affected = [];

	/**
	 * Constructor
	 *
	 * @param array<int, WidgetPlacement> $templatePlacements The template's placements.
	 * @param string                      $strategy           The resync strategy.
	 */
	public function __construct(
		public readonly array $templatePlacements,
		public readonly string $strategy,
	) {
	}//end __construct()
}//end class
