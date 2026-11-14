<?php

/**
 * DashboardReactionApiController Test
 *
 * Covers M4: ADR-023 action-auth wiring on all four reaction endpoints.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ReactionTools;
use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\DashboardReactionApiController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ReactionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for DashboardReactionApiController (M4 ADR-023 wiring).
 */
class DashboardReactionApiControllerTest extends TestCase {
	/** @var IRequest&MockObject */
	private $request;
	/** @var ReactionService&MockObject */
	private $reactionService;
	/** @var ActionAuthService&MockObject */
	private $actionAuth;
	/** @var IUserSession&MockObject */
	private $userSession;
	/** @var LoggerInterface&MockObject */
	private $logger;
	/** @var IUser&MockObject */
	private $user;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->reactionService = $this->createMock(ReactionService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($this->user);
	}//end setUp()

	private function makeController(?string $userId = 'user1'): DashboardReactionApiController {
		return new DashboardReactionApiController(
			request: $this->request,
			reactions: new ReactionTools(
				reactionService: $this->reactionService,
				logger: $this->logger
			),
			caller: new ActionCaller(
				actionAuth: $this->actionAuth,
				userSession: $this->userSession
			),
			userId: $userId
		);
	}//end makeController()

	/**
	 * M4: getReactions() must call requireAction with the correct action name.
	 */
	public function testGetReactionsCallsRequireAction(): void {
		$this->reactionService
			->method('getReactionsSummary')
			->willReturn(['counts' => [], 'mine' => [], 'enabled' => true]);

		$this->actionAuth
			->expects($this->once())
			->method('requireAction')
			->with($this->user, 'dashboard-reaction.get-reactions');

		$controller = $this->makeController();
		$response = $controller->getReactions('some-uuid');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testGetReactionsCallsRequireAction()

	/**
	 * M4: getReactions() must propagate OCSForbiddenException when denied.
	 */
	public function testGetReactionsPropagatesForbiddenWhenActionDenied(): void {
		$this->actionAuth
			->method('requireAction')
			->willThrowException(new OCSForbiddenException('Forbidden'));

		$this->expectException(OCSForbiddenException::class);

		$controller = $this->makeController();
		$controller->getReactions('some-uuid');
	}//end testGetReactionsPropagatesForbiddenWhenActionDenied()

	/**
	 * M4: addReaction() must call requireAction with the correct action name.
	 */
	public function testAddReactionCallsRequireAction(): void {
		$this->reactionService
			->method('addReaction')
			->willReturn(['counts' => [], 'mine' => [], 'enabled' => true]);

		$this->actionAuth
			->expects($this->once())
			->method('requireAction')
			->with($this->user, 'dashboard-reaction.add-reaction');

		$controller = $this->makeController();
		$response = $controller->addReaction('some-uuid', '👍');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testAddReactionCallsRequireAction()

	/**
	 * M4: addReaction() must propagate OCSForbiddenException when denied.
	 */
	public function testAddReactionPropagatesForbiddenWhenActionDenied(): void {
		$this->actionAuth
			->method('requireAction')
			->willThrowException(new OCSForbiddenException('Forbidden'));

		$this->expectException(OCSForbiddenException::class);

		$controller = $this->makeController();
		$controller->addReaction('some-uuid', '👍');
	}//end testAddReactionPropagatesForbiddenWhenActionDenied()

	/**
	 * M4: removeReaction() must call requireAction with the correct action name.
	 */
	public function testRemoveReactionCallsRequireAction(): void {
		$this->reactionService->method('removeReaction')->willReturn(true);

		$this->actionAuth
			->expects($this->once())
			->method('requireAction')
			->with($this->user, 'dashboard-reaction.remove-reaction');

		$controller = $this->makeController();
		$response = $controller->removeReaction('some-uuid', '👍');

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}//end testRemoveReactionCallsRequireAction()

	/**
	 * M4: removeReaction() must propagate OCSForbiddenException when denied.
	 */
	public function testRemoveReactionPropagatesForbiddenWhenActionDenied(): void {
		$this->actionAuth
			->method('requireAction')
			->willThrowException(new OCSForbiddenException('Forbidden'));

		$this->expectException(OCSForbiddenException::class);

		$controller = $this->makeController();
		$controller->removeReaction('some-uuid', '👍');
	}//end testRemoveReactionPropagatesForbiddenWhenActionDenied()

	/**
	 * M4: getReactorsByEmoji() must call requireAction with the correct action name.
	 */
	public function testGetReactorsByEmojiCallsRequireAction(): void {
		$this->reactionService
			->method('getReactorsByEmoji')
			->willReturn(['items' => [], 'nextCursor' => null]);

		$this->actionAuth
			->expects($this->once())
			->method('requireAction')
			->with($this->user, 'dashboard-reaction.get-reactors-by-emoji');

		$controller = $this->makeController();
		$response = $controller->getReactorsByEmoji('some-uuid', '👍');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testGetReactorsByEmojiCallsRequireAction()

	/**
	 * M4: getReactorsByEmoji() must propagate OCSForbiddenException when denied.
	 */
	public function testGetReactorsByEmojiPropagatesForbiddenWhenActionDenied(): void {
		$this->actionAuth
			->method('requireAction')
			->willThrowException(new OCSForbiddenException('Forbidden'));

		$this->expectException(OCSForbiddenException::class);

		$controller = $this->makeController();
		$controller->getReactorsByEmoji('some-uuid', '👍');
	}//end testGetReactorsByEmojiPropagatesForbiddenWhenActionDenied()

	/**
	 * All endpoints must return 401 when no authenticated user exists.
	 */
	public function testGetReactionsReturns401WhenUnauthenticated(): void {
		$anonSession = $this->createMock(IUserSession::class);
		$anonSession->method('getUser')->willReturn(null);

		$controller = new DashboardReactionApiController(
			request: $this->request,
			reactions: new ReactionTools(
				reactionService: $this->reactionService,
				logger: $this->logger
			),
			caller: new ActionCaller(
				actionAuth: $this->actionAuth,
				userSession: $anonSession
			),
			userId: null
		);
		$response = $controller->getReactions('some-uuid');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}//end testGetReactionsReturns401WhenUnauthenticated()

	/**
	 * Every failure answer of the four endpoints, with the logged message,
	 * and the 401 of each without a session user.
	 *
	 * @return void
	 */
	public function testFailureAnswersOfEveryEndpoint(): void {
		$failures = [
			new \OCP\AppFramework\Db\DoesNotExistException('gone'),
			new \OCA\LaunchPad\Service\PermissionDeniedException('no view'),
			new \OCA\LaunchPad\Service\ReactionsDisabledException('disabled'),
			new \InvalidArgumentException('bad emoji'),
			new \RuntimeException('db down'),
		];
		$calls = [
			'getReactions' => ['getReactionsSummary', fn ($c) => $c->getReactions('u')],
			'addReaction' => ['addReaction', fn ($c) => $c->addReaction('u', 'x')],
			'removeReaction' => ['removeReaction', fn ($c) => $c->removeReaction('u', 'x')],
			'getReactorsByEmoji' => ['getReactorsByEmoji', fn ($c) => $c->getReactorsByEmoji('u', 'x', null)],
		];
		$logged = [];
		$this->logger->method('error')->willReturnCallback(function (string $message) use (&$logged): void {
			$logged[] = $message;
		});

		$answers = [];
		foreach ($calls as $endpoint => [$method, $call]) {
			foreach ($failures as $failure) {
				$service = $this->createMock(ReactionService::class);
				$service->method($method)->willThrowException($failure);
				$this->reactionService = $service;
				$response = $call($this->makeController());
				$answers[] = $endpoint . ' ' . $response->getStatus() . ' ' . json_encode($response->getData());
			}
		}

		$this->assertSame(
			[
				'getReactions 404 {"error":"Dashboard not found"}',
				'getReactions 403 {"error":"no view"}',
				'getReactions 500 {"error":"Operation failed"}',
				'getReactions 500 {"error":"Operation failed"}',
				'getReactions 500 {"error":"Operation failed"}',
				'addReaction 404 {"error":"Dashboard not found"}',
				'addReaction 403 {"error":"no view"}',
				'addReaction 403 {"error":"disabled"}',
				'addReaction 400 {"error":"bad emoji"}',
				'addReaction 500 {"error":"Operation failed"}',
				'removeReaction 404 {"error":"Dashboard not found"}',
				'removeReaction 403 {"error":"no view"}',
				'removeReaction 500 {"error":"Operation failed"}',
				'removeReaction 500 {"error":"Operation failed"}',
				'removeReaction 500 {"error":"Operation failed"}',
				'getReactorsByEmoji 404 {"error":"Dashboard not found"}',
				'getReactorsByEmoji 403 {"error":"no view"}',
				'getReactorsByEmoji 500 {"error":"Operation failed"}',
				'getReactorsByEmoji 500 {"error":"Operation failed"}',
				'getReactorsByEmoji 500 {"error":"Operation failed"}',
			],
			$answers
		);
		$this->assertSame(
			[
				'getReactions failed: disabled', 'getReactions failed: bad emoji', 'getReactions failed: db down',
				'addReaction failed: db down',
				'removeReaction failed: disabled', 'removeReaction failed: bad emoji', 'removeReaction failed: db down',
				'getReactorsByEmoji failed: disabled', 'getReactorsByEmoji failed: bad emoji', 'getReactorsByEmoji failed: db down',
			],
			$logged
		);

		$anonymous = $this->createMock(IUserSession::class);
		$anonymous->method('getUser')->willReturn(null);
		$this->userSession = $anonymous;
		$this->actionAuth->expects($this->never())->method('requireAction');
		foreach ($calls as [$method, $call]) {
			$response = $call($this->makeController());
			$this->assertSame([401, ['error' => 'Not authenticated']], [$response->getStatus(), $response->getData()]);
		}
	}//end testFailureAnswersOfEveryEndpoint()
}//end class
