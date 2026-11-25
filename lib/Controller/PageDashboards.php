<?php

/**
 * PageDashboards
 *
 * What the main page reads about dashboards: the dashboard service, and the
 * builder of the first dashboard load the page carries in its initial state.
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
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PageFooter;
use OCA\LaunchPad\Service\SharingLimits;

/**
 * The dashboard service and the first-load builder of the main page.
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-2
 */
class PageDashboards {
	/**
	 * Constructor.
	 *
	 * @param DashboardService       $dashboardService Dashboard listing + resolver (also exposes the
	 *                                                 `allow_user_dashboards` flag — REQ-ASET-003).
	 * @param DashboardPayloads|null $payloads         The first dashboard load the page carries; without
	 *                                                 it the store fetches.
	 * @param SharingLimits|null     $sharing          Whether the viewer may share and may create public
	 *                                                 links (REQ-SHARE-015); without it the tab offers both.
	 * @param PageFooter|null        $footer           The footer below the grid and the viewer's language
	 *                                                 (REQ-FTR-004, REQ-FTR-007); without it no footer renders.
	 */
	public function __construct(
		public readonly DashboardService $dashboardService,
		public readonly ?DashboardPayloads $payloads=null,
		public readonly ?SharingLimits $sharing=null,
		public readonly ?PageFooter $footer=null,
	) {
	}//end __construct()

	/**
	 * What the Sharing tab may offer the viewer (REQ-SHARE-015); both when the
	 * page has no policy, since the server refuses what it does not allow.
	 *
	 * @param string $userId The viewer.
	 *
	 * @return array{mayShare: bool, publicLinksAllowed: bool}
	 *
	 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function sharingPolicy(string $userId): array {
		if ($this->sharing === null) {
			return ['mayShare' => true, 'publicLinksAllowed' => true];
		}

		return [
			'mayShare'           => $this->sharing->mayShare(userId: $userId),
			'publicLinksAllowed' => $this->sharing->publicLinksAllowed(),
		];
	}//end sharingPolicy()

	/**
	 * The footer below the grid for the active dashboard and the viewer's
	 * language (REQ-FTR-004, REQ-FTR-007); no footer without the resolver.
	 *
	 * @param Dashboard|null $dashboard The active dashboard, or null.
	 * @param string         $userId    The viewer, or ''.
	 *
	 * @return array{footer: array<string, mixed>|null, viewerLocale: string}
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function footerState(?Dashboard $dashboard, string $userId): array {
		if ($this->footer === null) {
			return ['footer' => null, 'viewerLocale' => 'en'];
		}

		return [
			'footer'       => $this->footer->footerFor(dashboard: $dashboard),
			'viewerLocale' => $this->footer->viewerLocale(userId: $userId),
		];
	}//end footerState()
}//end class
