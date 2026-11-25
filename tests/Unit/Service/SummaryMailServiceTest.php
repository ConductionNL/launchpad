<?php

/**
 * SummaryMailService and SummaryMailSender Test
 *
 * Personal-settings REQ-PSET-002: the summary goes out at 07:30 in the
 * person's own time zone, daily or on Monday, once; an empty daily summary
 * and a person without an email address get none; the last send is recorded;
 * the mail names the announcements and what changed.
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
 * @spec openspec/changes/archive/2026-11-25-personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Service\SummaryMailContent;
use OCA\LaunchPad\Service\SummaryMailPreference;
use OCA\LaunchPad\Service\SummaryMailSender;
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
use RuntimeException;

/**
 * Unit tests for the summary mail schedule and delivery.
 */
class SummaryMailServiceTest extends TestCase {

	/**
	 * Stored user values, keyed "user|app|key".
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * Body list items of each mail sent.
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

	/**
	 * Whether the mailer throws.
	 *
	 * @var boolean
	 */
	private bool $mailerFails = false;

	/**
	 * Start every test with nothing stored or sent.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->values = [
			'pieter|launchpad|summary_mail_frequency' => 'daily',
			'nomail|launchpad|summary_mail_frequency' => 'daily',
			'ghost|launchpad|summary_mail_frequency' => 'daily',
		];
		$this->mails = [];
		$this->lines = ['announcements' => [], 'changes' => []];
		$this->mailerFails = false;
	}//end setUp()

	/**
	 * Daily: from 07:30 until noon; off is never due.
	 *
	 * @return void
	 */
	public function testDailyIsDueFrom0730UntilNoon(): void {
		$tz = new DateTimeZone(timezone: 'Europe/Amsterdam');
		$at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable(datetime: '2026-11-25 '.$time, timezone: $tz);

		$this->assertNull(actual: SummaryMailService::dueMoment(frequency: 'daily', local: $at('07:29')));
		$this->assertEquals(expected: $at('07:30'), actual: SummaryMailService::dueMoment(frequency: 'daily', local: $at('07:30')));
		$this->assertEquals(expected: $at('07:30'), actual: SummaryMailService::dueMoment(frequency: 'daily', local: $at('11:59')));
		$this->assertNull(actual: SummaryMailService::dueMoment(frequency: 'daily', local: $at('12:00')));
		$this->assertNull(actual: SummaryMailService::dueMoment(frequency: 'off', local: $at('07:45')));
	}//end testDailyIsDueFrom0730UntilNoon()

	/**
	 * Weekly: on Monday only. 30 November 2026 is a Monday, 25 November a Wednesday.
	 *
	 * @return void
	 */
	public function testWeeklyIsDueOnMondayOnly(): void {
		$tz = new DateTimeZone(timezone: 'Europe/Amsterdam');
		$monday = new DateTimeImmutable(datetime: '2026-11-30 07:40', timezone: $tz);
		$wednesday = new DateTimeImmutable(datetime: '2026-11-25 07:40', timezone: $tz);

		$this->assertNotNull(actual: SummaryMailService::dueMoment(frequency: 'weekly', local: $monday));
		$this->assertNull(actual: SummaryMailService::dueMoment(frequency: 'weekly', local: $wednesday));
	}//end testWeeklyIsDueOnMondayOnly()

	/**
	 * Pieter's daily summary names the announcement and the change, once.
	 *
	 * @return void
	 */
	public function testPietersDailySummaryNamesTheAnnouncementAndTheChange(): void {
		$this->values['pieter|core|timezone'] = 'Europe/Amsterdam';
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => ['Team Vergunningen: Widget Teamagenda added']];

		// 06:35 UTC is 07:35 in Amsterdam.
		$now = $this->utc(time: '06:35');
		$this->assertTrue(condition: $this->service()->sendFor(userId: 'pieter', now: $now));

