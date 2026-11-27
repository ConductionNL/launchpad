<?php

/**
 * Unit tests for the New colleagues selection of REQ-PPL-014: the people
 * whose start date lies between `since` days ago and `until` days ahead,
 * newest first, only when the viewer may see the date, without disabled
 * accounts, and nothing when no field is the start date.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\NewColleaguesController;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\UserResponses;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Db\ProfileValueMapper;
use OCA\LaunchPad\Service\NewColleagues;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\IRequest;
use OCP\IUser;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

/**
 * Yasmina starts Monday, Lotte and Thomas started; Ella's date is hidden.
 */
class NewColleaguesTest extends TestCase {

	/** @var array{from: string, to: string}|null The range the mapper was asked for. */
	private ?array $askedRange = null;

	/**
	 * A stored start date.
	 *
	 * @param string      $userId The person.
	 * @param string      $date   The date.
	 * @param string|null $scope  The value's own visibility scope.
	 *
	 * @return ProfileValue The row.
	 */
	private static function row(string $userId, string $date, ?string $scope=null): ProfileValue {
		$row = new ProfileValue();
		$row->setUserId($userId);
		$row->setFieldKey('joined');
		$row->setValue($date);
		$row->setScope($scope);

		return $row;
	}//end row()

	/**
	 * The service over a start-date field and four people.
	 *
	 * @param array<int, array<string, mixed>> $definitions The field definitions.
	 * @param PeopleWidgetService|null         $people      The people widget's profile, or none.
	 *
	 * @return NewColleagues The service.
	 */
	private function colleagues(array $definitions, ?PeopleWidgetService $people=null): NewColleagues {
		$fields = $this->createMock(originalClassName: ProfileFieldService::class);
		$fields->method('getDefinitions')->willReturn($definitions);
		$fields->method('canSee')->willReturnCallback(
			fn (?string $viewerId, string $ownerId): bool => $ownerId !== 'ella'
		);
		$fields->method('scopeAllows')->willReturnCallback(
			fn (?string $scope): bool => $scope !== 'private'
		);
		$values = $this->createMock(originalClassName: ProfileValueMapper::class);
		$values->method('findDatesBetween')->willReturnCallback(
			function (string $fieldKey, array $range): array {
				$this->askedRange = $range;
				return [
					self::row(userId: 'yasmina', date: '2026-10-12'),
					self::row(userId: 'ella', date: '2026-10-08'),
					self::row(userId: 'gone', date: '2026-10-02'),
					self::row(userId: 'lotte', date: '2026-10-01'),
					self::row(userId: 'thomas', date: '2026-09-28'),
					self::row(userId: 'lotte', date: '2026-09-20', scope: 'private'),
				];
			}
		);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			$names = ['yasmina' => 'Yasmina El Amrani', 'lotte' => 'Lotte Verhoeven', 'thomas' => 'Thomas Brouwer', 'gone' => 'Gone'];
			if (isset($names[$uid]) === false) {
				return null;
			}

			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getDisplayName')->willReturn($names[$uid]);
			$user->method('isEnabled')->willReturn($uid !== 'gone');
			return $user;
		});

		return new NewColleagues(fields: $fields, values: $values, users: $users, people: $people);
	}//end colleagues()

	/**
	 * Scenario "Three new colleagues on the intranet".
	 *
	 * @return void
	 */
	public function testThreeNewColleaguesNewestFirst(): void {
		$answer = $this->colleagues(definitions: [['key' => 'office'], ['key' => 'joined', 'startDate' => true]])
			->listNew(viewerId: 'pieter', window: ['today' => '2026-10-05']);

		$this->assertTrue(condition: $answer['configured']);
		$this->assertSame(expected: ['from' => '2026-09-05', 'to' => '2026-10-19'], actual: $this->askedRange);
		$this->assertSame(
			expected: [
				['id' => 'yasmina', 'displayName' => 'Yasmina El Amrani', 'startDate' => '2026-10-12', 'starts' => true],
				['id' => 'lotte', 'displayName' => 'Lotte Verhoeven', 'startDate' => '2026-10-01', 'starts' => false],
				['id' => 'thomas', 'displayName' => 'Thomas Brouwer', 'startDate' => '2026-09-28', 'starts' => false],
			],
			actual: $answer['users']
		);
	}//end testThreeNewColleaguesNewestFirst()

	/**
	 * Each new colleague carries photo, role and team from the people widget's
	 * profile, built for the viewer so the owner's scopes apply to them.
	 *
	 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 *
	 * @return void
	 */
	public function testEachNewColleagueCarriesPhotoRoleAndTeam(): void {
		$people = $this->createMock(originalClassName: PeopleWidgetService::class);
		$people->method('buildUserProfile')
			->with($this->anything(), false, 'pieter')
			->willReturn(
			['uid' => 'x', 'avatarUrl' => '/avatar/x/128', 'role' => 'Beleidsadviseur', 'organisation' => 'Team Wonen', 'email' => 'x@example.org']
		);
		$withPeople = $this->colleagues(definitions: [['key' => 'joined', 'startDate' => true]], people: $people);

		$first = $withPeople->listNew(viewerId: 'pieter', window: ['today' => '2026-10-05'])['users'][0];

		$this->assertSame(
			expected: [
				'id' => 'yasmina',
				'displayName' => 'Yasmina El Amrani',
				'avatarUrl' => '/avatar/x/128',
				'role' => 'Beleidsadviseur',
				'organisation' => 'Team Wonen',
				'startDate' => '2026-10-12',
				'starts' => true,
			],
			actual: $first
		);
	}//end testEachNewColleagueCarriesPhotoRoleAndTeam()

	/**
	 * The windows are clamped to 1..90 days back and 0..30 ahead.
	 *
	 * @return void
	 */
	public function testTheWindowsAreClamped(): void {
		$answer = $this->colleagues(definitions: [['key' => 'joined', 'startDate' => true]])
			->listNew(viewerId: 'pieter', window: ['since' => 500, 'until' => -3, 'today' => '2026-10-05']);

		$this->assertSame(expected: [90, 0], actual: [$answer['since'], $answer['until']]);
		$this->assertSame(expected: ['from' => '2026-07-07', 'to' => '2026-10-05'], actual: $this->askedRange);
	}//end testTheWindowsAreClamped()

	/**
	 * Scenario "No start date field": nobody, and `configured` says why.
	 *
	 * @return void
	 */
	public function testWithoutAStartDateFieldNobody(): void {
		$answer = $this->colleagues(definitions: [['key' => 'office']])->listNew(viewerId: 'pieter', window: ['today' => '2026-10-05']);

		$this->assertFalse(condition: $answer['configured']);
		$this->assertSame(expected: [], actual: $answer['users']);
		$this->assertNull(actual: $this->askedRange);
	}//end testWithoutAStartDateFieldNobody()

	/**
	 * The controller for a caller (or none) the action check allows or refuses.
	 *
	 * @param string|null $userId  The caller.
	 * @param bool        $allowed Whether the action check lets them list people.
	 *
	 * @return NewColleaguesController The controller.
	 */
	private function controller(?string $userId, bool $allowed): NewColleaguesController {
		$user = $this->createMock(originalClassName: IUser::class);
		$session = $this->createMock(originalClassName: IUserSession::class);
		if ($userId !== null) {
			$session->method('getUser')->willReturn($user);
		}
		$auth = $this->createMock(originalClassName: ActionAuthService::class);
		if ($allowed === false) {
			$auth->method('requireAction')->willThrowException(new OCSForbiddenException());
		}

		return new NewColleaguesController(
			request: $this->createMock(originalClassName: IRequest::class),
			colleagues: $this->colleagues(definitions: [['key' => 'joined', 'startDate' => true]]),
			context: new ActorResponses(
				actor: new RequestActor(userSession: $session, actionAuth: $auth),
				responses: new UserResponses(userId: $userId, responseHelper: new ResponseHelper(logger: new NullLogger()))
			)
		);
	}//end controller()

	/**
	 * The route answers 401 without a caller, 403 when the action check
	 * refuses, and the list otherwise.
	 *
	 * @return void
	 */
	public function testTheRouteChecksTheCaller(): void {
		$this->assertSame(expected: 401, actual: $this->controller(userId: null, allowed: true)->getNew()->getStatus());
		$this->assertSame(expected: 403, actual: $this->controller(userId: 'pieter', allowed: false)->getNew()->getStatus());

		$ok = $this->controller(userId: 'pieter', allowed: true)->getNew(since: 30, until: 14);
		$this->assertSame(expected: 200, actual: $ok->getStatus());
		$this->assertTrue(condition: $ok->getData()['configured']);
	}//end testTheRouteChecksTheCaller()

}//end class
