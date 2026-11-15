<?php

/**
 * ShareNotifierFailureTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCA\LaunchPad\Controller\DashboardShareApiController;
use OCA\LaunchPad\Controller\ShareApiTools;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\ShareNotifier;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\IncompleteNotificationException;
use OCP\Notification\INotification;
use OCP\Notification\InvalidValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * A share is committed before it is announced, and a retry sends nothing. So
 * a failed announcement answers the write's own success, shows no exception
 * text to the client, is logged, and does not stop the other recipients.
 *
 * @covers \OCA\LaunchPad\Service\ShareNotifier
 * @uses \OCA\LaunchPad\Controller\DashboardShareApiController
 * @uses \OCA\LaunchPad\Controller\ShareApiTools
 * @uses \OCA\LaunchPad\Service\DashboardShareService
 * @uses \OCA\LaunchPad\Service\ShareGrant
 * @uses \OCA\LaunchPad\Db\Dashboard
 * @uses \OCA\LaunchPad\Db\DashboardShare
 */
class ShareNotifierFailureTest extends TestCase {
	/**
	 * Recipients that reached notify().
	 *
	 * @var string[]
	 */
	private array $notified = [];

	/**
	 * Recipient the current notification addresses.
	 */
	private string $addressed = '';

	/**
	 * Classes of the failures that were logged.
	 *
	 * @var string[]
	 */
	private array $logged = [];

	/**
	 * Share rows committed through the mapper.
	 *
	 * @var string[]
	 */
	private array $written = [];

	/**
	 * The exceptions a notification layer can raise, by the step raising them.
	 *
	 * @return array<string, array{0: string, 1: Throwable}>
	 */
	public static function failures(): array {
		return [
			'notify runtime' => ['notify', new RuntimeException('push relay secret-host down')],
			'notify incomplete' => ['notify', new IncompleteNotificationException('secret incomplete')],
			'setUser invalid value' => ['setUser', new InvalidValueException('secret user')],
		];
	}

	/**
	 * Adding a share succeeds while its one notification fails.
	 *
	 * @param string    $step    The step that fails.
	 * @param Throwable $failure The exception.
	 *
	 * @return void
	 */
	#[DataProvider('failures')]
	public function testCreateSucceedsWhenTheNotificationFails(string $step, Throwable $failure): void {
		$controller = $this->controller(failing: ['carol' => [$step, $failure]]);

		$response = $controller->create(id: 1, shareType: 'user', shareWith: 'carol', permissionLevel: 'view_only');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame('carol', $response->getData()['shareWith']);
		$this->assertStringNotContainsString('secret', (string)json_encode($response->getData()));
		$this->assertSame(['user:carol'], $this->written);
		$this->assertSame([$failure::class], $this->logged);
		$this->assertSame([], $this->notified);
	}

