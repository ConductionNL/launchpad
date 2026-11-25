<?php

/**
 * Unit tests for the leaver handover of REQ-SHARE-016: where a leaver's
 * dashboards go (their manager, otherwise a named person), the listener that
 * hands them over before the account is deleted, and the setting itself.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Listener\LeaverHandoverListener;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\LeaverHandover;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\Events\BeforeUserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The handover goes to the manager, else the named person, else nowhere.
 */
class LeaverHandoverTest extends TestCase {

	/** @var mixed The stored handover setting. */
	private mixed $stored = null;

	/** @var list<string> The accounts that still exist. */
	private array $accounts = ['pieter', 'marieke', 'femke', 'sanne'];

	/**
	 * The handover over the stored setting and accounts.
	 *
	 * @return LeaverHandover The handover.
	 */
	private function handover(): LeaverHandover {
		$settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(fn (): mixed => $this->stored);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('userExists')->willReturnCallback(
			fn (string $uid): bool => in_array(needle: $uid, haystack: $this->accounts, strict: true)
		);

		return new LeaverHandover(settings: $settings, users: $users);
	}//end handover()

	/**
	 * A person with these managers.
	 *
	 * @param string       $uid      The person.
	 * @param list<string> $managers Their managers.
	 *
	 * @return IUser The person.
	 */
	private function person(string $uid, array $managers=[]): IUser {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getManagerUids')->willReturn($managers);

		return $user;
	}//end person()

	/**
	 * A stored value reads in its shape; anything else as not set.
	 *
	 * @return void
	 */
	public function testTheSettingIsNormalised(): void {
		$none = ['manager' => false, 'fallbackUid' => ''];
		$this->assertSame(expected: $none, actual: $this->handover()->normalise(value: null));
		$this->assertSame(expected: $none, actual: $this->handover()->normalise(value: 'manager'));
		$this->assertSame(
			expected: ['manager' => true, 'fallbackUid' => 'femke'],
			actual: $this->handover()->normalise(value: ['manager' => true, 'fallbackUid' => ' femke '])
		);
		$this->assertSame(
			expected: ['manager' => false, 'fallbackUid' => ''],
			actual: $this->handover()->normalise(value: ['manager' => 'yes', 'fallbackUid' => 7])
		);
	}//end testTheSettingIsNormalised()

	/**
	 * Scenario "Pieter leaves": his dashboards go to his manager.
	 *
	 * @return void
	 */
	public function testTheManagerComesFirst(): void {
		$this->stored = ['manager' => true, 'fallbackUid' => 'femke'];
		$this->assertSame(expected: 'marieke', actual: $this->handover()->targetFor(leaver: $this->person(uid: 'pieter', managers: ['marieke'])));
	}//end testTheManagerComesFirst()

	/**
	 * Scenario "No manager": the named person; also when the manager has no account any more.
	 *
	 * @return void
	 */
	public function testWithoutAManagerTheNamedPerson(): void {
		$this->stored = ['manager' => true, 'fallbackUid' => 'femke'];
		$this->assertSame(expected: 'femke', actual: $this->handover()->targetFor(leaver: $this->person(uid: 'sanne')));
		$this->assertSame(expected: 'femke', actual: $this->handover()->targetFor(leaver: $this->person(uid: 'sanne', managers: ['gone'])));
	}//end testWithoutAManagerTheNamedPerson()

	/**
	 * Scenario "No setting": nobody, so REQ-SHARE-012 applies; the named person
	 * leaving, or one without an account, is nobody too.
	 *
	 * @return void
	 */
	public function testWithoutASettingNobody(): void {
		$this->assertNull(actual: $this->handover()->targetFor(leaver: $this->person(uid: 'pieter', managers: ['marieke'])));

		$this->stored = ['manager' => false, 'fallbackUid' => 'femke'];
		$this->assertSame(expected: 'femke', actual: $this->handover()->targetFor(leaver: $this->person(uid: 'pieter', managers: ['marieke'])));
		$this->assertNull(actual: $this->handover()->targetFor(leaver: $this->person(uid: 'femke')));

		$this->stored = ['manager' => false, 'fallbackUid' => 'gone'];
		$this->assertNull(actual: $this->handover()->targetFor(leaver: $this->person(uid: 'pieter')));
	}//end testWithoutASettingNobody()

