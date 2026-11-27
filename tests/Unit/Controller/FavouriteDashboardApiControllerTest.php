<?php

/**
 * Unit tests for FavouriteDashboardApiController (more-menu-favourites, REQ-SHELL-016).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-11
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
 * Star and unstar answer the list, 404 for a dashboard the person cannot open, 401 without a session.
 */
class FavouriteDashboardApiControllerTest extends TestCase {
	/** @var FavouriteDashboardService&MockObject */
	private $favourites;

	/** @var IUserSession&MockObject */
	private $session;

	private FavouriteDashboardApiController $controller;

	protected function setUp(): void {
		$this->favourites = $this->createMock(FavouriteDashboardService::class);
		$this->session    = $this->createMock(IUserSession::class);
		$this->controller = new FavouriteDashboardApiController(
			$this->createMock(IRequest::class),
			$this->favourites,
			$this->session
		);
	}

	private function signIn(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->session->method('getUser')->willReturn($user);
	}

	public function testStarAnswersTheList(): void {
		$this->signIn();
		$this->favourites->expects($this->once())->method('star')->with('alice', 'a')->willReturn(['a']);
		$response = $this->controller->star('a');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['favourites' => ['a']], $response->getData());
	}

	public function testUnstarAnswersTheList(): void {
		$this->signIn();
		$this->favourites->expects($this->once())->method('unstar')->with('alice', 'a')->willReturn([]);
		$this->assertSame(['favourites' => []], $this->controller->unstar('a')->getData());
	}

	public function testADashboardThePersonCannotOpenIs404(): void {
		$this->signIn();
		$this->favourites->method('star')->willThrowException(new DoesNotExistException('x'));
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->star('secret')->getStatus());
	}

	public function testWithoutASessionIs401(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->favourites->expects($this->never())->method('unstar');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller->unstar('a')->getStatus());
	}
}
