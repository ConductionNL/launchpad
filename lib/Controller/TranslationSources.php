<?php

/**
 * TranslationSources
 *
 * The dashboard lookup and the translation service of the translation API.
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
 * @spec openspec/specs/dashboard-language-content/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardTranslationService;

/**
 * The dashboard lookup and the translation service of the translation API.
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */
class TranslationSources {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper             $dashboardMapper Dashboard mapper (used for the ownership check before
	 *                                                     any translation mutation).
	 * @param DashboardTranslationService $translationService Translation service.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly DashboardTranslationService $translationService,
	) {
	}//end __construct()
}//end class
