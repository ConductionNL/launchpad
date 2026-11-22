<?php

/**
 * DemoSeedAccountsTest
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

namespace Unit\Service;

use OCA\LaunchPad\Service\DemoSeedAccounts;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Demo users and groups are created once and kept in the seeded shape.
 */
class DemoSeedAccountsTest extends TestCase {
	/** @var array<string, IUser> */
	private array $users = [];

	/** @var array<string, IGroup> */
	private array $groups = [];

	/** @var array<string, array<string, true>> Group id => member ids. */
	private array $members = [];

	/** @var DemoSeedAccounts The helper under test. */
	private DemoSeedAccounts $accounts;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$userManager = $this->createMock(originalClassName: IUserManager::class);
		$userManager->method('get')->willReturnCallback(fn (string $uid): ?IUser => $this->users[$uid] ?? null);
		$userManager->method('createUser')->willReturnCallback(function (string $uid): IUser {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			$this->users[$uid] = $user;
			return $user;
		});

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('get')->willReturnCallback(fn (string $gid): ?IGroup => $this->groups[$gid] ?? null);
		$groupManager->method('createGroup')->willReturnCallback(fn (string $gid): IGroup => $this->group(gid: $gid));

		$this->accounts = new DemoSeedAccounts(userManager: $userManager, groupManager: $groupManager);
	}//end setUp()

	/**
	 * Users and groups are created once with their memberships.
	 *
	 * @return void
	 */
	public function testUsersAndGroupsAreCreatedOnceWithTheirMemberships(): void {
		$groups = [['id' => 'vergunningen', 'name' => 'Team Vergunningen'], ['id' => 'medewerkers', 'name' => 'Medewerkers']];
		$users  = [
			['id' => 'pieter', 'displayName' => 'Pieter Jansen', 'groups' => ['vergunningen', 'medewerkers']],
			['id' => 'nieuw', 'displayName' => 'Noor Nieuw', 'groups' => []],
		];

		$this->assertSame(expected: ['vergunningen', 'medewerkers'], actual: $this->accounts->ensureGroups(groups: $groups));
		$seedGroups = ['vergunningen', 'medewerkers'];
		$this->assertSame(expected: ['pieter', 'nieuw'], actual: $this->accounts->ensureUsers(users: $users, seedGroups: $seedGroups, password: 'pw'));

		$this->members['medewerkers']['nieuw'] = true;

		$this->assertSame(expected: [], actual: $this->accounts->ensureGroups(groups: $groups));
		$this->assertSame(expected: [], actual: $this->accounts->ensureUsers(users: $users, seedGroups: $seedGroups, password: 'pw'));
		$this->assertSame(expected: ['pieter' => true], actual: $this->members['vergunningen']);
		$this->assertSame(expected: ['pieter' => true], actual: $this->members['medewerkers']);
	}//end testUsersAndGroupsAreCreatedOnceWithTheirMemberships()

	/**
	 * A group double that records its members.
	 *
	 * @param string $gid The group id.
	 *
	 * @return IGroup The group.
	 */
	private function group(string $gid): IGroup {
		$this->members[$gid] = [];
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getDisplayName')->willReturn($gid);
		$group->method('inGroup')->willReturnCallback(fn (IUser $u): bool => isset($this->members[$gid][$u->getUID()]));
		$group->method('addUser')->willReturnCallback(function (IUser $u) use ($gid): void {
			$this->members[$gid][$u->getUID()] = true;
		});
		$group->method('removeUser')->willReturnCallback(function (IUser $u) use ($gid): void {
			unset($this->members[$gid][$u->getUID()]);
		});
		$this->groups[$gid] = $group;
		return $group;
	}//end group()
}//end class
