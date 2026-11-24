<?php

/**
 * DashboardPayloads
 *
 * The two dashboard answers the workspace reads first: the visible list and
 * the active dashboard. The API controller sends them, and the main page
 * carries the same payloads in its initial state, so they are built in one
 * place and cannot drift apart.
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
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\Dashboard;

/**
 * Builds the visible-dashboards list and the active-dashboard envelope.
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
 */
class DashboardPayloads {
	/**
	 * Constructor.
	 *
	 * @param DashboardApiServices $services The dashboard, quota, translation and visibility services.
	 * @param RequestActor|null    $actor    The action matrix check; without it no first load is given.
	 */
	public function __construct(
		private readonly DashboardApiServices $services,
		private readonly ?RequestActor $actor=null,
	) {
	}//end __construct()

	/**
	 * What the workspace's first dashboard load would fetch, so the page can
	 * carry it in its initial state: `{visible, active}` with the payloads of
	 * `GET /api/dashboards/visible` and `GET /api/dashboard`. Null when the
	 * caller may not read either of them, or without the action check, so
	 * the store then asks the API as before.
	 *
	 * @param string                                            $userId  The caller's uid.
	 * @param list<array{dashboard: Dashboard, source: string}> $visible The visible entries the page read.
	 *
	 * @return array{visible: array, active: array|null}|null The first load, or null.
	 *
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-2
	 */
	public function firstLoad(string $userId, array $visible): ?array {
		if ($userId === '' || $this->actor === null) {
			return null;
		}

		foreach (['dashboard.visible', 'dashboard.get-active'] as $action) {
			if ($this->actor->refusal(userId: $userId, action: $action) !== null) {
				return null;
			}
		}

		return [
			'visible' => $this->visibleList(userId: $userId, items: $visible),
			'active' => $this->active(userId: $userId),
		];
	}//end firstLoad()

	/**
	 * The visible-dashboards payload: each row serialised with its source and
	 * whether the caller owns it, inside `{items, quota}` when the quota
	 * service is there, else the bare list (REQ-QUOTA-006).
	 *
	 * @param string                                            $userId The caller's uid.
	 * @param list<array{dashboard: Dashboard, source: string}> $items  The visible entries.
	 *
	 * @return array The payload.
	 *
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	public function visibleList(string $userId, array $items): array {
		$serialized = [];
		foreach ($items as $entry) {
			$row = $entry['dashboard']->jsonSerialize();
			$row['source'] = $entry['source'];
			// Only personal rows owned by the caller take the id-based
			// `is_active` path; group/default rows are activated by UUID.
			$row['isOwner'] = ($entry['dashboard']->getUserId() === $userId);
			$serialized[] = $row;
		}

		if ($this->services->quotaService === null) {
			return $serialized;
		}

		return [
			'items' => $serialized,
			'quota' => $this->services->quotaService->getQuotaStatus(userId: $userId),
		];
	}//end visibleList()

	/**
	 * The active-dashboard envelope: the dashboard, its rendered placements,
	 * the caller's permission level and ownership. Null when the caller has
	 * no dashboard.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array|null The envelope, or null.
	 *
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	public function active(string $userId): ?array {
		$result = $this->services->dashboardService->getEffectiveDashboard(userId: $userId);
		if ($result === null) {
			return null;
		}

		// The effective dashboard can be a group/default dashboard the user
		// does not own, so ownership is tagged rather than assumed.
		$dashboard = $result['dashboard'];
		$isOwner = ($dashboard->getUserId() === $userId);
		$sharedBy = null;
		if ($isOwner === false) {
			$sharedBy = $dashboard->getUserId();
		}

		return [
			'dashboard' => $dashboard->jsonSerialize(),
			'placements' => $this->renderedPlacements(
				placements: $result['placements'],
				permissionLevel: (string)$result['permissionLevel'],
				userId: $userId
			),
			'permissionLevel' => $result['permissionLevel'],
			'isOwner' => $isOwner,
			'sharedBy' => $sharedBy,
			'hasVariants' => $this->hasVariants(uuid: (string)$dashboard->getUuid()),
		];
	}//end active()

	/**
	 * The placements a dashboard renders for the caller, with the widget
	 * visibility rules applied: a viewer does not get a hidden widget, the
	 * owner and editors get it marked `hiddenForViewers` (REQ-VIS-013). A
	 * widget the caller's role does not permit comes back with `isVisible` 0
	 * (REQ-RFP-001, REQ-RFP-003, REQ-RFP-004).
	 *
	 * @param array  $placements      The dashboard's placement entities.
	 * @param string $permissionLevel The caller's permission level on the dashboard.
	 * @param string $userId          The caller's uid.
	 *
	 * @return array The serialised placements.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	public function renderedPlacements(array $placements, string $permissionLevel, string $userId): array {
		$serialized = [];
		foreach ($placements as $placement) {
			$serialized[] = $placement->jsonSerialize();
		}

		if ($this->services->roleWidgets !== null) {
			$serialized = $this->services->roleWidgets->hideUnpermittedPlacements(
				placements: $serialized,
				userId: $userId
			);
		}

		if ($this->services->visibility === null) {
			return $serialized;
		}

		return $this->services->visibility->visiblePlacements(
			placements: $serialized,
			userId: $userId,
			canEdit: $permissionLevel !== Dashboard::PERMISSION_VIEW_ONLY
		);
	}//end renderedPlacements()

	/**
	 * Whether a dashboard has more than one language version, so the page
	 * asks for the resolved variant only when it can differ.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return bool True with two or more variants.
	 *
	 * @spec openspec/specs/dashboard-language-content/spec.md
	 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-1
	 */
	public function hasVariants(string $uuid): bool {
		if ($this->services->translationService === null || $uuid === '') {
			return false;
		}

		return count($this->services->translationService->listVariants(dashboardUuid: $uuid)) > 1;
	}//end hasVariants()
}//end class
