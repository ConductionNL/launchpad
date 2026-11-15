<?php

/**
 * PermissionService
 *
 * Service for managing dashboard permissions.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Exception;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;

/**
 * Service for resolving dashboard permissions across personal,
 * shared, and group-shared scopes (REQ-DASH-014). Also consults the
 * role-assignment system layered on top via RoleService
 * (REQ-ROLE-001, REQ-ROLE-007, REQ-ROLE-008).
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)     Twelve methods cover the
 *                                                three scopes' permission
 *                                                checks without splitting.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) The role-layer
 *                                                    additions for
 *                                                    REQ-ROLE-007 /
 *                                                    REQ-ROLE-008 are
 *                                                    early-return guards
 *                                                    keyed off RoleService
 *                                                    and unavoidably
 *                                                    raise the cyclomatic
 *                                                    score over the
 *                                                    default 50.
 */
class PermissionService {
	/**
	 * Constructor
	 *
	 * @param DashboardMapper $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param AdminSettingMapper $settingMapper Admin setting mapper.
	 * @param DashboardShareService $shareService Share resolution service.
	 * @param IGroupManager $groupManager Group manager for the
	 *                                    `isAdmin` check (group
	 *                                    membership lookups go
	 *                                    through the routing
	 *                                    resolver — REQ-TMPL-013).
	 * @param AdminTemplateService $adminTemplateService Routing resolver — single
	 *                                                   source of truth for
	 *                                                   `IGroupManager::getUserGroupIds`
	 *                                                   (REQ-TMPL-013).
	 * @param RoleService $roleService Effective-role resolver
	 *                                 layered on top of the
	 *                                 permissions capability
	 *                                 (REQ-ROLE-007, REQ-ROLE-008).
	 */
	public function __construct(
		private readonly DashboardMapper $dashboardMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly AdminSettingMapper $settingMapper,
		private readonly DashboardShareService $shareService,
		private readonly IGroupManager $groupManager,
		private readonly AdminTemplateService $adminTemplateService,
		private readonly RoleService $roleService,
	) {
	}//end __construct()

	/**
	 * Whether the user can see a dashboard at all (owner OR has any share).
	 *
	 * @param string $userId The acting user ID.
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return bool True when the dashboard is visible to the user.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canViewDashboard(string $userId, int $dashboardId): bool {
		return $this->resolveAccessLevel(userId: $userId, dashboardId: $dashboardId) !== null;
	}//end canViewDashboard()

	/**
	 * Check if user can edit a dashboard (widgets, tiles, layout).
	 *
	 * @param string $userId The user ID.
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return bool Whether the user can edit the dashboard.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-22
	 */
	public function canEditDashboard(string $userId, int $dashboardId): bool {
		$dashboard = $this->findDashboard(dashboardId: $dashboardId);
		if ($dashboard === null) {
			return false;
		}

		// REQ-ROLE-008: Viewer role blocks any mutation.
		if ($this->roleService->isViewer(userId: $userId) === true) {
			return false;
		}

		if ($this->isAdminEditable(userId: $userId, dashboard: $dashboard) === true) {
			return true;
		}

		// Admin templates can only be edited by admins.
		if ($dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE) {
			return false;
		}

		return $this->allowsEditing(
			level: $this->resolveAccessLevel(userId: $userId, dashboard: $dashboard)
		);
	}//end canEditDashboard()

	/**
	 * REQ-ROLE-001 / REQ-ROLE-007: LaunchPad Admin can edit any dashboard
	 * except for the admin-template type which is gated by Nextcloud admin
	 * status only (REQ-PERM-011).
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return bool TRUE when the admin role alone grants editing.
	 */
	private function isAdminEditable(string $userId, Dashboard $dashboard): bool {
		return $this->roleService->isAdmin(userId: $userId) === true
			&& $dashboard->getType() !== Dashboard::TYPE_ADMIN_TEMPLATE;
	}//end isAdminEditable()

	/**
	 * Whether an access level lets its holder change the dashboard:
	 * add_only and full do, view_only and no access do not.
	 *
	 * @param string|null $level The resolved access level.
	 *
	 * @return bool TRUE for add_only or full.
	 */
	private function allowsEditing(?string $level): bool {
		if ($level === null) {
			return false;
		}

		return in_array(
			needle: $level,
			haystack: [
				Dashboard::PERMISSION_ADD_ONLY,
				Dashboard::PERMISSION_FULL,
			]
		);
	}//end allowsEditing()

