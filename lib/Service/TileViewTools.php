<?php

/**
 * TileViewTools
 *
 * The unique viewer dedup and the analytics service of the tile analytics service.
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
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The unique viewer dedup and the analytics service of the tile analytics service.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
class TileViewTools {
	/**
	 * Constructor.
	 *
	 * @param UniqueViewerDedup $dedup Reused unique-actor dedup service (REQ-TANLT-002).
	 * @param AnalyticsService  $analyticsService Reused for `analytics_enabled` / `analytics_optout` / retention
	 *                                            (REQ-TANLT-003, REQ-TANLT-005) — this service never reads
	 *                                            `IAppConfig`/`IConfig` directly so there is exactly one place
	 *                                            those gates live.
	 */
	public function __construct(
		public readonly UniqueViewerDedup $dedup,
		public readonly AnalyticsService $analyticsService,
	) {
	}//end __construct()
}//end class
