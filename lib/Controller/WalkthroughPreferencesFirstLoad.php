<?php

/**
 * WalkthroughPreferencesFirstLoad
 *
 * The walkthrough preferences the workspace page carries for the app shell.
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
use OCA\LaunchPad\Service\PreferenceKey;
use OCP\IConfig;

/**
 * `{seen, progress}`: what `GET /api/preferences/{key}` answers for the
 * walkthrough's completion key and its `-progress` twin, for the page's
 * `walkthrough_preferences` key (REQ-INIT-010).
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class WalkthroughPreferencesFirstLoad {
	/**
	 * The walkthrough's completion key (`manifest.walkthrough.completionConfigKey`).
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'walkthrough_completed_version';

	/**
	 * Constructor.
	 *
	 * @param IConfig       $config The caller's stored preferences.
	 * @param PreferenceKey $keys   The user value a preference key is stored under.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly PreferenceKey $keys,
	) {
	}//end __construct()

	/**
	 * Both preferences as stored (null when unset); null without a user.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array{seen: string|null, progress: string|null}|null The preferences, or null.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId): ?array {
		if ($userId === '') {
			return null;
		}

		return [
			'seen'     => $this->stored(userId: $userId, key: self::CONFIG_KEY),
			'progress' => $this->stored(userId: $userId, key: self::CONFIG_KEY.'-progress'),
		];
	}//end firstLoad()

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
	private function stored(string $userId, string $key): ?string {
		$value = $this->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: $this->keys->stored(key: $key),
			default: ''
		);
		if ($value === '') {
			return null;
		}

		return $value;
	}//end stored()
}//end class
