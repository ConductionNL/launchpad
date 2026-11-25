<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace page carries for the shell's setup status and
 * walkthrough preferences.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PreferencesController;
use OCA\LaunchPad\Controller\SetupController;
use OCA\LaunchPad\Controller\ShellBootReads;
use OCA\LaunchPad\Kernel\Contracts\DemoDataServiceInterface;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Settings\ISettings;
use OCP\Settings\IManager as ISettingsManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\ShellBootReads
 * @covers \OCA\LaunchPad\Controller\PreferencesController::storedKey
 * @covers \OCA\LaunchPad\Controller\SetupController::statusDocument
 */
class ShellBootReadsTest extends TestCase {
	/** @var IAppConfig&MockObject */
	private IAppConfig $appConfig;

	/** @var DemoDataServiceInterface&MockObject */
	private DemoDataServiceInterface $demoData;

	/** @var IConfig&MockObject */
	private IConfig $config;

	/** @var IGroupManager&MockObject */
	private IGroupManager $groups;

	/** @var IUserManager&MockObject */
	private IUserManager $users;

	/** @var ISettingsManager&MockObject */
	private ISettingsManager $settings;

	/**
	 * The services the builder reads, as doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->appConfig->method('getValueString')->willReturn('');
		$this->demoData = $this->createMock(originalClassName: DemoDataServiceInterface::class);
		$this->demoData->method('listChoices')->willReturn([['id' => 'none']]);
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->groups = $this->createMock(originalClassName: IGroupManager::class);
		$this->users = $this->createMock(originalClassName: IUserManager::class);
		$this->users->method('get')->willReturn($this->createMock(originalClassName: IUser::class));
		$this->settings = $this->createMock(originalClassName: ISettingsManager::class);
	}//end setUp()

	/**
	 * The builder under test.
	 *
	 * @return ShellBootReads
	 */
	private function reads(): ShellBootReads {
		return new ShellBootReads(
			appConfig: $this->appConfig,
			demoDataService: $this->demoData,
			config: $this->config,
			groupManager: $this->groups,
			userManager: $this->users,
			settingsManager: $this->settings,
			logger: new NullLogger()
		);
	}//end reads()

	/**
	 * An admin gets exactly the document the endpoint answers.
	 *
	 * @return void
	 */
	public function testAnAdminGetsTheStatusDocument(): void {
		$this->groups->method('isAdmin')->willReturn(true);
		$this->settings->expects($this->never())->method('getAllowedAdminSettings');

		$status = $this->reads()->setupStatus(userId: 'admin');

		$this->assertSame(
			expected: SetupController::statusDocument(appConfig: $this->appConfig, demoDataService: $this->demoData),
			actual: $status
		);
		$this->assertTrue($status['completed']);
		$this->assertSame(expected: ['done' => false], actual: $status['steps']['demo-data']);
	}//end testAnAdminGetsTheStatusDocument()

	/**
	 * A caller the endpoint refuses gets `{forbidden: true}`.
	 *
	 * @return void
	 */
	public function testARefusedCallerGetsForbidden(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$other = $this->createMock(originalClassName: ISettings::class);
		$this->settings->method('getAllowedAdminSettings')->willReturn([10 => [$other]]);

		$this->assertSame(expected: ['forbidden' => true], actual: $this->reads()->setupStatus(userId: 'marieke'));
	}//end testARefusedCallerGetsForbidden()

	/**
	 * A delegated LaunchPad admin gets nothing, so the shell asks the endpoint
	 * that lets them through.
	 *
	 * @return void
	 */
	public function testADelegatedAdminGetsNothing(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->settings->method('getAllowedAdminSettings')
			->with('launchpad', $this->anything())
			->willReturn([10 => [$this->createMock(originalClassName: LaunchPadAdmin::class)]]);

		$this->assertNull($this->reads()->setupStatus(userId: 'delegate'));
	}//end testADelegatedAdminGetsNothing()

	/**
	 * A failing read and a missing user leave the key out.
	 *
	 * @return void
	 */
	public function testAFailingReadOrNoUserGetsNothing(): void {
		$this->groups->method('isAdmin')->willThrowException(new RuntimeException('db down'));

		$this->assertNull($this->reads()->setupStatus(userId: 'admin'));
		$this->assertNull($this->reads()->setupStatus(userId: ''));
		$this->assertNull($this->reads()->walkthroughPreferences(userId: ''));
	}//end testAFailingReadOrNoUserGetsNothing()

	/**
	 * Both preferences come as stored, under the keys the preference endpoint
	 * stores them by; unset reads null.
	 *
	 * @return void
	 */
	public function testTheWalkthroughPreferencesAreReadAsTheEndpointReadsThem(): void {
		$stored = ['pref_walkthroughcompletedversion' => '1.4.0'];
		$this->config->method('getUserValue')
			->willReturnCallback(static fn (string $userId, string $app, string $key, string $default): string => $stored[$key] ?? $default);

		$this->assertSame(
			expected: ['seen' => '1.4.0', 'progress' => null],
			actual: $this->reads()->walkthroughPreferences(userId: 'marieke')
		);
	}//end testTheWalkthroughPreferencesAreReadAsTheEndpointReadsThem()

	/**
	 * The stored key is the endpoint's: `pref_` plus the sanitised key.
	 *
	 * @return void
	 */
	public function testTheStoredKeyMatchesThePreferenceEndpoint(): void {
		$this->assertSame(expected: 'pref_walkthroughcompletedversion', actual: PreferencesController::storedKey(key: 'walkthrough_completed_version'));
		$this->assertSame(expected: 'pref_walkthroughcompletedversion-progress', actual: PreferencesController::storedKey(key: 'walkthrough_completed_version-progress'));
		$this->assertSame(expected: '', actual: PreferencesController::storedKey(key: '__'));
	}//end testTheStoredKeyMatchesThePreferenceEndpoint()
}//end class
