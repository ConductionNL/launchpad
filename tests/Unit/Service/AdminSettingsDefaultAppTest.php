<?php

/**
 * AdminSettingsService — default app toggle (REQ-PSP-003)
 *
 * The toggle reads as on until switched off, a switch goes through
 * DefaultAppSetting, and a value that is not a boolean is refused.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DefaultAppSetting;
use OCA\LaunchPad\Service\TileLaunchSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the default app toggle in the admin settings.
 */
#[CoversClass(AdminSettingsService::class)]
#[UsesClass(AdminSettingKey::class)]
#[UsesClass(TileLaunchSettings::class)]
class AdminSettingsDefaultAppTest extends TestCase {

	/**
	 * With nothing stored the toggle reads as on; a stored `false` reads as off.
	 *
	 * @return void
	 */
	public function testTheToggleReadsAsOnUntilSwitchedOff(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnOnConsecutiveCalls([], ['default_app_enabled' => false]);
		$service = new AdminSettingsService(settingMapper: $mapper);

		$this->assertTrue($service->getSettings()['defaultAppEnabled']);
		$this->assertFalse($service->getSettings()['defaultAppEnabled']);
	}//end testTheToggleReadsAsOnUntilSwitchedOff()

	/**
	 * A switch goes through DefaultAppSetting, which gives the default app back or sets it.
	 *
	 * @return void
	 */
	public function testASwitchGoesThroughTheDefaultAppSetting(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->expects($this->never())->method('setSetting');
		$defaultApp = $this->createMock(DefaultAppSetting::class);
		$defaultApp->expects($this->once())->method('setEnabled')->with(false)->willReturn(DefaultAppSetting::RESTORED);

		(new AdminSettingsService(settingMapper: $mapper, defaultApp: $defaultApp))
			->updateSettings(settings: ['defaultAppEnabled' => false]);
	}//end testASwitchGoesThroughTheDefaultAppSetting()

	/**
	 * Without DefaultAppSetting only the toggle is stored.
	 *
	 * @return void
	 */
	public function testWithoutTheServiceOnlyTheToggleIsStored(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->expects($this->once())->method('setSetting')->with('default_app_enabled', true);

		(new AdminSettingsService(settingMapper: $mapper))->updateSettings(settings: ['defaultAppEnabled' => true]);
	}//end testWithoutTheServiceOnlyTheToggleIsStored()

	/**
	 * An absent toggle leaves the default app alone.
	 *
	 * @return void
	 */
	public function testAnAbsentToggleChangesNothing(): void {
		$defaultApp = $this->createMock(DefaultAppSetting::class);
		$defaultApp->expects($this->never())->method('setEnabled');

		(new AdminSettingsService(settingMapper: $this->createMock(AdminSettingMapper::class), defaultApp: $defaultApp))
			->updateSettings(settings: ['defaultAppEnabled' => null]);
	}//end testAnAbsentToggleChangesNothing()

	/**
	 * A value that is not a boolean is refused and nothing is written.
	 *
	 * @return void
	 */
	public function testANonBooleanIsRefused(): void {
		$defaultApp = $this->createMock(DefaultAppSetting::class);
		$defaultApp->expects($this->never())->method('setEnabled');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('defaultAppEnabled must be a boolean.');

		(new AdminSettingsService(settingMapper: $this->createMock(AdminSettingMapper::class), defaultApp: $defaultApp))
			->updateSettings(settings: ['defaultAppEnabled' => 'yes']);
	}//end testANonBooleanIsRefused()
}//end class
