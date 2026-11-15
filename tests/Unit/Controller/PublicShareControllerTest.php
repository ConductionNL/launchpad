<?php

/**
 * PublicShareControllerTest
 *
 * Unit tests for PublicShareController covering the 5 endpoints:
 * create (401/403/404/201), index (403/200), destroy (403/204),
 * show (401-password/404/200), unlock (429/200/401).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-10
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PublicShareController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\DB\Exception as DbException;
use OCP\IRequest;
use OCP\Security\Bruteforce\MaxDelayReached;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PublicShareControllerTest extends TestCase {

	/** @var IRequest&MockObject */
	private $request;

	/** @var PublicShareService&MockObject */
	private $shareService;

	/** @var PublicShareContext&MockObject */
	private $shareContext;

	/** @var LoggerInterface&MockObject */
	private $logger;

	private function makeController(?string $userId = 'alice'): PublicShareController {
		return new PublicShareController(
			request: $this->request,
			shareService: $this->shareService,
			shareContext: $this->shareContext,
			logger: $this->logger,
			userId: $userId,
		);
	}

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->shareService = $this->createMock(PublicShareService::class);
		$this->shareContext = $this->createMock(PublicShareContext::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	// -------------------------------------------------------------------------
	// create
	// -------------------------------------------------------------------------

	public function testCreateReturns401WhenNotLoggedIn(): void {
		$controller = $this->makeController(userId: null);
		$response = $controller->create(uuid: 'some-uuid');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturns403WhenNotOwner(): void {
		$this->shareService
			->method('createPublicShare')
			->willThrowException(new OCSForbiddenException('Not authorized'));

		$response = $this->makeController()->create(uuid: 'some-uuid');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testCreateReturns404WhenDashboardMissing(): void {
		$this->shareService
			->method('createPublicShare')
			->willThrowException(new DoesNotExistException('not found'));

		$response = $this->makeController()->create(uuid: 'missing-uuid');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateReturns201OnSuccess(): void {
		$share = new PublicShare();
		$share->setToken(str_repeat('a', 64));
		$share->setViewCount(0);

		$this->shareService->method('createPublicShare')->willReturn($share);

		$response = $this->makeController()->create(uuid: 'some-uuid');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// index
	// -------------------------------------------------------------------------

	public function testIndexReturns403ForNonOwner(): void {
		$this->shareService
			->method('listActiveShares')
			->willThrowException(new OCSForbiddenException('Not authorized'));

		$response = $this->makeController()->index(uuid: 'some-uuid');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testIndexReturnsEmptyArrayWhenNoShares(): void {
		$this->shareService->method('listActiveShares')->willReturn([]);

		$response = $this->makeController()->index(uuid: 'some-uuid');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	// -------------------------------------------------------------------------
	// destroy (revoke)
	// -------------------------------------------------------------------------

	public function testDestroyReturns403ForNonOwner(): void {
		$this->shareService
			->method('revokeShare')
			->willThrowException(new OCSForbiddenException('Not authorized'));

		$response = $this->makeController()->destroy(uuid: 'some-uuid', id: 7);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testDestroyReturns204OnSuccess(): void {
		$this->shareService->method('revokeShare');

		$response = $this->makeController()->destroy(uuid: 'some-uuid', id: 7);

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function testDestroyIsIdempotentOnAlreadyRevoked(): void {
		// revokeShare is a no-op on already-revoked shares.
		$this->shareService->method('revokeShare');

		$response = $this->makeController()->destroy(uuid: 'some-uuid', id: 7);

		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// show (public render)
	// -------------------------------------------------------------------------

	public function testShowReturns401WhenPasswordRequired(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->request->method('getHeader')->willReturn('');

		$this->shareService
			->method('renderShareContent')
			->willThrowException(new SharePasswordRequiredException());

		$response = $this->makeController()->show(token: 'tok');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertTrue($response->getData()['passwordRequired']);
	}

	public function testShowReturns404ForInvalidToken(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->request->method('getHeader')->willReturn('');

		$this->shareService
			->method('renderShareContent')
			->willThrowException(new ShareNotFoundException());

		$response = $this->makeController()->show(token: 'invalid');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShowReturns200WithDashboardData(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->request->method('getHeader')->willReturn('');

		$share = new PublicShare();
		$share->setToken('tok');
		$share->setViewCount(1);
		$dashboard = new Dashboard();
		$dashboard->setUserId('alice');

		$this->shareService
			->method('renderShareContent')
			->willReturn(['share' => $share, 'dashboard' => $dashboard, 'placements' => []]);

		$response = $this->makeController()->show(token: 'tok');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayHasKey('share', $response->getData());
		$this->assertArrayHasKey('dashboard', $response->getData());
		$this->assertArrayHasKey('placements', $response->getData());
	}

	// -------------------------------------------------------------------------
	// unlock (password verification)
	// -------------------------------------------------------------------------

	public function testUnlockReturns429WhenThrottled(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');

		$this->shareService
			->method('unlockShare')
			->willThrowException(new MaxDelayReached('throttled'));

		$response = $this->makeController()->unlock(
			token: 'tok',
			password: 'WrongPass'
		);

		$this->assertSame(Http::STATUS_TOO_MANY_REQUESTS, $response->getStatus());
	}

	public function testUnlockReturns200WithAccessTrueOnCorrectPassword(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->shareService->method('unlockShare')->willReturn(true);

		$response = $this->makeController()->unlock(
			token: 'tok',
			password: 'SecurePass123!'
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['access']);
	}

	public function testUnlockReturns401WithAccessFalseOnWrongPassword(): void {
		$this->request->method('getRemoteAddress')->willReturn('127.0.0.1');
		$this->shareService->method('unlockShare')->willReturn(false);

		$response = $this->makeController()->unlock(
			token: 'tok',
			password: 'WrongPassword'
		);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertFalse($response->getData()['access']);
	}

	// -------------------------------------------------------------------------
	// unexpected failures on the owner endpoints
	// -------------------------------------------------------------------------

	/**
	 * The owner endpoints with the service method each one reaches.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	public static function ownerEndpoints(): array {
		return [
			'create' => ['create', 'createPublicShare', ['uuid' => 'some-uuid']],
			'index' => ['index', 'listActiveShares', ['uuid' => 'some-uuid']],
			'destroy' => ['destroy', 'revokeShare', ['uuid' => 'some-uuid', 'id' => 1]],
		];
	}

	/**
	 * A database failure answers a neutral 500 and logs the exception.
	 *
	 * @param string               $endpoint The controller method.
	 * @param string               $service  The service method that fails.
	 * @param array<string, mixed> $args     The endpoint arguments.
	 *
	 * @return void
	 */
	#[DataProvider('ownerEndpoints')]
	public function testUnexpectedFailureAnswersNeutrally(string $endpoint, string $service, array $args): void {
		$failure = new DbException('insert failed: secret column');
		$this->shareService->method($service)->willThrowException($failure);
		$this->logger->expects($this->once())->method('error')->with(
			'Public share request failed: ' . DbException::class,
			$this->callback(static fn (array $context): bool => $context['exception'] === $failure && $context['exceptionClass'] === DbException::class)
		);

		$response = $this->makeController()->$endpoint(...$args);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Internal error'], $response->getData());
	}
}
