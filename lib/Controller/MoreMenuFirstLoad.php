<?php

/**
 * MoreMenuFirstLoad
 *
 * What the More menu reads on every page, carried in the workspace page's
 * initial state.
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
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\FavouriteDashboardService;

/**
 * The More menu's reads: the viewer's favourite dashboards (REQ-SHELL-014)
 * and whether the viewer may manage announcements, which offers the
 * Announcements entry under Settings. Opening More reads both from the page,
 * never from the API.
 *
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
 */
class MoreMenuFirstLoad {
	/**
	 * Constructor.
	 *
	 * @param FavouriteDashboardService|null $favourites    The favourite dashboards.
	 * @param AnnouncementService|null       $announcements Who may manage announcements.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
	 */
	public function __construct(
		public readonly ?FavouriteDashboardService $favourites=null,
		public readonly ?AnnouncementService $announcements=null,
	) {
	}//end __construct()

	/**
	 * The reads under their initial-state keys: `favouriteDashboards` and
	 * `announcementsAccess` (`{canManage}`). A read that is not wired, or
	 * that has nobody to answer for, is null.
	 *
	 * @param string $userId  The caller's uid, or '' when nobody is logged in.
	 * @param array  $visible The entries of DashboardService::getVisibleToUser()
	 *                        the page already read; the favourites are checked
	 *                        against them without another query.
	 *
	 * @return array<string, array|null> The reads by initial-state key.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
	 */
	public function firstLoad(string $userId, array $visible=[]): array {
		return [
			'favouriteDashboards' => $this->favourites?->forPage(userId: $userId, visible: $visible),
			'announcementsAccess' => $this->announcementsAccess(userId: $userId),
		];
	}//end firstLoad()

	/**
	 * Whether the caller may manage announcements: an administrator, or a
	 * member of a group the announcements setting is delegated to.
	 *
	 * @param string $userId The caller's uid, or '' when nobody is logged in.
	 *
	 * @return array{canManage: bool}|null Null when nobody is logged in or the read is not wired.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
	 */
	private function announcementsAccess(string $userId): ?array {
		if ($userId === '' || $this->announcements === null) {
			return null;
		}

		return ['canManage' => $this->announcements->isAuthor(userId: $userId)];
	}//end announcementsAccess()
}//end class
