<?php

/**
 * OfficeNetworksControllerTest
 *
 * launcher-tile-internal-address REQ-TIA-001: the administrators' office
 * networks endpoint through the real OfficeNetworkService. An administrator
 * stores ranges and is told whether this request comes from one; an invalid
 * range is 400; signed out, non-admin and a refused action store nothing.
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

use OCA\LaunchPad\Controller\OfficeNetworksController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\OfficeNetworkService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Unit\Support\CidrIpFactory;

/**
 * Tests for OfficeNetworksController.
 */
class OfficeNetworksControllerTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * A controller for a caller at 10.20.1.5.
	 *
	 * @param string|null $uid The caller, or null when signed out.
	 * @param boolean $admin Whether the caller is an administrator.
	 * @param boolean $allowed Whether the action matrix lets them through.
	 *
	 * @return OfficeNetworksController
	 */
	private function controller(?string $uid, bool $admin = true, bool $allowed = true): OfficeNetworksController {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value): AdminSetting {
			$this->settings[$key] = $value;
			return new AdminSetting();
		});
		$request = $this->createMock(IRequest::class);
		$request->method('getRemoteAddress')->willReturn('10.20.1.5');
		$service = new OfficeNetworkService(settingMapper: $mapper, request: $request, ipFactory: new CidrIpFactory());

		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);
		$auth = $this->createMock(ActionAuthService::class);
		if ($allowed === false) {
			$auth->method('requireAction')->willThrowException(new OCSForbiddenException('no'));
		}

		return new OfficeNetworksController(
			request: $request,
			officeNetworks: $service,
			actionAuth: $auth,
			userSession: $session,
			groupManager: $groups,
		);
	}//end controller()

	/**
	 * An administrator stores a range and sees that this request is on it.
	 *
	 * @return void
	 */
	public function testAnAdministratorSavesARangeAndSeesTheCurrentAddress(): void {
		$this->assertFalse($this->controller(uid: 'admin')->index()->getData()['onOfficeNetwork']);

		$saved = $this->controller(uid: 'admin')->save(ranges: ['10.20.0.0/16'])->getData();
		$this->assertSame(['10.20.0.0/16'], $saved['ranges']);
		$this->assertSame('10.20.1.5', $saved['currentAddress']);
		$this->assertTrue($saved['onOfficeNetwork']);
	}//end testAnAdministratorSavesARangeAndSeesTheCurrentAddress()

	/**
	 * An invalid range is 400 and stores nothing.
	 *
	 * @return void
	 */
	public function testAnInvalidRangeIs400(): void {
		$response = $this->controller(uid: 'admin')->save(ranges: ['not a network']);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([], $this->settings);
	}//end testAnInvalidRangeIs400()

	/**
	 * Signed out is 401, a non-admin 403, a refused action 403; nothing is stored.
	 *
	 * @return void
	 */
	public function testOnlyAnAllowedAdministratorGetsIn(): void {
		foreach ([[null, true, true, Http::STATUS_UNAUTHORIZED], ['sanne', false, true, Http::STATUS_FORBIDDEN], ['admin', true, false, Http::STATUS_FORBIDDEN]] as [$uid, $admin, $allowed, $status]) {
			$this->assertSame($status, $this->controller(uid: $uid, admin: $admin, allowed: $allowed)->index()->getStatus());
			$this->assertSame($status, $this->controller(uid: $uid, admin: $admin, allowed: $allowed)->save(ranges: ['10.20.0.0/16'])->getStatus());
		}

		$this->assertSame([], $this->settings, 'a refused write stores nothing');
	}//end testOnlyAnAllowedAdministratorGetsIn()
}//end class
