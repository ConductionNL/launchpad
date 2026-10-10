<?php

/**
 * ColleagueActivityServiceTest
 *
 * The permission filter of the colleague activity widget, including its
 * effect on the total (REQ-DWMS-007; task 5.3).
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

use OCA\LaunchPad\Db\ActivityEventReader;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\ColleagueActivityService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ColleagueActivityService.
 */
class ColleagueActivityServiceTest extends TestCase {
	public function testAColleaguesWorkOnAHiddenDashboardIsAbsentFromTheListAndTheCount(): void {
		$rows = [
			$this->row(id: 5, actor: 'carla', uuid: 'uuid-a'),
			$this->row(id: 4, actor: 'carla', uuid: 'uuid-b'),
			$this->row(id: 3, actor: 'bram', uuid: '', app: 'files', objectType: 'files', file: '/Projecten/plan.odt'),
			$this->row(id: 2, actor: 'bram', uuid: 'uuid-gone'),
		];

		$result = $this->service(rows: $rows, visible: ['uuid-b'])->recent(readerId: 'anna');

		$this->assertSame([4, 3], array_column($result['items'], 'id'));
		$this->assertSame(2, $result['total']);
		$this->assertSame('Team B', $result['items'][0]['object']);
		$this->assertSame('plan.odt', $result['items'][1]['object']);
		$this->assertSame('Carla de Vries', $result['items'][0]['actorName']);
		$this->assertStringNotContainsString('uuid-a', json_encode($result));
	}//end testAColleaguesWorkOnAHiddenDashboardIsAbsentFromTheListAndTheCount()

	public function testTheListIsFilledPastFilteredEntriesUpToTheLimit(): void {
		$rows = [];
		for ($id = 30; $id > 0; $id--) {
			$rows[] = $this->row(id: $id, actor: 'carla', uuid: (($id % 2) === 0 ? 'uuid-a' : 'uuid-b'));
		}

		$result = $this->service(rows: $rows, visible: ['uuid-b'])->recent(readerId: 'anna', limit: 5);

		$this->assertSame([29, 27, 25, 23, 21], array_column($result['items'], 'id'));
		$this->assertSame(5, $result['total']);
	}//end testTheListIsFilledPastFilteredEntriesUpToTheLimit()

	/**
	 * One activity row as the reader returns it.
	 *
	 * @param int $id The activity id.
	 * @param string $actor Who did it.
	 * @param string $uuid The dashboard uuid.
	 * @param string $app The app.
	 * @param string $objectType The object type.
	 * @param string|null $file The file column, defaulting to the uuid.
	 *
	 * @return array<string, mixed>
	 */
	private function row(int $id, string $actor, string $uuid, string $app = 'launchpad', string $objectType = 'launchpad_dashboard', ?string $file = null): array {
		return [
			'activity_id' => $id,
			'timestamp' => (1790000000 + $id),
			'type' => 'launchpad_dashboard',
			'user' => $actor,
			'app' => $app,
			'subject' => 'dashboard_updated',
			'object_type' => $objectType,
			'file' => ($file ?? $uuid),
			'link' => '',
		];
	}//end row()

	/**
	 * The service over a fake stream, dashboards and permissions.
	 *
	 * @param array<int, array<string, mixed>> $rows The stream, newest first.
	 * @param array<int, string> $visible Dashboard uuids the reader may see.
	 *
	 * @return ColleagueActivityService
	 */
	private function service(array $rows, array $visible): ColleagueActivityService {
		$events = $this->getMockBuilder(ActivityEventReader::class)
			->disableOriginalConstructor()
			->onlyMethods(['recentFromOthers', 'isAvailable'])
			->getMock();
		$events->method('isAvailable')->willReturn(true);
		$events->method('recentFromOthers')->willReturnCallback(
			static fn (string $readerId, int $limit): array => array_slice($rows, 0, $limit)
		);

		$ids = ['uuid-a' => 1, 'uuid-b' => 2];
		$dashboards = $this->getMockBuilder(DashboardMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByUuid'])
			->getMock();
		$dashboards->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($ids): Dashboard {
				if (isset($ids[$uuid]) === false) {
					throw new DoesNotExistException('gone');
				}

				$dashboard = new Dashboard();
				$dashboard->setId($ids[$uuid]);
				$dashboard->setUuid($uuid);
				$dashboard->setName(($uuid === 'uuid-a') ? 'Team A' : 'Team B');
				return $dashboard;
			}
		);

		$permissions = $this->getMockBuilder(PermissionService::class)
			->disableOriginalConstructor()
			->onlyMethods(['canViewDashboard'])
			->getMock();
		$permissions->method('canViewDashboard')->willReturnCallback(
			static fn (string $userId, int $dashboardId): bool => in_array(array_search($dashboardId, $ids, true), $visible, true)
		);

		$users = $this->createMock(IUserManager::class);
		$users->method('getDisplayName')->willReturnCallback(
			static fn (string $uid): ?string => (['carla' => 'Carla de Vries', 'bram' => 'Bram Jansen'][$uid] ?? null)
		);

		return new ColleagueActivityService($events, $dashboards, $permissions, $users);
	}//end service()
}//end class
