<?php

/**
 * ShellBootReads
 *
 * The setup status and the walkthrough preferences the app shell reads on
 * every page, carried in the workspace page's initial state.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Kernel\Contracts\DemoDataServiceInterface;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Settings\IManager as ISettingsManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the shell (nextcloud-vue CnAppRoot) asks on every load besides the
 * manifest: `GET /api/setup/status` and the two walkthrough preferences. The
 * page carries them as `setup_status` and `walkthrough_preferences`, the keys
 * the library reads before it asks (REQ-INIT-010).
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class ShellBootReads {
	/**
	 * The walkthrough's completion key (`manifest.walkthrough.completionConfigKey`).
	 * The tour progress lives next to it under `<key>-progress`.
	 *
	 * @var string
	 */
	public const WALKTHROUGH_CONFIG_KEY = 'walkthrough_completed_version';

	/**
	 * The admin settings section LaunchPad's setup endpoints are delegated by.
	 *
	 * @var string
	 */
	private const SETTINGS_SECTION = 'launchpad';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig               $appConfig       Holds the demo-data decision (the status document).
	 * @param DemoDataServiceInterface $demoDataService Lists the datasets (the status document).
	 * @param IConfig                  $config          The caller's stored preferences.
	 * @param IGroupManager            $groupManager    Whether the caller is a Nextcloud admin.
	 * @param IUserManager             $userManager     Resolves the caller for the delegation check.
	 * @param ISettingsManager         $settingsManager Whether LaunchPad's admin settings are delegated to the caller.
	 * @param LoggerInterface          $logger          Records a read that failed and was left out.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly DemoDataServiceInterface $demoDataService,
		private readonly IConfig $config,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly ISettingsManager $settingsManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * What `GET /api/setup/status` answers the caller: the document for a
	 * Nextcloud admin, `{forbidden: true}` for a caller the endpoint refuses.
	 * Null (the shell asks the endpoint) for a caller LaunchPad's admin
	 * settings are delegated to, without a user, or when a read fails.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array<string, mixed>|null The carried status, or null.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function setupStatus(string $userId): ?array {
		if ($userId === '') {
			return null;
		}

		try {
			if ($this->groupManager->isAdmin(userId: $userId) === true) {
				return SetupController::statusDocument(appConfig: $this->appConfig, demoDataService: $this->demoDataService);
			}

			if ($this->isDelegatedAdmin(userId: $userId) === true) {
				return null;
			}
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: setup status left out of the page: '.$e->getMessage(),
				['app' => Application::APP_ID]
			);
			return null;
		}

		return ['forbidden' => true];
	}//end setupStatus()

	/**
	 * `{seen, progress}`: what `GET /api/preferences/{key}` answers for the
	 * walkthrough's completion key and its `-progress` twin (the stored
	 * string, or null when unset). Null without a user or when a read fails.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array{seen: string|null, progress: string|null}|null The preferences, or null.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function walkthroughPreferences(string $userId): ?array {
		if ($userId === '') {
			return null;
		}

		try {
			return [
				'seen'     => $this->storedPreference(userId: $userId, key: self::WALKTHROUGH_CONFIG_KEY),
				'progress' => $this->storedPreference(userId: $userId, key: self::WALKTHROUGH_CONFIG_KEY.'-progress'),
			];
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: walkthrough preferences left out of the page: '.$e->getMessage(),
				['app' => Application::APP_ID]
			);
			return null;
		}
	}//end walkthroughPreferences()

	/**
	 * One preference as `GET /api/preferences/{key}` reads it.
	 *
	 * @param string $userId The caller's uid.
	 * @param string $key    The preference key as the browser names it.
	 *
	 * @return string|null The stored value, or null when unset.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	private function storedPreference(string $userId, string $key): ?string {
		$value = $this->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: PreferencesController::storedKey(key: $key),
			default: ''
		);

		if ($value === '') {
			return null;
		}

		return $value;
	}//end storedPreference()

	/**
	 * Whether LaunchPad's admin settings are delegated to the caller, which is
	 * what lets a non-admin through `#[AuthorizedAdminSetting(LaunchPadAdmin)]`.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return bool True when the caller may use LaunchPad's admin settings.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	private function isDelegatedAdmin(string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return false;
		}

		foreach ($this->settingsManager->getAllowedAdminSettings(section: self::SETTINGS_SECTION, user: $user) as $settings) {
			foreach ($settings as $setting) {
				if ($setting instanceof LaunchPadAdmin) {
					return true;
				}
			}
		}

		return false;
	}//end isDelegatedAdmin()
}//end class
