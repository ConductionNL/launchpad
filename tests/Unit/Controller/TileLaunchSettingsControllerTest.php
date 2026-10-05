<?php

/**
 * TileLaunchSettingsControllerTest
 *
 * The administrator endpoints for allowed program schemes and single sign-on
 * launch templates (launcher-tile-launch-types, REQ-TLT-001, REQ-TLT-003),
 * through the real TileLaunchSettingsService.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\TileLaunchSettingsController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class TileLaunchSettingsControllerTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Actions the controller asked the action matrix for.
	 *
	 * @var string[]
	 */
	private array $actions = [];

	private function controller(?string $uid, bool $admin = true, bool $allowed = true): TileLaunchSettingsController {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value): AdminSetting {
			$this->settings[$key] = $value;
			return new AdminSetting();
		});

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
		$auth->method('requireAction')->willReturnCallback(function (IUser $user, string $action) use ($allowed): void {
			$this->actions[] = $action;
			if ($allowed === false) {
				throw new OCSForbiddenException('no');
			}
		});

		return new TileLaunchSettingsController(
			request: $this->createMock(IRequest::class),
			launchSettings: new TileLaunchSettingsService(settingMapper: $mapper),
			actionAuth: $auth,
			userSession: $session,
			groupManager: $groups,
		);
	}//end controller()

	public function testAnAdministratorSavesSchemesAndTemplates(): void {
		$this->assertSame(['allowedSchemes' => [], 'ssoTemplates' => []], $this->controller(uid: 'noor')->index()->getData());

		$saved = $this->controller(uid: 'noor')->save(
			allowedSchemes: ['ms-word'],
			ssoTemplates: [['name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso']]
		);

		$this->assertSame(Http::STATUS_OK, $saved->getStatus());
		$this->assertSame(['ms-word'], $saved->getData()['allowedSchemes']);
		$this->assertSame('microsoft-entra-id', $saved->getData()['ssoTemplates'][0]['key']);
		$this->assertSame($saved->getData(), $this->controller(uid: 'noor')->index()->getData());
		$this->assertSame(['tile-launch.list', 'tile-launch.save', 'tile-launch.list'], $this->actions);
	}//end testAnAdministratorSavesSchemesAndTemplates()

	public function testAForbiddenSchemeIs400AndStoresNothing(): void {
		$response = $this->controller(uid: 'noor')->save(allowedSchemes: ['javascript'], ssoTemplates: []);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([], $this->settings);
	}//end testAForbiddenSchemeIs400AndStoresNothing()

	public function testAnInvalidTemplateIs400AndKeepsTheSchemesUnchanged(): void {
		$response = $this->controller(uid: 'noor')->save(allowedSchemes: ['ms-word'], ssoTemplates: [['name' => 'X', 'urlTemplate' => 'http://x.nl/{appId}']]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([], $this->settings);
	}//end testAnInvalidTemplateIs400AndKeepsTheSchemesUnchanged()

	public function testOnlySignedInAdministratorsGetIn(): void {
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(uid: null)->index()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'ella', admin: false)->index()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'ella', admin: false)->save(allowedSchemes: ['ms-word'])->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'noor', allowed: false)->save(allowedSchemes: ['ms-word'])->getStatus());
		$this->assertSame([], $this->settings);
	}//end testOnlySignedInAdministratorsGetIn()
}//end class
