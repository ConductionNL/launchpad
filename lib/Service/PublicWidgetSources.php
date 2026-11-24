<?php

/**
 * PublicWidgetSources
 *
 * Where the live widgets on a public link or a kiosk screen read their data:
 * the weather, the live data values and the announcements.
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
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\WeatherServiceInterface;

/**
 * The data sources of the live widgets on a shared dashboard.
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
class PublicWidgetSources {
	/**
	 * Constructor.
	 *
	 * @param WeatherServiceInterface      $weather       Resolves a weather reading.
	 * @param LiveTileService              $liveData      Resolves a live data value.
	 * @param AnnouncementServiceInterface $announcements The published announcements.
	 */
	public function __construct(
		public readonly WeatherServiceInterface $weather,
		public readonly LiveTileService $liveData,
		public readonly AnnouncementServiceInterface $announcements,
	) {
	}//end __construct()
}//end class
