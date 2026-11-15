<?php

/**
 * KioskStores
 *
 * Where kiosk playlists and the dashboards they show are stored.
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;

/**
 * Where kiosk playlists and the dashboards they show are stored.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class KioskStores {
	/**
	 * Constructor.
	 *
	 * @param KioskPlaylistMapper   $playlistMapper Mapper for kiosk playlists.
	 * @param DashboardMapper       $dashMapper Dashboard mapper for existence/ownership.
	 * @param WidgetPlacementMapper $placementMapper Placements of each played dashboard.
	 * @param PublicShareService    $shareService Reused owner-or-admin authorization rule.
	 */
	public function __construct(
		public readonly KioskPlaylistMapper $playlistMapper,
		public readonly DashboardMapper $dashMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly PublicShareService $shareService,
	) {
	}//end __construct()
}//end class
