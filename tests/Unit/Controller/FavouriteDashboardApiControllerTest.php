<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Star and unstar answer the list, 404 for a dashboard the person cannot
 * open, 401 without a session (more-menu-favourites, REQ-SHELL-016).
 *
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-11
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\FavouriteDashboardApiController;
use OCA\LaunchPad\Service\FavouriteDashboardService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\FavouriteDashboardApiController
 */
class FavouriteDashboardApiControllerTest extends TestCase {

	/**
	 * The favourites service double.
	 *
	 * @var FavouriteDashboardService&MockObject
	 */
	private FavouriteDashboardService $favourites;

	/**
	 * The session double.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession $session;

	/**
	 * The controller under test.
	 *
	 * @var FavouriteDashboardApiController
	 */
	private FavouriteDashboardApiController $controller;

	/**
	 * The controller on doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->favourites = $this->createMock(originalClassName: FavouriteDashboardService::class);
		$this->session    = $this->createMock(originalClassName: IUserSession::class);
		$this->controller = new FavouriteDashboardApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			favourites: $this->favourites,
			userSession: $this->session
		);
	}//end setUp()

	/**
	 * Sign alice in.
	 *
	 * @return void
	 */
	private function signIn(): void {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->session->method('getUser')->willReturn($user);
	}//end signIn()

	/**
	 * Starring answers the list.
	 *
	 * @return void
	 */
	public function testStarAnswersTheList(): void {
		$this->signIn();
		$this->favourites->expects($this->once())->method('star')->with('alice', 'a')->willReturn(['a']);
		$response = $this->controller->star(uuid: 'a');
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: ['favourites' => ['a']], actual: $response->getData());
	}//end testStarAnswersTheList()

	/**
	 * Unstarring answers the list.
	 *
	 * @return void
	 */
	public function testUnstarAnswersTheList(): void {
		$this->signIn();
		$this->favourites->expects($this->once())->method('unstar')->with('alice', 'a')->willReturn([]);
		$this->assertSame(expected: ['favourites' => []], actual: $this->controller->unstar(uuid: 'a')->getData());
	}//end testUnstarAnswersTheList()

	/**
	 * A dashboard the person cannot open is 404.
	 *
	 * @return void
	 */
	public function testADashboardThePersonCannotOpenIs404(): void {
		$this->signIn();
		$this->favourites->method('star')->willThrowException(new DoesNotExistException(msg: 'x'));
		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $this->controller->star(uuid: 'secret')->getStatus());
	}//end testADashboardThePersonCannotOpenIs404()

	/**
	 * Without a session the answer is 401 and nothing changes.
	 *
	 * @return void
	 */
	public function testWithoutASessionIs401(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->favourites->expects($this->never())->method('unstar');
		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $this->controller->unstar(uuid: 'a')->getStatus());
	}//end testWithoutASessionIs401()
}//end class
