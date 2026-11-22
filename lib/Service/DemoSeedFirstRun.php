<?php

/**
 * DemoSeedFirstRun
 *
 * Prepares the demo users' first visit. The first-run user loses its own
 * dashboards and saved choices on every run, so the empty state of a first
 * visit (board LpEersteKeer) shows again; every other demo user has seen the
 * getting-started tour, so it does not cover the demo dashboards.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Event\DashboardDeletedEvent;

/**
 * Reset the first-run user and mark the tour as seen for the others.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedFirstRun {
	/**
	 * Constructor.
	 *
	 * @param ShowcaseStores      $stores      The dashboard and placement mappers.
	 * @param DemoSeedPreferences $preferences Dashboard choices and tour progress.
	 * @param ShowcasePlatform    $platform    Announces deleted dashboards so their rows are cleaned up.
	 */
	public function __construct(
		private readonly ShowcaseStores $stores,
		private readonly DemoSeedPreferences $preferences,
		private readonly ShowcasePlatform $platform,
	) {
	}//end __construct()

	/**
	 * Record that a user has seen the getting-started tour.
	 *
	 * @param string $userId  The user.
	 * @param string $version The tour version seen.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function markTourSeen(string $userId, string $version): void {
		$this->preferences->markTourSeen(userId: $userId, version: $version);
	}//end markTourSeen()

	/**
	 * Remove a user's own dashboards, dashboard choices and tour progress,
	 * so the next visit is a first visit.
	 *
	 * @param string $userId The user.
	 *
	 * @return integer The number of dashboards removed.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function resetToFirstRun(string $userId): int {
		$removed = 0;
		foreach ($this->stores->dashboardMapper->findByUserId(userId: $userId) as $dashboard) {
			$dashboardId = (int)$dashboard->getId();
			$this->stores->placementMapper->deleteByDashboardId(dashboardId: $dashboardId);
			$this->stores->dashboardMapper->delete(entity: $dashboard);
			$this->platform->eventDispatcher?->dispatchTyped(
				new DashboardDeletedEvent(
					dashboardUuid: (string)$dashboard->getUuid(),
					ownerUserId: $userId,
					type: (string)$dashboard->getType(),
					deletedAt: new DateTimeImmutable(),
					dashboardId: $dashboardId
				)
			);
			$removed++;
		}

		$this->preferences->clear(userId: $userId);

		return $removed;
	}//end resetToFirstRun()
}//end class
