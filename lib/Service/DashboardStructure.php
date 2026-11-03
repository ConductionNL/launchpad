<?php

/**
 * DashboardStructure
 *
 * The tree, the personal layer, the translations and the footer of a dashboard.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The tree, the personal layer, the translations and the footer of a dashboard.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardStructure {
	/**
	 * Constructor.
	 *
	 * @param DashboardTreeService             $treeService Tree-aware validation / cascade walker
	 *                                                      (REQ-DASH-023..030).
	 * @param PersonalLayerService             $personalLayers Lays a reader's own arrangement over a dashboard
	 *                                                         somebody else owns (REQ-DWMS-001). Required, not
	 *                                                         nullable: a null here is indistinguishable from a
	 *                                                         reader who has no layer, so broken wiring would
	 *                                                         store layers and show none of them, in silence and
	 *                                                         with every test green.
	 * @param DashboardTranslationService|null $translationService Optional translation service for the
	 *                                                             per-language content variants
	 *                                                             (REQ-DASH-038..044). Nullable so legacy test
	 *                                                             doubles constructed without it keep working.
	 * @param FooterService|null               $footerService Optional per-dashboard footer sanitiser + resolver
	 *                                                        (REQ-FTR-006). Nullable for backwards- compat with
	 *                                                        existing test doubles.
	 */
	public function __construct(
		public readonly DashboardTreeService $treeService,
		public readonly PersonalLayerService $personalLayers,
		public readonly ?DashboardTranslationService $translationService=null,
		public readonly ?FooterService $footerService=null,
	) {
	}//end __construct()
}//end class
