<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AttentionController;
use OCA\LaunchPad\Service\AttentionSourceService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * `GET /api/attention/sources` (REQ-ATT-002).
 */
class AttentionControllerTest extends TestCase {
	public function testASignedInUserGetsTheSources(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pieter');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$service = $this->createMock(AttentionSourceService::class);
		$service->expects(self::once())->method('collect')->with($user)
			->willReturn(['sources' => [['appId' => 'dossiq']], 'invalid' => []]);

		$response = (new AttentionController($this->createMock(IRequest::class), $service, $session))->sources();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(
			['userId' => 'pieter', 'sources' => [['appId' => 'dossiq']], 'invalid' => []],
			$response->getData()
		);
	}

	public function testNobodySignedInGets401(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$service = $this->createMock(AttentionSourceService::class);
		$service->expects(self::never())->method('collect');

		$response = (new AttentionController($this->createMock(IRequest::class), $service, $session))->sources();

		self::assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * Without this attribute Nextcloud answers every non-admin with 403
	 * before the method runs, and the widget would show nothing for exactly
	 * the people it is for.
	 */
	public function testAnEmployeeNeedsNoAdminRights(): void {
		$attributes = (new ReflectionMethod(AttentionController::class, 'sources'))
			->getAttributes(NoAdminRequired::class);

		self::assertCount(1, $attributes);
	}
}