	/**
	 * Check if user can edit dashboard metadata (name, description). Owner only.
	 *
	 * @param string $userId The user ID.
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return bool Whether the user can edit the dashboard metadata.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canEditDashboardMetadata(
		string $userId,
		int $dashboardId,
	): bool {
		$dashboard = $this->findDashboard(dashboardId: $dashboardId);
		if ($dashboard === null) {
			return false;
		}

		// L4: admin-template owners retain full metadata-edit rights
		// (REQ-PERM-011). An admin who owns a template must not be blocked
		// from renaming it. Non-admin users can never edit admin templates.
		if ($dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE) {
			return $dashboard->getUserId() === $userId
				&& $this->groupManager->isAdmin(userId: $userId);
		}

		// Only owner can rename / change description.
		return $dashboard->getUserId() === $userId;
	}//end canEditDashboardMetadata()

	/**
	 * Check if user can add widgets to a dashboard.
	 *
	 * @param string $userId The user ID.
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return bool Whether the user can add widgets.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-23
	 */
	public function canAddWidget(string $userId, int $dashboardId): bool {
		// REQ-ROLE-008: Viewer role blocks any mutation, including
		// widget additions. Admin role grants by virtue of resolving
		// access level (admin override is in `resolveAccessLevel`).
		if ($this->roleService->isViewer(userId: $userId) === true) {
			return false;
		}

		return $this->allowsEditing(
			level: $this->resolveAccessLevel(userId: $userId, dashboardId: $dashboardId)
		);
	}//end canAddWidget()

	/**
	 * Check if user can remove a widget.
	 *
	 * @param string $userId The user ID.
	 * @param int $placementId The placement ID.
	 *
	 * @return bool Whether the user can remove the widget.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-22
	 */
	public function canRemoveWidget(string $userId, int $placementId): bool {
		// REQ-ROLE-008: Viewer role blocks any mutation.
		if ($this->roleService->isViewer(userId: $userId) === true) {
			return false;
		}

		$placed = $this->findPlacedWidget(placementId: $placementId);
		if ($placed === null) {
			return false;
		}

		$level = $this->resolveAccessLevel(userId: $userId, dashboard: $placed->dashboard);

		return $this->allowsRemoval(level: $level, placement: $placed->placement);
	}//end canRemoveWidget()

	/**
	 * Whether an access level lets its holder remove a placement: full
	 * removes any, add_only only a non-compulsory one, view_only and no
	 * access none.
	 *
	 * @param string|null     $level     The resolved access level.
	 * @param WidgetPlacement $placement The placement to remove.
	 *
	 * @return bool TRUE when removal is allowed.
	 */
	private function allowsRemoval(?string $level, WidgetPlacement $placement): bool {
		if ($level === Dashboard::PERMISSION_FULL) {
			return true;
		}

		if ($level === Dashboard::PERMISSION_ADD_ONLY) {
			return $placement->getIsCompulsory() === 0;
		}

		return false;
	}//end allowsRemoval()

