<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SendentWorkspace\Service;

use OCP\IConfig;
use OCP\IDBConnection;

class WorkspaceService {

	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
	) {
	}

	// ─── Legacy single-layout methods (kept for backward compatibility) ───

	/**
	 * Get workspace layout for a specific group (legacy single-layout)
	 */
	public function getWorkspaceLayout(string $groupId): array {
		// Try new multi-dashboard format first
		$dashboards = $this->getGroupDashboards($groupId);
		if (!empty($dashboards['dashboards'])) {
			$defaultId = $dashboards['defaultDashboardId'] ?? null;
			foreach ($dashboards['dashboards'] as $dash) {
				if ($dash['id'] === $defaultId) {
					return $dash['layout'] ?? [];
				}
			}
			// Fall back to first dashboard
			return $dashboards['dashboards'][0]['layout'] ?? [];
		}
		return [];
	}

	/**
	 * Save workspace layout for a specific group (legacy - saves to default dashboard)
	 */
	public function saveWorkspaceLayout(string $groupId, array $layout): void {
		$dashboards = $this->getGroupDashboards($groupId);
		if (!empty($dashboards['dashboards'])) {
			$defaultId = $dashboards['defaultDashboardId'] ?? $dashboards['dashboards'][0]['id'];
			foreach ($dashboards['dashboards'] as &$dash) {
				if ($dash['id'] === $defaultId) {
					$dash['layout'] = $layout;
					$dash['updatedAt'] = time();
					break;
				}
			}
			unset($dash);
			$this->saveGroupDashboards($groupId, $dashboards);
		}
	}

	// ─── Multi-dashboard methods ───

	/**
	 * Get all dashboards for a group (with lazy migration from old format)
	 */
	public function getGroupDashboards(string $groupId): array {
		// Try new format first
		$raw = $this->config->getAppValue('sendentworkspace', 'dashboards_' . $groupId, '');
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && isset($decoded['dashboards'])) {
				return $decoded;
			}
		}

		// Fall back to old single-layout format
		$oldLayout = $this->config->getAppValue('sendentworkspace', 'layout_' . $groupId, '');
		if ($oldLayout !== '') {
			$layout = json_decode($oldLayout, true);
			if (!is_array($layout)) {
				$layout = [];
			}
		} else {
			$layout = [];
		}

		// Migrate to new format immediately (write-on-read) so IDs are stable
		$dashId = 'dash_' . uniqid();
		$migrated = [
			'version' => 2,
			'defaultDashboardId' => $dashId,
			'dashboards' => [
				[
					'id' => $dashId,
					'name' => 'Default',
					'icon' => 'ViewDashboard',
					'layout' => $layout,
					'createdAt' => time(),
					'updatedAt' => time(),
				],
			],
		];
		$this->saveGroupDashboards($groupId, $migrated);
		return $migrated;
	}

	/**
	 * Save all dashboards for a group (writes new format, deletes old key)
	 */
	public function saveGroupDashboards(string $groupId, array $dashboards): void {
		$dashboards['version'] = 2;
		$this->config->setAppValue(
			'sendentworkspace',
			'dashboards_' . $groupId,
			json_encode($dashboards)
		);
		// Clean up old single-layout key
		$this->config->deleteAppValue('sendentworkspace', 'layout_' . $groupId);
	}

	/**
	 * Get a single dashboard by ID from a group
	 */
	public function getGroupDashboard(string $groupId, string $dashboardId): ?array {
		$dashboards = $this->getGroupDashboards($groupId);
		foreach ($dashboards['dashboards'] ?? [] as $dash) {
			if ($dash['id'] === $dashboardId) {
				return $dash;
			}
		}
		return null;
	}

	/**
	 * Create a new dashboard in a group
	 */
	public function createGroupDashboard(string $groupId, string $name, array $layout = [], string $icon = 'ViewDashboard'): array {
		$dashboards = $this->getGroupDashboards($groupId);
		$newDash = [
			'id' => 'dash_' . uniqid(),
			'name' => $name,
			'icon' => $icon,
			'layout' => $layout,
			'createdAt' => time(),
			'updatedAt' => time(),
		];
		$dashboards['dashboards'][] = $newDash;
		$this->saveGroupDashboards($groupId, $dashboards);
		return $newDash;
	}

	/**
	 * Update a specific dashboard within a group
	 */
	public function saveGroupDashboard(string $groupId, string $dashboardId, array $updates): void {
		$dashboards = $this->getGroupDashboards($groupId);
		foreach ($dashboards['dashboards'] as &$dash) {
			if ($dash['id'] === $dashboardId) {
				if (isset($updates['name'])) {
					$dash['name'] = $updates['name'];
				}
				if (isset($updates['icon'])) {
					$dash['icon'] = $updates['icon'];
				}
				if (isset($updates['layout'])) {
					$dash['layout'] = $updates['layout'];
				}
				$dash['updatedAt'] = time();
				break;
			}
		}
		unset($dash);
		$this->saveGroupDashboards($groupId, $dashboards);
	}

	/**
	 * Delete a dashboard from a group (cannot delete the last one)
	 */
	public function deleteGroupDashboard(string $groupId, string $dashboardId): bool {
		$dashboards = $this->getGroupDashboards($groupId);
		if (count($dashboards['dashboards'] ?? []) <= 1) {
			return false; // Cannot delete the only dashboard
		}

		$dashboards['dashboards'] = array_values(array_filter(
			$dashboards['dashboards'],
			fn($d) => $d['id'] !== $dashboardId
		));

		// If we deleted the default, set new default to first remaining
		if (($dashboards['defaultDashboardId'] ?? '') === $dashboardId) {
			$dashboards['defaultDashboardId'] = $dashboards['dashboards'][0]['id'];
		}

		$this->saveGroupDashboards($groupId, $dashboards);
		return true;
	}

	/**
	 * Set the default dashboard for a group
	 */
	public function setDefaultDashboard(string $groupId, string $dashboardId): void {
		$dashboards = $this->getGroupDashboards($groupId);
		$dashboards['defaultDashboardId'] = $dashboardId;
		$this->saveGroupDashboards($groupId, $dashboards);
	}

	// ─── User dashboard methods ───

	/**
	 * Get all dashboards for a user
	 */
	public function getUserDashboards(string $userId): array {
		$raw = $this->config->getUserValue($userId, 'sendentworkspace', 'user_dashboards', '');
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && isset($decoded['dashboards'])) {
				return $decoded;
			}
		}
		return ['dashboards' => []];
	}

	/**
	 * Save all user dashboards
	 */
	private function saveUserDashboards(string $userId, array $data): void {
		$this->config->setUserValue(
			$userId,
			'sendentworkspace',
			'user_dashboards',
			json_encode($data)
		);
	}

	/**
	 * Create a new user dashboard (forked from a layout)
	 */
	public function createUserDashboard(string $userId, string $name, array $layout = [], string $icon = 'ViewDashboard'): array {
		$data = $this->getUserDashboards($userId);
		$newDash = [
			'id' => 'udash_' . uniqid(),
			'name' => $name,
			'icon' => $icon,
			'layout' => $layout,
			'createdAt' => time(),
			'updatedAt' => time(),
		];
		$data['dashboards'][] = $newDash;
		$this->saveUserDashboards($userId, $data);
		return $newDash;
	}

	/**
	 * Update a user dashboard
	 */
	public function updateUserDashboard(string $userId, string $dashboardId, array $updates): bool {
		$data = $this->getUserDashboards($userId);
		$found = false;
		foreach ($data['dashboards'] as &$dash) {
			if ($dash['id'] === $dashboardId) {
				if (isset($updates['name'])) {
					$dash['name'] = $updates['name'];
				}
				if (isset($updates['icon'])) {
					$dash['icon'] = $updates['icon'];
				}
				if (isset($updates['layout'])) {
					$dash['layout'] = $updates['layout'];
				}
				$dash['updatedAt'] = time();
				$found = true;
				break;
			}
		}
		unset($dash);
		if ($found) {
			$this->saveUserDashboards($userId, $data);
		}
		return $found;
	}

	/**
	 * Delete a user dashboard
	 */
	public function deleteUserDashboard(string $userId, string $dashboardId): bool {
		$data = $this->getUserDashboards($userId);
		$initialCount = count($data['dashboards']);
		$data['dashboards'] = array_values(array_filter(
			$data['dashboards'],
			fn($d) => $d['id'] !== $dashboardId
		));
		if (count($data['dashboards']) < $initialCount) {
			$this->saveUserDashboards($userId, $data);
			return true;
		}
		return false;
	}

	// ─── Settings ───

	/**
	 * Check if users are allowed to create their own dashboards
	 */
	public function getAllowUserDashboards(): bool {
		return $this->config->getAppValue('sendentworkspace', 'allow_user_dashboards', '0') === '1';
	}

	/**
	 * Set whether users can create their own dashboards
	 */
	public function setAllowUserDashboards(bool $allow): void {
		$this->config->setAppValue('sendentworkspace', 'allow_user_dashboards', $allow ? '1' : '0');
	}

	// ─── Active dashboard preference ───

	/**
	 * Get the user's active dashboard preference
	 */
	public function getActiveDashboard(string $userId): string {
		return $this->config->getUserValue($userId, 'sendentworkspace', 'active_dashboard', '');
	}

	/**
	 * Set the user's active dashboard preference
	 */
	public function setActiveDashboard(string $userId, string $dashboardId): void {
		$this->config->setUserValue($userId, 'sendentworkspace', 'active_dashboard', $dashboardId);
	}
}
