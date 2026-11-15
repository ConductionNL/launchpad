<?php

/**
 * ExportNotifierTest
 *
 * Failure notifications of scheduled exports (REQ-SCH-002/006): every
 * enabled administrator and the creator, unless the creator is the one
 * who is disabled; a notification that cannot be queued does not throw.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
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

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\ExportNotifier;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\ExportNotifier
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
class ExportNotifierTest extends TestCase {

	/**
	 * A user mock.
	 *
	 * @param string $uid     The id.
	 * @param bool   $enabled Whether enabled.
	 *
	 * @return IUser
	 */
	private function user(string $uid, bool $enabled): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('isEnabled')->willReturn($enabled);
		return $user;
	}//end user()

	/**
	 * Enabled admins and the creator get one notification each, with the name and the reason.
	 *
	 * @return void
	 */
	public function testAdminsAndCreatorAreNotified(): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$this->user(uid: 'root', enabled: true), $this->user(uid: 'old', enabled: false), $this->user(uid: 'alice', enabled: true)]);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->with('admin')->willReturn($group);

		$sent = [];
		$manager = $this->createMock(IManager::class);
		$manager->method('createNotification')->willReturnCallback(function () use (&$sent): INotification {
			$n = $this->createMock(INotification::class);
			foreach (['setApp', 'setDateTime'] as $m) {
				$n->method($m)->willReturn($n);
			}

			$n->method('setUser')->willReturnCallback(static function (string $uid) use (&$sent, $n): INotification {
				$sent[] = ['user' => $uid];
				return $n;
			});
			$n->method('setObject')->willReturnCallback(static function (string $type, string $id) use (&$sent, $n): INotification {
				$sent[count($sent) - 1]['object'] = $type . ':' . $id;
				return $n;
			});
			$n->method('setSubject')->willReturnCallback(static function (string $s, array $p) use (&$sent, $n): INotification {
				$sent[count($sent) - 1]['subject'] = [$s, $p];
				return $n;
			});
			return $n;
		});
		$manager->expects($this->exactly(2))->method('notify');

		$users = (new ExportNotifier($manager, $groups))->failed(export: ['id' => 'e1', 'name' => 'Weekly', 'createdBy' => 'alice'], reason: 'webhook: HTTP 503');

		$this->assertSame(['root', 'alice'], $users);
		$this->assertSame(['user' => 'root', 'object' => 'scheduled_export:e1', 'subject' => ['scheduled_export_failed', ['Weekly', 'webhook: HTTP 503']]], $sent[0]);
	}//end testAdminsAndCreatorAreNotified()

	/**
	 * A disabled creator is not notified; a manager that throws does not fail the call.
	 *
	 * @return void
	 */
	public function testDisabledCreatorAndQueueFailure(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturn(null);
		$manager = $this->createMock(IManager::class);
		$manager->method('createNotification')->willThrowException(new RuntimeException('down'));

		$notifier = new ExportNotifier($manager, $groups);
		$this->assertSame([], $notifier->failed(export: ['createdBy' => 'alice', 'creatorEnabled' => false], reason: 'x'));
		$this->assertSame(['alice'], $notifier->failed(export: ['createdBy' => 'alice'], reason: 'x'));
	}//end testDisabledCreatorAndQueueFailure()
}//end class
