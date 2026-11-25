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
use OCA\LaunchPad\Service\DemoSeedStartDates;
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

	/** @var DemoSeedStartDates&\PHPUnit\Framework\MockObject\MockObject The start dates part. */
	private DemoSeedStartDates $startDates;

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

		$this->startDates = $this->createMock(originalClassName: DemoSeedStartDates::class);
		$this->accounts = new DemoSeedAccounts(userManager: $userManager, groupManager: $groupManager, startDates: $this->startDates);
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
		$seed = ['groups' => $groups, 'users' => $users];
		$this->assertSame(expected: ['pieter', 'nieuw'], actual: $this->accounts->ensureUsers(seed: $seed, password: 'pw'));

		$this->members['medewerkers']['nieuw'] = true;

		$this->assertSame(expected: [], actual: $this->accounts->ensureGroups(groups: $groups));
		$this->assertSame(expected: [], actual: $this->accounts->ensureUsers(seed: $seed, password: 'pw'));
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

	/**
	 * The seed's start dates go to the start dates part with the load day.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
	 */
	public function testStartDatesAreSeededForTheLoadDay(): void {
		$definition = ['field' => ['key' => 'in_dienst'], 'days' => ['yasmina' => 7]];
		$this->startDates->expects($this->once())->method('seed')
			->with($definition, '2026-10-05')
			->willReturn(['field' => 'in_dienst', 'people' => ['yasmina' => '2026-10-12']]);

		$report = $this->accounts->ensureStartDates(seed: ['startDates' => $definition], today: '2026-10-05');

		$this->assertSame(expected: ['yasmina' => '2026-10-12'], actual: $report['people']);
	}//end testStartDatesAreSeededForTheLoadDay()
}//end class
