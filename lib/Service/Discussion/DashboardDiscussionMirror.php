<?php

/**
 * DashboardDiscussionMirror
 *
 * Keeps a shared or group dashboard's object in OpenRegister, the object the
 * Talk leaf attaches a discussion to (openregister-leaf-integrations, Ruben
 * 19 Nov 2026). Dashboards live in LaunchPad's own table; the mirror is an
 * object of the `launchpad` register's Dashboard schema whose slug, and for
 * a new mirror whose uuid, is the dashboard's uuid (the mapping
 * Version002000Date20260519000000 used). It carries the dashboard's
 * audience in the fields the schema's read rule matches, so OpenRegister
 * admits exactly those {@see \OCA\LaunchPad\Service\PermissionService} lets
 * view the dashboard. A dashboard nobody shares gets no mirror; a mirror
 * whose dashboard stops being shared keeps its history and narrows to the
 * owner.
 *
 * OpenRegister is optional: without it every call does nothing.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Discussion
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-003-discussion-access-follows-the-existing-view-guard
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Discussion;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes, narrows and removes the OpenRegister object of a shared dashboard.
 */
class DashboardDiscussionMirror {

	/**
	 * The schema of the mirror objects in the `launchpad` register.
	 */
	public const SCHEMA = 'dashboard';

	/**
	 * Constructor.
	 *
	 * @param ExportObjectStore    $store       Object CRUD on the `launchpad` register, OpenRegister optional.
	 * @param DashboardMapper      $dashboards  Reads the dashboards.
	 * @param DashboardShareMapper $shares      Reads their shares.
	 * @param LoggerInterface      $logger      Logs a mirror that could not be written.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
		private readonly DashboardMapper $dashboards,
		private readonly DashboardShareMapper $shares,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Bring one dashboard's mirror in step with who may view it.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
	 */
	public function syncDashboard(int $dashboardId): void {
		try {
			$this->sync(dashboard: $this->dashboards->find(id: $dashboardId));
		} catch (Throwable $e) {
			$this->logFailure(subject: 'dashboard '.$dashboardId, error: $e);
		}
	}//end syncDashboard()

	/**
	 * Bring the mirrors of every dashboard an owner holds in step: a
	 * revocation by recipient can reach several of them.
	 *
	 * @param string $ownerId The owner.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
	 */
	public function syncOwnedBy(string $ownerId): void {
		try {
			foreach ($this->dashboards->findByUserId(userId: $ownerId) as $dashboard) {
				$this->sync(dashboard: $dashboard);
			}
		} catch (Throwable $e) {
			$this->logFailure(subject: 'the dashboards of '.$ownerId, error: $e);
		}
	}//end syncOwnedBy()

	/**
	 * Bring every shared and group dashboard's mirror in step, for an
	 * instance whose dashboards were shared before the mirror existed.
	 *
	 * @return int How many dashboards were looked at.
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
	 */
	public function syncAllShared(): int {
		if ($this->store->isAvailable() === false) {
			return 0;
		}

		$ids = $this->shares->findSharedDashboardIds();
		foreach ($this->dashboards->findByGroup(groupId: null) as $dashboard) {
			$ids[] = (int)$dashboard->getId();
		}

		$ids = array_values(array_unique($ids));
		foreach ($ids as $id) {
			$this->syncDashboard(dashboardId: $id);
		}

		return count($ids);
	}//end syncAllShared()

	/**
	 * Remove a deleted dashboard's mirror.
	 *
	 * @param string $dashboardUuid The deleted dashboard's uuid.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
	 */
	public function remove(string $dashboardUuid): void {
		if ($dashboardUuid === '' || $this->store->isAvailable() === false) {
			return;
		}

		try {
			$mirror = $this->store->find(schema: self::SCHEMA, uuid: $dashboardUuid);
			if ($mirror !== null) {
				$this->store->delete(uuid: (string)$mirror['id']);
			}
		} catch (Throwable $e) {
			$this->logFailure(subject: 'dashboard '.$dashboardUuid, error: $e);
		}
	}//end remove()

	/**
	 * Write a dashboard's mirror when it is shared, narrow an existing one
	 * when it is not, and leave an unshared dashboard without one.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return void
	 */
	private function sync(Dashboard $dashboard): void {
		$uuid = (string)$dashboard->getUuid();
		if ($uuid === '' || $this->store->isAvailable() === false) {
			return;
		}

		$audience = (new DashboardAudienceDeriver())->derive(
			dashboard: $dashboard,
			shares: $this->shares->findByDashboardId(dashboardId: (int)$dashboard->getId())
		);
		$existing = $this->store->find(schema: self::SCHEMA, uuid: $uuid);
		if ($existing === null && $audience->collaborative === false) {
			return;
		}

		$this->store->save(schema: self::SCHEMA, data: [
			'id'               => (string)($existing['id'] ?? $uuid),
			'slug'             => $uuid,
			'title'            => (string)($dashboard->getName() ?? 'Dashboard'),
			'type'             => (string)$dashboard->getType(),
			'groupId'          => $dashboard->getGroupId(),
			'audienceUsers'    => $audience->users,
			'audienceGroups'   => $audience->groups,
			'audienceEveryone' => $audience->everyone,
		]);
	}//end sync()

	/**
	 * Log a mirror that could not be written; the dashboard itself is unaffected.
	 *
	 * @param string    $subject What was being mirrored.
	 * @param Throwable $error   Why it failed.
	 *
	 * @return void
	 */
	private function logFailure(string $subject, Throwable $error): void {
		$this->logger->warning(
			'LaunchPad: the discussion object of '.$subject.' could not be updated: '.$error->getMessage(),
			['app' => 'launchpad', 'exception' => $error]
		);
	}//end logFailure()
}//end class
