<?php

/**
 * PageFooter
 *
 * The footer the workspace page shows below the grid, and the language the
 * footer picks its variant in (footer-customization REQ-FTR-004, REQ-FTR-006,
 * REQ-FTR-007).
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
 * @spec openspec/specs/footer-customization/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/**
 * Resolves the page footer and the viewer's footer language.
 *
 * @spec openspec/specs/footer-customization/spec.md
 */
class PageFooter {
	/**
	 * Constructor.
	 *
	 * @param FooterService              $footerService      Global footer settings and per-dashboard resolution.
	 * @param DashboardTranslationMapper $translationMapper  The dashboard's language variants.
	 * @param IFactory                   $l10nFactory        The viewer's Nextcloud language.
	 * @param IUserManager               $userManager        Looks the viewer up.
	 */
	public function __construct(
		private readonly FooterService $footerService,
		private readonly DashboardTranslationMapper $translationMapper,
		private readonly IFactory $l10nFactory,
		private readonly IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * The footer for the page: the active dashboard's effective footer with
	 * its primary language, which is the second step of the variant choice
	 * (REQ-FTR-007), or the global footer when no dashboard is active. NULL
	 * renders no footer.
	 *
	 * @param Dashboard|null $dashboard The active dashboard, or null.
	 *
	 * @return array<string, mixed>|null The footer payload.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function footerFor(?Dashboard $dashboard): ?array {
		if ($dashboard === null) {
			return $this->footerService->resolveGlobalFooter();
		}

		$footer = $this->footerService->resolveFooterForDashboard(dashboard: $dashboard);
		if ($footer === null) {
			return null;
		}

		$primary = $this->translationMapper->findPrimaryByDashboardUuid(
			dashboardUuid: (string)$dashboard->getUuid()
		);
		if ($primary !== null && $primary->getLanguageCode() !== null) {
			$footer['primaryLanguage'] = $primary->getLanguageCode();
		}

		return $footer;
	}//end footerFor()

	/**
	 * The viewer's Nextcloud language, which picks the footer variant
	 * first (REQ-FTR-007); `en` for nobody.
	 *
	 * @param string $userId The viewer, or ''.
	 *
	 * @return string The language code.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function viewerLocale(string $userId): string {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return 'en';
		}

		return $this->l10nFactory->getUserLanguage($user);
	}//end viewerLocale()
}//end class
