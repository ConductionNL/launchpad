<?php

/**
 * SummaryMailPreference
 *
 * How often one person wants launchpad's summary email (personal-settings
 * REQ-PSET-002): off, daily or weekly, off until the person changes it; and
 * what the summary needs to know of that person: their time zone and when
 * their last summary went out. All of it lives in Nextcloud's user preferences.
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

use DateTimeImmutable;
use DateTimeZone;
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

	/**
	 * The people who chose this frequency.
	 *
	 * @param string $frequency Daily or weekly.
	 *
	 * @return list<string> Their user ids.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function usersWith(string $frequency): array {
		return array_values(
			array_map(
				'strval',
				$this->config->getUsersForUserValue(Application::APP_ID, self::KEY_FREQUENCY, $frequency)
			)
		);
	}//end usersWith()

	/**
	 * The person's time zone, else the server's.
	 *
	 * @param string $userId The person.
	 *
	 * @return DateTimeZone
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function timeZoneOf(string $userId): DateTimeZone {
		$name = (string)$this->config->getUserValue($userId, 'core', 'timezone', '');
		if (in_array($name, timezone_identifiers_list(), true) === false) {
			$name = date_default_timezone_get();
		}

		return new DateTimeZone($name);
	}//end timeZoneOf()

	/**
	 * When the person's last summary went out.
	 *
	 * @param string $userId The person.
	 *
	 * @return DateTimeImmutable|null Null when never, or unreadable.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function lastSent(string $userId): ?DateTimeImmutable {
		$value = (string)$this->config->getUserValue($userId, Application::APP_ID, self::KEY_LAST_SENT, '');
		$parsed = date_create_immutable_from_format(DATE_ATOM, $value);
		if ($parsed === false) {
			return null;
		}

		return $parsed;
	}//end lastSent()

	/**
	 * Record that the person's summary went out now.
	 *
	 * @param string            $userId The person.
	 * @param DateTimeImmutable $sentAt The moment.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function recordSent(string $userId, DateTimeImmutable $sentAt): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::KEY_LAST_SENT, $sentAt->format(DATE_ATOM));
	}//end recordSent()
}//end class
