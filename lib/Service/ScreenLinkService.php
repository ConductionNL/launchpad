<?php

/**
 * ScreenLinkService
 *
 * The screen link of one dashboard (REQ-KIOSK-006): a kiosk playlist of
 * exactly that dashboard, marked with `screen_dashboard_uuid`, made from the
 * dashboard's Sharing tab and played at `/kiosk/{token}` like every playlist.
 * Whoever may share the dashboard may make and revoke it (owner or
 * administrator, under the sharing policy); an administrator may revoke any
 * (REQ-KIOSK-008); new ones need the administrator's switch on (REQ-KIOSK-007).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use Psr\Log\LoggerInterface;

/**
 * Read, create and revoke the screen link of a dashboard.
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-3
 */
class ScreenLinkService {
	/**
	 * Constructor.
	 *
	 * @param KioskStores     $stores  The playlist and dashboard mappers and the owner-or-admin rule.
	 * @param KioskService    $kiosk   Makes the playlist with its token.
	 * @param SharingLimits   $sharing Who may share, and whether screen links are on.
	 * @param LoggerInterface $logger  PSR-3 logger.
	 */
	public function __construct(
		private readonly KioskStores $stores,
		private readonly KioskService $kiosk,
		private readonly SharingLimits $sharing,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The active screen link of a dashboard, for its owner or an
	 * administrator.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $callerId      Caller user ID.
	 *
	 * @return KioskPlaylist|null The screen link, or null when there is none.
	 *
	 * @throws OCSForbiddenException When the dashboard does not exist or the caller is neither owner nor admin.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-3
	 */
	public function get(string $dashboardUuid, string $callerId): ?KioskPlaylist {
		$this->resolveShareableDashboard(dashboardUuid: $dashboardUuid, callerId: $callerId);

		return $this->stores->playlistMapper->findActiveScreenLink(dashboardUuid: $dashboardUuid);
	}//end get()

	/**
	 * Create the screen link, or return the active one (idempotent). The
	 * caller must be allowed to share the dashboard under the sharing
	 * policy; a new link needs the switch on.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $callerId      Caller user ID.
	 *
	 * @return array{playlist: KioskPlaylist, created: bool} The screen link and whether it was made now.
	 *
	 * @throws OCSForbiddenException When the dashboard does not exist, the caller may not share it,
	 *                               or screen links are switched off.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-3
	 */
	public function create(string $dashboardUuid, string $callerId): array {
		$dashboard = $this->resolveShareableDashboard(dashboardUuid: $dashboardUuid, callerId: $callerId);
		if ($this->sharing->mayShare(userId: $callerId) === false) {
			throw new OCSForbiddenException('You are not allowed to share dashboards.');
		}

		$existing = $this->stores->playlistMapper->findActiveScreenLink(dashboardUuid: $dashboardUuid);
		if ($existing !== null) {
			return ['playlist' => $existing, 'created' => false];
		}

		if ($this->sharing->screenLinksAllowed() === false) {
			throw new OCSForbiddenException('Screen links are switched off.');
		}

		$playlist = $this->kiosk->newPlaylist(
			name: (string)($dashboard->getName() ?? ''),
			entries: [['dashboardUuid' => $dashboardUuid, 'dwellSeconds' => KioskService::DWELL_MIN]],
			refresh: KioskService::REFRESH_DEFAULT,
			callerId: $callerId
		);
		// phpcs:ignore CustomSn.Functions.NamedParameters -- Entity magic __call breaks with named args.
		$playlist->setScreenDashboardUuid($dashboardUuid);

		$saved = $this->stores->playlistMapper->insert(entity: $playlist);
		$this->logger->debug(
			message: sprintf('launchpad: screen link created by %s', $callerId),
			context: ['app' => 'launchpad']
		);

		return ['playlist' => $saved, 'created' => true];
	}//end create()

	/**
	 * Revoke the screen link: its dashboard's owner or an administrator.
	 * No screen link is a no-op.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $callerId      Caller user ID.
	 *
	 * @return void
	 *
	 * @throws OCSForbiddenException When the dashboard does not exist or the caller is neither owner nor admin.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-3
	 */
	public function revoke(string $dashboardUuid, string $callerId): void {
		$this->resolveShareableDashboard(dashboardUuid: $dashboardUuid, callerId: $callerId);

		$this->stores->playlistMapper->revokeScreenLinksOf(dashboardUuid: $dashboardUuid);
	}//end revoke()

	/**
	 * The dashboard behind a request, when the caller is its owner or an
	 * administrator. A missing dashboard answers like a refused one, so the
	 * endpoint does not reveal which UUIDs exist.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $callerId      Caller user ID.
	 *
	 * @return Dashboard The dashboard.
	 *
	 * @throws OCSForbiddenException When the dashboard does not exist or the caller is neither owner nor admin.
	 */
	private function resolveShareableDashboard(string $dashboardUuid, string $callerId): Dashboard {
		try {
			$dashboard = $this->stores->dashMapper->findByUuid(uuid: $dashboardUuid);
		} catch (DoesNotExistException) {
			throw new OCSForbiddenException('Not authorized');
		}

		$this->stores->shareService->authorizeShareMutation(dashboard: $dashboard, userId: $callerId);

		return $dashboard;
	}//end resolveShareableDashboard()
}//end class
