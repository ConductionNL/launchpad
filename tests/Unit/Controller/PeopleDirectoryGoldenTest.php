<?php

/**
 * People directory golden test
 *
 * Pins what the People widget endpoint returns for each viewer and
 * request: the page, the total, the fields shown, the viewer every
 * visibility and search lookup ran for, and every directory read, against
 * a recorded fixture.
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

use OCA\LaunchPad\Controller\PeopleWidgetController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Directory pages, viewer by viewer.
 *
 * @covers \OCA\LaunchPad\Controller\PeopleWidgetController
 * @covers \OCA\LaunchPad\Service\PeopleWidgetService
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Service\PeopleQuery
 * @uses   \OCA\LaunchPad\Service\PeoplePage
 */
class PeopleDirectoryGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/people-directory.json';

	/**
	 * Users: display name, enabled, email, groups, phone scope, birthdate.
	 */
	private const USERS = [
		'anna' => ['Anna', true, 'anna@example.test', ['staff'], 'v2-federated', '1990-05-06'],
		'bart' => ['bart', true, '', ['staff', 'hr'], 'v2-private', '06-07-1985'],
		'cees' => ['Cees', false, 'cees@example.test', ['hr'], 'v2-local', null],
		'dirk' => ['Dirk', true, null, [], 'v2-private', 'not a date'],
		'eva' => ['Eva', true, 'eva@example.test', ['Staff'], 'v2-federated', '2000-02-29'],
		'fien' => ['Fien', false, 'fien@example.test', ['staff'], 'v2-local', '1970-12-31'],
	];

	/**
	 * Every request matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testPagesMatchTheRecordedFixture(): void {
		$group = static fn (array $values): string => json_encode([['fieldName' => 'group', 'operator' => 'in', 'values' => $values]]);
		$filters = [
			'none' => null,
			'empty' => '',
			'empty list' => '[]',
			'staff' => $group(['staff']),
			'hr and staff' => $group(['hr', 'staff', 'hr']),
			'no groups' => $group([]),
			'ghost and blank' => $group(['ghost', '', 7]),
			'other field' => json_encode([['fieldName' => 'role', 'operator' => 'in', 'values' => ['x']]]),
			'malformed' => '{',
			'scalar' => '"x"',
		];

		$actual = [];
		foreach ($filters as $filterName => $filter) {
			foreach (['displayName', 'group', 'recent-activity', null, 'foo'] as $sort) {
				foreach ([null, 'e', 'ee', 'an', '  '] as $q) {
					foreach ([1, 0] as $excludeDisabled) {
						$name = "filter $filterName sort " . json_encode($sort) . ' q ' . json_encode($q) . " excl $excludeDisabled";
						$actual[$name] = $this->request(viewer: 'anna', params: [$filter, $excludeDisabled, 1, $sort, 2, 1, $q]);
					}
				}
			}
		}

		foreach (['none' => null, 'staff' => $group(['staff'])] as $filterName => $filter) {
			foreach ([1, 2, 50, 100, 0, 101, null] as $limit) {
				foreach ([0, 1, 4, 9, -1, null] as $offset) {
					$name = "page $filterName limit " . json_encode($limit) . ' offset ' . json_encode($offset);
					$actual[$name] = $this->request(viewer: 'eva', params: [$filter, null, null, 'displayName', $limit, $offset, null]);
				}
			}
		}

		foreach (['anna', 'bart', 'eva', 'blocked', 'ghost', null] as $viewer) {
			foreach ([1, 0, null] as $birthdays) {
				foreach ([null, 'ee'] as $q) {
					foreach (['group', 'displayName'] as $sort) {
						$name = 'viewer ' . json_encode($viewer) . " birthdays " . json_encode($birthdays) . ' q ' . json_encode($q) . " sort $sort";
						$actual[$name] = $this->request(viewer: $viewer, params: [null, 0, $birthdays, $sort, 50, 0, $q]);
					}
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
	 * Run one directory request for a session viewer and record it.
	 *
	 * The viewer "ghost" has a session id but no user object; "blocked" is
	 * refused by the action check.
	 *
	 * @param string|null       $viewer The session user, or none.
	 * @param array<int, mixed> $params filters, excludeDisabled, showBirthdays, sortBy, limit, offset, q.
	 *
	 * @return array<string, mixed>
	 */
	private function request(?string $viewer, array $params): array {
		$log = [];
		$users = [];
		foreach (self::USERS as $uid => [$name, $enabled, $email]) {
			$user = $this->getMockBuilder(className: IUser::class)->getMock();
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn($name);
			$user->method('isEnabled')->willReturn($enabled);
			$user->method('getEMailAddress')->willReturn($email);
			$users[$uid] = $user;
		}

		$byName = $users;
		uasort($byName, static fn (IUser $a, IUser $b): int => strcasecmp($a->getDisplayName(), $b->getDisplayName()));

		$directory = $this->getMockBuilder(className: IUserManager::class)->getMock();
		$directory->method('search')->willReturnCallback(static function (string $pattern, ?int $limit=null, ?int $offset=null) use (&$log, $users): array {
			$log[] = ['search', $pattern, $limit, $offset];
			return array_values(array_filter($users, static fn (IUser $user): bool => $pattern === '' || stripos($user->getDisplayName(), $pattern) !== false));
		});
		$directory->method('searchDisplayName')->willReturnCallback(static function (string $pattern, ?int $limit=null, ?int $offset=null) use (&$log, $byName): array {
			$log[] = ['searchDisplayName', $pattern, $limit, $offset];
			return array_slice(array_values($byName), (int)$offset, $limit);
		});
		$directory->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $users[$uid] ?? null);
		$directory->method('countUsersTotal')->willReturn(count($users));
		$directory->method('countDisabledUsers')->willReturn(2);

		$groups = $this->getMockBuilder(className: IGroupManager::class)->getMock();
		$groups->method('get')->willReturnCallback(function (string $gid) use ($users): ?IGroup {
			$members = array_values(array_filter($users, static fn (IUser $user): bool => in_array($gid, self::USERS[$user->getUID()][3], true)));
			if ($members === []) {
				return null;
			}

			$group = $this->getMockBuilder(className: IGroup::class)->getMock();
			$group->method('getUsers')->willReturn($members);
			return $group;
		});

		$accounts = $this->getMockBuilder(className: IAccountManager::class)->getMock();
		$accounts->method('getAccount')->willReturnCallback(function (IUser $user): IAccount {
			[, , , , $scope, $birthdate] = self::USERS[$user->getUID()];
			$account = $this->getMockBuilder(className: IAccount::class)->getMock();
			$account->method('getProperty')->willReturnCallback(function (string $property) use ($scope, $birthdate, $user): IAccountProperty {
				$values = [IAccountManager::PROPERTY_PHONE => '+31 ' . $user->getUID(), IAccountManager::PROPERTY_BIRTHDATE => $birthdate, IAccountManager::PROPERTY_ROLE => ''];
				if (array_key_exists($property, $values) === false) {
					throw new \OCP\Accounts\PropertyDoesNotExistException($property);
				}

				$prop = $this->getMockBuilder(className: IAccountProperty::class)->getMock();
				$prop->method('getValue')->willReturn((string)$values[$property]);
				$prop->method('getScope')->willReturn($scope);
				return $prop;
			});
			return $account;
		});

		$urls = $this->getMockBuilder(className: IURLGenerator::class)->getMock();
		$urls->method('linkToRouteAbsolute')->willReturnCallback(static fn (string $route, array $args=[]): string => $route . '?' . http_build_query($args));

		$templates = $this->getMockBuilder(className: AdminTemplateService::class)->disableOriginalConstructor()->getMock();
		$templates->method('getUserGroupIdsFor')->willReturnCallback(static fn (string $userId): array => self::USERS[$userId][3]);

		$fields = $this->getMockBuilder(className: ProfileFieldService::class)->disableOriginalConstructor()->getMock();
		$fields->method('scopeAllows')->willReturnCallback(static fn (?string $scope, ?string $viewerId, string $ownerId): bool => $viewerId === $ownerId || ($viewerId !== null && $scope !== 'v2-private'));
		$fields->method('findMatchingUserIds')->willReturnCallback(static function (?string $viewerId, string $query) use (&$log): array {
			$log[] = ['findMatchingUserIds', $viewerId, $query];
			return str_contains($query, 'ee') ? ['fien', 'nobody', 'cees', 'dirk'] : [];
		});
		$fields->method('visibleFieldsFor')->willReturnCallback(static function (?string $viewerId, array $userIds) use (&$log): array {
			$log[] = ['visibleFieldsFor', $viewerId, $userIds];
			return array_fill_keys(array_slice($userIds, 0, 1), ['team' => 'seen by ' . $viewerId]);
		});

		$service = new PeopleWidgetService(
			userManager: $directory,
			groupManager: $groups,
			accountManager: $accounts,
			urlGenerator: $urls,
			adminTemplateService: $templates,
			profileFields: $fields
		);

		$session = $this->getMockBuilder(className: IUserSession::class)->getMock();
		$session->method('getUser')->willReturn($users[$viewer] ?? ($viewer === 'blocked' ? $this->blockedUser() : null));

		$auth = $this->getMockBuilder(className: ActionAuthService::class)->disableOriginalConstructor()->getMock();
		$auth->method('requireAction')->willReturnCallback(static function (IUser $user, string $action) use (&$log): void {
			$log[] = ['requireAction', $user->getUID(), $action];
			if ($user->getUID() === 'blocked') {
				throw new OCSForbiddenException('no');
			}
		});

		$controller = new PeopleWidgetController(
			request: $this->getMockBuilder(className: IRequest::class)->getMock(),
			service: $service,
			actionAuth: $auth,
			userSession: $session,
			logger: $this->getMockBuilder(className: LoggerInterface::class)->getMock(),
			userId: $viewer
		);

		[$filters, $excludeDisabled, $showBirthdays, $sortBy, $limit, $offset, $q] = $params;
		$response = $controller->getUsers(filters: $filters, excludeDisabled: $excludeDisabled, showBirthdays: $showBirthdays, sortBy: $sortBy, limit: $limit, offset: $offset, q: $q);

		return ['status' => $response->getStatus(), 'body' => $response->getData(), 'log' => $log];
	}

	/**
	 * A signed-in user the action check refuses.
	 *
	 * @return IUser
	 */
	private function blockedUser(): IUser {
		$user = $this->getMockBuilder(className: IUser::class)->getMock();
		$user->method('getUID')->willReturn('blocked');
		return $user;
	}
}
