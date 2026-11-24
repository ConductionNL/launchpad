<?php

/**
 * AnnouncementInput Test
 *
 * How an author's fields are checked and copied onto an announcement: kind,
 * title, body, category, notice level, flags, target groups and the period.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Service\AnnouncementInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\AnnouncementInput
 * @uses   \OCA\LaunchPad\Db\Announcement
 */
class AnnouncementInputTest extends TestCase {

	/**
	 * The service under test.
	 *
	 * @var AnnouncementInput
	 */
	private AnnouncementInput $input;

	/**
	 * Build the service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->input = new AnnouncementInput();
	}//end setUp()

	/**
	 * A complete notice is copied field by field, trimmed where the author typed spaces.
	 *
	 * @return void
	 */
	public function testACompleteNoticeIsCopied(): void {
		$announcement = new Announcement();

		$this->input->apply(
			announcement: $announcement,
			input: [
				'kind'          => Announcement::KIND_NOTICE,
				'title'         => '  Onderhoud zaaksysteem ',
				'body'          => 'Vanavond van 18:00 tot 20:00.',
				'category'      => ' ICT ',
				'level'         => 'warning',
				'dismissible'   => true,
				'allowComments' => false,
				'targetGroups'  => [' medewerkers ', '', 'ict', 'ict'],
				'publishAt'     => '2026-11-23T08:00:00+01:00',
				'expiresAt'     => '2026-11-24T20:00:00+01:00',
			]
		);

		self::assertSame(expected: Announcement::KIND_NOTICE, actual: $announcement->getKind());
		self::assertSame(expected: 'Onderhoud zaaksysteem', actual: $announcement->getTitle());
		self::assertSame(expected: 'Vanavond van 18:00 tot 20:00.', actual: $announcement->getBody());
		self::assertSame(expected: 'ICT', actual: $announcement->getCategory());
		self::assertSame(expected: 'warning', actual: $announcement->getLevel());
		self::assertSame(expected: 1, actual: $announcement->getDismissible());
		self::assertSame(expected: 0, actual: $announcement->getAllowComments());
		self::assertSame(expected: ['medewerkers', 'ict'], actual: $announcement->getTargetGroupList());
		self::assertSame(expected: '["medewerkers","ict"]', actual: $announcement->getTargetGroups(), message: 'blank groups are not stored');
		self::assertSame(expected: '2026-11-23T08:00:00+01:00', actual: $announcement->getPublishAt()->format(DATE_ATOM));
		self::assertSame(expected: '2026-11-24T20:00:00+01:00', actual: $announcement->getExpiresAt()->format(DATE_ATOM));
	}//end testACompleteNoticeIsCopied()

	/**
	 * A notice without a level gets "info"; news never keeps a level.
	 *
	 * @return void
	 */
	public function testTheLevelFollowsTheKind(): void {
		$notice = new Announcement();
		$this->input->apply(announcement: $notice, input: ['kind' => Announcement::KIND_NOTICE, 'title' => 'Printer']);
		self::assertSame(expected: 'info', actual: $notice->getLevel());

		$news = new Announcement();
		$this->input->apply(announcement: $news, input: ['kind' => Announcement::KIND_NEWS, 'title' => 'Nieuws', 'level' => 'warning']);
		self::assertNull(actual: $news->getLevel());
	}//end testTheLevelFollowsTheKind()

	/**
	 * Fields left out keep what the announcement already has, so an update can send one field.
	 *
	 * @return void
	 */
	public function testAnUpdateKeepsTheFieldsItLeavesOut(): void {
		$announcement = new Announcement();
		$this->input->apply(
			announcement: $announcement,
			input: ['kind' => Announcement::KIND_NEWS, 'title' => 'Eerste', 'body' => 'Tekst', 'category' => 'HR', 'targetGroups' => ['hr']]
		);

		$this->input->apply(announcement: $announcement, input: ['title' => 'Tweede']);

		self::assertSame(expected: 'Tweede', actual: $announcement->getTitle());
		self::assertSame(expected: 'Tekst', actual: $announcement->getBody());
		self::assertSame(expected: 'HR', actual: $announcement->getCategory());
		self::assertSame(expected: ['hr'], actual: $announcement->getTargetGroupList());
	}//end testAnUpdateKeepsTheFieldsItLeavesOut()

	/**
	 * Null or blank clears the optional fields.
	 *
	 * @return void
	 */
	public function testNullAndBlankClearTheOptionalFields(): void {
		$announcement = new Announcement();
		$this->input->apply(
			announcement: $announcement,
			input: ['kind' => Announcement::KIND_NEWS, 'title' => 'X', 'body' => 'Tekst', 'category' => 'HR', 'publishAt' => '2026-11-23T08:00:00Z']
		);

		$this->input->apply(announcement: $announcement, input: ['body' => null, 'category' => '   ', 'publishAt' => '', 'expiresAt' => null]);

		self::assertNull(actual: $announcement->getBody());
		self::assertNull(actual: $announcement->getCategory());
		self::assertNull(actual: $announcement->getPublishAt());
		self::assertNull(actual: $announcement->getExpiresAt());
	}//end testNullAndBlankClearTheOptionalFields()

