<?php

/**
 * PublicShareControllerCoverageTest
 *
 * Unit tests for the PublicShareController answers the main suite leaves out:
 * the unlock refusals (no password, unknown share, unexpected failure), the
 * neutral 500 on the public render path and the 400 an invalid owner request
 * maps to.
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

use InvalidArgumentException;
use OCA\LaunchPad\Controller\PublicShareController;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\PublicShareController
 * @uses   \OCA\LaunchPad\Exception\ShareNotFoundException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class PublicShareControllerCoverageTest extends TestCase {
	private IRequest&MockObject $request;

	private PublicShareService&MockObject $shareService;

	private PublicShareContext&MockObject $shareContext;

	private LoggerInterface&MockObject $logger;

	/**
	 * Set up the shared collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getRemoteAddress')->willReturn('10.0.0.7');
		$this->shareService = $this->createMock(PublicShareService::class);
		$this->shareContext = $this->createMock(PublicShareContext::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * Build the controller under test.
	 *
	 * @param string|null $userId The session user.
	 *
	 * @return PublicShareController
	 */
	private function makeController(?string $userId = 'alice'): PublicShareController {
		return new PublicShareController(
			request: $this->request,
			shareService: $this->shareService,
			shareContext: $this->shareContext,
			logger: $this->logger,
			userId: $userId
		);
	}//end makeController()

	public function testUnlockWithoutAPasswordIsRefusedWithoutTouchingTheService(): void {
		$this->shareService->expects($this->never())->method('unlockShare');

		foreach ([null, ''] as $password) {
			$response = $this->makeController()->unlock(token: 'tok', password: $password);

			$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
			$this->assertSame(['access' => false], $response->getData());
		}
	}

	public function testUnlockPassesTokenPasswordAndIpToTheService(): void {
		$this->shareService->expects($this->once())
			->method('unlockShare')
			->with('tok', 'secret', '10.0.0.7')
			->willReturn(true);

		$response = $this->makeController()->unlock(token: 'tok', password: 'secret');

		$this->assertSame(['access' => true], $response->getData());
	}

	public function testUnlockOfAnUnknownShareIs404(): void {
		$this->shareService->method('unlockShare')->willThrowException(new ShareNotFoundException());

		$response = $this->makeController()->unlock(token: 'tok', password: 'secret');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Not found'], $response->getData());
	}

	public function testUnlockFailureAnswersANeutral500AndLogsTheMessage(): void {
		$this->shareService->method('unlockShare')->willThrowException(new RuntimeException('hash backend down'));
		$this->logger->expects($this->once())
			->method('warning')
			->with('PublicShareController error: hash backend down', ['app' => 'launchpad']);

		$response = $this->makeController()->unlock(token: 'tok', password: 'secret');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Internal error'], $response->getData());
	}

	public function testShowFailureAnswersANeutral500WithoutMarkingTheBearer(): void {
		$this->request->method('getHeader')->with('X-Share-Password')->willReturn('');
		$this->shareService->expects($this->once())
			->method('renderShareContent')
			->with('tok', '10.0.0.7', null)
			->willThrowException(new RuntimeException('render failed'));
		$this->shareContext->expects($this->never())->method('markBearer');
		$this->logger->expects($this->once())
			->method('warning')
			->with('PublicShareController error: render failed', ['app' => 'launchpad']);

		$response = $this->makeController(userId: null)->show(token: 'tok');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Internal error'], $response->getData());
	}

	public function testAnInvalidOwnerRequestIs400(): void {
		$this->shareService->method('createPublicShare')
			->willThrowException(new InvalidArgumentException('expiresAt in the past'));
		$this->logger->expects($this->never())->method('error');

		$response = $this->makeController()->create(uuid: 'dash-1', expiresAt: '2000-01-01');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Invalid request'], $response->getData());
	}

	public function testOwnerEndpointsRefuseAnAnonymousCaller(): void {
		$this->shareService->expects($this->never())->method('listActiveShares');
		$this->shareService->expects($this->never())->method('revokeShare');

		$controller = $this->makeController(userId: null);

		foreach ([$controller->index(uuid: 'dash-1'), $controller->destroy(uuid: 'dash-1', id: 3)] as $response) {
			$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
			$this->assertSame(['error' => 'Not logged in'], $response->getData());
		}
	}
}//end class
