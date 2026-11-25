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
use DateTimeZone;
use Exception;
use OCA\LaunchPad\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Decides who is due and sends their summary.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailService {

	/**
	 * The local time a summary goes out.
	 */
	public const SEND_AT = '07:30';

	/**
	 * The local hour after which a missed morning is not sent any more, so a
	 * summary never arrives in the evening.
	 */
	public const LATEST_HOUR = 12;

	/**
	 * Constructor.
	 *
	 * @param IConfig         $config      The user preferences.
	 * @param IUserManager    $users       Finds the people.
	 * @param SummaryMailContent $content  What a summary says.
	 * @param IMailer         $mailer      Sends the mail.
	 * @param IFactory        $l10n        The person's language.
	 * @param IURLGenerator   $urls        The link back to launchpad.
	 * @param LoggerInterface $logger      A summary that could not be sent.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IUserManager $users,
		private readonly SummaryMailContent $content,
		private readonly IMailer $mailer,
		private readonly IFactory $l10n,
		private readonly IURLGenerator $urls,
		private readonly LoggerInterface $logger,
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
				if ($this->sendFor(userId: (string)$userId, frequency: $frequency, now: $now) === true) {
					$sent++;
				}
			}
		}

		return $sent;
	}//end sendDue()

	/**
	 * Send one person's summary when it is due.
	 *
	 * @param string            $userId    The person.
	 * @param string            $frequency Daily or weekly.
	 * @param DateTimeImmutable $now       The moment of the run.
	 *
	 * @return bool True when a summary went out.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function sendFor(string $userId, string $frequency, DateTimeImmutable $now): bool {
		$user = $this->users->get($userId);
		if ($user === null || (string)$user->getEMailAddress() === '') {
			return false;
		}

		$local = $now->setTimezone($this->timeZoneOf(userId: $userId));
		$lastSent = $this->lastSent(userId: $userId);
		if (self::isDue(frequency: $frequency, local: $local, lastSent: $lastSent) === false) {
			return false;
		}

		$since = $lastSent ?? $local->modify(self::windowOf(frequency: $frequency));
		$lines = $this->content->collect(userId: $userId, since: $since);
		$empty = ($lines['announcements'] === [] && $lines['changes'] === []);
		if ($empty === true && $frequency === SummaryMailPreference::DAILY) {
			// Nothing to tell: no mail, and the window stays open so a change
			// later today is in tomorrow's summary.
			return false;
		}

		try {
			$this->deliver(user: $user, lines: $lines, frequency: $frequency);
		} catch (Throwable $exception) {
			$this->logger->warning('LaunchPad summary mail to {user} failed: {message}', ['user' => $userId, 'message' => $exception->getMessage()]);
			return false;
		}

		$this->config->setUserValue($userId, Application::APP_ID, SummaryMailPreference::KEY_LAST_SENT, $now->format(DATE_ATOM));

		return true;
	}//end sendFor()

	/**
	 * Whether a summary is due at this local moment: on or after 07:30 and
	 * before noon, on Monday for a weekly one, and not sent since today's 07:30.
	 *
	 * @param string                 $frequency Daily or weekly.
	 * @param DateTimeImmutable      $local     Now, in the person's time zone.
	 * @param DateTimeImmutable|null $lastSent  The last summary, if any.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public static function isDue(string $frequency, DateTimeImmutable $local, ?DateTimeImmutable $lastSent): bool {
		if ($frequency !== SummaryMailPreference::DAILY && $frequency !== SummaryMailPreference::WEEKLY) {
			return false;
		}

		if ($frequency === SummaryMailPreference::WEEKLY && $local->format('N') !== '1') {
			return false;
		}

		[$hour, $minute] = array_map('intval', explode(':', self::SEND_AT));
		$sendAt = $local->setTime($hour, $minute);
		if ($local < $sendAt || (int)$local->format('G') >= self::LATEST_HOUR) {
			return false;
		}

		return ($lastSent === null || $lastSent < $sendAt);
	}//end isDue()

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
		if ($name === '') {
			$name = date_default_timezone_get();
		}

		try {
			return new DateTimeZone($name);
		} catch (Exception) {
			return new DateTimeZone('UTC');
		}
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
		if ($value === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Exception) {
			return null;
		}
	}//end lastSent()

	/**
	 * Build and send the mail, in the person's language.
	 *
	 * @param IUser                                                        $user      The person.
	 * @param array{announcements: list<string>, changes: list<string>} $lines     What it says.
	 * @param string                                                       $frequency Daily or weekly.
	 *
	 * @return void
	 */
	private function deliver(IUser $user, array $lines, string $frequency): void {
		$l10n = $this->l10n->get(Application::APP_ID, $this->l10n->getUserLanguage($user));
		$subject = $l10n->t('Your daily LaunchPad summary');
		if ($frequency === SummaryMailPreference::WEEKLY) {
			$subject = $l10n->t('Your weekly LaunchPad summary');
		}

		$template = $this->mailer->createEMailTemplate('launchpad.SummaryMail', ['frequency' => $frequency]);
		$template->setSubject($subject);
		$template->addHeader();
		$template->addHeading($subject);

		if ($lines['announcements'] === [] && $lines['changes'] === []) {
			$template->addBodyText($l10n->t('Nothing new was published and nothing changed on your dashboards.'));
		}

		if ($lines['announcements'] !== []) {
			$template->addBodyText($l10n->t('New announcements'));
			foreach ($lines['announcements'] as $title) {
				$template->addBodyListItem($title);
			}
		}

		if ($lines['changes'] !== []) {
			$template->addBodyText($l10n->t('What changed on your dashboards'));
			foreach ($lines['changes'] as $change) {
				$template->addBodyListItem($change);
			}
		}

		$template->addBodyButton(
			$l10n->t('Open LaunchPad'),
			$this->urls->getAbsoluteURL($this->urls->linkToRoute('launchpad.page.index'))
		);
		$template->addFooter($l10n->t('You get this mail because you asked for it in your LaunchPad settings.'));

		$message = $this->mailer->createMessage();
		$message->setTo([(string)$user->getEMailAddress() => $user->getDisplayName()]);
		$message->useTemplate($template);
		$this->mailer->send($message);
	}//end deliver()
}//end class
