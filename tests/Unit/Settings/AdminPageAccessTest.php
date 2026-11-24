<?php

/**
 * AdminPageAccessTest
 *
 * Pins who may open the administration page (REQ-ADM-030): a Nextcloud
 * administrator, a user Nextcloud's admin delegation grants the Launchpad
 * settings, and nobody else.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use OCA\LaunchPad\Settings\AdminPageAccess;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Settings\IManager;
use OCP\Settings\ISettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AdminPageAccess.
 *
 * @covers \OCA\LaunchPad\Settings\AdminPageAccess
 * @uses   \OCA\LaunchPad\Settings\LaunchPadAdmin
 */
class AdminPageAccessTest extends TestCase {

	/** @var IGroupManager&MockObject */
	private $groupManager;
	/** @var IManager&MockObject */
	private $settingsManager;

	/**
	 * The check, for a signed-in user `pieter` or nobody.
	 *
	 * @param bool $signedIn Whether somebody is signed in.
	 *
	 * @return AdminPageAccess
	 */
	private function access(bool $signedIn = true): AdminPageAccess {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('pieter');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn(null);
		if ($signedIn === true) {
			$session = $this->createMock(originalClassName: IUserSession::class);
			$session->method('getUser')->willReturn($user);
		}
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->settingsManager = $this->createMock(originalClassName: IManager::class);

		return new AdminPageAccess(
			userSession: $session,
			groupManager: $this->groupManager,
			settingsManager: $this->settingsManager
		);
	}//end access()

	/**
	 * An administrator may open the page.
	 *
	 * @return void
	 */
	public function testAnAdministratorIsAllowed(): void {
		$access = $this->access();
		$this->groupManager->method('isAdmin')->with('pieter')->willReturn(true);
		$this->settingsManager->expects($this->never())->method('getAllowedAdminSettings');

		$this->assertTrue(condition: $access->allows());
	}//end testAnAdministratorIsAllowed()

	/**
	 * A user the admin delegation grants the Launchpad settings may open it.
	 *
	 * @return void
	 */
	public function testADelegatedLaunchpadAdministratorIsAllowed(): void {
		$access = $this->access();
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->settingsManager->method('getAllowedAdminSettings')
			->with('launchpad', $this->anything())
			->willReturn([10 => [new LaunchPadAdmin(urlGenerator: $this->createMock(originalClassName: IURLGenerator::class))]]);

		$this->assertTrue(condition: $access->allows());
	}//end testADelegatedLaunchpadAdministratorIsAllowed()

	/**
	 * A user delegated another setting of the section, or none, may not.
	 *
	 * @return void
	 */
	public function testAnyoneElseIsRefused(): void {
		$access = $this->access();
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->settingsManager->method('getAllowedAdminSettings')
			->willReturnOnConsecutiveCalls([5 => [$this->createMock(originalClassName: ISettings::class)]], []);

		$this->assertFalse(condition: $access->allows());
		$this->assertFalse(condition: $access->allows());
	}//end testAnyoneElseIsRefused()

	/**
	 * Nobody signed in may not.
	 *
	 * @return void
	 */
	public function testNobodySignedInIsRefused(): void {
		$access = $this->access(signedIn: false);
		$this->groupManager->expects($this->never())->method('isAdmin');

		$this->assertFalse(condition: $access->allows());
	}//end testNobodySignedInIsRefused()
}//end class
