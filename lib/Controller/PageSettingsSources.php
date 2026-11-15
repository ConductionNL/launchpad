<?php

/**
 * PageSettingsSources
 *
 * The role features, tree, log and admin settings the main page reads.
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

use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PublicStartPage;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * The role features, tree, log and admin settings the main page reads.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class PageSettingsSources {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionService $roleFeaturePerm Per-user widget allow-list source (REQ-RFP-009..010).
	 * @param DashboardTreeService         $treeService Slug-chain resolver used by the deep-link route.
	 * @param LoggerInterface              $logger Used to record silent fallback when a deep-link path doesn't
	 *                                             resolve to a visible dashboard.
	 * @param AdminSettingsService         $adminSettingsService Source of the quick-search no-match
	 *                                                           fallback-target admin setting (tile-quick-search
	 *                                                           REQ-QSEARCH-004).
	 * @param PublicStartPage              $publicStartPage The public start page's link (sharing-public-start-page D3).
	 * @param IURLGenerator                $urlGenerator The login form address the start page falls back to.
	 */
	public function __construct(
		public readonly RoleFeaturePermissionService $roleFeaturePerm,
		public readonly DashboardTreeService $treeService,
		public readonly LoggerInterface $logger,
		public readonly AdminSettingsService $adminSettingsService,
		public readonly PublicStartPage $publicStartPage,
		public readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()
}//end class
