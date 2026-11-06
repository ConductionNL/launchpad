<?php

/**
 * Dashboard share write golden test
 *
 * Pins what adding one share and replacing the share list do for each
 * caller: the response, every share row written or removed, the
 * transaction, the notifications and the activity rows, against a
 * recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use DateTime;
use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCA\LaunchPad\Controller\DashboardShareApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\ShareNotifier;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use OCA\LaunchPad\Controller\ShareApiTools;
use Psr\Log\LoggerInterface;

/**
 * Share writes, caller by caller.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardShareApiController
 * @covers \OCA\LaunchPad\Service\DashboardShareService
 * @covers \OCA\LaunchPad\Service\ShareNotifier
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardShare
 * @uses   \OCA\LaunchPad\Service\ShareGrant
 * @uses   \OCA\LaunchPad\Controller\ShareApiTools
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class DashboardShareWriteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/dashboard-share-write.json';

	/**
	 * Every write matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testWritesMatchTheRecordedFixture(): void {
		$grants = [
			'new user' => ['user', 'carol', 'view_only'],
			'same level' => ['user', 'bob', 'view_only'],
			'upgrade' => ['user', 'bob', 'full'],
			'downgrade group' => ['group', 'staff', 'view_only'],
			'upgrade group' => ['group', 'staff', 'full'],
			'unknown group' => ['group', 'ghost', 'add_only'],
			'self' => ['user', 'alice', 'add_only'],
			'empty type' => ['', 'carol', 'view_only'],
			'link type' => ['link', 'carol', 'view_only'],
			'upper type' => ['USER', 'carol', 'view_only'],
			'no recipient' => ['user', '', 'view_only'],
			'bad level' => ['user', 'carol', 'admin'],
			'nothing' => [null, null, null],
			'insert fails' => ['user', 'boom', 'full'],
		];

		$actual = [];
		foreach (['alice', 'bob', null] as $caller) {
			foreach ([1, 2, 3] as $dashboardId) {
				foreach ($grants as $name => $grant) {
					$actual["create $caller $dashboardId $name"] = $this->write(caller: $caller, call: ['create', $dashboardId, $grant]);
				}
			}
		}

		$lists = [
			'empty' => [],
			'none' => null,
			'keep' => [
				['shareType' => 'user', 'shareWith' => 'bob', 'permissionLevel' => 'view_only'],
				['shareType' => 'group', 'shareWith' => 'staff', 'permissionLevel' => 'add_only'],
			],
			'change' => [
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'bob', 'permissionLevel' => 'full'],
				['shareType' => 'group', 'shareWith' => 'staff', 'permissionLevel' => 'view_only'],
			],
			'invalid second' => [
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'dave', 'permissionLevel' => 'owner'],
			],
			'missing keys' => [['shareWith' => 'carol']],
			'duplicate' => [
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'full'],
			],
			'extra keys' => [['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'add_only', 'id' => 7, 'dashboardId' => 2]],
			'insert fails' => [
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'boom', 'permissionLevel' => 'view_only'],
			],
			'commit fails' => [['shareType' => 'user', 'shareWith' => 'late', 'permissionLevel' => 'view_only']],
			'numeric recipient' => [['shareType' => 'user', 'shareWith' => 10, 'permissionLevel' => 'view_only']],
		];
		foreach (['alice', 'bob', null] as $caller) {
			foreach ([1, 2, 3] as $dashboardId) {
				foreach ($lists as $name => $list) {
					$actual["replace $caller $dashboardId $name"] = $this->write(caller: $caller, call: ['replace', $dashboardId, $list]);
				}
			}
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		$this->assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $case) {
			$this->assertSame(expected: $case, actual: $actual[$name], message: $name);
		}
	}

	/**
	 * Run one controller write for a session caller and record it.
	 *
	 * @param string|null       $caller The session user, or none.
	 * @param array<int, mixed> $call   Action, dashboard id, grant or list.
	 *
	 * @return array<string, mixed>
	 */
	private function write(?string $caller, array $call): array {
		$log = [];
		$rows = [];
		$commitFails = false;
		foreach ([[11, 1, 'user', 'bob', 'view_only'], [12, 1, 'group', 'staff', 'add_only'], [13, 2, 'user', 'alice', 'full']] as [$id, $dashboardId, $type, $with, $level]) {
			$row = new DashboardShare();
			$row->setId($id);
			$row->setDashboardId($dashboardId);
			$row->setShareType($type);
			$row->setShareWith($with);
			$row->setPermissionLevel($level);
			$row->setCreatedAt('2020-01-01 00:00:00');
			$row->setUpdatedAt('2020-01-01 00:00:00');
			$rows["$dashboardId:$type:$with"] = $row;
		}

		$shares = $this->getMockBuilder(className: DashboardShareMapper::class)->disableOriginalConstructor()->getMock();
		$shares->method('findShare')->willReturnCallback(static function (int $dashboardId, string $shareType, string $shareWith) use (&$rows, &$log): ?DashboardShare {
			$log[] = "findShare $dashboardId $shareType $shareWith";
			return $rows["$dashboardId:$shareType:$shareWith"] ?? null;
		});
		$shares->method('insert')->willReturnCallback(static function (DashboardShare $row) use (&$rows, &$log, &$commitFails): DashboardShare {
			$log[] = ['insert', self::row(share: $row)];
			if ($row->getShareWith() === 'boom') {
				throw new RuntimeException('insert failed');
			}

			$commitFails = $commitFails || $row->getShareWith() === 'late';

			$row->setId(100 + count($rows));
			$rows[$row->getDashboardId() . ':' . $row->getShareType() . ':' . $row->getShareWith()] = $row;
			return $row;
		});
		$shares->method('update')->willReturnCallback(static function (DashboardShare $row) use (&$log): DashboardShare {
			$log[] = ['update', self::row(share: $row)];
			return $row;
		});
		$shares->method('deleteNotIn')->willReturnCallback(static function (int $dashboardId, array $keepKeys) use (&$rows, &$log): int {
			$log[] = ['deleteNotIn', $dashboardId, $keepKeys];
			$removed = 0;
			foreach ($rows as $key => $row) {
				if ($row->getDashboardId() === $dashboardId && in_array($row->getShareType() . ':' . $row->getShareWith(), $keepKeys, true) === false) {
					unset($rows[$key]);
					$removed++;
				}
			}

			return $removed;
		});
		$shares->method('findByDashboardId')->willReturnCallback(static function (int $dashboardId) use (&$rows): array {
			return array_values(array_filter($rows, static fn (DashboardShare $row): bool => $row->getDashboardId() === $dashboardId));
		});

		$dashboards = $this->getMockBuilder(className: DashboardMapper::class)->disableOriginalConstructor()->getMock();
		$dashboards->method('find')->willReturnCallback(static function (int $id) use (&$log): Dashboard {
			$log[] = "find $id";
			if ($id === 3) {
				throw new DoesNotExistException('none');
			}

			$dashboard = new Dashboard();
			$dashboard->setId($id);
			$dashboard->setUserId([1 => 'alice', 2 => 'bob'][$id]);
			$dashboard->setName('Board ' . $id);
			return $dashboard;
		});

		$groups = $this->getMockBuilder(className: IGroupManager::class)->getMock();
		$groups->method('get')->willReturnCallback(function (string $gid) use (&$log): ?IGroup {
			$log[] = "group $gid";
			if ($gid !== 'staff') {
				return null;
			}

			$members = [];
			foreach (['alice', 'bob', 'carol'] as $uid) {
				$member = $this->getMockBuilder(className: IUser::class)->getMock();
				$member->method('getUID')->willReturn($uid);
				$members[] = $member;
			}

			$group = $this->getMockBuilder(className: IGroup::class)->getMock();
			$group->method('getUsers')->willReturn($members);
			return $group;
		});

		$db = $this->getMockBuilder(className: IDBConnection::class)->getMock();
		foreach (['beginTransaction', 'commit', 'rollBack'] as $step) {
			$db->method($step)->willReturnCallback(static function () use (&$log, $step, &$commitFails): bool {
				$log[] = $step;
				if ($step === 'commit' && $commitFails === true) {
					throw new RuntimeException('commit failed');
				}

				return true;
			});
		}

		$notifications = $this->getMockBuilder(className: INotificationManager::class)->getMock();
		$notifications->method('createNotification')->willReturnCallback(function () use (&$log): INotification {
			$entry = count($log);
			$log[$entry] = ['notification'];
			$notification = $this->getMockBuilder(className: INotification::class)->getMock();
			foreach (['setApp', 'setUser', 'setObject', 'setSubject'] as $setter) {
				$notification->method($setter)->willReturnCallback(static function (...$args) use (&$log, $entry, $setter, &$notification): INotification {
					$log[$entry][] = [$setter, ...$args];
					return $notification;
				});
			}

			$notification->method('setDateTime')->willReturnSelf();
			return $notification;
		});
		$notifications->method('notify')->willReturnCallback(static function () use (&$log): void {
			$log[] = 'notify';
		});

		$activity = $this->getMockBuilder(className: DashboardActivityEmitter::class)->disableOriginalConstructor()->getMock();
		$activity->method('shared')->willReturnCallback(static function (Dashboard $dashboard, DashboardShare $share, string $actor, array $recipients) use (&$log): int {
			$log[] = ['activity shared', $dashboard->getId(), self::row(share: $share), $actor, $recipients];
			return count($recipients);
		});

		$service = new DashboardShareService(
			shareMapper: $shares,
			dashboardMapper: $dashboards,
			db: $db,
			notifier: new ShareNotifier(
				notificationManager: $notifications,
				groupManager: $groups,
				logger: $this->getMockBuilder(className: LoggerInterface::class)->getMock(),
				activity: $activity
			)
		);
		$controller = new DashboardShareApiController(
			request: $this->getMockBuilder(className: IRequest::class)->getMock(),
			shareService: $service,
			tools: new ShareApiTools(
				userManager: $this->getMockBuilder(className: IUserManager::class)->getMock(),
				groupManager: $groups,
				logger: $this->getMockBuilder(className: LoggerInterface::class)->getMock()
			),
			userId: $caller
		);

		try {
			if ($call[0] === 'create') {
				[$type, $with, $level] = $call[2];
				$response = $controller->create(id: $call[1], shareType: $type, shareWith: $with, permissionLevel: $level);
			} else {
				$response = $controller->replace(id: $call[1], shares: $call[2]);
			}
		} catch (Throwable $e) {
			return ['thrown' => get_class($e), 'log' => $log];
		}

		$data = $response->getData();
		if (isset($data['createdAt']) === true) {
			$data['createdAt'] = self::clock(value: $data['createdAt']);
			$data['updatedAt'] = self::clock(value: $data['updatedAt']);
		} elseif (isset($data['error']) === false) {
			foreach ($data as $i => $item) {
				$data[$i]['createdAt'] = self::clock(value: $item['createdAt']);
				$data[$i]['updatedAt'] = self::clock(value: $item['updatedAt']);
			}
		}

		return ['status' => $response->getStatus(), 'body' => $data, 'log' => $log];
	}

	/**
	 * A share row as plain values, clock timestamps replaced by a marker.
	 *
	 * @param DashboardShare $share The row.
	 *
	 * @return array<string, mixed>
	 */
	private static function row(DashboardShare $share): array {
		$values = $share->jsonSerialize();
		$values['createdAt'] = self::clock(value: $values['createdAt']);
		$values['updatedAt'] = self::clock(value: $values['updatedAt']);
		return $values;
	}

	/**
	 * Replace a timestamp taken from the clock during the run with a marker.
	 *
	 * @param string|null $value A stored timestamp.
	 *
	 * @return string|null
	 */
	private static function clock(?string $value): ?string {
		if ($value === null || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) !== 1) {
			return $value;
		}

		$delta = abs((new DateTime($value))->getTimestamp() - time());
		return $delta < 600 ? '<now>' : $value;
	}
}