	/**
	 * Check if user can view a widget's data (read-path).
	 *
	 * Data-fetch endpoints (newsItems, calendarEvents) only require view
	 * permission on the underlying dashboard — not write/style permission.
	 * This is distinct from `canStyleWidget` which gates mutations.
	 * M1: fixes 403 for VIEW_ONLY users on data-fetch endpoints.
	 *
	 * @param string $userId The user ID.
	 * @param int $placementId The placement ID.
	 *
	 * @return bool Whether the user can view the widget's data.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canViewPlacement(string $userId, int $placementId): bool {
		$placement = $this->findPlacement(placementId: $placementId);
		if ($placement === null) {
			return false;
		}

		return $this->canViewDashboard(
			userId: $userId,
			dashboardId: $placement->getDashboardId()
		);
	}//end canViewPlacement()

	/**
	 * Check if user can style a widget.
	 *
	 * @param string $userId The user ID.
	 * @param int $placementId The placement ID.
	 *
	 * @return bool Whether the user can style the widget.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canStyleWidget(string $userId, int $placementId): bool {
		// REQ-ROLE-008: Viewer role blocks any mutation.
		if ($this->roleService->isViewer(userId: $userId) === true) {
			return false;
		}

		$placed = $this->findPlacedWidget(placementId: $placementId);
		if ($placed === null) {
			return false;
		}

		return $this->allowsEditing(
			level: $this->resolveAccessLevel(userId: $userId, dashboard: $placed->dashboard)
		);
	}//end canStyleWidget()

	/**
	 * Check if user can create dashboards.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return bool Whether the user can create dashboards.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canCreateDashboard(string $userId): bool {
		// REQ-ROLE-008: Viewer role explicitly blocks dashboard creation
		// including personal dashboards (REQ-ROLE-003 scenario).
		if ($this->roleService->isViewer(userId: $userId) === true) {
			return false;
		}

		// REQ-ROLE-001 / REQ-ROLE-002: Admin and Editor may always
		// create personal dashboards regardless of the
		// `allow_user_dashboards` admin flag — the role is the
		// canonical override.
		if ($this->roleService->isEditorOrHigher(userId: $userId) === true) {
			return true;
		}

		// REQ-ASET-003 (extended): default `false` — when no row exists,
		// personal dashboard creation MUST be blocked. Defense-in-depth
		// companion to DashboardService::assertPersonalDashboardsAllowed().
		return (bool)$this->settingMapper->getValue(
			key: AdminSetting::KEY_ALLOW_USER_DASHBOARDS,
			default: false
		);
	}//end canCreateDashboard()

	/**
	 * Check if multiple dashboards are allowed (global admin setting).
	 *
	 * This is a global configuration flag, not per-user — the admin setting
	 * `allow_multiple_dashboards` either permits or blocks all users from
	 * owning more than one dashboard. Call-sites already hold the user's
	 * dashboard list; they only need this flag to decide whether to allow
	 * the creation of an additional one.
	 *
	 * @return bool Whether multiple dashboards are allowed.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function canHaveMultipleDashboards(): bool {
		return (bool)$this->settingMapper->getValue(
			key: AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS,
			default: true
		);
	}//end canHaveMultipleDashboards()

	/**
	 * Get the effective permission level for a dashboard, ignoring sharing.
	 *
	 * For `group_shared` dashboards (REQ-DASH-014): the resolved level is
	 * always `view_only` for non-admin members and `full` for admins —
	 * the row's own `permissionLevel` field is intentionally ignored so
	 * the read-only-for-members rule lives in one place. Pass `$userId`
	 * to enable the admin override; omit it to keep the legacy "ignore
	 * sharing, return record level" behaviour.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param string|null $userId The acting user (enables the
	 *                            group-shared admin override). Pass
	 *                            null to fall back to the record's
	 *                            own permission level.
	 *
	 * @return string The effective permission level.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-24
	 */
	public function getEffectivePermissionLevel(
		Dashboard $dashboard,
		?string $userId = null,
	): string {
		// REQ-DASH-014: group-shared dashboards are read-only for
		// non-admin members and fully editable for admins, regardless of
		// the row's persisted `permissionLevel` field (which is kept on
		// the row for forward-compat with future per-tile editing).
		if ($dashboard->getType() === Dashboard::TYPE_GROUP_SHARED) {
			return $this->groupSharedPermissionLevel(userId: $userId);
		}

		// If based on a template, use template's permission level.
		$templateLevel = $this->templatePermissionLevel(dashboard: $dashboard);
		if ($templateLevel !== null) {
			return $templateLevel;
		}

		// Use dashboard's permission level or default.
		$level = $dashboard->getPermissionLevel();
		if (empty($level) === false) {
			return $level;
		}

		return $this->settingMapper->getValue(
			key: AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL,
			default: Dashboard::PERMISSION_FULL
		);
	}//end getEffectivePermissionLevel()

	/**
	 * REQ-ROLE-001 / REQ-ROLE-007: NC admin OR a LaunchPad Admin role
	 * grants full permissions on group_shared dashboards. Editors get full
	 * access only when the underlying group membership check (in
	 * `resolveAccessLevel`) has already succeeded — they edit through the
	 * membership path, not an unconditional override.
	 *
	 * @param string|null $userId The user ID, or NULL for no user.
	 *
	 * @return string Full for admins and editors, view_only otherwise.
	 */
	private function groupSharedPermissionLevel(?string $userId): string {
		if ($userId !== null
			&& ($this->groupManager->isAdmin(userId: $userId) === true
			|| $this->roleService->isAdmin(userId: $userId) === true
			|| $this->roleService->isEditorOrHigher(userId: $userId) === true)
		) {
			return Dashboard::PERMISSION_FULL;
		}

		return Dashboard::PERMISSION_VIEW_ONLY;
	}//end groupSharedPermissionLevel()

	/**
	 * The permission level of the template a dashboard is based on; NULL
	 * when it is not based on one or the template was deleted.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string|null The template's permission level.
	 */
	private function templatePermissionLevel(Dashboard $dashboard): ?string {
		if ($dashboard->getBasedOnTemplate() === null) {
			return null;
		}

		$template = $this->findDashboard(dashboardId: $dashboard->getBasedOnTemplate());
		if ($template === null) {
			// Template deleted, use dashboard's level.
			return null;
		}

		return $template->getPermissionLevel();
	}//end templatePermissionLevel()

