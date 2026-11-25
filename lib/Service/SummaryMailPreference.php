<?php

/**
 * SummaryMailPreference
 *
 * How often one person wants launchpad's summary email (personal-settings
 * REQ-PSET-002): off, daily or weekly. Stored per person in Nextcloud's user
 * preferences, and off until the person changes it.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCP\IConfig;

/**
 * Reads and writes the summary email frequency of one person.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailPreference {

	/**
	 * The user preference that holds the frequency.
	 */
	public const KEY_FREQUENCY = 'summary_mail_frequency';

	/**
	 * The user preference that holds when the last summary went out, ISO 8601.
	 */
	public const KEY_LAST_SENT = 'summary_mail_last_sent';

	public const OFF = 'off';

	public const DAILY = 'daily';

	public const WEEKLY = 'weekly';

	/**
	 * The frequencies a person can choose.
	 */
	public const FREQUENCIES = [self::OFF, self::DAILY, self::WEEKLY];

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The user preferences.
	 */
	public function __construct(
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The person's frequency; off when they never chose, or when the stored
	 * value is not one of the three.
	 *
	 * @param string $userId The person.
	 *
	 * @return string One of FREQUENCIES.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function frequency(string $userId): string {
		$value = (string)$this->config->getUserValue($userId, Application::APP_ID, self::KEY_FREQUENCY, self::OFF);
		if (in_array($value, self::FREQUENCIES, true) === false) {
			return self::OFF;
		}

		return $value;
	}//end frequency()

	/**
	 * Store the person's frequency.
	 *
	 * @param string $userId    The person.
	 * @param string $frequency One of FREQUENCIES.
	 *
	 * @return string The stored frequency.
	 *
	 * @throws InvalidArgumentException When the frequency is not one of the three.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function setFrequency(string $userId, string $frequency): string {
		if (in_array($frequency, self::FREQUENCIES, true) === false) {
			throw new InvalidArgumentException('Unknown summary frequency: '.$frequency);
		}

		$this->config->setUserValue($userId, Application::APP_ID, self::KEY_FREQUENCY, $frequency);

		return $frequency;
	}//end setFrequency()
}//end class
