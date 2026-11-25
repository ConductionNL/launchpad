<?php

/**
 * SummaryMailService
 *
 * Sends launchpad's summary email (personal-settings REQ-PSET-002) to every
 * person who chose one: at 07:30 in their own time zone, every day or on
 * Monday, to their email address. A daily summary with nothing in it is not
 * sent, a person without an email address gets none, and the moment of the
 * last summary is recorded so the next one starts where it ended.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\AppInfo\Application;
use OCP\IConfig;
use OCP\IUserManager;

/**
 * Decides who is due and has their summary sent.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailService {

	/**
	 * The local hour and minute a summary goes out.
	 */
	public const SEND_HOUR = 7;

	public const SEND_MINUTE = 30;

	/**
	 * The local hour after which a missed morning is not sent any more, so a
	 * summary never arrives in the evening.
	 */
	public const LATEST_HOUR = 12;

	/**
	 * Constructor.
	 *
	 * @param IConfig               $config     The user preferences.
	 * @param IUserManager          $users      Finds the people.
	 * @param SummaryMailPreference $preference Each person's choice.
	 * @param SummaryMailContent    $content    What a summary says.
	 * @param SummaryMailSender     $sender     Sends it.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IUserManager $users,
		private readonly SummaryMailPreference $preference,
		private readonly SummaryMailContent $content,
		private readonly SummaryMailSender $sender,
	) {
	}//end __construct()

	/**
	 * Send every summary that is due now.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return int How many summaries went out.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function sendDue(DateTimeImmutable $now): int {
		$sent = 0;
		foreach ([SummaryMailPreference::DAILY, SummaryMailPreference::WEEKLY] as $frequency) {
			$userIds = $this->config->getUsersForUserValue(Application::APP_ID, SummaryMailPreference::KEY_FREQUENCY, $frequency);
			foreach ($userIds as $userId) {
				$sent += (int)$this->sendFor(userId: (string)$userId, now: $now);
			}
		}

		return $sent;
	}//end sendDue()

	/**
	 * Send one person's summary when it is due by their own choice.
	 *
	 * @param string            $userId The person.
	 * @param DateTimeImmutable $now    The moment of the run.
	 *
	 * @return bool True when a summary went out.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function sendFor(string $userId, DateTimeImmutable $now): bool {
		$user = $this->users->get($userId);
		if ($user === null || (string)$user->getEMailAddress() === '') {
			return false;
		}

		$frequency = $this->preference->frequency(userId: $userId);
		$local = $now->setTimezone($this->timeZoneOf(userId: $userId));
		$sendAt = self::dueMoment(frequency: $frequency, local: $local);
		$lastSent = $this->lastSent(userId: $userId);
		if ($sendAt === null || ($lastSent !== null && $lastSent >= $sendAt)) {
			return false;
		}

		$since = ($lastSent ?? $local->modify(self::windowOf(frequency: $frequency)));
		$summary = ['frequency' => $frequency] + $this->content->collect(userId: $userId, since: $since);
		$empty = ($summary['announcements'] === [] && $summary['changes'] === []);
		if ($empty === true && $frequency === SummaryMailPreference::DAILY) {
			// Nothing to tell: no mail, and the window stays open so a change
			// later today is in tomorrow's summary.
			return false;
		}

		if ($this->sender->send(user: $user, summary: $summary) === false) {
			return false;
		}

		$this->config->setUserValue($userId, Application::APP_ID, SummaryMailPreference::KEY_LAST_SENT, $now->format(DATE_ATOM));

		return true;
	}//end sendFor()

	/**
	 * Today's send moment (07:30 local) when a summary of this frequency is
	 * due at this local moment: on or after 07:30 and before noon, and on
	 * Monday for a weekly one. Null when it is not.
	 *
	 * @param string            $frequency Off, daily or weekly.
	 * @param DateTimeImmutable $local     Now, in the person's time zone.
	 *
	 * @return DateTimeImmutable|null
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public static function dueMoment(string $frequency, DateTimeImmutable $local): ?DateTimeImmutable {
		$onItsDay = ($frequency === SummaryMailPreference::DAILY)
			|| ($frequency === SummaryMailPreference::WEEKLY && $local->format('N') === '1');
		$sendAt = $local->setTime(self::SEND_HOUR, self::SEND_MINUTE);
		if ($onItsDay === false || $local < $sendAt || (int)$local->format('G') >= self::LATEST_HOUR) {
			return null;
		}

		return $sendAt;
	}//end dueMoment()

	/**
	 * How far back a first summary looks.
	 *
	 * @param string $frequency Daily or weekly.
	 *
	 * @return string A `modify()` expression.
	 */
	private static function windowOf(string $frequency): string {
		if ($frequency === SummaryMailPreference::WEEKLY) {
			return '-7 days';
		}

		return '-1 day';
	}//end windowOf()

	/**
	 * The person's time zone, else the server's.
	 *
	 * @param string $userId The person.
	 *
	 * @return DateTimeZone
	 */
	private function timeZoneOf(string $userId): DateTimeZone {
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
	 */
	private function lastSent(string $userId): ?DateTimeImmutable {
		$value = (string)$this->config->getUserValue($userId, Application::APP_ID, SummaryMailPreference::KEY_LAST_SENT, '');
		$parsed = date_create_immutable_from_format(DATE_ATOM, $value);
		if ($parsed === false) {
			return null;
		}

		return $parsed;
	}//end lastSent()
}//end class