	/**
	 * Only a literal true sets a flag.
	 *
	 * @return void
	 */
	public function testOnlyTrueSetsAFlag(): void {
		$announcement = new Announcement();
		$this->input->apply(announcement: $announcement, input: ['title' => 'X', 'dismissible' => 'yes', 'allowComments' => 1]);

		self::assertSame(expected: 0, actual: $announcement->getDismissible());
		self::assertSame(expected: 0, actual: $announcement->getAllowComments());
	}//end testOnlyTrueSetsAFlag()

	/**
	 * The inputs that are refused, each with the message the author sees.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function refusedInputs(): array {
		$long = str_repeat('a', 256);
		return [
			'unknown kind'         => [['kind' => 'memo', 'title' => 'X'], 'kind must be news or notice'],
			'blank title'          => [['title' => '   '], 'A title of 1 to 255 characters is required'],
			'title too long'       => [['title' => $long], 'A title of 1 to 255 characters is required'],
			'no title at all'      => [['body' => 'Tekst'], 'A title of 1 to 255 characters is required'],
			'category too long'    => [['title' => 'X', 'category' => str_repeat('c', 65)], 'A category of 1 to 64 characters is required'],
			'unknown level'        => [['title' => 'X', 'level' => 'danger'], 'level must be info or warning'],
			'groups not a list'    => [['title' => 'X', 'targetGroups' => ['a' => 'hr']], 'targetGroups must be a list of group ids'],
			'groups not strings'   => [['title' => 'X', 'targetGroups' => ['hr', 5]], 'targetGroups must be a list of group ids'],
			'groups not an array'  => [['title' => 'X', 'targetGroups' => 'hr'], 'targetGroups must be a list of group ids'],
			'date not a string'    => [['title' => 'X', 'publishAt' => 20261123], 'publishAt must be an ISO 8601 date'],
			'date not a date'      => [['title' => 'X', 'expiresAt' => 'next tuesday-ish'], 'expiresAt must be an ISO 8601 date'],
		];
	}//end refusedInputs()

	/**
	 * Each refused input throws before the announcement can be stored.
	 *
	 * @param array<string, mixed> $input   The author's fields.
	 * @param string               $message The expected message.
	 *
	 * @return void
	 */
	#[DataProvider('refusedInputs')]
	public function testRefusedInput(array $input, string $message): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: $message);

		$this->input->apply(announcement: new Announcement(), input: $input);
	}//end testRefusedInput()

	/**
	 * A category name is trimmed, and 64 characters is the longest allowed.
	 *
	 * @return void
	 */
	public function testCategoryIsTrimmedUpToItsLimit(): void {
		self::assertSame(expected: 'Gemeente', actual: $this->input->category(value: ' Gemeente '));
		self::assertSame(expected: str_repeat('c', 64), actual: $this->input->category(value: str_repeat('c', 64)));

		$this->expectException(exception: InvalidArgumentException::class);
		$this->input->category(value: '');
	}//end testCategoryIsTrimmedUpToItsLimit()

	/**
	 * The end-time rule: an end time is required, and it must lie after the publish time when one is set.
	 *
	 * @param string|null $publishAt The publish time.
	 * @param string|null $expiresAt The end time.
	 * @param string|null $message   The refusal, or null when accepted.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[DataProvider('endTimes')]
	public function testTheEndTimeIsRequiredAndAfterThePublishTime(?string $publishAt, ?string $expiresAt, ?string $message): void {
		$announcement = new Announcement();
		$announcement->setPublishAt(self::date(value: $publishAt));
		$announcement->setExpiresAt(self::date(value: $expiresAt));

		if ($message !== null) {
			$this->expectException(exception: InvalidArgumentException::class);
			$this->expectExceptionMessage(message: $message);
		}

		$this->input->assertEndTime(announcement: $announcement);
		$this->addToAssertionCount(count: 1);
	}//end testTheEndTimeIsRequiredAndAfterThePublishTime()

	/**
	 * A date, or null.
	 *
	 * @param string|null $value An ISO 8601 date.
	 *
	 * @return DateTime|null
	 */
	private static function date(?string $value): ?DateTime {
		if ($value === null) {
			return null;
		}

		return new DateTime($value);
	}//end date()

	/**
	 * Publish and end times with the expected refusal.
	 *
	 * @return array<string, array{0: string|null, 1: string|null, 2: string|null}>
	 */
	public static function endTimes(): array {
		$missing = 'expiresAt is required: an announcement needs a "Show until" end time';
		$order = 'The end time must be after the publish time';

		return [
			'no end time' => [null, null, $missing],
			'no end time, scheduled' => ['2026-11-16T08:00:00+00:00', null, $missing],
			'end only' => [null, '2026-10-30T17:00:00+00:00', null],
			'end after start' => ['2026-10-30T08:00:00+00:00', '2026-10-30T17:00:00+00:00', null],
			'end equals start' => ['2026-10-30T17:00:00+00:00', '2026-10-30T17:00:00+00:00', $order],
			'end before start' => ['2026-10-31T08:00:00+00:00', '2026-10-30T17:00:00+00:00', $order],
		];
	}//end endTimes()
}//end class