	/**
	 * Resolve the effective permission level a user has on a dashboard:
	 *  - If the user is the owner, returns the dashboard's effective level.
	 *  - If a share applies (direct or via group), returns that share's level.
	 *  - Otherwise returns null (no access).
	 *
	 * Pass either a dashboard id or an already-loaded dashboard entity.
	 *
	 * @param string $userId The user id.
	 * @param int|null $dashboardId The dashboard id (optional if $dashboard given).
	 * @param Dashboard|null $dashboard The dashboard entity (optional if $dashboardId given).
	 *
	 * @return string|null The permission level or null when no access.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function resolveAccessLevel(
		string $userId,
		?int $dashboardId = null,
		?Dashboard $dashboard = null,
	): ?string {
		$dashboard = ($dashboard ?? $this->findDashboard(dashboardId: $dashboardId));
		if ($dashboard === null) {
			return null;
		}

		// Group-shared dashboards bypass the ownership-vs-share path:
		// visibility is by group membership, not by per-row sharing.
		// REQ-DASH-014.
		if ($dashboard->getType() === Dashboard::TYPE_GROUP_SHARED) {
			return $this->groupSharedAccessLevel(userId: $userId, dashboard: $dashboard);
		}

		return $this->ownedOrSharedAccessLevel(userId: $userId, dashboard: $dashboard);
	}//end resolveAccessLevel()

	/**
	 * The access level on a personal dashboard: its effective level for
	 * the owner, the shared level for anyone else.
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string|null The access level, or NULL for no access.
	 */
	private function ownedOrSharedAccessLevel(string $userId, Dashboard $dashboard): ?string {
		if ($dashboard->getUserId() === $userId) {
			return $this->getEffectivePermissionLevel(
				dashboard: $dashboard,
				userId: $userId
			);
		}

		return $this->sharedAccessLevel(userId: $userId, dashboard: $dashboard);
	}//end ownedOrSharedAccessLevel()

	/**
	 * The access level on a group-shared dashboard: its effective level
	 * for members of its group (everyone for the default group) and for
	 * admins, NULL for anyone else.
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The group-shared dashboard.
	 *
	 * @return string|null The access level, or NULL for no access.
	 */
	private function groupSharedAccessLevel(string $userId, Dashboard $dashboard): ?string {
		if ($this->reachesGroupDashboard(userId: $userId, dashboard: $dashboard) === false) {
			return null;
		}

		return $this->getEffectivePermissionLevel(
			dashboard: $dashboard,
			userId: $userId
		);
	}//end groupSharedAccessLevel()

	/**
	 * Whether a user reaches a group-shared dashboard: it is shared with
	 * the default group, the user is in its group, or the user is an NC
	 * admin or a LaunchPad Admin.
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The group-shared dashboard.
	 *
	 * @return bool TRUE when the user reaches it.
	 */
	private function reachesGroupDashboard(string $userId, Dashboard $dashboard): bool {
		$groupId = (string)$dashboard->getGroupId();
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			return true;
		}

		$userGroupIds = $this->adminTemplateService->getUserGroupIdsFor(
			userId: $userId
		);

