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
	 * @param IUserManager          $users      Finds the people.
	 * @param SummaryMailPreference $preference Each person's choice, time zone and last send.
	 * @param SummaryMailContent    $content    What a summary says.
	 * @param SummaryMailSender     $sender     Sends it.
	 */
	public function __construct(
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
			foreach ($this->preference->usersWith(frequency: $frequency) as $userId) {
				$sent += (int)$this->sendFor(userId: $userId, now: $now);
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
		$since = $this->windowStart(userId: $userId, now: $now);
		if ($user === null || (string)$user->getEMailAddress() === '' || $since === null) {
			return false;
		}

		$summary = ['frequency' => $this->preference->frequency(userId: $userId)]
			+ $this->content->collect(userId: $userId, since: $since);
		if (self::worthSending(summary: $summary) === false
			|| $this->sender->send(user: $user, summary: $summary) === false
		) {
			// An empty daily summary is not sent, and a failed send is not
			// recorded: either way the window stays open for the next run.
			return false;
		}

		$this->preference->recordSent(userId: $userId, sentAt: $now);

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
	 * Where this person's summary starts when one is due now: the last
	 * summary, or a day (a week) back for a first one. Null when none is due.
	 *
	 * @param string            $userId The person.
	 * @param DateTimeImmutable $now    The moment of the run.
	 *
	 * @return DateTimeImmutable|null
	 */
	private function windowStart(string $userId, DateTimeImmutable $now): ?DateTimeImmutable {
		$frequency = $this->preference->frequency(userId: $userId);
		$local = $now->setTimezone($this->preference->timeZoneOf(userId: $userId));
		$sendAt = self::dueMoment(frequency: $frequency, local: $local);
		$lastSent = $this->preference->lastSent(userId: $userId);
		if ($sendAt === null || ($lastSent !== null && $lastSent >= $sendAt)) {
			return null;
		}

		if ($lastSent !== null) {
			return $lastSent;
		}

		if ($frequency === SummaryMailPreference::WEEKLY) {
			return $local->modify('-7 days');
		}

		return $local->modify('-1 day');
	}//end windowStart()

	/**
	 * Whether a summary is worth a mail: a weekly one always, a daily one
	 * only when something was published or changed.
	 *
	 * @param array{frequency: string, announcements: list<string>, changes: list<string>} $summary The summary.
	 *
	 * @return bool
	 */
	private static function worthSending(array $summary): bool {
		return $summary['frequency'] !== SummaryMailPreference::DAILY
			|| $summary['announcements'] !== []
			|| $summary['changes'] !== [];
	}//end worthSending()
}//end class
