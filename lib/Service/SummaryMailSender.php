<?php

/**
 * SummaryMailSender
 *
 * Builds and sends one summary email (personal-settings REQ-PSET-002) through
 * Nextcloud's mailer and email template, in the person's own language.
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

use OCA\LaunchPad\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends one person's summary.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailSender {
	/**
	 * Constructor.
	 *
	 * @param IMailer         $mailer The mailer.
	 * @param IFactory        $l10n   The person's language.
	 * @param IURLGenerator   $urls   The link back to LaunchPad.
	 * @param LoggerInterface $logger A summary that could not be sent.
	 */
	public function __construct(
		private readonly IMailer $mailer,
		private readonly IFactory $l10n,
		private readonly IURLGenerator $urls,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Send the summary to the person's email address.
	 *
	 * @param IUser                                                     $user      The person, with an email address.
	 * @param array{announcements: list<string>, changes: list<string>} $lines     What it says.
	 * @param string                                                    $frequency Daily or weekly.
	 *
	 * @return bool True when the mailer took it.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function send(IUser $user, array $lines, string $frequency): bool {
		try {
			$l10n = $this->l10n->get(Application::APP_ID, $this->l10n->getUserLanguage($user));
			$message = $this->mailer->createMessage();
			$message->setTo([(string)$user->getEMailAddress() => $user->getDisplayName()]);
			$message->useTemplate($this->template(l10n: $l10n, lines: $lines, frequency: $frequency));
			$this->mailer->send($message);
		} catch (Throwable $exception) {
			$this->logger->warning(
				'LaunchPad summary mail to {user} failed: {message}',
				['user' => $user->getUID(), 'message' => $exception->getMessage()]
			);
			return false;
		}

		return true;
	}//end send()

	/**
	 * The mail: heading, the announcements, what changed, a link back.
	 *
	 * @param IL10N                                                     $l10n      The person's language.
	 * @param array{announcements: list<string>, changes: list<string>} $lines     What it says.
	 * @param string                                                    $frequency Daily or weekly.
	 *
	 * @return IEMailTemplate
	 */
	private function template(IL10N $l10n, array $lines, string $frequency): IEMailTemplate {
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

		self::addList(template: $template, heading: $l10n->t('New announcements'), items: $lines['announcements']);
		self::addList(template: $template, heading: $l10n->t('What changed on your dashboards'), items: $lines['changes']);

		$template->addBodyButton(
			$l10n->t('Open LaunchPad'),
			$this->urls->getAbsoluteURL($this->urls->linkToRoute('launchpad.page.index'))
		);
		$template->addFooter($l10n->t('You get this mail because you asked for it in your LaunchPad settings.'));

		return $template;
	}//end template()

	/**
	 * A heading and its items, when there are any.
	 *
	 * @param IEMailTemplate $template The mail.
	 * @param string         $heading  The heading.
	 * @param list<string>   $items    The items.
	 *
	 * @return void
	 */
	private static function addList(IEMailTemplate $template, string $heading, array $items): void {
		if ($items === []) {
			return;
		}

		$template->addBodyText($heading);
		foreach ($items as $item) {
			$template->addBodyListItem($item);
		}
	}//end addList()
}//end class
