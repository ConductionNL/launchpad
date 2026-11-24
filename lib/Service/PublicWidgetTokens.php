<?php

/**
 * PublicWidgetTokens
 *
 * Opens the token of a public share or a kiosk playlist for a live widget's
 * read: the dashboards it shows, behind the share's password gate, and the
 * request marked as a read-only bearer (REQ-PSHR-011).
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
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The dashboards a public share or kiosk playlist token shows.
 *
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
class PublicWidgetTokens {
	/**
	 * Constructor.
	 *
	 * @param PublicShareService $shares       Opens a public share token.
	 * @param KioskService       $kiosk        Opens a kiosk playlist token.
	 * @param PublicShareContext $shareContext Marks the request as a read-only bearer.
	 */
	public function __construct(
		private readonly PublicShareService $shares,
		private readonly KioskService $kiosk,
		private readonly PublicShareContext $shareContext,
	) {
	}//end __construct()

	/**
	 * The dashboard a public share shows.
	 *
	 * @param string      $token     The share token.
	 * @param string      $ipAddress Client IP address for throttling.
	 * @param string|null $password  The share password, when sent.
	 *
	 * @return list<int> The shared dashboard's id.
	 *
	 * @throws ShareNotFoundException When token invalid, revoked, or expired.
	 * @throws SharePasswordRequiredException When password is required but not supplied or wrong.
	 * @throws DoesNotExistException When the shared dashboard was deleted.
	 *
	 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	public function shareDashboards(
		string $token,
		string $ipAddress,
		#[\SensitiveParameter]
		?string $password,
	): array {
		$dashboardId = $this->shares->sharedDashboardId(token: $token, ipAddress: $ipAddress, password: $password);
		$this->shareContext->markBearer(token: $token);

		return [$dashboardId];
	}//end shareDashboards()

	/**
	 * The dashboards a kiosk playlist shows.
	 *
	 * @param string $token The playlist token.
	 *
	 * @return list<int> The entries' dashboard ids.
	 *
	 * @throws PlaylistNotFoundException When the token is unknown or revoked.
	 *
	 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	public function kioskDashboards(string $token): array {
		$dashboardIds = $this->kiosk->playlistDashboardIds(token: $token);
		$this->shareContext->markBearer(token: $token);

		return $dashboardIds;
	}//end kioskDashboards()
}//end class
