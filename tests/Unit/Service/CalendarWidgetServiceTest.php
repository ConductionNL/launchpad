<?php

/**
 * CalendarWidgetService Test
 *
 * Covers REQ-CAL-005 (cache key + TTL behaviour), REQ-CAL-006
 * (allow-list and HTTPS-only / SSRF guard), REQ-CAL-009 (failure
 * tolerance: bad URLs and parse errors do not abort the call), and
 * REQ-CAL-003 (event normalisation + sort order).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FeedFetchTools;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CalendarWidgetServiceTest extends TestCase {
	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var ICacheFactory&MockObject */
	private $cacheFactory;

	/** @var ICache&MockObject */
	private $cache;

	/** @var IClientService&MockObject */
	private $clientService;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/** @var UrlSafetyValidator&MockObject */
	private $urlValidator;

	private CalendarWidgetService $service;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(ICache::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		// Default: all URLs safe — individual tests override as needed.
		$this->urlValidator = $this->createMock(UrlSafetyValidator::class);
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->urlValidator->method('checkAllowList')->willReturn(true);

		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);

		$this->service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(
				cacheFactory: $this->cacheFactory,
				clientService: $this->clientService,
				urlValidator: $this->urlValidator
			),
			logger: $this->logger,
			calendarMgr: null
		);
	}

	/**
	 * Create a CalendarWidgetService with a real UrlSafetyValidator
	 * (used by tests that exercise the real SSRF/allow-list logic).
	 */
	private function makeServiceWithRealValidator(): CalendarWidgetService {
		$realValidator = new UrlSafetyValidator(appConfig: $this->appConfig);
		return new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(
				cacheFactory: $this->cacheFactory,
				clientService: $this->clientService,
				urlValidator: $realValidator
			),
			logger: $this->logger,
			calendarMgr: null
		);
	}

	public function testRejectsHttpUrl(): void {
		// Use real validator — validates scheme.
		$svc = $this->makeServiceWithRealValidator();
		$this->assertFalse($svc->validateUrl('http://example.test/cal.ics'));
	}

	public function testRejectsMalformedUrl(): void {
		$svc = $this->makeServiceWithRealValidator();
		$this->assertFalse($svc->validateUrl('not a url'));
	}

	public function testAllowListEmptyAllowsAll(): void {
		$this->appConfig->method('getValueString')
			->with('launchpad', CalendarWidgetService::CONFIG_KEY_ALLOWED_HOSTS, '')
			->willReturn('');
		$svc = $this->makeServiceWithRealValidator();
		$this->assertTrue($svc->checkAllowList('https://anything.test/cal.ics'));
	}

	public function testAllowListMatchesHostCaseInsensitive(): void {
		$this->appConfig->method('getValueString')
			->with('launchpad', CalendarWidgetService::CONFIG_KEY_ALLOWED_HOSTS, '')
			->willReturn(json_encode(['Calendar.Example.COM']));
		$svc = $this->makeServiceWithRealValidator();
		$this->assertTrue($svc->checkAllowList('https://calendar.example.com/cal.ics'));
	}

	public function testAllowListRejectsNonMatchingHost(): void {
		$this->appConfig->method('getValueString')
			->with('launchpad', CalendarWidgetService::CONFIG_KEY_ALLOWED_HOSTS, '')
			->willReturn(json_encode(['calendar.example.com']));
		$svc = $this->makeServiceWithRealValidator();
		$this->assertFalse($svc->checkAllowList('https://untrusted.test/cal.ics'));
	}

	public function testAllowListNoSubdomainExpansion(): void {
		$this->appConfig->method('getValueString')
			->with('launchpad', CalendarWidgetService::CONFIG_KEY_ALLOWED_HOSTS, '')
			->willReturn(json_encode(['example.com']));
		$svc = $this->makeServiceWithRealValidator();
		$this->assertFalse($svc->checkAllowList('https://sub.example.com/cal.ics'));
	}

	public function testFetchIcsBodyServesFromCache(): void {
		$url = 'https://example.test/cached.ics';
		$cacheKey = 'ics_' . md5($url);

		$this->cache->expects($this->once())
			->method('get')
			->with($cacheKey)
			->willReturn('CACHED-ICS-BODY');

		$this->clientService->expects($this->never())->method('newClient');

		$this->assertSame('CACHED-ICS-BODY', $this->service->fetchIcsBody($url));
	}

	public function testFetchIcsBodyFetchesAndCachesOnMiss(): void {
		$url = 'https://example.test/fresh.ics';
		$cacheKey = 'ics_' . md5($url);

		$this->cache->expects($this->once())
			->method('get')
			->with($cacheKey)
			->willReturn(null);

		/** @var IClient&MockObject $client */
		$client = $this->createMock(IClient::class);

		/** @var IResponse&MockObject $response */
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn('FRESH-ICS');

		$client->expects($this->once())
			->method('get')
			->with(
				$url,
				$this->callback(static function ($options): bool {
					return ($options['timeout'] ?? null) === CalendarWidgetService::FETCH_TIMEOUT_SECONDS;
				})
			)
			->willReturn($response);

		$this->clientService->method('newClient')->willReturn($client);

		$this->appConfig->method('getValueInt')
			->with('launchpad', CalendarWidgetService::CONFIG_KEY_CACHE_TTL, CalendarWidgetService::DEFAULT_CACHE_TTL_SECONDS)
			->willReturn(7200);

		$this->cache->expects($this->once())
			->method('set')
			->with($cacheKey, 'FRESH-ICS', 7200);

		$this->assertSame('FRESH-ICS', $this->service->fetchIcsBody($url));
	}

	public function testFetchIcsBodyRejectsOversizedResponse(): void {
		$url = 'https://example.test/big.ics';

		$this->cache->method('get')->willReturn(null);

		/** @var IClient&MockObject $client */
		$client = $this->createMock(IClient::class);

		$oversize = str_repeat('x', CalendarWidgetService::MAX_RESPONSE_SIZE_BYTES + 1);

		/** @var IResponse&MockObject $response */
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($oversize);

		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);

		$this->expectException(\RuntimeException::class);
		$this->service->fetchIcsBody($url);
	}

	public function testFetchExternalIcsEventsSkipsRejectedUrls(): void {
		// No allow-list configured. Use real validator so non-HTTPS URLs
		// are correctly rejected by isSafe().
		$this->appConfig->method('getValueString')->willReturn('');

		$result = $this->makeServiceWithRealValidator()->fetchExternalIcsEvents(
			urls: ['ftp://nope.test/x.ics', 'http://no-tls.test/y.ics'],
			from: '2026-05-01T00:00:00Z',
			to: '2026-05-31T23:59:59Z'
		);

		$this->assertSame([], $result['events']);
		$this->assertCount(2, $result['failures']);
	}

	public function testGetEventsSortsByStart(): void {
		// Force the external path to fail fast (no URLs) so we hit
		// only the explicit normalize behaviour.
		$events = [
			$this->service->normalizeInternalEvent([
				'UID' => 'a',
				'SUMMARY' => 'Late',
				'DTSTART' => '2026-05-03T15:00:00Z',
				'DTEND' => '2026-05-03T16:00:00Z',
			]),
			$this->service->normalizeInternalEvent([
				'UID' => 'b',
				'SUMMARY' => 'Early',
				'DTSTART' => '2026-05-03T09:00:00Z',
				'DTEND' => '2026-05-03T10:00:00Z',
			]),
		];

		usort(
			$events,
			static fn (array $eventA, array $eventB): int => strcmp($eventA['start'], $eventB['start'])
		);

		$this->assertSame('Early', $events[0]['title']);
		$this->assertSame('Late', $events[1]['title']);
	}

	public function testGetEventsAggregatesAndReportsFailures(): void {
		// Empty allow-list, no internal manager — only external path.
		// Use real validator so http:// is correctly rejected.
		$this->appConfig->method('getValueString')->willReturn('');
		$this->appConfig->method('getValueInt')->willReturn(1800);

		$this->cache->method('get')->willReturn(null);

		// Bad URL is rejected by validateUrl; no fetch occurs.
		$result = $this->makeServiceWithRealValidator()->getEvents(
			config: [
				'internalCalendars' => [],
				'externalIcsUrls' => ['http://invalid.test/x.ics'],
			],
			from: '2026-05-01T00:00:00Z',
			to: '2026-05-02T00:00:00Z'
		);

		$this->assertSame([], $result['events']);
		$this->assertNotEmpty($result['failures']);
	}

	/**
	 * Internal events: no calendar manager, a bad range, a failing search,
	 * and the principal filter on calendar key / uri / name.
	 *
	 * @return void
	 */
	public function testFetchInternalEventsGuardsAndFiltersByPrincipal(): void {
		$this->assertSame(['events' => [], 'failures' => []], $this->service->fetchInternalEvents([], '2026-01-01', '2026-01-31'));

		$calendars = $this->createMock(\OCP\Calendar\IManager::class);
		$searches  = [];
		$calendars->method('search')->willReturnCallback(
			function (string $pattern, array $searchProperties, array $options) use (&$searches): array {
				$searches[] = [$pattern, $searchProperties, $options['timerange']['start']->format('c'), $options['timerange']['end']->format('c')];
				if ($options['timerange']['start']->format('Y') === '1999') {
					throw new \RuntimeException('caldav down');
				}
				return [
					['calendar-key' => 'work', 'calendar-name' => 'Work', 'objects' => []],
					['calendarUri' => 'home', 'calendarName' => 'Home', 'objects' => []],
					['calendar-key' => 'other', 'calendarName' => 'Shared', 'objects' => []],
				];
			}
		);
		$warnings = [];
		$logger   = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings): void {
				$warnings[] = $message;
			}
		);
		$service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(cacheFactory: $this->cacheFactory, clientService: $this->clientService, urlValidator: $this->urlValidator),
			logger: $logger,
			calendarMgr: $calendars
		);

		$this->assertSame(['events' => [], 'failures' => ['internal: invalid date range']], $service->fetchInternalEvents([], 'not a date', '2026-01-31'));
		$this->assertSame(['events' => [], 'failures' => ['internal: caldav down']], $service->fetchInternalEvents([], '1999-01-01', '1999-01-31'));

		$count = static fn (array $result): array => [count($result['events']), $result['failures']];
		$this->assertSame([3, []], $count($service->fetchInternalEvents([], '2026-01-01', '2026-01-31')));
		$this->assertSame([2, []], $count($service->fetchInternalEvents(['work', 'Home'], '2026-01-01', '2026-01-31')));
		$this->assertSame([1, []], $count($service->fetchInternalEvents(['Shared'], '2026-01-01', '2026-01-31')));
		$this->assertSame([0, []], $count($service->fetchInternalEvents(['nope'], '2026-01-01', '2026-01-31')));
		$this->assertSame(
			$service->normalizeInternalEvent(raw: ['calendarUri' => 'home', 'calendarName' => 'Home', 'objects' => []]),
			$service->fetchInternalEvents(['home'], '2026-01-01', '2026-01-31')['events'][0]
		);

		$this->assertCount(6, $searches);
		$this->assertSame(['', [], '1999-01-01T00:00:00+00:00', '1999-01-31T00:00:00+00:00'], $searches[0]);
		$this->assertSame(2, count($warnings));
		$this->assertStringStartsWith('Calendar widget received malformed from/to: ', $warnings[0]);
		$this->assertSame('Calendar widget internal search failed: caldav down', $warnings[1]);
	}//end testFetchInternalEventsGuardsAndFiltersByPrincipal()

	/**
	 * Every instance NC's search expands a recurring event into is an event,
	 * a DATE start makes it all-day, and the calendar's name and colour come
	 * from the calendar the match names by key.
	 *
	 * @return void
	 */
	public function testInternalInstancesAreEventsWithTheirCalendar(): void {
		$day = static fn (string $date): array => [
			'SUMMARY' => ['Office closed', []],
			'UID' => ['closed-1', []],
			'DTSTART' => [new \DateTimeImmutable($date), ['VALUE' => 'DATE']],
		];
		$calendar = $this->createMock(\OCP\Calendar\ICalendar::class);
		$calendar->method('getKey')->willReturn('work');
		$calendar->method('getDisplayName')->willReturn('Work');
		$calendar->method('getDisplayColor')->willReturn('#e9322d');
		$calendars = $this->createMock(\OCP\Calendar\IManager::class);
		$calendars->method('getCalendars')->willReturn([$calendar]);
		$calendars->method('search')->willReturn([
			['calendar-key' => 'work', 'objects' => [$day('2026-05-04'), $day('2026-05-05'), $day('2026-05-06')]],
		]);
		$service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(cacheFactory: $this->cacheFactory, clientService: $this->clientService, urlValidator: $this->urlValidator),
			logger: $this->logger,
			calendarMgr: $calendars
		);

		$events = $service->fetchInternalEvents(['work'], '2026-05-01', '2026-05-31')['events'];

		$this->assertSame(
			['2026-05-04', '2026-05-05', '2026-05-06'],
			array_map(static fn (array $event): string => substr($event['start'], 0, 10), $events)
		);
		foreach ($events as $event) {
			$this->assertTrue($event['allDay']);
			$this->assertSame('Work', $event['calendarName']);
			$this->assertSame('#e9322d', $event['color']);
			$this->assertSame('work', $event['calendarId']);
		}
	}//end testInternalInstancesAreEventsWithTheirCalendar()

	/**
	 * NC's search names a calendar only by key; a widget that lists the
	 * calendar by its URI (`personal`, as the seeds and starter widgets do)
	 * or by its name still gets that calendar's events, and only those.
	 *
	 * @return void
	 */
	public function testInternalCalendarsMatchByUriAndName(): void {
		$personal = $this->createMock(originalClassName: \OCP\Calendar\ICalendar::class);
		$personal->method('getKey')->willReturn('7311');
		$personal->method('getUri')->willReturn('personal');
		$personal->method('getDisplayName')->willReturn('Personal');
		$team = $this->createMock(originalClassName: \OCP\Calendar\ICalendar::class);
		$team->method('getKey')->willReturn('7325');
		$team->method('getUri')->willReturn('team');
		$team->method('getDisplayName')->willReturn('Team');
		$event = static fn (string $title): array => [
			'SUMMARY' => [$title, []],
			'UID' => [$title, []],
			'DTSTART' => [new \DateTimeImmutable(datetime: '2026-11-24T09:30:00+01:00'), []],
		];
		$calendars = $this->createMock(originalClassName: \OCP\Calendar\IManager::class);
		$calendars->method('getCalendars')->willReturn([$personal, $team]);
		$calendars->method('search')->willReturn([
			['calendar-key' => '7311', 'objects' => [$event('Week planning')]],
			['calendar-key' => '7325', 'objects' => [$event('Stand-up')]],
		]);
		$service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(cacheFactory: $this->cacheFactory, clientService: $this->clientService, urlValidator: $this->urlValidator),
			logger: $this->logger,
			calendarMgr: $calendars
		);
		$titles = static fn (array $principals): array => array_column(
			array: $service->fetchInternalEvents(principals: $principals, from: '2026-11-24', to: '2026-11-25')['events'],
			column_key: 'title'
		);

		$this->assertSame(expected: ['Week planning'], actual: $titles(['personal']));
		$this->assertSame(expected: ['Stand-up'], actual: $titles(['Team']));
		$this->assertSame(expected: ['Week planning', 'Stand-up'], actual: $titles(['7311', 'team']));
		$this->assertSame(expected: [], actual: $titles(['contact_birthdays']));
	}//end testInternalCalendarsMatchByUriAndName()

	/**
	 * A cache miss re-validates the URL before any request; a refused URL is never fetched.
	 *
	 * @return void
	 */
	public function testFetchIcsBodyRevalidatesBeforeTheRequest(): void {
		$validator = $this->createMock(UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturn(false);
		$validator->method('checkAllowList')->willReturn(true);
		$this->cache->method('get')->willReturn(null);
		$this->clientService->expects($this->never())->method('newClient');

		$service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(cacheFactory: $this->cacheFactory, clientService: $this->clientService, urlValidator: $validator),
			logger: $this->logger,
			calendarMgr: null
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ICS URL failed re-validation (SSRF guard)');
		$service->fetchIcsBody('https://cal.example/a.ics');
	}//end testFetchIcsBodyRevalidatesBeforeTheRequest()

	/**
	 * Internal search results normalise the same from every shape NC returns.
	 *
	 * @return void
	 */
	public function testNormalizeInternalEventReadsEveryShape(): void {
		$start = new \DateTimeImmutable('2026-03-01T09:00:00+00:00');
		$cases = [
			'objects pairs' => [
				'calendar-key' => 'work', 'calendar-name' => 'Work', 'calendar-color' => '#f00', 'allDay' => 1,
				'objects' => [[
					'SUMMARY' => ['Stand-up', []], 'DTSTART' => [$start, []], 'DTEND' => ['2026-03-01T09:15:00+00:00', []],
					'LOCATION' => [['Room 1', []], ['Room 2', []]], 'DESCRIPTION' => ['', []], 'UID' => ['u-1', []],
				]],
			],
			'raw wins over object' => [
				'SUMMARY' => 'Raw title', 'UID' => 'raw-uid', 'objects' => [['SUMMARY' => ['Object title', []], 'UID' => ['obj-uid', []]]],
			],
			'flat keys' => [
				'calendarUri' => 'home', 'calendarName' => 'Home', 'title' => 'Flat', 'start' => '2026-03-02', 'end' => '2026-03-03',
				'location' => 'Hall', 'description' => 'Notes', 'uid' => 'flat-uid',
			],
			'nothing' => [],
			'empty title and blank location' => ['objects' => [['SUMMARY' => ['', []], 'LOCATION' => ['', []], 'DTSTART' => [42, []]]]],
		];

		$actual = [];
		foreach ($cases as $name => $raw) {
			$actual[$name] = $this->service->normalizeInternalEvent($raw);
		}

		$fixture = __DIR__.'/fixtures/calendar-internal-events.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents($fixture, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents($fixture), true), $actual);
	}//end testNormalizeInternalEventReadsEveryShape()

	/**
	 * A safe URL outside the allow-list is reported and never fetched.
	 *
	 * @return void
	 */
	public function testFetchExternalIcsEventsReportsUrlsOutsideTheAllowList(): void {
		$validator = $this->createMock(UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturn(true);
		$validator->method('checkAllowList')->willReturn(false);
		$this->clientService->expects($this->never())->method('newClient');
		$infos  = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			function (string $message) use (&$infos): void {
				$infos[] = $message;
			}
		);

		$service = new CalendarWidgetService(
			appConfig: $this->appConfig,
			fetch: new FeedFetchTools(cacheFactory: $this->cacheFactory, clientService: $this->clientService, urlValidator: $validator),
			logger: $logger,
			calendarMgr: null
		);

		$this->assertSame(
			['events' => [], 'failures' => ['external: not in allow-list https://cal.example/a.ics']],
			$service->fetchExternalIcsEvents(urls: ['https://cal.example/a.ics'], from: '2026-05-01T00:00:00Z', to: '2026-05-31T23:59:59Z')
		);
		$this->assertSame(['Calendar widget URL not in allow-list: https://cal.example/a.ics'], $infos);
	}//end testFetchExternalIcsEventsReportsUrlsOutsideTheAllowList()
}
