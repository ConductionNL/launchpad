<?php

/**
 * SearchShortcutsControllerTest
 *
 * search-ai-prefix-shortcuts: the administrators' shortcuts endpoint through
 * the real SearchShortcutService. Only an administrator who passes the action
 * check reads or writes; a refused write stores nothing; an invalid list is
 * 400 with the reason.
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

use OCA\LaunchPad\Controller\SearchShortcutsController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\SearchShortcutService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SearchShortcutsController.
 */
class SearchShortcutsControllerTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * A controller for a caller.
	 *
	 * @param string|null $uid The caller, or null when signed out.
	 * @param boolean $admin Whether the caller is an administrator.
	 * @param boolean $allowed Whether the action matrix lets them through.
	 *
	 * @return SearchShortcutsController
	 */
	private function controller(?string $uid, bool $admin = true, bool $allowed = true): SearchShortcutsController {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value): AdminSetting {
			$this->settings[$key] = $value;
			return new AdminSetting();
		});
		$service = new SearchShortcutService(settingMapper: $mapper, adminSettings: new AdminSettingsService(settingMapper: $mapper));

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

		return new SearchShortcutsController(
			request: $this->createMock(IRequest::class),
			shortcuts: $service,
			actionAuth: $auth,
			userSession: $session,
			groupManager: $groups,
		);
	}//end controller()

	/**
	 * An administrator stores a list and reads it back.
	 *
	 * @return void
	 */
	public function testAnAdministratorSavesAndReadsTheShortcuts(): void {
		$saved = $this->controller(uid: 'admin')->save(shortcuts: [['prefix' => '!T', 'name' => 'TOPdesk', 'urlTemplate' => 'https://topdesk.example.nl/search?q={query}']]);
		$this->assertSame(Http::STATUS_OK, $saved->getStatus());

		$read = $this->controller(uid: 'admin')->index()->getData()['shortcuts'];
		$this->assertSame('!t', $read[0]['prefix']);
	}//end testAnAdministratorSavesAndReadsTheShortcuts()

	/**
	 * An invalid list is 400 and stores nothing.
	 *
	 * @return void
	 */
	public function testAnInvalidListIs400(): void {
		$response = $this->controller(uid: 'admin')->save(shortcuts: [['prefix' => 't', 'name' => 'T', 'urlTemplate' => 'https://x.example/?q={query}']]);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
		$this->assertSame([], $this->settings);
	}//end testAnInvalidListIs400()

	/**
	 * Signed out is 401, a non-admin 403, a refused action 403; nothing is stored.
	 *
	 * @return void
	 */
	public function testOnlyAnAllowedAdministratorGetsIn(): void {
		$list = [['prefix' => '!t', 'name' => 'T', 'urlTemplate' => 'https://x.example/?q={query}']];
		foreach ([[null, true, true, Http::STATUS_UNAUTHORIZED], ['sanne', false, true, Http::STATUS_FORBIDDEN], ['admin', true, false, Http::STATUS_FORBIDDEN]] as [$uid, $admin, $allowed, $status]) {
			$this->assertSame($status, $this->controller(uid: $uid, admin: $admin, allowed: $allowed)->index()->getStatus());
			$this->assertSame($status, $this->controller(uid: $uid, admin: $admin, allowed: $allowed)->save(shortcuts: $list)->getStatus());
		}

		$this->assertSame([], $this->settings, 'a refused write stores nothing');
	}//end testOnlyAnAllowedAdministratorGetsIn()
}//end class