		return in_array(needle: $groupId, haystack: $userGroupIds, strict: true) === true
			|| $this->groupManager->isAdmin(userId: $userId) === true
			|| $this->roleService->isAdmin(userId: $userId) === true;
	}//end reachesGroupDashboard()

	/**
	 * The access level a user holds on someone else's dashboard through a
	 * user or group share, NULL when it is not shared with them.
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string|null The shared access level.
	 */
	private function sharedAccessLevel(string $userId, Dashboard $dashboard): ?string {
		$shares = $this->shareService->resolveSharedDashboards(
			userId: $userId,
			groupIds: $this->adminTemplateService->getUserGroupIdsFor(
				userId: $userId
			)
		);

		return $shares[$dashboard->getId()] ?? null;
	}//end sharedAccessLevel()

	/**
	 * Verify user owns a dashboard.
	 *
	 * @param string $userId The user ID.
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return Dashboard The verified dashboard.
	 *
	 * @throws \Exception If access is denied.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function verifyDashboardOwnership(
		string $userId,
		int $dashboardId,
	): Dashboard {
		$dashboard = $this->dashboardMapper->find(id: $dashboardId);

		if ($dashboard->getUserId() !== $userId) {
			throw new Exception(message: 'Access denied');
		}

		return $dashboard;
	}//end verifyDashboardOwnership()

	/**
	 * Whether the user may set/change/clear the mandatory-read
	 * acknowledgement requirement on a placement: a Nextcloud admin, a
	 * LaunchPad admin, or the author of the announcement. Once a placement
	 * carries an announcement key the author is the owner of the
	 * announcement's admin template, or, without a template, the owner of
	 * the first placement that carried the key; a recipient of a copy is not
	 * the author. Before that, the owner of the placement's dashboard may set
	 * a requirement. A non-author MUST be rejected (REQ-ACK-001, ADR-005).
	 * Returns false when the placement or its dashboard is missing.
	 *
	 * @param string $userId The acting user ID.
	 * @param int $placementId The placement ID.
	 *
	 * @return bool True when the user may manage the requirement.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function canManageAcknowledgement(
		string $userId,
		int $placementId,
	): bool {
		if ($this->groupManager->isAdmin(userId: $userId) === true
			|| $this->roleService->isAdmin(userId: $userId) === true
		) {
			return true;
		}

		$placed = $this->findPlacedWidget(placementId: $placementId);
		if ($placed === null) {
			return false;
		}

		$key = (string)$placed->placement->getAnnouncementKey();
		if ($key === '') {
			return $placed->dashboard->getUserId() === $userId;
		}

		return $this->announcementAuthor(announcementKey: $key) === $userId;
	}//end canManageAcknowledgement()

	/**
	 * The author of an announcement: the owner of the admin template that
	 * carries the key, or, without one, the owner of the placement with the
	 * lowest id (the original; copies and forks are created later).
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return string|null The author's user id, or null.
	 */
	private function announcementAuthor(string $announcementKey): ?string {
		$original = null;
		foreach ($this->placementMapper->findByAnnouncementKey(announcementKey: $announcementKey) as $placement) {
			$dashboard = $this->findDashboard(dashboardId: $placement->getDashboardId());
			if ($dashboard === null) {
				continue;
			}

			if ($dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE) {
				return $dashboard->getUserId();
			}

			if ($this->isEarlierCopy(placement: $placement, original: $original) === true) {
				$original = [$placement->getId(), $dashboard->getUserId()];
			}
		}

		return $original[1] ?? null;
	}//end announcementAuthor()

	/**
	 * Whether a placement is an earlier copy of the announcement than the
	 * earliest one seen so far.
	 *
	 * @param WidgetPlacement           $placement The placement.
	 * @param array{0: int, 1: string}|null $original The earliest copy so far: [placement id, owner].
	 *
	 * @return bool TRUE when it is the first or an earlier copy.
	 */
	private function isEarlierCopy(WidgetPlacement $placement, ?array $original): bool {
		return $original === null || $placement->getId() < $original[0];
	}//end isEarlierCopy()

	/**
	 * Find a dashboard by id, NULL when it does not exist.
	 *
	 * @param int|null $dashboardId The dashboard ID.
	 *
	 * @return Dashboard|null The dashboard.
	 */
	private function findDashboard(?int $dashboardId): ?Dashboard {
		try {
			return $this->dashboardMapper->find(id: $dashboardId);
		} catch (DoesNotExistException) {
			return null;
		}
	}//end findDashboard()

	/**
	 * Find a placement by id, NULL when it does not exist.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return WidgetPlacement|null The placement.
	 */
	private function findPlacement(int $placementId): ?WidgetPlacement {
		try {
			return $this->placementMapper->find(id: $placementId);
		} catch (DoesNotExistException) {
			return null;
		}
	}//end findPlacement()

	/**
	 * Find a placement and the dashboard it sits on, NULL when either does
	 * not exist.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return PlacedWidget|null The placement and its dashboard.
	 */
	private function findPlacedWidget(int $placementId): ?PlacedWidget {
		$placement = $this->findPlacement(placementId: $placementId);
		if ($placement === null) {
			return null;
		}

		$dashboard = $this->findDashboard(dashboardId: $placement->getDashboardId());
		if ($dashboard === null) {
			return null;
		}

		return new PlacedWidget(placement: $placement, dashboard: $dashboard);
	}//end findPlacedWidget()

	/**
	 * Verify user owns a placement's dashboard.
	 *
	 * @param string $userId The user ID.
	 * @param int $placementId The placement ID.
	 *
	 * @return WidgetPlacement The verified placement.
	 *
	 * @throws \Exception If access is denied.
	 *
	 * @spec openspec/specs/permissions/spec.md
	 */
	public function verifyPlacementOwnership(
		string $userId,
		int $placementId,
	): WidgetPlacement {
		$placement = $this->placementMapper->find(id: $placementId);
		$this->verifyDashboardOwnership(
			userId: $userId,
			dashboardId: $placement->getDashboardId()
		);

		return $placement;
	}//end verifyPlacementOwnership()
}//end class