	/**
	 * Replacing shares succeeds and reaches the recipients after the failed one.
	 *
	 * @param string    $step    The step that fails.
	 * @param Throwable $failure The exception.
	 *
	 * @return void
	 */
	#[DataProvider('failures')]
	public function testReplaceNotifiesTheOthersWhenOneFails(string $step, Throwable $failure): void {
		$controller = $this->controller(failing: ['carol' => [$step, $failure]]);

		$response = $controller->replace(
			id: 1,
			shares: [
				['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'dave', 'permissionLevel' => 'view_only'],
			]
		);

		$this->assertSame(200, $response->getStatus());
		$this->assertStringNotContainsString('secret', (string)json_encode($response->getData()));
		$this->assertSame(['user:carol', 'user:dave'], $this->written);
		$this->assertSame(['dave'], $this->notified);
		$this->assertSame([$failure::class], $this->logged);
	}

	/**
	 * One group member failing does not stop the next members.
	 *
	 * @return void
	 */
	public function testGroupFanOutCarriesOnAfterAFailedMember(): void {
		$failure = new RuntimeException('secret');
		$controller = $this->controller(failing: ['carol' => ['notify', $failure]], members: ['carol', 'alice', 'dave']);

		$response = $controller->create(id: 1, shareType: 'group', shareWith: 'staff', permissionLevel: 'view_only');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame(['dave'], $this->notified);
		$this->assertSame([RuntimeException::class], $this->logged);
	}

	/**
	 * A failed activity row is logged and the notifications still go out.
	 *
	 * @return void
	 */
	public function testActivityFailureStillNotifies(): void {
		$activity = $this->getMockBuilder(className: DashboardActivityEmitter::class)->disableOriginalConstructor()->getMock();
		$activity->method('shared')->willThrowException(new RuntimeException('secret'));
		$controller = $this->controller(failing: [], activity: $activity);

		$response = $controller->create(id: 1, shareType: 'user', shareWith: 'carol', permissionLevel: 'view_only');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame(['carol'], $this->notified);
		$this->assertSame([RuntimeException::class], $this->logged);
	}

	/**
	 * A failed group lookup is logged and answers the write's success.
	 *
	 * @return void
	 */
	public function testRecipientLookupFailureIsLogged(): void {
		$controller = $this->controller(failing: [], groupFails: true);

		$response = $controller->create(id: 1, shareType: 'group', shareWith: 'staff', permissionLevel: 'view_only');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame([], $this->notified);
		$this->assertSame([RuntimeException::class], $this->logged);
	}

	/**
	 * The controller on a real share service with alice in session, over doubles.
	 *
	 * @param array<string, array{0: string, 1: Throwable}> $failing    The failure per recipient.
	 * @param string[]                                      $members    The members of group staff.
	 * @param DashboardActivityEmitter|null                 $activity   The activity emitter.
	 * @param bool                                          $groupFails Whether the group lookup throws.
	 *
	 * @return DashboardShareApiController The controller.
	 */
	private function controller(array $failing, array $members = [], ?DashboardActivityEmitter $activity = null, bool $groupFails = false): DashboardShareApiController {
		$dashboard = new Dashboard();
		$dashboard->setId(1);
		$dashboard->setUserId('alice');
		$dashboard->setName('Team');
		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturn($dashboard);

		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->method('findShare')->willReturn(null);
		$shares->method('findByDashboardId')->willReturn([]);
		$shares->method('insert')->willReturnCallback(function (DashboardShare $share): DashboardShare {
			$this->written[] = $share->getShareType() . ':' . $share->getShareWith();
			$share->setId(count($this->written));
			return $share;
		});

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(function (string $message, array $context): void {
			$this->logged[] = $context['exceptionClass'];
		});

		$groups = $this->createMock(IGroupManager::class);
		if ($groupFails === true) {
			$groups->method('get')->willThrowException(new RuntimeException('secret'));
		} else {
			$groups->method('get')->willReturn($this->group(members: $members));
		}

		$service = new DashboardShareService(
			shareMapper: $shares,
			dashboardMapper: $dashboards,
			db: $this->createMock(IDBConnection::class),
			notifier: new ShareNotifier(
				notificationManager: $this->notifications(failing: $failing),
				groupManager: $groups,
				logger: $logger,
				activity: $activity
			)
		);
		return new DashboardShareApiController(
			request: $this->createMock(IRequest::class),
			shareService: $service,
			tools: new ShareApiTools(userManager: $this->createMock(IUserManager::class), groupManager: $groups, logger: $logger),
			userId: 'alice',
		);
	}

	/**
	 * A group double with the given members.
	 *
	 * @param string[] $members The member ids.
	 *
	 * @return IGroup The group.
	 */
	private function group(array $members): IGroup {
		$users = [];
		foreach ($members as $member) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($member);
			$users[] = $user;
		}

		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn($users);
		return $group;
	}

	/**
	 * A notification manager that records recipients and fails for the given ones.
	 *
	 * @param array<string, array{0: string, 1: Throwable}> $failing The failure per recipient.
	 *
	 * @return INotificationManager The manager.
	 */
	private function notifications(array $failing): INotificationManager {
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}

		$notification->method('setUser')->willReturnCallback(function (string $user) use ($notification, $failing): INotification {
			$this->addressed = $user;
			if (($failing[$user][0] ?? '') === 'setUser') {
				throw $failing[$user][1];
			}

			return $notification;
		});

		$manager = $this->createMock(INotificationManager::class);
		$manager->method('createNotification')->willReturn($notification);
		$manager->method('notify')->willReturnCallback(function () use ($failing): void {
			if (($failing[$this->addressed][0] ?? '') === 'notify') {
				throw $failing[$this->addressed][1];
			}

			$this->notified[] = $this->addressed;
		});
		return $manager;
	}
}
