<?php

/**
 * EmbedTokenApiController unit tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\EmbedTokenApiController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(EmbedTokenApiController::class)]
#[UsesClass(RequestGuard::class)]
class EmbedTokenApiControllerTest extends TestCase {

	private EmbedTokenService&MockObject $tokens;

	private UsageTelemetryService&MockObject $telemetry;

	/**
	 * A controller for a signed-in user, admin or not, with request params.
	 *
	 * @param bool                 $admin  Whether the user is an admin.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return EmbedTokenApiController
	 */
	private function controller(bool $admin=true, array $params=[]): EmbedTokenApiController {
		$this->tokens    = $this->createMock(EmbedTokenService::class);
		$this->telemetry = $this->createMock(UsageTelemetryService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);

		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);

		return new EmbedTokenApiController(
			$request,
			$this->tokens,
			$this->telemetry,
			new RequestGuard(userSession: $session, groupManager: $groups, actionAuth: $this->createMock(ActionAuthService::class))
		);
	}//end controller()

	/**
	 * A non-admin is refused before any token is touched.
	 *
	 * @return void
	 */
	public function testANonAdminIsRefused(): void {
		$controller = $this->controller(admin: false);
		$this->tokens->expects($this->never())->method($this->anything());

		$this->assertSame(403, $controller->index()->getStatus());
		$this->assertSame(403, $controller->create()->getStatus());
		$this->assertSame(403, $controller->revoke(id: 't-1', reason: 'compromised')->getStatus());
		$this->assertSame(403, $controller->setTelemetry(enabled: false)->getStatus());
	}//end testANonAdminIsRefused()

	/**
	 * A create passes only the known fields and the admin, and answers 201;
	 * a refusal answers 400.
	 *
	 * @return void
	 */
	public function testCreateIsCreatedOrRefused(): void {
		$controller = $this->controller(params: ['name' => 'Zeist', 'hostOrigins' => ['https://a.nl'], '_route' => 'x', 'tokenHash' => 'forged']);
		$this->tokens->expects($this->exactly(2))->method('createToken')
			->with(['name' => 'Zeist', 'hostOrigins' => ['https://a.nl']], 'admin')
			->willReturnOnConsecutiveCalls(['id' => 't-1', 'jwt' => 'x.y.z'], ['status' => 'error', 'error' => 'invalid_hostOrigins']);

		$created = $controller->create();
		$this->assertSame([201, ['id' => 't-1', 'jwt' => 'x.y.z']], [$created->getStatus(), $created->getData()]);
		$this->assertSame(400, $controller->create()->getStatus());
	}//end testCreateIsCreatedOrRefused()

	/**
	 * Show, update, revoke and un-revoke answer 404 for an unknown token and
	 * pass their arguments through.
	 *
	 * @return void
	 */
	public function testTokenActionsPassThroughAndAnswer404(): void {
		$controller = $this->controller(params: ['name' => 'New', 'scope' => ['mode' => 'read-with-interactions']]);
		$this->tokens->method('getToken')->willReturnMap([['t-1', ['id' => 't-1']], ['nope', null]]);
		$this->tokens->expects($this->once())->method('updateToken')->with('t-1', ['name' => 'New'])->willReturn(['id' => 't-1', 'name' => 'New']);
		$this->tokens->expects($this->once())->method('revokeToken')->with('t-1', 'compromised', 'leaked', 'admin')->willReturn(['id' => 't-1']);
		$this->tokens->method('unrevokeToken')->willReturn(null);
		$this->tokens->method('reissueToken')->willReturn(['id' => 't-2', 'jwt' => 'a.b.c']);
		$this->tokens->method('purgeCache')->willReturn('2026-05-22T14:35:30Z');

		$this->assertSame(200, $controller->show(id: 't-1')->getStatus());
		$this->assertSame(404, $controller->show(id: 'nope')->getStatus());
		$this->assertSame('New', $controller->update(id: 't-1')->getData()['name']);
		$this->assertSame(200, $controller->revoke(id: 't-1', reason: 'compromised', notes: 'leaked')->getStatus());
		$this->assertSame(404, $controller->unrevoke(id: 't-1')->getStatus());
		$this->assertSame(201, $controller->reissue(id: 't-1')->getStatus());
		$this->assertSame(
			['message' => 'Cache purged. All render workers will immediately reject this token.', 'purgedAt' => '2026-05-22T14:35:30Z'],
			$controller->purgeCache(id: 't-1')->getData()
		);
	}//end testTokenActionsPassThroughAndAnswer404()

	/**
	 * Usage reports a known token with its revocation time; the telemetry
	 * switch is read and set as the admin.
	 *
	 * @return void
	 */
	public function testUsageAndTelemetry(): void {
		$controller = $this->controller();
		$this->tokens->method('getToken')->willReturnMap([['t-1', ['id' => 't-1', 'revokedAt' => '2026-05-22T14:35:00Z']], ['nope', null]]);
		$this->telemetry->expects($this->once())->method('report')->with('t-1', '2026-05-22T14:35:00Z')->willReturn(['summary' => []]);
		$this->telemetry->expects($this->once())->method('setEnabled')->with(false, 'admin');
		$this->telemetry->method('isEnabled')->willReturn(false);

		$this->assertSame(['summary' => []], $controller->usage(id: 't-1')->getData());
		$this->assertSame(404, $controller->usage(id: 'nope')->getStatus());
		$this->assertSame(['enabled' => false], $controller->setTelemetry(enabled: false)->getData());
		$this->assertSame(['enabled' => false], $controller->telemetry()->getData());
	}//end testUsageAndTelemetry()

	/**
	 * Without OpenRegister the API answers 503 with the reason.
	 *
	 * @return void
	 */
	public function testAMissingStoreIs503(): void {
		$controller = $this->controller();
		$this->tokens->method('listTokens')->willThrowException(new RuntimeException('Embeds need OpenRegister, which is not available.'));

		$response = $controller->index();
		$this->assertSame([503, ['error' => 'unavailable', 'message' => 'Embeds need OpenRegister, which is not available.']], [$response->getStatus(), $response->getData()]);
	}//end testAMissingStoreIs503()
}//end class
