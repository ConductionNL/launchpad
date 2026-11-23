<?php

/**
 * DefaultAppSetting Test
 *
 * LaunchPad becomes the default app only when none is set, keeps an
 * administrator's own choice, and gives the default back when the toggle is
 * switched off (REQ-PSP-003).
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

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\DefaultAppSetting;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for setting, keeping and giving back the default app.
 */
#[CoversClass(DefaultAppSetting::class)]
#[UsesClass(AdminSetting::class)]
#[UsesClass(AdminSettingKey::class)]
class DefaultAppSettingTest extends TestCase {

	/**
	 * The system values, as config.php holds them.
	 *
	 * @var array<string, mixed>
	 */
	private array $system = [];

	/**
	 * The admin settings, by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * The service over in-memory config and settings.
	 *
	 * @return DefaultAppSetting
	 */
	private function service(): DefaultAppSetting {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->willReturnCallback(
			fn (string $key, mixed $default = ''): mixed => ($this->system[$key] ?? $default)
		);
		$config->method('setSystemValue')->willReturnCallback(
			function (string $key, mixed $value): void {
				$this->system[$key] = $value;
			}
		);
		$config->method('deleteSystemValue')->willReturnCallback(
			function (string $key): void {
				unset($this->system[$key]);
			}
		);

		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => array_key_exists($key, $this->settings) ? $this->settings[$key] : $default
		);
		$mapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value) {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);

		return new DefaultAppSetting($config, $mapper);
	}//end service()

	/**
	 * With no default app set, LaunchPad becomes it.
	 *
	 * @return void
	 */
	public function testClaimSetsLaunchpadWhenNoDefaultAppIsSet(): void {
		$this->assertSame(DefaultAppSetting::SET, $this->service()->claim());
		$this->assertSame('launchpad', $this->system['defaultapp']);
	}//end testClaimSetsLaunchpadWhenNoDefaultAppIsSet()

	/**
	 * An empty value is the administrator's "reset to default" in the theming
	 * settings, a choice like any other, so it is kept.
	 *
	 * @return void
	 */
	public function testClaimKeepsAnEmptyValueTheAdministratorSet(): void {
		$this->system['defaultapp'] = '';

		$this->assertSame(expected: DefaultAppSetting::KEPT, actual: $this->service()->claim());
		$this->assertSame(expected: '', actual: $this->system['defaultapp']);
	}//end testClaimKeepsAnEmptyValueTheAdministratorSet()

	/**
	 * The claim runs on every upgrade, and an administrator's later choice
	 * survives each of them.
	 *
	 * @return void
	 */
	public function testARepeatedClaimNeverOverwritesALaterChoice(): void {
		$service = $this->service();
		$this->assertSame(expected: DefaultAppSetting::SET, actual: $service->claim());

		$this->system['defaultapp'] = 'files';
		$this->assertSame(expected: DefaultAppSetting::KEPT, actual: $service->claim());
		$this->assertSame(expected: 'files', actual: $this->system['defaultapp']);
	}//end testARepeatedClaimNeverOverwritesALaterChoice()

	/**
	 * A default app the administrator chose is kept.
	 *
	 * @return void
	 */
	public function testClaimKeepsTheAdministratorsDefaultApp(): void {
		$this->system['defaultapp'] = 'files';

		$this->assertSame(DefaultAppSetting::KEPT, $this->service()->claim());
		$this->assertSame('files', $this->system['defaultapp']);
	}//end testClaimKeepsTheAdministratorsDefaultApp()

	/**
	 * With the toggle off nothing is set.
	 *
	 * @return void
	 */
	public function testClaimDoesNothingWhenSwitchedOff(): void {
		$this->settings['default_app_enabled'] = false;

		$this->assertSame(DefaultAppSetting::DISABLED, $this->service()->claim());
		$this->assertArrayNotHasKey('defaultapp', $this->system);
	}//end testClaimDoesNothingWhenSwitchedOff()

	/**
	 * Claiming twice changes nothing the second time.
	 *
	 * @return void
	 */
	public function testClaimIsIdempotent(): void {
		$service = $this->service();
		$service->claim();

		$this->assertSame(DefaultAppSetting::UNCHANGED, $service->claim());
		$this->assertSame('launchpad', $this->system['defaultapp']);
	}//end testClaimIsIdempotent()

	/**
	 * The toggle is on until switched off; only a stored `false` turns it off.
	 *
	 * @return void
	 */
	public function testTheToggleIsOnByDefault(): void {
		$this->assertTrue($this->service()->isEnabled());

		$this->settings['default_app_enabled'] = false;
		$this->assertFalse($this->service()->isEnabled());
	}//end testTheToggleIsOnByDefault()

	/**
	 * Switching off unsets the default app LaunchPad set, so Nextcloud's own default applies again.
	 *
	 * @return void
	 */
	public function testSwitchingOffUnsetsTheDefaultAppLaunchpadSet(): void {
		$service = $this->service();
		$service->claim();

		$this->assertSame(DefaultAppSetting::RESTORED, $service->setEnabled(false));
		$this->assertArrayNotHasKey('defaultapp', $this->system);
		$this->assertFalse($this->settings['default_app_enabled']);
	}//end testSwitchingOffUnsetsTheDefaultAppLaunchpadSet()

	/**
	 * Switching off keeps a default app the administrator chose after LaunchPad set it.
	 *
	 * @return void
	 */
	public function testSwitchingOffKeepsADefaultAppChosenLater(): void {
		$service = $this->service();
		$service->claim();
		$this->system['defaultapp'] = 'calendar';

		$this->assertSame(DefaultAppSetting::UNCHANGED, $service->setEnabled(false));
		$this->assertSame('calendar', $this->system['defaultapp']);
		$this->assertFalse($this->settings['default_app_enabled']);
	}//end testSwitchingOffKeepsADefaultAppChosenLater()

	/**
	 * Switching back on sets LaunchPad again when no default app is set.
	 *
	 * @return void
	 */
	public function testSwitchingBackOnSetsLaunchpadAgain(): void {
		$this->settings['default_app_enabled'] = false;

		$this->assertSame(DefaultAppSetting::SET, $this->service()->setEnabled(true));
		$this->assertSame('launchpad', $this->system['defaultapp']);
		$this->assertTrue($this->settings['default_app_enabled']);
	}//end testSwitchingBackOnSetsLaunchpadAgain()

	/**
	 * Saving the value it already has changes nothing, so a full-form save does not re-set the default app.
	 *
	 * @return void
	 */
	public function testSavingTheSameValueChangesNothing(): void {
		$this->system['defaultapp'] = '';

		$this->assertSame(DefaultAppSetting::UNCHANGED, $this->service()->setEnabled(true));
		$this->assertSame('', $this->system['defaultapp']);
		$this->assertArrayNotHasKey('default_app_enabled', $this->settings);
	}//end testSavingTheSameValueChangesNothing()
}//end class
