<?php

/**
 * DashboardSubline
 *
 * The detail line of a dashboard search result: who shared it and how
 * many widgets it has, or the group it belongs to.
 *
 * @category  Search
 * @package   OCA\LaunchPad\Search
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Search;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use Throwable;

/**
 * Builds the detail line of a dashboard result (REQ-SRCH-013).
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
class DashboardSubline {
	/**
	 * Constructor.
	 *
	 * @param IUserManager          $userManager     Resolves the owner's display name.
	 * @param IGroupManager         $groupManager    Resolves the group's display name.
	 * @param WidgetPlacementMapper $placementMapper Counts the dashboard's widgets.
	 */
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly WidgetPlacementMapper $placementMapper,
	) {
	}//end __construct()

	/**
	 * The detail line for a dashboard the viewer reached through `$source`.
	 *
	 * A dashboard someone shared reads "Shared by <owner> · <n> widgets"; a
	 * group dashboard reads "Group dashboard of <group>". Any other dashboard
	 * (the viewer's own, the default) has no detail line here and returns
	 * null, so the caller keeps its description.
	 *
	 * @param Dashboard $dashboard The matched dashboard.
	 * @param string    $source    How the viewer reached it (Dashboard::SOURCE_*).
	 * @param IL10N     $l10n      The app's translations.
	 *
	 * @return string|null The detail line, or null when there is none.
	 *
	 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	public function forDashboard(Dashboard $dashboard, string $source, IL10N $l10n): ?string {
		if ($source === Dashboard::SOURCE_SHARED) {
			return $this->sharedLine(dashboard: $dashboard, l10n: $l10n);
		}

		if ($source === Dashboard::SOURCE_GROUP) {
			return $this->groupLine(dashboard: $dashboard, l10n: $l10n);
		}

		return null;
	}//end forDashboard()

	/**
	 * "Shared by <owner> · <n> widgets", or null without a known owner.
	 *
	 * @param Dashboard $dashboard The shared dashboard.
	 * @param IL10N     $l10n      The app's translations.
	 *
	 * @return string|null The detail line.
	 *
	 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	private function sharedLine(Dashboard $dashboard, IL10N $l10n): ?string {
		$ownerId = (string)$dashboard->getUserId();
		if ($ownerId === '') {
			return null;
		}

		$owner = $this->userManager->getDisplayName(uid: $ownerId);
		if ($owner === null || $owner === '') {
			$owner = $ownerId;
		}

		return $l10n->n(
			'Shared by %1$s · %n widget',
			'Shared by %1$s · %n widgets',
			$this->widgetCount(dashboard: $dashboard),
			[$owner]
		);
	}//end sharedLine()

	/**
	 * "Group dashboard of <group>", or null for the default sentinel.
	 *
	 * @param Dashboard $dashboard The group dashboard.
	 * @param IL10N     $l10n      The app's translations.
	 *
	 * @return string|null The detail line.
	 *
	 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	private function groupLine(Dashboard $dashboard, IL10N $l10n): ?string {
		$groupId = (string)$dashboard->getGroupId();
		if ($groupId === '' || $groupId === Dashboard::DEFAULT_GROUP_ID) {
			return null;
		}

		$group = $this->groupManager->get(gid: $groupId);
		$name = $groupId;
		if ($group !== null && $group->getDisplayName() !== '') {
			$name = $group->getDisplayName();
		}

		return $l10n->t('Group dashboard of %s', [$name]);
	}//end groupLine()

	/**
	 * The number of widgets on the dashboard; 0 when it cannot be counted.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return int The widget count.
	 *
	 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
	 */
	private function widgetCount(Dashboard $dashboard): int {
		try {
			return $this->placementMapper->countByDashboardId(dashboardId: (int)$dashboard->getId());
		} catch (Throwable) {
			return 0;
		}
	}//end widgetCount()
}//end class
