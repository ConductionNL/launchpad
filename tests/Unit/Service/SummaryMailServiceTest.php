<?php

/**
 * SummaryMailService Test
 *
 * personal-settings REQ-PSET-002: the summary goes out at 07:30 in the
 * person's own time zone, daily or on Monday, once; an empty daily summary
 * and a person without an email address get none; the last send is recorded.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Service\SummaryMailContent;
use OCA\LaunchPad\Service\SummaryMailService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for SummaryMailService.
 */
class SummaryMailServiceTest extends TestCase {
	/**
	 * Stored user values, keyed "user|app|key".
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * Body list items of the mails sent.
	 *
	 * @var list<list<string>>
	 */
	private array $mails = [];

	/**
	 * What the content collector answers.
	 *
	 * @var array{announcements: list<string>, changes: list<string>}
	 */
	private array $lines = ['announcements' => [], 'changes' => []];

	protected function setUp(): void {
		parent::setUp();
		$this->values = [];
		$this->mails = [];
		$this->lines = ['announcements' => [], 'changes' => []];
	}//end setUp()

	public function testDailyIsDueFrom0730UntilNoonOncePerDay(): void {
		$tz = new DateTimeZone('Europe/Amsterdam');
		$at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable('2026-11-25 '.$time, $tz);

		$this->assertFalse(SummaryMailService::isDue('daily', $at('07:29'), null));
		$this->assertTrue(SummaryMailService::isDue('daily', $at('07:30'), null));
		$this->assertTrue(SummaryMailService::isDue('daily', $at('08:15'), $at('07:31')->modify('-1 day')));
		$this->assertFalse(SummaryMailService::isDue('daily', $at('08:15'), $at('07:31')));
		$this->assertFalse(SummaryMailService::isDue('daily', $at('12:00'), null));
		$this->assertFalse(SummaryMailService::isDue('off', $at('07:45'), null));
	}//end testDailyIsDueFrom0730UntilNoonOncePerDay()

	public function testWeeklyIsDueOnMondayOnly(): void {
		$tz = new DateTimeZone('Europe/Amsterdam');

		// 30 November 2026 is a Monday, 25 November a Wednesday.
		$this->assertTrue(SummaryMailService::isDue('weekly', new DateTimeImmutable('2026-11-30 07:40', $tz), null));
		$this->assertFalse(SummaryMailService::isDue('weekly', new DateTimeImmutable('2026-11-25 07:40', $tz), null));
	}//end testWeeklyIsDueOnMondayOnly()

	public function testPietersDailySummaryNamesTheAnnouncementAndTheChange(): void {
		$this->values['pieter|core|timezone'] = 'Europe/Amsterdam';
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => ['Team Vergunningen: Widget Teamagenda added']];

		// 06:35 UTC is 07:35 in Amsterdam.
		$now = new DateTimeImmutable('2026-11-25 06:35', new DateTimeZone('UTC'));
		$this->assertTrue($this->service()->sendFor('pieter', 'daily', $now));

		$this->assertCount(1, $this->mails);
		$this->assertContains('Kerstborrel', $this->mails[0]);
		$this->assertContains('Team Vergunningen: Widget Teamagenda added', $this->mails[0]);
		$this->assertSame($now->format(DATE_ATOM), $this->values['pieter|launchpad|summary_mail_last_sent']);

