<?php

/**
 * PermissionServiceAcknowledgementTest
 *
 * Who may change a placement's acknowledgement requirement: an admin or the
 * announcement's author, never the recipient of a copy (REQ-ACK-001).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * Acknowledgement management rights.
 */
class PermissionServiceAcknowledgementTest extends TestCase {
	/**
	 * Build the service over dashboards (id => [type, owner]) and placements
	 * (id => [dashboard id, announcement key]); only "root" is an admin.
	 *
	 * @param array<int, array{0: string, 1: string|null}> $dashboards The dashboards.
	 * @param array<int, array{0: int, 1: string|null}>    $placements The placements.
	 *
	 * @return PermissionService
	 */
	private function service(array $dashboards, array $placements): PermissionService {
		$rows = [];
		foreach ($placements as $id => [$dashboardId, $key]) {
			$row = new WidgetPlacement();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$row->setId($id);
			$row->setDashboardId($dashboardId);
			$row->setAnnouncementKey($key);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$rows[$id] = $row;
		}

		$placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placementMapper->method('find')->willReturnCallback(static function (int $id) use ($rows): WidgetPlacement {
			if (isset($rows[$id]) === false) {
				throw new DoesNotExistException(msg: 'none');
			}

			return $rows[$id];
		});
		$placementMapper->method('findByAnnouncementKey')->willReturnCallback(
			static fn (string $key): array => array_values(array_filter($rows, static fn (WidgetPlacement $row): bool => $row->getAnnouncementKey() === $key))
		);

		$dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboardMapper->method('find')->willReturnCallback(static function (int $id) use ($dashboards): Dashboard {
			if (isset($dashboards[$id]) === false) {
				throw new DoesNotExistException(msg: 'none');
			}

			$dashboard = new Dashboard();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setId($id);
			$dashboard->setType($dashboards[$id][0]);
			$dashboard->setUserId($dashboards[$id][1]);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			return $dashboard;
		});

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => $userId === 'root');

		return new PermissionService(
			dashboardMapper: $dashboardMapper,
			placementMapper: $placementMapper,
			settingMapper: $this->createMock(originalClassName: AdminSettingMapper::class),
			shareService: $this->createMock(originalClassName: DashboardShareService::class),
			groupManager: $groupManager,
			adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class),
			roleService: $this->createMock(originalClassName: RoleService::class)
		);
	}

	/**
	 * A recipient cannot change the requirement on their copy of a template
	 * announcement; the template owner and an admin can, on any placement.
	 *
	 * @return void
	 */
	public function testOnlyTheTemplateOwnerOrAnAdminManagesACopiedAnnouncement(): void {
		$service = $this->service(
			dashboards: [1 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom'], 2 => [Dashboard::TYPE_USER, 'bob']],
			placements: [20 => [2, 'ak'], 30 => [1, 'ak']]
		);

		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 20));
		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'tom', placementId: 20));
		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'tom', placementId: 30));
		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 30));
		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'root', placementId: 20));
	}

	/**
	 * Without a template the author is the owner of the original placement
	 * (a placement whose dashboard is gone does not count); a fork made later
	 * does not make its holder the author.
	 *
	 * @return void
	 */
	public function testWithoutATemplateTheOriginalOwnerIsTheAuthor(): void {
		$service = $this->service(
			dashboards: [3 => [Dashboard::TYPE_USER, 'bob'], 4 => [Dashboard::TYPE_USER, 'alice']],
			placements: [90 => [3, 'ak'], 40 => [4, 'ak'], 10 => [77, 'ak']]
		);

		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'alice', placementId: 40));
		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 90));
		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'alice', placementId: 90));
	}

	/**
	 * Before a requirement exists, the dashboard owner may set one; nobody
	 * may for a missing placement.
	 *
	 * @return void
	 */
	public function testTheDashboardOwnerSetsTheFirstRequirement(): void {
		$service = $this->service(
			dashboards: [2 => [Dashboard::TYPE_USER, 'bob']],
			placements: [20 => [2, null], 21 => [9, null]]
		);

		$this->assertTrue(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 20));
		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'carol', placementId: 20));
		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 21));
		$this->assertFalse(condition: $service->canManageAcknowledgement(userId: 'bob', placementId: 99));
	}
}//end class
