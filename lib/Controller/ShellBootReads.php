<?php

/**
 * ShellBootReads
 *
 * What the app shell reads on every page, carried in the workspace page's initial state.
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
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\FavouriteDashboardService;

/**
 * The shell's (nextcloud-vue CnAppRoot) boot reads the workspace page carries:
 * the kept runtime manifest, the setup status, the walkthrough preferences and
 * the viewer's favourite dashboards for the More menu.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
 */
class ShellBootReads {
	/**
	 * Constructor.
	 *
	 * @param ManifestFirstLoad|null               $manifest    The kept runtime manifest.
	 * @param SetupStatusFirstLoad|null            $setupStatus The setup status.
	 * @param WalkthroughPreferencesFirstLoad|null $walkthrough The walkthrough preferences.
	 * @param FavouriteDashboardService|null       $favourites  The favourite dashboards (REQ-SHELL-014).
	 * @param AnnouncementService|null             $announcements Whether the viewer may manage announcements (More's Announcements entry).
	 *
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-2
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-10
	 */
	public function __construct(
		public readonly ?ManifestFirstLoad $manifest=null,
		public readonly ?SetupStatusFirstLoad $setupStatus=null,
		public readonly ?WalkthroughPreferencesFirstLoad $walkthrough=null,
		public readonly ?FavouriteDashboardService $favourites=null,
		public readonly ?AnnouncementService $announcements=null,
	) {
	}//end __construct()

	/**
	 * The reads for the caller, under the initial-state keys the shell reads:
	 * `runtimeManifest`, `setup_status`, `walkthrough_preferences`,
	 * `favouriteDashboards` and `announcementsAccess` (`{canManage}`, which
	 * offers More's Announcements entry). A read that is not wired, or that
	 * gives nothing, is null.
	 *
	 * @param string $userId  The caller's uid, or '' when nobody is logged in.
	 * @param array  $visible The entries of DashboardService::getVisibleToUser()
	 *                        the page already read; the favourites are checked
	 *                        against them without another query.
	 *
	 * @return array<string, array|null> The reads by initial-state key.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-2
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-10
	 */
	public function firstLoad(string $userId, array $visible=[]): array {
		return [
			'runtimeManifest'         => $this->manifest?->firstLoad(userId: $userId),
			'setup_status'            => $this->setupStatus?->firstLoad(userId: $userId),
			'walkthrough_preferences' => $this->walkthrough?->firstLoad(userId: $userId),
			'favouriteDashboards'     => $this->favourites?->forPage(userId: $userId, visible: $visible),
			'announcementsAccess'     => $this->announcementsAccess(userId: $userId),
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
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-10
	 */
	private function announcementsAccess(string $userId): ?array {
		if ($userId === '' || $this->announcements === null) {
			return null;
		}

		return ['canManage' => $this->announcements->isAuthor(userId: $userId)];
	}//end announcementsAccess()
}//end class
