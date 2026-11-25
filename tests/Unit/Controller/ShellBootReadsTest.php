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

use OCA\LaunchPad\Controller\LaunchPadAdminDelegation;
use OCA\LaunchPad\Controller\SetupStatusFirstLoad;
use OCA\LaunchPad\Controller\ShellBootReads;
use OCA\LaunchPad\Controller\WalkthroughPreferencesFirstLoad;
use OCA\LaunchPad\Kernel\Contracts\DemoDataServiceInterface;
use OCA\LaunchPad\Service\DemoSeedPreferences;
use OCA\LaunchPad\Service\PreferenceKey;
use OCA\LaunchPad\Service\SetupStatusDocument;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Settings\IManager as ISettingsManager;
use OCP\Settings\ISettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\SetupStatusFirstLoad
 * @covers \OCA\LaunchPad\Controller\ShellBootReads
 * @covers \OCA\LaunchPad\Controller\LaunchPadAdminDelegation
 * @covers \OCA\LaunchPad\Controller\WalkthroughPreferencesFirstLoad
 * @covers \OCA\LaunchPad\Service\PreferenceKey
 * @covers \OCA\LaunchPad\Service\SetupStatusDocument
 */
class ShellBootReadsTest extends TestCase {
	/** @var IGroupManager&MockObject */
	private IGroupManager $groups;

	/** @var ISettingsManager&MockObject */
	private ISettingsManager $settings;

	/** @var SetupStatusDocument */
	private SetupStatusDocument $document;

	/**
	 * The services the builders read, as doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturn('');
		$demoData = $this->createMock(originalClassName: DemoDataServiceInterface::class);
		$demoData->method('listChoices')->willReturn([['id' => 'none']]);
		$this->document = new SetupStatusDocument(appConfig: $appConfig, demoDataService: $demoData);
		$this->groups = $this->createMock(originalClassName: IGroupManager::class);
		$this->settings = $this->createMock(originalClassName: ISettingsManager::class);
	}//end setUp()

	/**
	 * The setup status builder under test.
	 *
	 * @return SetupStatusFirstLoad
	 */
	private function setupStatus(): SetupStatusFirstLoad {
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturn($this->createMock(originalClassName: IUser::class));

		return new SetupStatusFirstLoad(
			document: $this->document,
			groups: $this->groups,
			delegation: new LaunchPadAdminDelegation(userManager: $users, settingsManager: $this->settings),
			logger: new NullLogger()
		);
	}//end setupStatus()

	/**
	 * An admin gets exactly the document the endpoint answers.
	 *
	 * @return void
	 */
	public function testAnAdminGetsTheStatusDocument(): void {
		$this->groups->method('isAdmin')->willReturn(true);
		$this->settings->expects($this->never())->method('getAllowedAdminSettings');

		$status = $this->setupStatus()->firstLoad(userId: 'admin');

		$this->assertSame(expected: $this->document->build(), actual: $status);
		$this->assertTrue(condition: $status['completed']);
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

		$this->assertSame(expected: ['forbidden' => true], actual: $this->setupStatus()->firstLoad(userId: 'marieke'));
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

		$this->assertNull(actual: $this->setupStatus()->firstLoad(userId: 'delegate'));
	}//end testADelegatedAdminGetsNothing()

	/**
	 * A failing read and a missing user leave the key out.
	 *
	 * @return void
	 */
	public function testAFailingReadOrNoUserGetsNothing(): void {
		$this->groups->method('isAdmin')->willThrowException(new RuntimeException('db down'));

		$this->assertNull(actual: $this->setupStatus()->firstLoad(userId: 'admin'));
		$this->assertNull(actual: $this->setupStatus()->firstLoad(userId: ''));
	}//end testAFailingReadOrNoUserGetsNothing()

	/**
	 * Both preferences come as stored, under the keys the preference endpoint
	 * stores them by; unset reads null; no user reads nothing.
	 *
	 * @return void
	 */
	public function testTheWalkthroughPreferencesAreReadAsTheEndpointReadsThem(): void {
		$stored = ['pref_walkthroughcompletedversion' => '1.4.0'];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')
			->willReturnCallback(static fn (string $userId, string $app, string $key, string $default): string => $stored[$key] ?? $default);
		$reads = new WalkthroughPreferencesFirstLoad(config: $config, keys: new PreferenceKey());

		$this->assertSame(expected: ['seen' => '1.4.0', 'progress' => null], actual: $reads->firstLoad(userId: 'marieke'));
		$this->assertNull(actual: $reads->firstLoad(userId: ''));
	}//end testTheWalkthroughPreferencesAreReadAsTheEndpointReadsThem()

	/**
	 * The stored key is the endpoint's: `pref_` plus the sanitised key.
	 *
	 * @return void
	 */
	public function testTheStoredKeyMatchesThePreferenceEndpoint(): void {
		$keys = new PreferenceKey();

		$this->assertSame(expected: 'pref_walkthroughcompletedversion', actual: $keys->stored(key: 'walkthrough_completed_version'));
		$this->assertSame(
			expected: 'pref_walkthroughcompletedversion-progress',
			actual: $keys->stored(key: 'walkthrough_completed_version-progress')
		);
		$this->assertSame(expected: '', actual: $keys->stored(key: '__'));
		// The demo seed marks the tour seen under the same stored name.
		$this->assertSame(
			expected: $keys->stored(key: WalkthroughPreferencesFirstLoad::CONFIG_KEY),
			actual: DemoSeedPreferences::WALKTHROUGH_PREF_KEY
		);
	}//end testTheStoredKeyMatchesThePreferenceEndpoint()
	/**
	 * The holder hands each read out under its initial-state key; a read that
	 * is not wired is null, so the builder leaves its key out.
	 *
	 * @return void
	 */
	public function testTheShellReadsComeByInitialStateKey(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->settings->method('getAllowedAdminSettings')->willReturn([]);

		$this->assertSame(
			expected: ['runtimeManifest' => null, 'setup_status' => ['forbidden' => true], 'walkthrough_preferences' => null],
			actual: (new ShellBootReads(setupStatus: $this->setupStatus()))->firstLoad(userId: 'marieke')
		);
		$this->assertSame(
			expected: ['runtimeManifest' => null, 'setup_status' => null, 'walkthrough_preferences' => null],
			actual: (new ShellBootReads())->firstLoad(userId: 'marieke')
		);
	}//end testTheShellReadsComeByInitialStateKey()
}//end class