		// The next hourly run the same morning sends nothing.
		$this->assertFalse($this->service()->sendFor('pieter', 'daily', $now->modify('+1 hour')));
		$this->assertCount(1, $this->mails);
	}//end testPietersDailySummaryNamesTheAnnouncementAndTheChange()

	public function testTheTimeZoneIsThePersons(): void {
		$this->values['pieter|core|timezone'] = 'America/New_York';
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];

		// 06:35 UTC is 01:35 in New York: not yet.
		$this->assertFalse($this->service()->sendFor('pieter', 'daily', new DateTimeImmutable('2026-11-25 06:35', new DateTimeZone('UTC'))));
		// 12:35 UTC is 07:35 in New York.
		$this->assertTrue($this->service()->sendFor('pieter', 'daily', new DateTimeImmutable('2026-11-25 12:35', new DateTimeZone('UTC'))));
	}//end testTheTimeZoneIsThePersons()

	public function testAnEmptyDailySummaryIsNotSent(): void {
		$this->values['pieter|core|timezone'] = 'UTC';

		$this->assertFalse($this->service()->sendFor('pieter', 'daily', new DateTimeImmutable('2026-11-25 07:35', new DateTimeZone('UTC'))));
		$this->assertSame([], $this->mails);
		$this->assertArrayNotHasKey('pieter|launchpad|summary_mail_last_sent', $this->values);
	}//end testAnEmptyDailySummaryIsNotSent()

	public function testAPersonWithoutEmailGetsNoSummary(): void {
		$this->values['nomail|core|timezone'] = 'UTC';
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];

		$this->assertFalse($this->service()->sendFor('nomail', 'daily', new DateTimeImmutable('2026-11-25 07:35', new DateTimeZone('UTC'))));
		$this->assertFalse($this->service()->sendFor('ghost', 'daily', new DateTimeImmutable('2026-11-25 07:35', new DateTimeZone('UTC'))));
		$this->assertSame([], $this->mails);
	}//end testAPersonWithoutEmailGetsNoSummary()

	public function testSendDueWalksTheDailyAndWeeklyPeopleOnly(): void {
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];
		$asked = [];

		$sent = $this->service(
			function (string $app, string $key, string $value) use (&$asked): array {
				$asked[] = $value;
				return $value === 'daily' ? ['pieter'] : [];
			}
		)->sendDue(new DateTimeImmutable('2026-11-25 07:35', new DateTimeZone('UTC')));

		$this->assertSame(['daily', 'weekly'], $asked);
		$this->assertSame(1, $sent);
	}//end testSendDueWalksTheDailyAndWeeklyPeopleOnly()

	/**
	 * The service over fakes.
	 *
	 * @param callable|null $usersFor Answers getUsersForUserValue.
	 *
	 * @return SummaryMailService
	 */
	private function service(?callable $usersFor = null): SummaryMailService {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => $this->values["$user|$app|$key"] ?? $default
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, $value): void {
				$this->values["$user|$app|$key"] = (string)$value;
			}
		);
		$config->method('getUsersForUserValue')->willReturnCallback($usersFor ?? static fn (): array => []);

		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(
			function (string $uid): ?IUser {
				if ($uid === 'ghost') {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($uid);
				$user->method('getDisplayName')->willReturn(ucfirst($uid));
				$user->method('getEMailAddress')->willReturn($uid === 'nomail' ? null : $uid.'@zuiddrecht.example');
				return $user;
			}
		);

		$content = $this->createMock(SummaryMailContent::class);
		$content->method('collect')->willReturnCallback(fn (): array => $this->lines);

		$items = [];
		$template = $this->createMock(IEMailTemplate::class);
		$template->method('addBodyListItem')->willReturnCallback(
			function (string $text) use (&$items): void {
				$items[] = $text;
			}
		);
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createEMailTemplate')->willReturnCallback(
			function () use ($template, &$items): IEMailTemplate {
				$items = [];
				return $template;
			}
		);
		$mailer->method('createMessage')->willReturn($this->createMock(IMessage::class));
		$mailer->method('send')->willReturnCallback(
			function () use (&$items): array {
				$this->mails[] = $items;
				return [];
			}
		);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$factory->method('getUserLanguage')->willReturn('en');

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturn('/apps/launchpad/');
		$urls->method('getAbsoluteURL')->willReturnArgument(0);

		return new SummaryMailService($config, $users, $content, $mailer, $factory, $urls, $this->createMock(LoggerInterface::class));
	}//end service()
}//end class
