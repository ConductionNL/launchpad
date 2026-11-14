<?php

/**
 * Unit tests for IframeController::checkFramable: auth, empty URL and delegation.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\IframeController;
use OCA\LaunchPad\Service\IframeService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\IframeController
 */
class IframeControllerCheckFramableTest extends TestCase {
	private IRequest&MockObject $request;
	private IframeService&MockObject $service;
	private IUserSession&MockObject $session;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(IframeService::class);
		$this->session = $this->createMock(IUserSession::class);
	}

	private function controller(bool $loggedIn = true): IframeController {
		$user = null;
		if ($loggedIn === true) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
		}
		$this->session->method('getUser')->willReturn($user);
		return new IframeController($this->request, $this->service, $this->session);
	}

	public function testAnonymousCallerIsRefused(): void {
		$this->service->expects($this->never())->method('checkFramable');

		$response = $this->controller(false)->checkFramable();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['status' => 'error', 'error' => 'unauthorized'], $response->getData());
	}

	public function testBlankUrlIsReportedAsRequired(): void {
		$this->request->method('getParam')->with('url', '')->willReturn('   ');
		$this->service->expects($this->never())->method('checkFramable');

		$response = $this->controller()->checkFramable();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['framable' => false, 'reason' => 'url_required'], $response->getData());
	}

	public function testTrimmedUrlIsCheckedByTheService(): void {
		$this->request->method('getParam')->with('url', '')->willReturn('  https://example.org  ');
		$this->service->expects($this->once())
			->method('checkFramable')
			->with('https://example.org')
			->willReturn(['framable' => true]);

		$response = $this->controller()->checkFramable();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['framable' => true], $response->getData());
	}
}