	/**
	 * A dashboard of the leaver.
	 *
	 * @param int      $id       The dashboard id.
	 * @param int|null $template The template it is a copy of.
	 *
	 * @return Dashboard The dashboard.
	 */
	private static function dashboard(int $id, ?int $template=null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setName('Board '.$id);
		$dashboard->setBasedOnTemplate($template);

		return $dashboard;
	}//end dashboard()

	/**
	 * The listener hands every own dashboard to the manager with a
	 * notification, leaves a template copy, and goes on after a failure.
	 *
	 * @return void
	 */
	public function testTheListenerHandsTheDashboardsOver(): void {
		$this->stored = ['manager' => true, 'fallbackUid' => 'femke'];
		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('findByUserId')->with('pieter')->willReturn(
			[self::dashboard(id: 1), self::dashboard(id: 2, template: 9), self::dashboard(id: 3), self::dashboard(id: 4)]
		);
		$transferred = [];
		$shares = $this->createMock(originalClassName: DashboardShareService::class);
		$shares->method('transferOwnership')->willReturnCallback(
			function (int $dashboardId, string $newUserId) use (&$transferred): void {
				if ($dashboardId === 3) {
					throw new RuntimeException(message: 'locked');
				}

				$transferred[] = [$dashboardId, $newUserId];
			}
		);
		$shares->expects($this->exactly(count: 2))->method('notifyOwnershipTransferred');
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$listener = new LeaverHandoverListener(handover: $this->handover(), dashboards: $dashboards, shares: $shares, logger: $logger);
		$listener->handle(event: new BeforeUserDeletedEvent(user: $this->person(uid: 'pieter', managers: ['marieke'])));

		$this->assertSame(expected: [[1, 'marieke'], [4, 'marieke']], actual: $transferred);
	}//end testTheListenerHandsTheDashboardsOver()

	/**
	 * Without a target, or for another event, the listener does nothing.
	 *
	 * @return void
	 */
	public function testTheListenerLeavesTheRestToTheDeletion(): void {
		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->expects($this->never())->method('findByUserId');
		$shares = $this->createMock(originalClassName: DashboardShareService::class);
		$shares->expects($this->never())->method('transferOwnership');
		$listener = new LeaverHandoverListener(
			handover: $this->handover(),
			dashboards: $dashboards,
			shares: $shares,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$listener->handle(event: new BeforeUserDeletedEvent(user: $this->person(uid: 'pieter', managers: ['marieke'])));
		$listener->handle(event: new Event());
	}//end testTheListenerLeavesTheRestToTheDeletion()

	/**
	 * The setting is read with its default and stored normalised.
	 *
	 * @return void
	 */
	public function testTheSettingIsStoredNormalised(): void {
		$written = [];
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written): AdminSetting {
			$written[$key] = $value;
			return new AdminSetting();
		});
		$mapper->method('getAllAsArray')->willReturn([]);
		$service = new AdminSettingsService(settingMapper: $mapper);

		$this->assertSame(expected: ['manager' => false, 'fallbackUid' => ''], actual: $service->getSettings()['leaverHandover']);

		$service->updateSettings(settings: ['leaverHandover' => ['manager' => true, 'fallbackUid' => ' femke ', 'other' => 1]]);
		$this->assertSame(expected: ['manager' => true, 'fallbackUid' => 'femke'], actual: $written['leaver_handover']);

		$controller = new \OCA\LaunchPad\Controller\SharingPolicyController(
			request: $this->createMock(originalClassName: \OCP\IRequest::class),
			settings: $service,
			responseHelper: new \OCA\LaunchPad\Controller\ResponseHelper(logger: new \Psr\Log\NullLogger())
		);
		$this->assertSame(expected: 200, actual: $controller->updateLeaverHandover(manager: false, fallbackUid: 'femke')->getStatus());
		$this->assertSame(expected: ['manager' => false, 'fallbackUid' => 'femke'], actual: $written['leaver_handover']);
	}//end testTheSettingIsStoredNormalised()

}//end class
