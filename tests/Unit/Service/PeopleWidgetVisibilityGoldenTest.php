<?php

/**
 * PeopleWidgetService visibility golden test
 *
 * Pins, on the code before its split, which people the widget lists
 * (disabled people skipped, the offset applied across backend chunks,
 * the page cut at its limit), which candidates a search or a group
 * filter yields (profile-field matches merged, group membership
 * intersected, duplicates and unknown groups dropped) and which account
 * fields a viewer sees (scope check, birthdate switch and parsing).
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

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\PeopleQuery;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class PeopleWidgetVisibilityGoldenTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private IAccountManager $accountManager;
	private ProfileFieldService $profileFields;

	/** @var list<string> */
	private array $calls = [];

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->profileFields = $this->createMock(ProfileFieldService::class);
	}

	private function service(): PeopleWidgetService {
		return new PeopleWidgetService(
			$this->userManager,
			$this->groupManager,
			$this->accountManager,
			$this->createMock(IURLGenerator::class),
			$this->createMock(AdminTemplateService::class),
			$this->profileFields,
		);
	}

	private function call(PeopleWidgetService $service, string $method, array $args): mixed {
		return (new ReflectionMethod(PeopleWidgetService::class, $method))->invokeArgs($service, $args);
	}

	private function user(string $uid, bool $enabled = true): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('isEnabled')->willReturn($enabled);

		return $user;
	}

	private function uids(array $users): array {
		return array_map(static fn (IUser $user): string => $user->getUID(), $users);
	}

	/**
	 * Directory pages: users in display-name order, window, expected page, backend calls.
	 *
	 * @return array<string,array{0:string,1:bool,2:int,3:int,4:list<string>,5:list<string>}>
	 */
	public static function directoryPages(): array {
		$directory = 'a,B-,c,d-,e,f,g,h';
		return [
			'first page'        => [$directory, true, 2, 0, ['a', 'c'], ['50@0']],
			'offset skips'      => [$directory, true, 2, 2, ['e', 'f'], ['50@0']],
			'keep disabled'     => [$directory, false, 3, 1, ['B-', 'c', 'd-'], ['50@0']],
			'past the end'      => [$directory, true, 5, 4, ['g', 'h'], ['50@0']],
			'empty directory'   => ['', true, 5, 0, [], ['50@0']],
		];
	}

	/**
	 * @dataProvider directoryPages
	 */
	public function testDirectoryPage(string $directory, bool $excludeDisabled, int $limit, int $offset, array $page, array $calls): void {
		$users = $directory === '' ? [] : array_map(
			fn (string $name): IUser => $this->user(rtrim($name, '-'), str_ends_with($name, '-') === false),
			explode(',', $directory)
		);
		$this->userManager->method('searchDisplayName')->willReturnCallback(function (string $pattern, int $limit, int $offset) use ($users): array {
			$this->calls[] = $limit . '@' . $offset;
			return array_slice($users, $offset, $limit);
		});

		$result = $this->call($this->service(), 'collectDisplayNamePage', [PeopleQuery::window(excludeDisabled: $excludeDisabled, showBirthdays: true, limit: $limit, offset: $offset)]);

		$this->assertSame(array_map(static fn (string $n): string => rtrim($n, '-'), $page), $this->uids($result));
		$this->assertSame($calls, $this->calls);
	}

	public function testDirectoryPageStreamsChunksUntilFullOrExhausted(): void {
		$users = [];
		for ($i = 0; $i < 130; $i++) {
			$users[] = $this->user('u' . $i, $i % 3 !== 0);
		}

		$this->userManager->method('searchDisplayName')->willReturnCallback(function (string $pattern, int $limit, int $offset) use ($users): array {
			$this->calls[] = $limit . '@' . $offset;
			return array_slice($users, $offset, $limit);
		});

		$full = $this->call($this->service(), 'collectDisplayNamePage', [PeopleQuery::window(excludeDisabled: true, showBirthdays: true, limit: 60, offset: 20)]);
		$this->assertSame(['60@0', '60@60'], $this->calls);
		$this->assertCount(60, $full);
		$this->assertSame('u31', $full[0]->getUID());

		$this->calls = [];
		$rest = $this->call($this->service(), 'collectDisplayNamePage', [PeopleQuery::window(excludeDisabled: true, showBirthdays: true, limit: 10, offset: 80)]);
		$this->assertSame(['50@0', '50@50', '50@100'], $this->calls);
		$this->assertSame(['u121', 'u122', 'u124', 'u125', 'u127', 'u128'], $this->uids($rest));
	}

	private function withGroups(array $members): void {
		$this->groupManager->method('get')->willReturnCallback(function (string $gid) use ($members): ?IGroup {
			if (isset($members[$gid]) === false) {
				return null;
			}

			$group = $this->createMock(IGroup::class);
			$group->method('getUsers')->willReturn(array_map(fn (string $uid): IUser => $this->user($uid), $members[$gid]));
			return $group;
		});
	}

	public function testGroupMembersAreDedupedInOrder(): void {
		$this->withGroups(['sales' => ['ann', 'bob'], 'hr' => ['cat', 'ann'], 'empty' => []]);

		$members = $this->call($this->service(), 'collectGroupMembers', [['hr', '', 7, 'ghost', 'sales', 'empty', 'hr']]);

		$this->assertSame(['cat', 'ann', 'bob'], $this->uids($members));
	}

	/**
	 * Candidate filters and the members they yield (null = whole directory).
	 *
	 * @return array<string,array{0:array,1:?array,2:?list<string>}>
	 */
	public static function candidateFilters(): array {
		return [
			'no filter'          => [[], null, null],
			'filter from list'   => [[['fieldName' => 'group', 'values' => ['sales']]], null, ['ann', 'bob']],
			'given filter'       => [[], ['fieldName' => 'group', 'values' => ['hr']], ['cat']],
			'empty values'       => [[], ['fieldName' => 'group', 'values' => []], []],
			'scalar values'      => [[], ['fieldName' => 'group', 'values' => 'sales'], []],
			'no values key'      => [[], ['fieldName' => 'group'], []],
		];
	}

	/**
	 * @dataProvider candidateFilters
	 */
	public function testCandidates(array $filters, ?array $groupFilter, ?array $expected): void {
		$this->withGroups(['sales' => ['ann', 'bob'], 'hr' => ['cat']]);
		$this->userManager->method('search')->willReturn([$this->user('everyone')]);

		$result = $this->call($this->service(), 'resolveCandidates', [$filters, $groupFilter]);

		$this->assertSame($expected ?? ['everyone'], $this->uids($result));
	}

	public function testSearchMergesFieldMatchesAndHonoursTheGroupFilter(): void {
		$this->withGroups(['sales' => ['bob', 'dan', 'eve']]);
		$this->userManager->method('search')->willReturnCallback(function (string $pattern, int $limit): array {
			$this->calls[] = 'search:' . $pattern . ':' . $limit;
			return [$this->user('ann'), $this->user('bob')];
		});
		$this->userManager->method('get')->willReturnCallback(function (string $uid): ?IUser {
			$this->calls[] = 'get:' . $uid;
			return $uid === 'gone' ? null : $this->user($uid);
		});
		$this->profileFields->method('findMatchingUserIds')->willReturnCallback(function (?string $viewerId, string $query): array {
			$this->calls[] = 'fields:' . $viewerId . ':' . $query;
			return ['bob', 'gone', 'dan', 'eve'];
		});
		$service = $this->service();
		(new ReflectionProperty(PeopleWidgetService::class, 'viewerId'))->setValue($service, 'viewer');

		$all = $this->call($service, 'searchCandidates', ['pe', null]);
		$this->assertSame(['ann', 'bob', 'dan', 'eve'], $this->uids($all));
		$this->assertSame(['search:pe:500', 'fields:viewer:pe', 'get:gone', 'get:dan', 'get:eve'], $this->calls);

		$inSales = $this->call($service, 'searchCandidates', ['pe', ['values' => ['sales']]]);
		$this->assertSame(['bob', 'dan', 'eve'], $this->uids($inSales));
		$this->assertSame([], $this->uids($this->call($service, 'searchCandidates', ['pe', ['values' => []]])));
	}

	private function account(array $values, array $private = []): IAccount {
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->willReturnCallback(function (string $property) use ($values, $private): IAccountProperty {
			if (array_key_exists($property, $values) === false) {
				throw new PropertyDoesNotExistException($property);
			}

			$prop = $this->createMock(IAccountProperty::class);
			$prop->method('getValue')->willReturn($values[$property]);
			$prop->method('getScope')->willReturn(in_array($property, $private, true) ? 'private' : 'public');
			return $prop;
		});

		return $account;
	}

	/**
	 * Account fields: stored values, private ones, birthdays shown, expected fields.
	 *
	 * @return array<string,array{0:array<string,string>,1:list<string>,2:bool,3:array<string,string>}>
	 */
	public static function accountFields(): array {
		$values = [
			IAccountManager::PROPERTY_PHONE => '+31 6',
			IAccountManager::PROPERTY_ROLE => '',
			IAccountManager::PROPERTY_HEADLINE => 'Lead',
			IAccountManager::PROPERTY_BIRTHDATE => '10-06-1990',
			IAccountManager::PROPERTY_PRONOUNS => 'they/them',
		];
		return [
			'all visible'        => [$values, [], true, [IAccountManager::PROPERTY_PHONE => '+31 6', IAccountManager::PROPERTY_HEADLINE => 'Lead', IAccountManager::PROPERTY_PRONOUNS => 'they/them', 'birthdate' => '1990-06-10']],
			'birthdays off'      => [$values, [], false, [IAccountManager::PROPERTY_PHONE => '+31 6', IAccountManager::PROPERTY_HEADLINE => 'Lead', IAccountManager::PROPERTY_PRONOUNS => 'they/them']],
			'private hidden'     => [$values, [IAccountManager::PROPERTY_PHONE, IAccountManager::PROPERTY_BIRTHDATE], true, [IAccountManager::PROPERTY_HEADLINE => 'Lead', IAccountManager::PROPERTY_PRONOUNS => 'they/them']],
			'unparsable birth'   => [[IAccountManager::PROPERTY_BIRTHDATE => 'soon'], [], true, []],
		];
	}

	/**
	 * @dataProvider accountFields
	 */
	public function testAccountFields(array $values, array $private, bool $showBirthdays, array $expected): void {
		$this->accountManager->method('getAccount')->willReturn($this->account($values, $private));
		$this->profileFields->method('scopeAllows')->willReturnCallback(static fn (?string $scope, ?string $viewerId, string $ownerId): bool => $scope !== 'private');

		$fields = $this->call($this->service(), 'buildAccountFields', [$this->user('ann'), $showBirthdays]);

		$this->assertSame($expected, $fields);
	}

	public function testAccountFailureGivesNoFields(): void {
		$this->accountManager->method('getAccount')->willThrowException(new RuntimeException('x'));

		$this->assertSame([], $this->call($this->service(), 'buildAccountFields', [$this->user('ann'), true]));
	}

	public function testHiddenBirthdateIsNotEvenRead(): void {
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->willReturnCallback(function (string $property): IAccountProperty {
			$this->calls[] = $property;
			throw new PropertyDoesNotExistException($property);
		});
		$this->accountManager->method('getAccount')->willReturn($account);

		$this->call($this->service(), 'buildAccountFields', [$this->user('ann'), false]);

		$this->assertNotContains(IAccountManager::PROPERTY_BIRTHDATE, $this->calls);
		$this->assertNotSame([], $this->calls);
	}
}//end class
