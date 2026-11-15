<?php

/**
 * SessionActionCheckTest
 *
 * Unit tests for the signed-in action check controllers run first.
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
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\SessionActionCheck;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SessionActionCheck.
 *
 * @covers \OCA\LaunchPad\Controller\SessionActionCheck
 */
class SessionActionCheckTest extends TestCase {
	public function testAnAnonymousCallerIsUnauthorizedWithoutAnActionCheck(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->never())->method('requireAction');

		$refusal = (new SessionActionCheck($session, $auth))->refusal('analytics.view');

		$this->assertSame(401, $refusal->getStatus());
		$this->assertSame(['error' => 'Not authenticated'], $refusal->getData());
	}

	public function testAForbiddenActionIsRefused(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->once())->method('requireAction')->with($user, 'analytics.view')
			->willThrowException(new OCSForbiddenException('no'));

		$refusal = (new SessionActionCheck($session, $auth))->refusal('analytics.view');

		$this->assertSame(403, $refusal->getStatus());
		$this->assertSame(['error' => 'Forbidden'], $refusal->getData());
	}

	public function testAnAllowedActionPasses(): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->once())->method('requireAction')->with($user, 'analytics.export');

		$this->assertNull((new SessionActionCheck($session, $auth))->refusal('analytics.export'));
	}
}