		$this->assertCount(expectedCount: 1, haystack: $this->mails);
		$this->assertContains(needle: 'Kerstborrel', haystack: $this->mails[0]);
		$this->assertContains(needle: 'Team Vergunningen: Widget Teamagenda added', haystack: $this->mails[0]);
		$this->assertSame(expected: $now->format(DATE_ATOM), actual: $this->values['pieter|launchpad|summary_mail_last_sent']);

		// The next hourly run the same morning sends nothing.
		$this->assertFalse(condition: $this->service()->sendFor(userId: 'pieter', now: $now->modify('+1 hour')));
		$this->assertCount(expectedCount: 1, haystack: $this->mails);
	}//end testPietersDailySummaryNamesTheAnnouncementAndTheChange()

	/**
	 * The 07:30 is the person's own: 06:35 UTC is 01:35 in New York, 12:35 UTC is 07:35.
	 *
	 * @return void
	 */
	public function testTheTimeZoneIsThePersons(): void {
		$this->values['pieter|core|timezone'] = 'America/New_York';
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];

		$this->assertFalse(condition: $this->service()->sendFor(userId: 'pieter', now: $this->utc(time: '06:35')));
		$this->assertTrue(condition: $this->service()->sendFor(userId: 'pieter', now: $this->utc(time: '12:35')));
	}//end testTheTimeZoneIsThePersons()

	/**
	 * An empty daily summary is not sent and the window stays open.
	 *
	 * @return void
	 */
	public function testAnEmptyDailySummaryIsNotSent(): void {
		$this->values['pieter|core|timezone'] = 'UTC';

		$this->assertFalse(condition: $this->service()->sendFor(userId: 'pieter', now: $this->utc(time: '07:35')));
		$this->assertSame(expected: [], actual: $this->mails);
		$this->assertArrayNotHasKey(key: 'pieter|launchpad|summary_mail_last_sent', array: $this->values);
	}//end testAnEmptyDailySummaryIsNotSent()

	/**
	 * A person who turned it off again gets nothing, even when listed.
	 *
	 * @return void
	 */
	public function testAPersonWhoTurnedItOffGetsNothing(): void {
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];
		$this->values['pieter|launchpad|summary_mail_frequency'] = 'off';

		$this->assertFalse(condition: $this->service()->sendFor(userId: 'pieter', now: $this->utc(time: '07:35')));
		$this->assertSame(expected: [], actual: $this->mails);
	}//end testAPersonWhoTurnedItOffGetsNothing()

	/**
	 * An empty weekly summary still goes out, saying nothing changed.
	 *
	 * @return void
	 */
	public function testAnEmptyWeeklySummaryIsSent(): void {
		$this->values['pieter|core|timezone'] = 'UTC';
		$this->values['pieter|launchpad|summary_mail_frequency'] = 'weekly';

		$monday = new DateTimeImmutable(datetime: '2026-11-30 07:35', timezone: new DateTimeZone(timezone: 'UTC'));
		$this->assertTrue(condition: $this->service()->sendFor(userId: 'pieter', now: $monday));
		$this->assertCount(expectedCount: 1, haystack: $this->mails);
	}//end testAnEmptyWeeklySummaryIsSent()

	/**
	 * No email address, or no such user: no summary.
	 *
	 * @return void
	 */
	public function testAPersonWithoutEmailGetsNoSummary(): void {
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];

		$this->assertFalse(condition: $this->service()->sendFor(userId: 'nomail', now: $this->utc(time: '07:35')));
		$this->assertFalse(condition: $this->service()->sendFor(userId: 'ghost', now: $this->utc(time: '07:35')));
		$this->assertSame(expected: [], actual: $this->mails);
	}//end testAPersonWithoutEmailGetsNoSummary()

	/**
	 * A mailer failure records no send, so the next run tries again.
	 *
	 * @return void
	 */
	public function testAFailedSendIsNotRecorded(): void {
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];
		$this->mailerFails = true;

		$this->assertFalse(condition: $this->service()->sendFor(userId: 'pieter', now: $this->utc(time: '07:35')));
		$this->assertArrayNotHasKey(key: 'pieter|launchpad|summary_mail_last_sent', array: $this->values);
	}//end testAFailedSendIsNotRecorded()

	/**
	 * The run walks the daily and weekly people only.
	 *
	 * @return void
	 */
	public function testSendDueWalksTheDailyAndWeeklyPeopleOnly(): void {
		$this->lines = ['announcements' => ['Kerstborrel'], 'changes' => []];
		$asked = [];

		$sent = $this->service(
			usersFor: function (string $app, string $key, string $value) use (&$asked): array {
				$asked[] = $value;
				if ($value === 'daily') {
					return ['pieter'];
				}

				return [];
			}
		)->sendDue(now: $this->utc(time: '07:35'));

		$this->assertSame(expected: ['daily', 'weekly'], actual: $asked);
		$this->assertSame(expected: 1, actual: $sent);
	}//end testSendDueWalksTheDailyAndWeeklyPeopleOnly()

	/**
	 * 25 November 2026 at a UTC time.
	 *
	 * @param string $time Hours and minutes.
	 *
	 * @return DateTimeImmutable
	 */
	private function utc(string $time): DateTimeImmutable {
		return new DateTimeImmutable(datetime: '2026-11-25 '.$time, timezone: new DateTimeZone(timezone: 'UTC'));
	}//end utc()

	/**
	 * The service over fakes.
	 *
	 * @param callable|null $usersFor Answers getUsersForUserValue.
	 *
	 * @return SummaryMailService
	 */
	private function service(?callable $usersFor=null): SummaryMailService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default='') => ($this->values["$user|$app|$key"] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, $value): void {
				$this->values["$user|$app|$key"] = (string)$value;
			}
		);
		$config->method('getUsersForUserValue')->willReturnCallback($usersFor ?? static fn (): array => []);

		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(fn (string $uid): ?IUser => $this->user(uid: $uid));

		$content = $this->createMock(originalClassName: SummaryMailContent::class);
		$content->method('collect')->willReturnCallback(fn (): array => $this->lines);

		return new SummaryMailService(
			users: $users,
			preference: new SummaryMailPreference(config: $config),
			content: $content,
			sender: $this->sender()
		);
	}//end service()

	/**
	 * A user with an email address, except `nomail`; `ghost` does not exist.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser|null
	 */
	private function user(string $uid): ?IUser {
		if ($uid === 'ghost') {
			return null;
		}

		$email = $uid.'@zuiddrecht.example';
		if ($uid === 'nomail') {
			$email = null;
		}

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn(ucfirst($uid));
		$user->method('getEMailAddress')->willReturn($email);

		return $user;
	}//end user()

	/**
	 * The real sender over a recording mailer.
	 *
	 * @return SummaryMailSender
	 */
	private function sender(): SummaryMailSender {
		$items = [];
		$template = $this->createMock(originalClassName: IEMailTemplate::class);
		$template->method('addBodyListItem')->willReturnCallback(
			function (string $text) use (&$items): void {
				$items[] = $text;
			}
		);

		$mailer = $this->createMock(originalClassName: IMailer::class);
		$mailer->method('createEMailTemplate')->willReturnCallback(
			function () use ($template, &$items): IEMailTemplate {
				$items = [];
				return $template;
			}
		);
		$mailer->method('createMessage')->willReturn($this->createMock(originalClassName: IMessage::class));
		$mailer->method('send')->willReturnCallback(
			function () use (&$items): array {
				if ($this->mailerFails === true) {
					throw new RuntimeException(message: 'smtp down');
				}

				$this->mails[] = $items;
				return [];
			}
		);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$factory->method('getUserLanguage')->willReturn('en');

		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturn('/apps/launchpad/');
		$urls->method('getAbsoluteURL')->willReturnArgument(0);

		return new SummaryMailSender(
			mailer: $mailer,
			l10n: $factory,
			urls: $urls,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end sender()
}//end class
