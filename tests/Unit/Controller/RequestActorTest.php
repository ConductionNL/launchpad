<?php

/**
 * RequestActorTest
 *
 * Unit tests for the caller guard of the dashboard API endpoints.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RequestActor.
 *
 * @covers \OCA\LaunchPad\Controller\RequestActor
 */
class RequestActorTest extends TestCase {
	public function testAnAnonymousCallerIsUnauthorizedWithoutAnActionCheck(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->never())->method('requireAction');

		$refusal = (new RequestActor($session, $auth))->refusal('alice', 'dashboard.publish');

		$this->assertSame(401, $refusal->getStatus());
	}

	public function testADeniedActionIsA403NotAThrownException(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->once())->method('requireAction')->with($user, 'dashboard.publish')
			->willThrowException(new OCSForbiddenException('no'));

		$refusal = (new RequestActor($session, $auth))->refusal('alice', 'dashboard.publish');

		$this->assertSame(403, $refusal->getStatus());
		$this->assertSame(['error' => 'Forbidden'], $refusal->getData());
	}

	public function testAnAllowedActionPasses(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->once())->method('requireAction')->with($user, 'dashboard.publish');

		$this->assertNull((new RequestActor($session, $auth))->refusal('alice', 'dashboard.publish'));
	}
}
