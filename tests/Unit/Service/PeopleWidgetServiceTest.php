<?php

/**
 * PeopleWidgetServiceTest
 *
 * Unit tests for {@see PeopleWidgetService} covering:
 *   - REQ-PPL-003: pagination shape (`{users, total, hasMore}`), MAX_LIMIT
 *     enforcement, default sort.
 *   - REQ-PPL-004: empty values are omitted from the response (never null).
 *   - REQ-PPL-005: birthdate normalisation + `computeDaysToBirthday()` Feb-29
 *     guard.
 *   - REQ-PPL-006: group filter union + dedup + unknown-group tolerance.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\LDAP\ILDAPProviderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Unit\Support\InMemoryProfileValues;

/**
 * Tests for the People widget backend service.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors constructor.
 */
class PeopleWidgetServiceTest extends TestCase {

	/**
	 * @var IUserManager&MockObject
	 */
	private $userManager;

	/**
	 * @var IGroupManager&MockObject
	 */
	private $groupManager;

	/**
	 * @var IAccountManager&MockObject
	 */
	private $accountManager;

	/**
	 * @var IURLGenerator&MockObject
	 */
	private $urlGenerator;

	/**
	 * @var AdminTemplateService&MockObject
	 */
	private $adminTemplateService;

	private PeopleWidgetService $service;

	private ProfileFieldService $profileFields;

	private InMemoryProfileValues $profileValues;

	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(originalClassName: IUserManager::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->accountManager = $this->createMock(originalClassName: IAccountManager::class);
		$this->urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$this->adminTemplateService = $this->createMock(originalClassName: AdminTemplateService::class);

		$this->urlGenerator->method('linkToRouteAbsolute')
			->willReturnCallback(
				callback: static fn (string $route, array $args = []): string => 'https://example.test/' . $route . '?' . http_build_query(data: $args)
			);

		// Default: any user has no groups. Tests can override per-call.
		$this->adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);

		// The real profile field service, over in-memory values and settings.
		$settingMapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			callback: fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settingMapper->method('setSetting')->willReturnCallback(
			callback: function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);
		$this->profileValues = new InMemoryProfileValues();
		$this->profileFields = new ProfileFieldService(
			settingMapper: $settingMapper,
			valueMapper: $this->profileValues,
			accountManager: $this->accountManager,
			adminTemplateService: $this->adminTemplateService,
			ldapProviderFactory: $this->createMock(originalClassName: ILDAPProviderFactory::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

		$this->service = new PeopleWidgetService(
			userManager: $this->userManager,
			groupManager: $this->groupManager,
			accountManager: $this->accountManager,
			urlGenerator: $this->urlGenerator,
			adminTemplateService: $this->adminTemplateService,
			profileFields: $this->profileFields,
		);
	}//end setUp()

	// ---------------------------------------------------------------
	// computeDaysToBirthday — pure helper (REQ-PPL-005)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testComputeDaysToBirthdayReturnsNullForBlankInput(): void {
		$this->assertNull(actual: PeopleWidgetService::computeDaysToBirthday(birthdate: null));
		$this->assertNull(actual: PeopleWidgetService::computeDaysToBirthday(birthdate: ''));
		$this->assertNull(actual: PeopleWidgetService::computeDaysToBirthday(birthdate: 'not-a-date'));
	}//end testComputeDaysToBirthdayReturnsNullForBlankInput()

	/**
	 * @return void
	 */
	public function testComputeDaysToBirthdayHandlesIsoInput(): void {
		$today = new \DateTimeImmutable(datetime: 'today');
		$iso = $today->modify(modifier: '+5 days')->format(format: '1990-m-d');

		$this->assertSame(
			expected: 5,
			actual: PeopleWidgetService::computeDaysToBirthday(birthdate: $iso)
		);
	}//end testComputeDaysToBirthdayHandlesIsoInput()

	/**
	 * @return void
	 */
	public function testComputeDaysToBirthdayHandlesLocaleFormat(): void {
		$today = new \DateTimeImmutable(datetime: 'today');
		$locale = $today->modify(modifier: '+10 days')->format(format: 'd-m-1990');

		$this->assertSame(
			expected: 10,
			actual: PeopleWidgetService::computeDaysToBirthday(birthdate: $locale)
		);
	}//end testComputeDaysToBirthdayHandlesLocaleFormat()

