<?php

/**
 * Unit tests for CalendarWidgetService's calendar listing, merged event feed and VEVENT normalisation.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\FeedFetchTools;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Where the calendar widget's events come from, and how an ICS event is shaped.
 *
 * @covers \OCA\LaunchPad\Service\CalendarWidgetService
 *
 * @uses \OCA\LaunchPad\Service\FeedFetchTools
 */
class CalendarWidgetServiceSourcesTest extends TestCase {

	/**
	 * The logger double.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface $logger;

	/**
	 * The HTTP client service double.
	 *
	 * @var IClientService&MockObject
	 */
	private IClientService $clientService;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->clientService = $this->createMock(IClientService::class);
	}//end setUp()

	/**
	 * The service under test, over the given calendar manager.
	 *
	 * @param IManager|null $calendars The calendar manager.
	 *
	 * @return CalendarWidgetService
	 */
	private function service(?IManager $calendars = null): CalendarWidgetService {
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$validator = $this->createMock(UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturn(true);
		$validator->method('checkAllowList')->willReturn(true);

		return new CalendarWidgetService(
			appConfig: $this->createMock(IAppConfig::class),
			fetch: new FeedFetchTools(clientService: $this->clientService, cacheFactory: $cacheFactory, urlValidator: $validator),
			logger: $this->logger,
			calendarMgr: $calendars,
		);
	}//end service()

	/**
	 * A calendar double.
	 *
	 * @param string      $key   The key.
	 * @param string|null $name  The display name.
	 * @param string|null $color The display colour.
	 *
	 * @return ICalendar
	 */
	private function calendar(string $key, ?string $name, ?string $color): ICalendar {
		$calendar = $this->createMock(ICalendar::class);
		$calendar->method('getKey')->willReturn($key);
		$calendar->method('getDisplayName')->willReturn($name);
		$calendar->method('getDisplayColor')->willReturn($color);

		return $calendar;
	}//end calendar()

	/**
	 * Without a calendar manager there is nothing to list.
	 *
	 * @return void
	 */
	public function testNoCalendarManagerListsNothing(): void {
		self::assertSame([], $this->service()->listCalendars('alice'));
	}//end testNoCalendarManagerListsNothing()

	/**
	 * The user's calendars are listed by key, name and colour, blanks as empty strings.
	 *
	 * @return void
	 */
	public function testTheUsersCalendarsAreListed(): void {
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())->method('getCalendarsForPrincipal')->with('principals/users/alice')->willReturn(
			[$this->calendar('personal', 'Personal', '#ff0000'), $this->calendar('team', null, null)]
		);

		self::assertSame(
			[
				['key' => 'personal', 'name' => 'Personal', 'color' => '#ff0000'],
				['key' => 'team', 'name' => '', 'color' => ''],
			],
			$this->service($manager)->listCalendars('alice')
		);
	}//end testTheUsersCalendarsAreListed()

	/**
	 * A failing calendar lookup is logged and lists nothing.
	 *
	 * @return void
	 */
	public function testAFailingCalendarLookupListsNothing(): void {
		$manager = $this->createMock(IManager::class);
		$manager->method('getCalendarsForPrincipal')->willThrowException(new RuntimeException('dav down'));
		$this->logger->expects(self::once())->method('warning')->with('Calendar widget: listing calendars failed: dav down');

		self::assertSame([], $this->service($manager)->listCalendars('alice'));
	}//end testAFailingCalendarLookupListsNothing()

	/**
	 * Internal and external events are merged and sorted by start, with each failure reported.
	 *
	 * @return void
	 */
	public function testInternalAndExternalEventsAreMergedAndSorted(): void {
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())->method('search')->willReturn(
			[
				['calendar-key' => 'team', 'SUMMARY' => 'Late', 'DTSTART' => '2026-05-03T15:00:00Z', 'UID' => 'b'],
				['calendar-key' => 'private', 'SUMMARY' => 'Hidden', 'DTSTART' => '2026-05-03T08:00:00Z', 'UID' => 'x'],
				['calendar-key' => 'team', 'SUMMARY' => 'Early', 'DTSTART' => '2026-05-03T09:00:00Z', 'UID' => 'a'],
			]
		);
		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(new RuntimeException('timeout'));
		$this->clientService->method('newClient')->willReturn($client);
		$this->logger->expects(self::once())->method('warning')
			->with('Calendar widget fetch failed for https://ics.example/feed.ics: timeout');

		$result = $this->service($manager)->getEvents(
			['internalCalendars' => ['team'], 'externalIcsUrls' => ['https://ics.example/feed.ics']],
			'2026-05-01T00:00:00Z',
			'2026-05-31T23:59:59Z'
		);

		self::assertSame(['Early', 'Late'], array_column($result['events'], 'title'));
		self::assertSame(['external: fetch failed https://ics.example/feed.ics'], $result['failures']);
	}//end testInternalAndExternalEventsAreMergedAndSorted()

	/**
	 * A configuration without sources answers no events and no failures.
	 *
	 * @return void
	 */
	public function testNoSourcesAnswerNothing(): void {
		self::assertSame(['events' => [], 'failures' => []], $this->service()->getEvents([], '2026-05-01', '2026-05-02'));
	}//end testNoSourcesAnswerNothing()

	/**
	 * Without sabre/vobject an ICS body cannot be parsed and answers no events.
	 *
	 * @return void
	 */
	public function testWithoutAnIcsParserNothingIsParsed(): void {
		if (class_exists(\Sabre\VObject\Reader::class) === true) {
			self::markTestSkipped('sabre/vobject is installed here.');
		}

		$this->logger->expects(self::once())->method('warning')->with('sabre/vobject is not available; cannot parse ICS');

		self::assertSame([], $this->service()->parseAndExpandIcs("BEGIN:VCALENDAR\nEND:VCALENDAR", '2026-05-01', '2026-05-02'));
	}//end testWithoutAnIcsParserNothingIsParsed()

	/**
	 * A VEVENT with typed dates is shaped as an external event; a start without a time makes it all-day.
	 *
	 * @return void
	 */
	public function testAVeventIsNormalised(): void {
		$vevent = (object) [
			'UID' => 'ev-1',
			'SUMMARY' => 'Raadsvergadering',
			'LOCATION' => 'Stadhuis',
			'DESCRIPTION' => '',
			'DTSTART' => self::dateProperty(new \DateTimeImmutable('2026-05-04T00:00:00+02:00'), true),
			'DTEND' => self::dateProperty(new \DateTimeImmutable('2026-05-05T00:00:00+02:00'), false),
		];

		self::assertSame(
			[
				'uid' => 'ev-1',
				'title' => 'Raadsvergadering',
				'start' => '2026-05-04T00:00:00+02:00',
				'end' => '2026-05-05T00:00:00+02:00',
				'allDay' => true,
				'location' => 'Stadhuis',
				'description' => null,
				'calendarId' => '',
				'calendarName' => '',
				'color' => null,
				'source' => 'external',
			],
			$this->service()->normalizeVevent($vevent)
		);
	}//end testAVeventIsNormalised()

	/**
	 * A bare VEVENT is untitled with empty dates; a date property that fails is read as text.
	 *
	 * @return void
	 */
	public function testABareOrUnreadableVeventFallsBack(): void {
		$bare = $this->service()->normalizeVevent((object) []);
		self::assertSame(['', 'Untitled', '', '', false, null, null], [$bare['uid'], $bare['title'], $bare['start'], $bare['end'], $bare['allDay'], $bare['location'], $bare['description']]);

		$broken = new class {
			/**
			 * Fail to give a date.
			 *
			 * @return \DateTimeInterface
			 */
			public function getDateTime(): \DateTimeInterface {
				throw new RuntimeException('bad TZID');
			}//end getDateTime()

			/**
			 * Fail to say whether there is a time.
			 *
			 * @return bool
			 */
			public function hasTime(): bool {
				throw new RuntimeException('bad TZID');
			}//end hasTime()

			/**
			 * The raw value.
			 *
			 * @return string
			 */
			public function __toString(): string {
				return '20260504T193000';
			}//end __toString()
		};
		$plain = new class {
			/**
			 * The raw value.
			 *
			 * @return string
			 */
			public function __toString(): string {
				return 'plain';
			}//end __toString()
		};
		$event = $this->service()->normalizeVevent((object) ['DTSTART' => $broken, 'DTEND' => $plain]);

		self::assertSame('20260504T193000', $event['start']);
		self::assertSame('plain', $event['end']);
		self::assertFalse($event['allDay']);
	}//end testABareOrUnreadableVeventFallsBack()

	/**
	 * A date property double.
	 *
	 * @param \DateTimeImmutable $value    The date.
	 * @param bool               $dateOnly Whether the property has no time.
	 *
	 * @return object
	 */
	private static function dateProperty(\DateTimeImmutable $value, bool $dateOnly): object {
		return new class($value, $dateOnly) {
			/**
			 * Constructor.
			 *
			 * @param \DateTimeImmutable $value    The date.
			 * @param bool               $dateOnly Whether the property has no time.
			 */
			public function __construct(private \DateTimeImmutable $value, private bool $dateOnly) {
			}//end __construct()

			/**
			 * The date.
			 *
			 * @return \DateTimeInterface
			 */
			public function getDateTime(): \DateTimeInterface {
				return $this->value;
			}//end getDateTime()

			/**
			 * Whether the property carries a time.
			 *
			 * @return bool
			 */
			public function hasTime(): bool {
				return $this->dateOnly === false;
			}//end hasTime()
		};
	}//end dateProperty()
}//end class
