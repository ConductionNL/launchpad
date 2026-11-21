<?php

/**
 * DefaultAppSetting
 *
 * Makes LaunchPad the instance's default app — the page a person lands on
 * after signing in — when the administrator has not chosen one, and gives
 * that choice back when the administrator switches it off.
 *
 * Nextcloud keeps the default app in the `defaultapp` system value. When the
 * value is absent no choice was made, and Nextcloud falls back to its own
 * default (dashboard, then files). LaunchPad only ever fills an absent value:
 * a default the administrator set is kept as it is, and so is an empty value,
 * which is what Nextcloud's own "reset to default" in the theming settings
 * writes.
 *
 * Because LaunchPad only fills an absent value, the previous default was always
 * "no choice made": switching the toggle off removes the value again, so
 * Nextcloud's own fallback applies as before. When the administrator changed
 * the default app themselves after LaunchPad set it, switching the toggle off
 * leaves their choice alone.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\IConfig;

/**
 * Sets, keeps or restores the instance default app.
 *
 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
 */
class DefaultAppSetting {

	/**
	 * The Nextcloud system value that holds the default app.
	 *
	 * @var string
	 */
	public const SYSTEM_KEY = 'defaultapp';

	/**
	 * Outcome: LaunchPad is now the default app.
	 *
	 * @var string
	 */
	public const SET = 'set';

	/**
	 * Outcome: the administrator's own default app was kept.
	 *
	 * @var string
	 */
	public const KEPT = 'kept';

	/**
	 * Outcome: the toggle is off, nothing was changed.
	 *
	 * @var string
	 */
	public const DISABLED = 'disabled';

	/**
	 * Outcome: the default app is unset again, so Nextcloud's own fallback applies.
	 *
	 * @var string
	 */
	public const RESTORED = 'restored';

	/**
	 * Outcome: nothing to change.
	 *
	 * @var string
	 */
	public const UNCHANGED = 'unchanged';

	/**
	 * Constructor.
	 *
	 * @param IConfig            $config        The system configuration.
	 * @param AdminSettingMapper $settingMapper The admin settings store.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly AdminSettingMapper $settingMapper,
	) {
	}//end __construct()

	/**
	 * Whether LaunchPad should be the default app. On unless switched off.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	public function isEnabled(): bool {
		return $this->settingMapper->getValue(key: AdminSettingKey::DEFAULT_APP_ENABLED->value, default: true) !== false;
	}//end isEnabled()

	/**
	 * Make LaunchPad the default app when the toggle is on and no default app is configured.
	 *
	 * Runs on install and on every upgrade, so it must never overwrite a choice:
	 * only an absent `defaultapp` value is filled.
	 *
	 * @return string One of {@see self::SET}, {@see self::KEPT}, {@see self::DISABLED}
	 *                or {@see self::UNCHANGED} (LaunchPad already is the default).
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	public function claim(): string {
		if ($this->isEnabled() === false) {
			return self::DISABLED;
		}

		$current = $this->current();
		if ($current === Application::APP_ID) {
			return self::UNCHANGED;
		}

		if ($current !== null) {
			return self::KEPT;
		}

		$this->config->setSystemValue(self::SYSTEM_KEY, Application::APP_ID);
		return self::SET;
	}//end claim()

	/**
	 * Switch the toggle. Only a change acts: off restores, on sets.
	 *
	 * @param bool $enabled The new toggle value.
	 *
	 * @return string What happened to the default app.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	public function setEnabled(bool $enabled): string {
		if ($enabled === $this->isEnabled()) {
			return self::UNCHANGED;
		}

		$this->settingMapper->setSetting(key: AdminSettingKey::DEFAULT_APP_ENABLED->value, value: $enabled);
		if ($enabled === true) {
			return $this->claim();
		}

		return $this->release();
	}//end setEnabled()

	/**
	 * Give back the empty default LaunchPad filled, when LaunchPad still is the default.
	 *
	 * @return string {@see self::RESTORED} or {@see self::UNCHANGED}.
	 */
	private function release(): string {
		if ($this->current() !== Application::APP_ID) {
			return self::UNCHANGED;
		}

		$this->config->deleteSystemValue(self::SYSTEM_KEY);
		return self::RESTORED;
	}//end release()

	/**
	 * The default app as configured, trimmed; null when no value is configured.
	 *
	 * @return string|null
	 */
	private function current(): ?string {
		$value = $this->config->getSystemValue(self::SYSTEM_KEY, null);
		if ($value === null) {
			return null;
		}

		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end current()
}//end class
