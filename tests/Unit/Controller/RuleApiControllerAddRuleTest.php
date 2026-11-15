<?php

/**
 * Unit tests for RuleApiController::addRule: auth, body validation, ownership and creation.
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

use OCA\LaunchPad\Controller\RuleApiController;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\RuleApiController
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Db\ConditionalRule
 */
class RuleApiControllerAddRuleTest extends TestCase {
	private ConditionalService&MockObject $rules;
	private PermissionService&MockObject $permissions;
	private ActionAuthService&MockObject $actionAuth;
	private IUserSession&MockObject $session;

	protected function setUp(): void {
		$this->rules = $this->createMock(ConditionalService::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->session = $this->createMock(IUserSession::class);
	}

	private function controller(?string $userId = 'alice'): RuleApiController {
		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}
		$this->session->method('getUser')->willReturn($user);

		return new RuleApiController(
			request: $this->createMock(IRequest::class),
			conditionalService: $this->rules,
			permissionService: $this->permissions,
			actionAuth: $this->actionAuth,
			userSession: $this->session,
			userId: $userId
		);
	}

	public function testAnonymousCallerIsRefusedBeforeAnyWork(): void {
		$this->permissions->expects($this->never())->method('verifyPlacementOwnership');
		$this->rules->expects($this->never())->method('addRule');

		$response = $this->controller(null)->addRule(placementId: 4, ruleType: 'group', ruleConfig: []);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDeniedActionIsForbidden(): void {
		$this->actionAuth->expects($this->once())
			->method('requireAction')
			->with($this->anything(), 'rule.add-rule')
			->willThrowException(new OCSForbiddenException());
		$this->rules->expects($this->never())->method('addRule');

		$response = $this->controller()->addRule(placementId: 4, ruleType: 'group', ruleConfig: []);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testMissingRuleTypeIsABadRequest(): void {
		$this->rules->expects($this->never())->method('addRule');

		$response = $this->controller()->addRule(placementId: 4, ruleType: '', ruleConfig: []);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Operation failed'], $response->getData());
	}

	public function testMissingRuleConfigIsABadRequest(): void {
		$this->permissions->expects($this->never())->method('verifyPlacementOwnership');

		$response = $this->controller()->addRule(placementId: 4, ruleType: 'group');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testOwnershipFailureStopsTheCreate(): void {
		$this->permissions->expects($this->once())
			->method('verifyPlacementOwnership')
			->with('alice', 4)
			->willThrowException(new RuntimeException('not yours'));
		$this->rules->expects($this->never())->method('addRule');

		$response = $this->controller()->addRule(placementId: 4, ruleType: 'group', ruleConfig: ['groups' => ['a']]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Operation failed'], $response->getData());
	}

	public function testOwnedPlacementGetsTheRuleCreated(): void {
		$rule = new ConditionalRule();
		$rule->setId(9);
		$rule->setRuleType('group');
		$this->permissions->expects($this->once())->method('verifyPlacementOwnership')->with('alice', 4);
		$this->rules->expects($this->once())
			->method('addRule')
			->with(4, 'group', ['groups' => ['a']], false)
			->willReturn($rule);

		$response = $this->controller()->addRule(placementId: 4, ruleType: 'group', ruleConfig: ['groups' => ['a']], isInclude: false);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($rule->jsonSerialize(), $response->getData());
	}
}