	/**
	 * @return void
	 */
	public function testComputeDaysToBirthdayWrapsToNextYearWhenPast(): void {
		$today = new \DateTimeImmutable(datetime: 'today');
		$past = $today->modify(modifier: '-30 days')->format(format: '1990-m-d');

		$days = PeopleWidgetService::computeDaysToBirthday(birthdate: $past);
		$this->assertNotNull(actual: $days);
		$this->assertGreaterThan(300, $days);
	}//end testComputeDaysToBirthdayWrapsToNextYearWhenPast()

	/**
	 * Feb-29 birthday must NOT throw on non-leap years; the service falls
	 * back to Feb-28. We verify by parsing 2027 (not a leap year) as the
	 * candidate window.
	 *
	 * @return void
	 */
	public function testComputeDaysToBirthdayHandlesFeb29OnNonLeapYear(): void {
		$days = PeopleWidgetService::computeDaysToBirthday(birthdate: '2000-02-29');
		$this->assertNotNull(
			actual: $days,
			message: 'Feb-29 input must not throw or return null on any year'
		);
	}//end testComputeDaysToBirthdayHandlesFeb29OnNonLeapYear()

	// ---------------------------------------------------------------
	// listUsers — argument validation (REQ-PPL-003)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testListUsersRejectsLimitOverMax(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->listUsers(limit: PeopleWidgetService::MAX_LIMIT + 1);
	}//end testListUsersRejectsLimitOverMax()

	/**
	 * @return void
	 */
	public function testListUsersRejectsZeroLimit(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->listUsers(limit: 0);
	}//end testListUsersRejectsZeroLimit()

	/**
	 * @return void
	 */
	public function testListUsersRejectsNegativeOffset(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->listUsers(offset: -1);
	}//end testListUsersRejectsNegativeOffset()

	/**
	 * @return void
	 */
	public function testListUsersRejectsRecentActivitySort(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->listUsers(sortBy: 'recent-activity');
	}//end testListUsersRejectsRecentActivitySort()

	// ---------------------------------------------------------------
	// listUsers — pagination + projection (REQ-PPL-003, REQ-PPL-004)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testListUsersReturnsPaginationShape(): void {
		$users = [];
		$users[] = $this->makeUser(uid: 'alice', display: 'Alice', email: 'alice@example.test');
		$users[] = $this->makeUser(uid: 'bob', display: 'Bob', email: '');
		$users[] = $this->makeUser(uid: 'carol', display: 'Carol', email: 'carol@example.test');

		$this->wireDirectory(orderedUsers: $users);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);

		// Empty account so the optional fields are omitted.
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(limit: 2, offset: 0);

		$this->assertSame(expected: 3, actual: $result['total']);
		$this->assertTrue(condition: $result['hasMore']);
		$this->assertCount(expectedCount: 2, haystack: $result['users']);

		// Default sort = displayName ASC.
		$this->assertSame(expected: 'alice', actual: $result['users'][0]['uid']);
		$this->assertSame(expected: 'bob', actual: $result['users'][1]['uid']);

		// Empty email is OMITTED, not nulled (REQ-PPL-004).
		$this->assertArrayNotHasKey(key: 'email', array: $result['users'][1]);
		$this->assertArrayHasKey(key: 'email', array: $result['users'][0]);
		$this->assertSame(
			expected: 'alice@example.test',
			actual: $result['users'][0]['email']
		);

		// Avatar URL points to the configured route.
		$this->assertStringContainsString(
			needle: 'core.avatar.getAvatar',
			haystack: $result['users'][0]['avatarUrl']
		);
	}//end testListUsersReturnsPaginationShape()

	/**
	 * @return void
	 */
	public function testListUsersLastPageHasMoreFalse(): void {
		$users = [];
		$users[] = $this->makeUser(uid: 'alice', display: 'Alice');
		$users[] = $this->makeUser(uid: 'bob', display: 'Bob');

		$this->wireDirectory(orderedUsers: $users);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(limit: 50, offset: 0);

		$this->assertFalse(condition: $result['hasMore']);
		$this->assertSame(expected: 2, actual: $result['total']);
		$this->assertCount(expectedCount: 2, haystack: $result['users']);
	}//end testListUsersLastPageHasMoreFalse()

	/**
	 * @return void
	 */
	public function testListUsersExcludesDisabledByDefault(): void {
		$alice = $this->makeUser(uid: 'alice', display: 'Alice', enabled: true);
		$eve = $this->makeUser(uid: 'eve', display: 'Eve', enabled: false);

		// The backend returns both (display-name order); the bounded page
		// path skips the disabled user inside the window, and the exact
		// total comes from countUsersTotal() minus countDisabledUsers().
		$this->wireDirectory(orderedUsers: [$alice, $eve], disabledCount: 1);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers();

		$this->assertSame(expected: 1, actual: $result['total']);
		$this->assertSame(expected: 'alice', actual: $result['users'][0]['uid']);
	}//end testListUsersExcludesDisabledByDefault()

	// ---------------------------------------------------------------
	// listUsers — group filter (REQ-PPL-006)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testGroupFilterUnionDeduplicates(): void {
		$alice = $this->makeUser(uid: 'alice', display: 'Alice');
		$bob = $this->makeUser(uid: 'bob', display: 'Bob');
		$carol = $this->makeUser(uid: 'carol', display: 'Carol');

		$mgmt = $this->createMock(originalClassName: IGroup::class);
		$mgmt->method('getUsers')->willReturn([$alice, $bob]);

		$prod = $this->createMock(originalClassName: IGroup::class);
		$prod->method('getUsers')->willReturn([$bob, $carol]);

		$this->groupManager->method('get')
			->willReturnCallback(
				callback: static function (string $gid) use ($mgmt, $prod) {
					if ($gid === 'management') {
						return $mgmt;
					}

					if ($gid === 'product') {
						return $prod;
					}

					return null;
				}
			);

		// Group sort path consults getUserGroupIds; default sort doesn't.
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(
			filters: [
				[
					'fieldName' => 'group',
					'operator' => 'in',
					'values' => ['management', 'product'],
				],
			],
		);

		// Bob appears once across both groups (dedup).
		$uids = array_map(
			callback: static fn (array $u): string => $u['uid'],
			array: $result['users']
		);
		$this->assertSame(expected: ['alice', 'bob', 'carol'], actual: $uids);
		$this->assertSame(expected: 3, actual: $result['total']);
	}//end testGroupFilterUnionDeduplicates()

	/**
	 * @return void
	 */
	public function testUnknownGroupYieldsZeroUsersWithoutError(): void {
		$this->groupManager->method('get')->willReturn(null);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(
			filters: [
				[
					'fieldName' => 'group',
					'operator' => 'in',
					'values' => ['nonexistent'],
				],
			],
		);

		$this->assertSame(expected: 0, actual: $result['total']);
		$this->assertSame(expected: [], actual: $result['users']);
		$this->assertFalse(condition: $result['hasMore']);
	}//end testUnknownGroupYieldsZeroUsersWithoutError()

	// ---------------------------------------------------------------
	// listUsers — account-field projection (REQ-PPL-005)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testBirthdateIsNormalisedToIso(): void {
		$alice = $this->makeUser(uid: 'alice', display: 'Alice');
		$this->wireDirectory(orderedUsers: [$alice]);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);

		$account = $this->makeAccount(
			properties: [
				IAccountManager::PROPERTY_BIRTHDATE => '10-06-1990',
				IAccountManager::PROPERTY_ROLE => 'PM',
			]
		);
		$this->accountManager->method('getAccount')->willReturn($account);

		$result = $this->service->listUsers(viewerId: 'viewer');

		$this->assertSame(
			expected: '1990-06-10',
			actual: $result['users'][0]['birthdate']
		);
		$this->assertSame(expected: 'PM', actual: $result['users'][0]['role']);
	}//end testBirthdateIsNormalisedToIso()

	/**
	 * @return void
	 */
	public function testShowBirthdaysFalseStripsBirthdate(): void {
		$alice = $this->makeUser(uid: 'alice', display: 'Alice');
		$this->wireDirectory(orderedUsers: [$alice]);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);

		$account = $this->makeAccount(
			properties: [IAccountManager::PROPERTY_BIRTHDATE => '1990-06-10']
		);
		$this->accountManager->method('getAccount')->willReturn($account);

		$result = $this->service->listUsers(showBirthdays: false);

		$this->assertArrayNotHasKey(
			key: 'birthdate',
			array: $result['users'][0]
		);
	}//end testShowBirthdaysFalseStripsBirthdate()

	// ---------------------------------------------------------------
	// listUsers — bounded directory scan (fix-people-widget-unbounded-user-scan)
	// ---------------------------------------------------------------

	/**
	 * With no `group` filter and the default `displayName` sort, the
	 * service MUST page directly from the backend via a bounded
	 * `searchDisplayName($pattern, $limit, $offset)` call and MUST NOT
	 * fall back to the unbounded `search('')` full-directory scan.
	 *
	 * @return void
	 */
	public function testDisplayNameSortUsesBoundedSearchNotFullScan(): void {
		$alice = $this->makeUser(uid: 'alice', display: 'Alice');
		$bob = $this->makeUser(uid: 'bob', display: 'Bob');

		// The unbounded scan MUST NOT be used for this path.
		$this->userManager->expects($this->never())->method('search');

		$captured = [];
		$this->userManager->expects($this->atLeastOnce())
			->method('searchDisplayName')
			->willReturnCallback(
				function (string $pattern, ?int $limit = null, ?int $offset = null) use (&$captured, $alice, $bob): array {
					$captured[] = ['pattern' => $pattern, 'limit' => $limit, 'offset' => $offset];
					return array_slice([$alice, $bob], (int)$offset, ($limit ?? 2));
				}
			);
		$this->userManager->method('countUsersTotal')->willReturn(2);
		$this->userManager->method('countDisabledUsers')->willReturn(0);
		$this->groupManager->method('getUserGroupIds')->willReturn([]);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(limit: 10, offset: 0);

		// The backend was asked for a bounded, non-null limit.
		$this->assertNotEmpty($captured);
		$this->assertNotNull($captured[0]['limit']);
		$this->assertGreaterThanOrEqual(10, $captured[0]['limit']);
		$this->assertSame(0, $captured[0]['offset']);
		$this->assertSame('', $captured[0]['pattern']);

		// Envelope semantics unchanged from the caller's point of view.
		$this->assertSame(2, $result['total']);
		$this->assertFalse($result['hasMore']);
		$this->assertSame(['alice', 'bob'], array_column($result['users'], 'uid'));
	}//end testDisplayNameSortUsesBoundedSearchNotFullScan()

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	// ---------------------------------------------------------------
	// Search and custom fields (widgets-people-expertise-and-fields)
	// ---------------------------------------------------------------

	/**
	 * REQ-PEX-003 scenario "Sanne finds the subsidies expert": Pieter is not
	 * on the first page and his name does not contain the query; his tag does.
	 *
	 * @return void
	 */
	public function testSearchFindsPieterByHisTagAcrossTheDirectory(): void {
		$pieter = $this->makeUser(uid: 'pieter', display: 'Pieter de Vries');
		$anna = $this->makeUser(uid: 'anna', display: 'Anna Subsidiemedewerker');
		$this->userManager->expects($this->once())->method('search')
			->with('subsidie', PeopleWidgetService::SEARCH_CANDIDATE_CAP)
			->willReturn([$anna]);
		$this->userManager->method('get')->willReturnMap([['pieter', $pieter]]);
		$this->userManager->expects($this->never())->method('searchDisplayName');
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$this->profileFields->saveDefinitions(raw: [['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'searchable' => true]]);
		$this->profileFields->saveOwnValues(userId: 'pieter', values: ['expertise' => ['subsidies', 'Omgevingswet']]);

		$result = $this->service->listUsers(query: 'subsidie', viewerId: 'sanne');

		$uids = array_column(array: $result['users'], column_key: 'uid');
		$this->assertSame(expected: ['anna', 'pieter'], actual: $uids);
		$this->assertSame(expected: 2, actual: $result['total']);
		$this->assertSame(
			expected: [['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'values' => ['subsidies', 'Omgevingswet']]],
			actual: $result['users'][1]['customFields']
		);
		$this->assertSame(expected: [], actual: $result['users'][0]['customFields']);
	}//end testSearchFindsPieterByHisTagAcrossTheDirectory()

	public function testAOneCharacterQueryIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->listUsers(query: 's', viewerId: 'sanne');
	}//end testAOneCharacterQueryIsRefused()

	public function testSearchStaysInsideTheWidgetsGroups(): void {
		$pieter = $this->makeUser(uid: 'pieter', display: 'Pieter');
		$karin = $this->makeUser(uid: 'karin', display: 'Karin');
		$this->userManager->method('search')->willReturn([$pieter, $karin]);
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getUsers')->willReturn([$karin]);
		$this->groupManager->method('get')->willReturn($group);
		$this->accountManager->method('getAccount')->willReturn($this->emptyAccount());

		$result = $this->service->listUsers(
			filters: [['fieldName' => 'group', 'operator' => 'in', 'values' => ['kcc']]],
			query: 'ie',
			viewerId: 'sanne'
		);

		$this->assertSame(expected: ['karin'], actual: array_column(array: $result['users'], column_key: 'uid'));
	}//end testSearchStaysInsideTheWidgetsGroups()

	/**
	 * REQ-PEX-004 scenario "Private biography is not searchable": Karin does
	 * not match on her private biography, and her card does not show it.
	 *
	 * @return void
	 */
	public function testAPrivateBiographyNeitherMatchesNorShows(): void {
		$karin = $this->makeUser(uid: 'karin', display: 'Karin');
		$account = $this->makeAccount(
			properties: [
				IAccountManager::PROPERTY_BIOGRAPHY => 'Ik weet alles van subsidies',
				IAccountManager::PROPERTY_ROLE => 'Beleidsmedewerker',
			],
			scopes: [IAccountManager::PROPERTY_BIOGRAPHY => IAccountManager::SCOPE_PRIVATE]
		);
		$this->accountManager->method('getAccount')->willReturn($account);
		$this->profileFields->mirrorStandardFields(user: $karin);

		$this->userManager->method('search')->willReturn([]);
		$this->userManager->method('get')->willReturnMap([['karin', $karin]]);
		$searched = $this->service->listUsers(query: 'subsidies', viewerId: 'sanne');
		$this->assertSame(expected: [], actual: $searched['users'], message: 'Karin matched on her private biography');

		$this->wireDirectory(orderedUsers: [$karin]);
		$card = $this->service->listUsers(viewerId: 'sanne')['users'][0];
		$this->assertArrayNotHasKey(key: 'biography', array: $card);
		$this->assertSame(expected: 'Beleidsmedewerker', actual: $card['role'], message: 'CONTROL: a local field still shows');

		$own = $this->service->listUsers(viewerId: 'karin')['users'][0];
		$this->assertSame(expected: 'Ik weet alles van subsidies', actual: $own['biography'], message: 'CONTROL: Karin sees her own biography');
	}//end testAPrivateBiographyNeitherMatchesNorShows()

	/**
	 * Wire the user-directory backend for a no-group-filter,
	 * display-name-sorted listing: a bounded `searchDisplayName` that
	 * honours the streamed `limit`/`offset` window plus the
	 * `countUsersTotal`/`countDisabledUsers` counters the bounded path
	 * uses to size `total` without a full scan.
	 *
	 * @param IUser[] $orderedUsers Users in display-name order (enabled
	 *                              and disabled). `countUsersTotal`
	 *                              reports the full length.
	 * @param int $disabledCount Number of disabled users in the set.
	 *
	 * @return void
	 */
	private function wireDirectory(array $orderedUsers, int $disabledCount = 0): void {
		$this->userManager->method('searchDisplayName')
			->willReturnCallback(
				static function (string $pattern, ?int $limit = null, ?int $offset = null) use ($orderedUsers): array {
					return array_slice(
						$orderedUsers,
						(int)$offset,
						($limit ?? count($orderedUsers))
					);
				}
			);
		$this->userManager->method('countUsersTotal')->willReturn(count($orderedUsers));
		$this->userManager->method('countDisabledUsers')->willReturn($disabledCount);
	}//end wireDirectory()

	/**
	 * @param string $uid The user id.
	 * @param string $display Display name.
	 * @param string $email Email or empty string.
	 * @param bool $enabled Whether the user is enabled.
	 *
	 * @return IUser&MockObject
	 */
	private function makeUser(
		string $uid,
		string $display,
		string $email = '',
		bool $enabled = true,
	): IUser {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($display);
		$user->method('getEMailAddress')->willReturn($email === '' ? null : $email);
		$user->method('isEnabled')->willReturn($enabled);
		return $user;
	}//end makeUser()

	/**
	 * Build an account whose every property returns the empty string —
	 * matches the "no profile fields set" baseline.
	 *
	 * @return IAccount&MockObject
	 */
	private function emptyAccount(): IAccount {
		return $this->makeAccount(properties: []);
	}//end emptyAccount()

	/**
	 * @param array<string, string> $properties Map of property name → value.
	 *                                          Properties absent from the map
	 *                                          resolve to the empty string.
	 *
	 * @return IAccount&MockObject
	 */
	private function makeAccount(array $properties, array $scopes = []): IAccount {
		$account = $this->createMock(originalClassName: IAccount::class);
		$account->method('getProperty')
			->willReturnCallback(
				callback: function (string $name) use ($properties, $scopes): IAccountProperty {
					$prop = $this->createMock(originalClassName: IAccountProperty::class);
					$prop->method('getValue')->willReturn($properties[$name] ?? '');
					$prop->method('getName')->willReturn($name);
					$prop->method('getScope')->willReturn($scopes[$name] ?? IAccountManager::SCOPE_LOCAL);
					return $prop;
				}
			);

		return $account;
	}//end makeAccount()
}//end class
