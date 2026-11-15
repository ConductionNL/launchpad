<?php

/**
 * ProfileFieldsControllerGuardTest
 *
 * Unit tests for ProfileFieldsController's refusals: an anonymous caller, a
 * caller the action matrix forbids, a non-admin on the definition endpoints,
 * and a definitions save the service rejects.
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
use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\ProfileFieldsController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\ProfileFieldsController
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 */
class ProfileFieldsControllerGuardTest extends TestCase {
	private ProfileFieldService&MockObject $service;

	private ActionAuthService&MockObject $actionAuth;

	private IUserSession&MockObject $session;

	private IGroupManager&MockObject $groups;

	/**
	 * Set up the shared collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(ProfileFieldService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->groups = $this->createMock(IGroupManager::class);
	}//end setUp()

	/**
	 * Build the controller under test.
	 *
	 * @return ProfileFieldsController
	 */
	private function controller(): ProfileFieldsController {
		return new ProfileFieldsController(
			request: $this->createMock(IRequest::class),
			profileFields: $this->service,
			caller: new ActionCaller(actionAuth: $this->actionAuth, userSession: $this->session),
			groupManager: $this->groups
		);
	}//end controller()

	/**
	 * Sign in a user with the given admin flag.
	 *
	 * @param bool $admin Whether the user is an admin.
	 *
	 * @return IUser
	 */
	private function signIn(bool $admin): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('carol');
		$this->session->method('getUser')->willReturn($user);
		$this->groups->method('isAdmin')->with('carol')->willReturn($admin);

		return $user;
	}//end signIn()

	public function testAnonymousCallersAreUnauthenticatedOnEveryEndpoint(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->actionAuth->expects($this->never())->method('requireAction');
		$this->service->expects($this->never())->method($this->anything());

		$controller = $this->controller();
		$responses = [
			$controller->getOwn(),
			$controller->saveOwn(values: ['x' => 'y']),
			$controller->getDefinitions(),
			$controller->saveDefinitions(fields: []),
		];

		foreach ($responses as $response) {
			$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
			$this->assertSame(['error' => 'Not authenticated'], $response->getData());
		}
	}

	public function testAForbiddenActionRefusesTheOwnEndpoints(): void {
		$user = $this->signIn(admin: false);
		$asked = [];
		$this->actionAuth->method('requireAction')
			->willReturnCallback(static function (IUser $caller, string $action) use ($user, &$asked): void {
				self::assertSame($user, $caller);
				$asked[] = $action;
				throw new OCSForbiddenException('no');
			});
		$this->service->expects($this->never())->method('getOwnFields');
		$this->service->expects($this->never())->method('saveOwnValues');

		$controller = $this->controller();
		foreach ([$controller->getOwn(), $controller->saveOwn()] as $response) {
			$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
			$this->assertSame(['error' => 'Forbidden'], $response->getData());
		}

		$this->assertSame(['profile-fields.get-own', 'profile-fields.save-own'], $asked);
	}

	public function testANonAdminIsRefusedTheDefinitionsBeforeTheActionCheck(): void {
		$this->signIn(admin: false);
		$this->actionAuth->expects($this->never())->method('requireAction');
		$this->service->expects($this->never())->method('getDefinitions');
		$this->service->expects($this->never())->method('saveDefinitions');

		$controller = $this->controller();
		foreach ([$controller->getDefinitions(), $controller->saveDefinitions(fields: [])] as $response) {
			$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
			$this->assertSame(['error' => 'Admin required'], $response->getData());
		}
	}

	public function testAnAdminForbiddenByTheActionMatrixIsRefusedTheDefinitions(): void {
		$this->signIn(admin: true);
		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException('no'));
		$this->service->expects($this->never())->method('getDefinitions');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->getDefinitions()->getStatus());
	}

	public function testRejectedDefinitionsAre400WithTheServiceMessage(): void {
		$this->signIn(admin: true);
		$this->service->expects($this->once())
			->method('saveDefinitions')
			->with([])
			->willThrowException(new InvalidArgumentException('Field key "x" is reserved'));

		$response = $this->controller()->saveDefinitions();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Field key "x" is reserved'], $response->getData());
	}

	public function testSaveOwnWithoutValuesSavesAnEmptyMap(): void {
		$this->signIn(admin: false);
		$this->service->expects($this->once())
			->method('saveOwnValues')
			->with('carol', [])
			->willReturn([['key' => 'room', 'value' => '']]);

		$this->assertSame(['fields' => [['key' => 'room', 'value' => '']]], $this->controller()->saveOwn()->getData());
	}
}//end class
