<?php

/**
 * Unit tests for REQ-PPL-015: the people listings (People, Birthdays and
 * New colleagues) follow Nextcloud's user-enumeration settings. Open: the
 * whole directory. Restricted to own groups: only members of the viewer's
 * groups, also inside a widget's group filter and a search. Off: nobody.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Db\ProfileValueMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\NewColleagues;
use OCA\LaunchPad\Service\PeopleQuery;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Service\UserEnumerationScope;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Pieter is in "wonen"; Anna is in "wonen" too, Bram only in "zorg".
 */
class UserEnumerationScopeTest extends TestCase {

	/**
	 * Each person's groups.
	 */
	private const GROUPS = [
		'pieter' => ['wonen'],
		'anna'   => ['wonen', 'zorg'],
		'bram'   => ['zorg'],
		'loner'  => [],
	];

	/**
	 * The scope over the given core settings.
	 *
	 * @param array<string, string> $settings Core sharing keys that differ from their defaults.
	 *
	 * @return UserEnumerationScope The scope.
	 */
	private function scope(array $settings=[]): UserEnumerationScope {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default='') use ($settings): string {
				self::assertSame(expected: 'core', actual: $app);
				return ($settings[$key] ?? $default);
			}
		);
		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			static fn (string $userId): array => (self::GROUPS[$userId] ?? [])
		);

		return new UserEnumerationScope(config: $config, templates: $templates);
	}//end scope()

	/**
	 * The restricted-to-own-groups settings.
	 *
	 * @return array<string, string> The settings.
	 */
	private static function restricted(): array {
		return [UserEnumerationScope::KEY_TO_GROUP => 'yes'];
	}//end restricted()

	/**
	 * Open enumeration (Nextcloud's default) lists everyone.
	 *
	 * @return void
	 */
	public function testOpenEnumerationListsEveryone(): void {
		$scope = $this->scope();

		$this->assertNull(actual: $scope->visibleGroupsFor(viewerId: 'pieter'));
		$this->assertTrue(condition: $scope->maySee(viewerId: 'pieter', ownerId: 'bram'));
	}//end testOpenEnumerationListsEveryone()

	/**
	 * Enumeration off lists nobody, not even someone in the same group.
	 *
	 * @return void
	 */
	public function testEnumerationOffListsNobody(): void {
		$scope = $this->scope(settings: [UserEnumerationScope::KEY_ALLOW => 'no', UserEnumerationScope::KEY_TO_GROUP => 'yes']);

		$this->assertSame(expected: [], actual: $scope->visibleGroupsFor(viewerId: 'pieter'));
		$this->assertFalse(condition: $scope->maySee(viewerId: 'pieter', ownerId: 'anna'));
	}//end testEnumerationOffListsNobody()

	/**
	 * Restricted to own groups: the viewer's groups, and only who shares one.
	 *
	 * @return void
	 */
	public function testRestrictedToOwnGroupsListsOnlyGroupMembers(): void {
		$scope = $this->scope(settings: self::restricted());

		$this->assertSame(expected: ['wonen'], actual: $scope->visibleGroupsFor(viewerId: 'pieter'));
		$this->assertTrue(condition: $scope->maySee(viewerId: 'pieter', ownerId: 'anna'));
		$this->assertFalse(condition: $scope->maySee(viewerId: 'pieter', ownerId: 'bram'));
		$this->assertFalse(condition: $scope->maySee(viewerId: 'loner', ownerId: 'anna'), message: 'no groups: nobody');
	}//end testRestrictedToOwnGroupsListsOnlyGroupMembers()

	/**
	 * A phone-book-only restriction lists nobody: there is no phone book to match.
	 *
	 * @return void
	 */
	public function testPhoneBookOnlyRestrictionListsNobody(): void {
		$scope = $this->scope(settings: [UserEnumerationScope::KEY_TO_PHONE => 'yes']);

		$this->assertSame(expected: [], actual: $scope->visibleGroupsFor(viewerId: 'pieter'));
	}//end testPhoneBookOnlyRestrictionListsNobody()

	/**
	 * Without a signed-in viewer nobody is listed.
	 *
	 * @return void
	 */
	public function testNoViewerListsNobody(): void {
		$this->assertSame(expected: [], actual: $this->scope()->visibleGroupsFor(viewerId: null));
		$this->assertFalse(condition: $this->scope()->maySee(viewerId: null, ownerId: 'anna'));
	}//end testNoViewerListsNobody()

	/**
	 * The people service over Pieter, Anna and Bram.
	 *
	 * @return PeopleWidgetService The service.
	 */
	private function people(): PeopleWidgetService {
		$users = [];
		foreach (['anna' => 'Anna', 'bram' => 'Bram', 'pieter' => 'Pieter'] as $uid => $name) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn($name);
			$user->method('isEnabled')->willReturn(true);
			$users[$uid] = $user;
		}

		$userManager = $this->createMock(originalClassName: IUserManager::class);
		$userManager->method('searchDisplayName')->willReturn(array_values($users));
		$userManager->method('search')->willReturn(array_values($users));
		$userManager->method('countUsersTotal')->willReturn(3);

		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('get')->willReturnCallback(
			function (string $gid) use ($users): ?IGroup {
				$members = [];
				foreach (self::GROUPS as $uid => $groups) {
					if (in_array(needle: $gid, haystack: $groups, strict: true) === true && isset($users[$uid]) === true) {
						$members[] = $users[$uid];
					}
				}

				$group = $this->createMock(originalClassName: IGroup::class);
				$group->method('getUsers')->willReturn($members);
				return $group;
			}
		);

		$accounts = $this->createMock(originalClassName: IAccountManager::class);
		$accounts->method('getAccount')->willReturn($this->createMock(originalClassName: IAccount::class));

		return new PeopleWidgetService(
			userManager: $userManager,
			groupManager: $groupManager,
			accountManager: $accounts,
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class),
			adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class),
			profileFields: $this->createMock(originalClassName: ProfileFieldService::class)
		);
	}//end people()

	/**
	 * The uids one listing returns, its filters narrowed by the scope as
	 * the controller narrows them.
	 *
	 * @param UserEnumerationScope $scope   The enumeration scope.
	 * @param array                $filters The widget's filters.
	 * @param string|null          $search  Search text.
	 *
	 * @return array<int, string> The uids, in order.
	 */
	private function uids(UserEnumerationScope $scope, array $filters=[], ?string $search=null): array {
		$answer = $this->people()->listUsers(
			request: new PeopleQuery(
				filters: $scope->narrowFilters(filters: $filters, viewerId: 'pieter'),
				sortBy: 'displayName',
				search: $search,
				page: PeopleQuery::window(excludeDisabled: true, showBirthdays: false, limit: 50, offset: 0)
			),
			viewerId: 'pieter'
		);

		return array_map(static fn (array $user): string => $user['uid'], $answer['users']);
	}//end uids()

	/**
	 * Open enumeration: the people widget lists the whole directory.
	 *
	 * @return void
	 */
	public function testPeopleWidgetListsEveryoneWhenOpen(): void {
		$this->assertSame(expected: ['anna', 'bram', 'pieter'], actual: $this->uids(scope: $this->scope()));
	}//end testPeopleWidgetListsEveryoneWhenOpen()

	/**
	 * Restricted: the people widget, its search and its group filter list
	 * only members of the viewer's groups.
	 *
	 * @return void
	 */
	public function testPeopleWidgetListsOnlyOwnGroupMembersWhenRestricted(): void {
		$scope = $this->scope(settings: self::restricted());
		$zorg = [['fieldName' => 'group', 'operator' => 'in', 'values' => ['zorg']]];
		$both = [['fieldName' => 'group', 'operator' => 'in', 'values' => ['zorg', 'wonen']]];

		$this->assertSame(expected: ['anna', 'pieter'], actual: $this->uids(scope: $scope));
		$this->assertSame(expected: ['anna', 'pieter'], actual: $this->uids(scope: $scope, search: 'an'));
		$this->assertSame(expected: [], actual: $this->uids(scope: $scope, filters: $zorg), message: 'a group the viewer is not in shows nobody');
		$this->assertSame(expected: ['anna', 'pieter'], actual: $this->uids(scope: $scope, filters: $both));
	}//end testPeopleWidgetListsOnlyOwnGroupMembersWhenRestricted()

	/**
	 * Enumeration off: the people widget lists nobody, browsing or searching.
	 *
	 * @return void
	 */
	public function testPeopleWidgetListsNobodyWhenOff(): void {
		$scope = $this->scope(settings: [UserEnumerationScope::KEY_ALLOW => 'no']);

		$this->assertSame(expected: [], actual: $this->uids(scope: $scope));
		$this->assertSame(expected: [], actual: $this->uids(scope: $scope, search: 'an'));
	}//end testPeopleWidgetListsNobodyWhenOff()

	/**
	 * Restricted: New colleagues leaves out people outside the viewer's groups.
	 *
	 * @return void
	 */
	public function testNewColleaguesListsOnlyOwnGroupMembersWhenRestricted(): void {
		$fields = $this->createMock(originalClassName: ProfileFieldService::class);
		$fields->method('getDefinitions')->willReturn([['key' => 'joined', 'startDate' => true]]);
		$fields->method('canSee')->willReturn(true);
		$fields->method('scopeAllows')->willReturn(true);

		$rows = [];
		foreach (['anna' => '2026-10-02', 'bram' => '2026-10-01'] as $uid => $date) {
			$row = new ProfileValue();
			$row->setUserId($uid);
			$row->setFieldKey('joined');
			$row->setValue($date);
			$rows[] = $row;
		}

		$values = $this->createMock(originalClassName: ProfileValueMapper::class);
		$values->method('findDatesBetween')->willReturn($rows);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): IUser {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getDisplayName')->willReturn(ucfirst($uid));
			$user->method('isEnabled')->willReturn(true);
			return $user;
		});

		$colleagues = new NewColleagues(fields: $fields, values: $values, users: $users);
		$answer = $colleagues->listNew(viewerId: 'pieter', window: ['today' => '2026-10-05']);
		$scope = $this->scope(settings: self::restricted());

		$this->assertSame(expected: ['anna', 'bram'], actual: array_column($answer['users'], 'id'), message: 'the selection itself is unscoped');
		$this->assertSame(expected: ['anna'], actual: array_column($scope->visibleOnly(viewerId: 'pieter', people: $answer['users']), 'id'));
		$this->assertSame(expected: $answer['users'], actual: $this->scope()->visibleOnly(viewerId: 'pieter', people: $answer['users']), message: 'open: unchanged');
		$this->assertSame(expected: [], actual: $this->scope(settings: [UserEnumerationScope::KEY_ALLOW => 'no'])->visibleOnly(viewerId: 'pieter', people: $answer['users']));
	}//end testNewColleaguesListsOnlyOwnGroupMembersWhenRestricted()
}//end class
