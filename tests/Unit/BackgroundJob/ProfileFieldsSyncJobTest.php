<?php

/**
 * Unit tests for ProfileFieldsSyncJob: every seen user is refreshed and failures are counted.
 *
 * @category Test
 * @package  Unit\BackgroundJob
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\ProfileFieldsSyncJob;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\ProfileFieldsSyncJob
 */
class ProfileFieldsSyncJobTest extends TestCase {
	private IUserManager&MockObject $users;
	private ProfileFieldService&MockObject $fields;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		$this->users = $this->createMock(IUserManager::class);
		$this->fields = $this->createMock(ProfileFieldService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function job(): ProfileFieldsSyncJob {
		return new ProfileFieldsSyncJob($this->createMock(ITimeFactory::class), $this->users, $this->fields, $this->logger);
	}

	/**
	 * Make callForSeenUsers walk the given users and record the callback results.
	 *
	 * @param IUser[] $seen
	 */
	private function seenUsers(array $seen, array &$returns): void {
		$this->users->expects($this->once())
			->method('callForSeenUsers')
			->willReturnCallback(function (\Closure $callback) use ($seen, &$returns): void {
				foreach ($seen as $user) {
					$returns[] = $callback($user);
				}
			});
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	public function testIntervalIsOneDay(): void {
		$this->assertSame(86400, $this->job()->getInterval());
	}

	public function testEveryUserIsSyncedThenMirroredAndNothingIsLogged(): void {
		$alice = $this->user('alice');
		$bob = $this->user('bob');
		$returns = [];
		$this->seenUsers([$alice, $bob], $returns);
		$calls = [];
		$this->fields->method('syncLdapFields')->willReturnCallback(function (IUser $user) use (&$calls): int {
			$calls[] = 'sync ' . $user->getUID();
			return 1;
		});
		$this->fields->method('mirrorStandardFields')->willReturnCallback(function (IUser $user) use (&$calls): void {
			$calls[] = 'mirror ' . $user->getUID();
		});
		$this->logger->expects($this->never())->method('warning');

		(new ReflectionMethod($this->job(), 'run'))->invoke($this->job(), null);

		$this->assertSame(['sync alice', 'mirror alice', 'sync bob', 'mirror bob'], $calls);
		$this->assertSame([true, true], $returns);
	}

	public function testAFailingUserDoesNotStopTheRunAndIsCounted(): void {
		$returns = [];
		$this->seenUsers([$this->user('a'), $this->user('b'), $this->user('c')], $returns);
		$this->fields->method('syncLdapFields')->willReturnCallback(function (IUser $user): int {
			if ($user->getUID() !== 'b') {
				throw new RuntimeException('ldap down');
			}
			return 0;
		});
		$this->fields->expects($this->once())->method('mirrorStandardFields');
		$this->logger->expects($this->once())
			->method('warning')
			->with('ProfileFieldsSyncJob: 2 user(s) could not be refreshed.');

		$job = $this->job();
		(new ReflectionMethod($job, 'run'))->invoke($job, null);

		$this->assertSame([true, true, true], $returns);
	}
}
