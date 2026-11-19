<?php

/**
 * Unit tests for the presence endpoints (building-edit-a-dashboard-together,
 * task 1.1): the answers for a signed-in editor, an unknown dashboard, a
 * person without edit rights, a refused action and no session.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardPresenceApiController;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardPresenceService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\DashboardPresenceApiController
 * @uses   \OCA\LaunchPad\Service\RequestGuard
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class DashboardPresenceApiControllerTest extends TestCase {

	/**
	 * The presence service double.
	 *
	 * @var DashboardPresenceService&MockObject
	 */
	private $presence;

	/**
	 * The action gate double.
	 *
	 * @var ActionAuthService&MockObject
	 */
	private $actionAuth;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->presence   = $this->createMock(DashboardPresenceService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
	}//end setUp()

	/**
	 * The controller for a user, or for no session.
	 *
	 * @param string|null $userId The signed-in user.
	 *
	 * @return DashboardPresenceApiController
	 */
	private function controller(?string $userId = 'sanne'): DashboardPresenceApiController {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}

		$session->method('getUser')->willReturn($user);

		return new DashboardPresenceApiController(
			request: $this->createMock(IRequest::class),
			presence: $this->presence,
			guard: new RequestGuard(userSession: $session, groupManager: $this->createMock(IGroupManager::class), actionAuth: $this->actionAuth),
			userId: $userId
		);
	}//end controller()

	/**
	 * An editor gets the colleagues editing, under the join action.
	 *
	 * @return void
	 */
	public function testJoinAnswersTheColleagues(): void {
		$colleagues = [['displayName' => 'Marieke Jansen', 'since' => '2026-11-14T10:42:00+00:00']];
		$this->actionAuth->expects(self::once())->method('requireAction')->with(self::isInstanceOf(IUser::class), 'dashboard-presence.join');
		$this->presence->expects(self::once())->method('join')->with('dash-7', 'sanne')->willReturn($colleagues);

		$response = $this->controller()->join(uuid: 'dash-7');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['editors' => $colleagues], $response->getData());
	}//end testJoinAnswersTheColleagues()

	/**
	 * An unknown dashboard is 404 and a person without edit rights 403.
	 *
	 * @return void
	 */
	public function testJoinRefusals(): void {
		$this->presence->method('join')->willReturnCallback(static function (string $uuid): array {
			if ($uuid === 'missing') {
				throw new DoesNotExistException('none');
			}

			throw new ForbiddenException(message: 'You may not edit this dashboard');
		});
		$controller = $this->controller();

		self::assertSame(Http::STATUS_NOT_FOUND, $controller->join(uuid: 'missing')->getStatus());
		$forbidden = $controller->join(uuid: 'dash-7');
		self::assertSame(Http::STATUS_FORBIDDEN, $forbidden->getStatus());
		self::assertSame('You may not edit this dashboard', $forbidden->getData()['error'] ?? null);
	}//end testJoinRefusals()

	/**
	 * Leaving takes the caller off and answers 204.
	 *
	 * @return void
	 */
	public function testLeaveAnswers204(): void {
		$this->actionAuth->expects(self::once())->method('requireAction')->with(self::isInstanceOf(IUser::class), 'dashboard-presence.leave');
		$this->presence->expects(self::once())->method('leave')->with('dash-7', 'sanne');

		self::assertSame(Http::STATUS_NO_CONTENT, $this->controller()->leave(uuid: 'dash-7')->getStatus());
	}//end testLeaveAnswers204()

	/**
	 * Without a session both are 401; a refused action is 403; neither reaches the service.
	 *
	 * @return void
	 */
	public function testNoSessionAndARefusedActionReachNothing(): void {
		$this->presence->expects(self::never())->method('join');
		$this->presence->expects(self::never())->method('leave');

		$anonymous = $this->controller(userId: null);
		self::assertSame(Http::STATUS_UNAUTHORIZED, $anonymous->join(uuid: 'dash-7')->getStatus());
		self::assertSame(Http::STATUS_UNAUTHORIZED, $anonymous->leave(uuid: 'dash-7')->getStatus());

		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		$refused = $this->controller();
		self::assertSame(Http::STATUS_FORBIDDEN, $refused->join(uuid: 'dash-7')->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $refused->leave(uuid: 'dash-7')->getStatus());
	}//end testNoSessionAndARefusedActionReachNothing()
}//end class
