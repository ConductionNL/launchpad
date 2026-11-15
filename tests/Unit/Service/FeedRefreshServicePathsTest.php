<?php

/**
 * FeedRefreshServicePathsTest
 *
 * Characterisation tests for the {@see FeedRefreshService} paths the main
 * test leaves open: the SSRF and scheme guards, the size cap and parse
 * failure, the conditional-GET request (headers, user agent, proxy), Atom
 * and RSS normalisation, title fallback, the batch cursor and the
 * defence-in-depth catch in the batch loop.
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

use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\FeedCacheMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCA\LaunchPad\Service\FeedRefreshSources;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Characterisation tests for FeedRefreshService.
 */
class FeedRefreshServicePathsTest extends TestCase {

	/** @var IClient&MockObject */
	private $client;

	/** @var IClientService&MockObject */
	private $clientService;

	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var IConfig&MockObject */
	private $config;

	/** @var FeedCacheMapper&MockObject */
	private $cacheMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/** @var UrlSafetyValidator&MockObject */
	private $urlValidator;

	/** @var array<string, string> */
	private array $appValues = [];

	/** @var array<string, string> */
	private array $systemValues = [];

	/** @var array<int, FeedCache> */
	private array $updates = [];

	private FeedRefreshService $service;

	/**
	 * Wire up mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default): string => ($this->appValues[$key] ?? $default)
		);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')->willReturnCallback(
			fn (string $key, $default) => ($this->systemValues[$key] ?? $default)
		);
		$this->cacheMapper = $this->createMock(FeedCacheMapper::class);
		$this->cacheMapper->method('upsertUrl')->willReturnCallback(static function (string $url): FeedCache {
			$row = new FeedCache();
			$row->setFeedUrl($url);
			$row->encodeItems(items: [['guid' => 'kept']]);
			return $row;
		});
		$this->cacheMapper->method('update')->willReturnCallback(function (FeedCache $row): FeedCache {
			$this->updates[] = clone $row;
			return $row;
		});
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->urlValidator = $this->createMock(UrlSafetyValidator::class);

		$this->service = new FeedRefreshService(
			sources: new FeedRefreshSources(
				clientService: $this->clientService,
				urlValidator: $this->urlValidator,
				cacheMapper: $this->cacheMapper,
				config: $this->config
			),
			appConfig: $this->appConfig,
			placementMapper: $this->placementMapper,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * Build a response mock.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $body    Body.
	 * @param array  $headers Headers map.
	 *
	 * @return IResponse&MockObject
	 */
	private function response(int $status, string $body, array $headers=[]): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$response->method('getHeaders')->willReturn($headers);
		return $response;
	}//end response()

	/**
	 * Give the news placements these feed URL configs.
	 *
	 * @param array $configs Style config arrays.
	 *
	 * @return void
	 */
	private function placements(array $configs): void {
		$placements = [];
		foreach ($configs as $config) {
			$placement = new WidgetPlacement();
			$placement->setStyleConfigArray($config);
			$placements[] = $placement;
		}

		$this->placementMapper->method('findByWidgetId')->with('launchpad_news')->willReturn($placements);
	}//end placements()

	/**
	 * SSRF rejection stamps lastFetchedAt, records the reason and logs.
	 *
	 * @return void
	 */
	public function testSsrfGuardRejectsBeforeFetch(): void {
		$this->urlValidator->method('isSafe')->willReturn(false);
		$this->client->expects($this->never())->method('get');
		$this->logger->expects($this->once())->method('warning')->with(
			'Feed URL rejected by SSRF guard — private IP or non-HTTPS',
			['app' => 'launchpad', 'feedUrl' => 'https://10.0.0.1/rss']
		);

		$result = $this->service->refreshFeed(feedUrl: 'https://10.0.0.1/rss');

		$this->assertSame('failed', $result['status']);
		$this->assertSame(1, $result['itemCount']);
		$this->assertCount(1, $this->updates);
		$this->assertSame('SSRF guard: private/reserved IP or non-HTTPS URL rejected', $this->updates[0]->getLastFailureReason());
		$this->assertNotNull($this->updates[0]->getLastFetchedAt());
	}//end testSsrfGuardRejectsBeforeFetch()

	/**
	 * A non-HTTP scheme that passes the validator still fails.
	 *
	 * @return void
	 */
	public function testSchemeGuardRejectsNonHttp(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->expects($this->never())->method('get');

		$result = $this->service->refreshFeed(feedUrl: 'FTP://files.example.com/rss');

		$this->assertSame('failed', $result['status']);
		$this->assertSame('invalid scheme: ftp', $this->updates[0]->getLastFailureReason());
		$this->assertNotNull($this->updates[0]->getLastFetchedAt());
	}//end testSchemeGuardRejectsNonHttp()

	/**
	 * Allow-list check runs before the SSRF guard.
	 *
	 * @return void
	 */
	public function testAllowListMissSkipsWithoutConsultingSsrfGuard(): void {
		$this->appValues['news_widget_allowed_feed_hosts'] = ' Other.example.com , x.example.com';
		$this->urlValidator->expects($this->never())->method('isSafe');

		$result = $this->service->refreshFeed(feedUrl: 'https://news.example.com/rss');

		$this->assertSame('skipped', $result['status']);
		$this->assertSame(1, $result['itemCount']);
		$this->assertSame('host not in allow-list', $this->updates[0]->getLastFailureReason());
	}//end testAllowListMissSkipsWithoutConsultingSsrfGuard()

	/**
	 * Allow-list entries are trimmed and case-folded; a URL without host fails it.
	 *
	 * @return void
	 */
	public function testAllowListMatchIsTrimmedAndCaseInsensitive(): void {
		$this->appValues['news_widget_allowed_feed_hosts'] = ' News.Example.com ,x';
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturn($this->response(status: 304, body: ''));

		$this->assertSame('not-modified', $this->service->refreshFeed(feedUrl: 'https://NEWS.example.com/rss')['status']);
		$this->assertSame('skipped', $this->service->refreshFeed(feedUrl: 'https:///nohost')['status']);
	}//end testAllowListMatchIsTrimmedAndCaseInsensitive()

	/**
	 * The conditional GET carries cached validators, the user agent and proxy settings.
	 *
	 * @return void
	 */
	public function testConditionalGetRequestShape(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->appValues['installed_version'] = '2.3.4';
		$this->systemValues = [
			'overwrite.cli.url' => 'https://cloud.example.com/',
			'proxy' => 'proxy.example.com:3128',
			'proxyuserpwd' => 'user:pw',
		];
		$cacheMapper = $this->createMock(FeedCacheMapper::class);
		$cacheMapper->method('upsertUrl')->willReturnCallback(static function (string $url): FeedCache {
			$row = new FeedCache();
			$row->setFeedUrl($url);
			$row->setEtag('"e1"');
			$row->setLastModified('Mon, 01 May 2026 10:00:00 GMT');
			return $row;
		});
		$service = new FeedRefreshService(
			sources: new FeedRefreshSources(
				clientService: $this->clientService,
				urlValidator: $this->urlValidator,
				cacheMapper: $cacheMapper,
				config: $this->config
			),
			appConfig: $this->appConfig,
			placementMapper: $this->placementMapper,
			logger: $this->logger
		);
		$this->client->expects($this->once())->method('get')->with(
			'https://news.example.com/rss',
			[
				'connect_timeout' => 10,
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (compatible; LaunchPad/2.3.4; +https://cloud.example.com/apps/launchpad)',
					'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5',
					'If-None-Match' => '"e1"',
					'If-Modified-Since' => 'Mon, 01 May 2026 10:00:00 GMT',
					'Proxy-Authorization' => 'Basic ' . base64_encode('user:pw'),
				],
				'http_errors' => false,
				'allow_redirects' => false,
				'proxy' => 'proxy.example.com:3128',
			]
		)->willReturn($this->response(status: 304, body: ''));

		$this->assertSame('not-modified', $service->refreshFeed(feedUrl: 'https://news.example.com/rss')['status']);
	}//end testConditionalGetRequestShape()

	/**
	 * Without validators, proxy or instance URL the request uses the defaults.
	 *
	 * @return void
	 */
	public function testConditionalGetDefaults(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->systemValues = ['proxy' => 'p:1'];
		$this->client->expects($this->once())->method('get')->with(
			'https://news.example.com/rss',
			[
				'connect_timeout' => 10,
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (compatible; LaunchPad/1.0.0; +https://localhost/apps/launchpad)',
					'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5',
				],
				'http_errors' => false,
				'allow_redirects' => false,
				'proxy' => 'p:1',
			]
		)->willReturn($this->response(status: 304, body: ''));

		$this->service->refreshFeed(feedUrl: 'https://news.example.com/rss');
	}//end testConditionalGetDefaults()

	/**
	 * Transport errors are classified by message.
	 *
	 * @return void
	 */
	public function testTransportErrorsAreClassified(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturnOnConsecutiveCalls(
			$this->throwException(new RuntimeException('Operation Timed Out after 30s')),
			$this->throwException(new RuntimeException('Connection refused'))
		);

		$this->assertSame('failed', $this->service->refreshFeed(feedUrl: 'https://a.example.com/rss')['status']);
		$this->service->refreshFeed(feedUrl: 'https://b.example.com/rss');

		$this->assertSame('timeout: Operation Timed Out after 30s', $this->updates[0]->getLastFailureReason());
		$this->assertNotNull($this->updates[0]->getLastFetchedAt());
		$this->assertSame('transport error: Connection refused', $this->updates[1]->getLastFailureReason());
	}//end testTransportErrorsAreClassified()

	/**
	 * Status, size and parse rejections.
	 *
	 * @return void
	 */
	public function testResponseRejections(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturnOnConsecutiveCalls(
			$this->response(status: 199, body: '<rss/>'),
			$this->response(status: 200, body: str_repeat('x', ((10 * 1024 * 1024) + 1))),
			$this->response(status: 200, body: '<rss><channel>'),
			$this->response(status: 200, body: '')
		);

		$results = [];
		foreach (['a', 'b', 'c', 'd'] as $host) {
			$results[] = $this->service->refreshFeed(feedUrl: 'https://' . $host . '.example.com/rss')['status'];
		}

		$this->assertSame(['failed', 'failed', 'failed', 'failed'], $results);
		$this->assertSame('199 ', $this->updates[0]->getLastFailureReason());
		$this->assertSame('response too large', $this->updates[1]->getLastFailureReason());
		$this->assertStringStartsWith('parse error: ', $this->updates[2]->getLastFailureReason());
		$this->assertStringStartsWith('parse error: ', $this->updates[3]->getLastFailureReason());
		$this->assertSame([['guid' => 'kept']], $this->updates[2]->decodeItems());
	}//end testResponseRejections()

	/**
	 * Atom entries: alternate link, summary/content and published/updated fallbacks, id or hash guid.
	 *
	 * @return void
	 */
	public function testAtomEntriesAreNormalised(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$atom = '<feed xmlns="http://www.w3.org/2005/Atom"><title>  </title>'
			. '<entry><id> urn:1 </id><title> One </title><summary>S1</summary>'
			. '<link rel="self" href="https://x/self"/><link href="https://x/one"/><link rel="alternate" href="https://x/later"/>'
			. '<published>2026-05-02T10:00:00Z</published><updated>2026-05-09T10:00:00Z</updated></entry>'
			. '<entry><title>Two</title><content> C2 </content><link rel="alternate" href=""/>'
			. '<updated>2026-05-03T10:00:00Z</updated></entry>'
			. '</feed>';
		$this->client->method('get')->willReturn(
			$this->response(status: 200, body: $atom, headers: ['etag' => '"t"', 'last-modified' => ['L1', 'L2']])
		);

		$result = $this->service->refreshFeed(feedUrl: 'https://atom.example.com/feed');

		$this->assertSame(['status' => 'ok', 'itemCount' => 2], ['status' => $result['status'], 'itemCount' => $result['itemCount']]);
		$row = $this->updates[0];
		$this->assertSame('"t"', $row->getEtag());
		$this->assertSame('L1', $row->getLastModified());
		$this->assertSame(
			[
				[
					'guid' => hash('sha256', 'Two2026-05-03T10:00:00Zhttps://atom.example.com/feed'),
					'title' => 'Two',
					'summary' => 'C2',
					'link' => '',
					'pubDate' => '2026-05-03T10:00:00Z',
					'sourceUrl' => 'https://atom.example.com/feed',
					'sourceTitle' => 'atom.example.com',
					'thumbnailUrl' => null,
				],
				[
					'guid' => 'urn:1',
					'title' => 'One',
					'summary' => 'S1',
					'link' => 'https://x/one',
					'pubDate' => '2026-05-02T10:00:00Z',
					'sourceUrl' => 'https://atom.example.com/feed',
					'sourceTitle' => 'atom.example.com',
					'thumbnailUrl' => null,
				],
			],
			$row->decodeItems()
		);
	}//end testAtomEntriesAreNormalised()

	/**
	 * RSS items: trimmed fields, image enclosures only, hash guid, feed title and undated items last.
	 *
	 * @return void
	 */
	public function testRssItemsAreNormalised(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$rss = '<rss><channel><title> Chan </title>'
			. '<item><title> A </title><description> d </description><link> https://x/a </link>'
			. '<enclosure url="https://x/a.png" type="image/png"/></item>'
			. '<item><title>B</title><pubDate>Mon, 01 May 2026 10:00:00 GMT</pubDate><guid> g-b </guid>'
			. '<enclosure url="https://x/b.mp3" type="audio/mpeg"/></item>'
			. '<item><title>C</title><pubDate>Tue, 02 May 2026 10:00:00 GMT</pubDate><guid>g-c</guid>'
			. '<enclosure url="https://x/c.png"/></item>'
			. '</channel></rss>';
		$this->client->method('get')->willReturn($this->response(status: 200, body: $rss));

		$this->service->refreshFeed(feedUrl: 'https://rss.example.com/feed');

		$items = $this->updates[0]->decodeItems();
		$this->assertSame(['g-c', 'g-b', hash('sha256', 'Ahttps://rss.example.com/feed')], array_column($items, 'guid'));
		$this->assertSame([null, null, 'https://x/a.png'], array_column($items, 'thumbnailUrl'));
		$this->assertSame('Chan', $items[0]['sourceTitle']);
		$this->assertSame(['title' => 'A', 'summary' => 'd', 'link' => 'https://x/a', 'pubDate' => ''], [
			'title' => $items[2]['title'],
			'summary' => $items[2]['summary'],
			'link' => $items[2]['link'],
			'pubDate' => $items[2]['pubDate'],
		]);
		$this->assertNull($this->updates[0]->getLastFailureReason());
		$this->assertNotNull($this->updates[0]->getLastSuccessAt());
	}//end testRssItemsAreNormalised()

	/**
	 * Feed-level title fallback and a document with neither shape.
	 *
	 * @return void
	 */
	public function testTitleFallbacksAndEmptyDocument(): void {
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturnOnConsecutiveCalls(
			$this->response(status: 200, body: '<rss><channel><title></title><item><title>x</title></item></channel><title>Outer</title></rss>'),
			$this->response(status: 200, body: '<something/>')
		);

		$this->service->refreshFeed(feedUrl: 'https://t.example.com/feed');
		$empty = $this->service->refreshFeed(feedUrl: 'https://u.example.com/feed');

		$this->assertSame('Outer', $this->updates[0]->decodeItems()[0]['sourceTitle']);
		$this->assertSame(['status' => 'ok', 'itemCount' => 0], ['status' => $empty['status'], 'itemCount' => $empty['itemCount']]);
	}//end testTitleFallbacksAndEmptyDocument()

	/**
	 * Discovery skips non-array and non-string entries, dedupes and sorts.
	 *
	 * @return void
	 */
	public function testDiscoveryFiltersAndSorts(): void {
		$this->placements([
			['feedUrls' => ['https://b/', '', 7, 'https://a/']],
			['feedUrls' => 'https://c/'],
			[],
			['feedUrls' => ['https://a/']],
		]);

		$this->assertSame(['https://a/', 'https://b/'], $this->service->discoverFeedUrls());
	}//end testDiscoveryFiltersAndSorts()

	/**
	 * Discovery failure is logged at debug and yields no URLs.
	 *
	 * @return void
	 */
	public function testDiscoveryFailureYieldsNothing(): void {
		$this->placementMapper->method('findByWidgetId')->willThrowException(new RuntimeException('no table'));
		$this->logger->expects($this->exactly(2))->method('debug')->with(
			'FeedRefreshService discovery skipped',
			['app' => 'launchpad', 'exception' => 'no table']
		);
		$this->logger->expects($this->once())->method('info')->with(
			'No news widget placements found; nothing to refresh',
			['app' => 'launchpad']
		);

		$this->assertSame([], $this->service->discoverFeedUrls());
		$summary = $this->service->refreshAll();
		$this->assertSame([0, 0, 0], [$summary['processedCount'], $summary['successCount'], $summary['failureCount']]);
	}//end testDiscoveryFailureYieldsNothing()

	/**
	 * A sweep resumes after the cursor and clears it at the tail.
	 *
	 * @return void
	 */
	public function testSweepResumesAfterCursorAndClearsIt(): void {
		$this->placements([['feedUrls' => ['https://a.example.com/', 'https://b.example.com/', 'https://c.example.com/']]]);
		$this->appValues['feed_refresh_cursor'] = 'https://a.example.com/';
		$this->urlValidator->method('isSafe')->willReturn(true);
		$fetched = [];
		$this->client->method('get')->willReturnCallback(function (string $url) use (&$fetched): IResponse {
			$fetched[] = $url;
			return $this->response(status: 304, body: '');
		});
		$this->appConfig->expects($this->once())->method('deleteKey')->with('launchpad', 'feed_refresh_cursor');
		$this->appConfig->expects($this->never())->method('setValueString');

		$summary = $this->service->refreshAll();

		$this->assertSame(['https://b.example.com/', 'https://c.example.com/'], $fetched);
		$this->assertSame([2, 2, 0], [$summary['processedCount'], $summary['successCount'], $summary['failureCount']]);
	}//end testSweepResumesAfterCursorAndClearsIt()

	/**
	 * A stale cursor restarts the sweep; skipped and failed feeds count as failures.
	 *
	 * @return void
	 */
	public function testStaleCursorRestartsAndCountsOutcomes(): void {
		$this->placements([['feedUrls' => ['https://a.example.com/', 'https://b.example.com/']]]);
		$this->appValues['feed_refresh_cursor'] = 'https://gone.example.com/';
		$this->appValues['news_widget_allowed_feed_hosts'] = 'b.example.com';
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturn($this->response(status: 500, body: ''));
		$this->appConfig->expects($this->once())->method('deleteKey');

		$summary = $this->service->refreshAll();

		$this->assertSame([2, 0, 2], [$summary['processedCount'], $summary['successCount'], $summary['failureCount']]);
		$this->assertSame('host not in allow-list', $this->updates[0]->getLastFailureReason());
		$this->assertSame('500 ', $this->updates[1]->getLastFailureReason());
	}//end testStaleCursorRestartsAndCountsOutcomes()

	/**
	 * A single-URL refresh still discovers but neither reads nor writes the cursor.
	 *
	 * @return void
	 */
	public function testSingleUrlRefreshLeavesCursorAlone(): void {
		$this->placementMapper->expects($this->once())->method('findByWidgetId')->willReturn([]);
		$this->urlValidator->method('isSafe')->willReturn(true);
		$this->client->method('get')->willReturn($this->response(status: 304, body: ''));
		$this->appConfig->expects($this->never())->method('deleteKey');
		$this->appConfig->expects($this->never())->method('setValueString');
		$this->appConfig->expects($this->exactly(2))->method('getValueString');

		$summary = $this->service->refreshAll(onlyUrl: 'https://one.example.com/');

		$this->assertSame([1, 1, 0], [$summary['processedCount'], $summary['successCount'], $summary['failureCount']]);
	}//end testSingleUrlRefreshLeavesCursorAlone()

	/**
	 * An exception escaping refreshFeed is logged and counted as a failure.
	 *
	 * @return void
	 */
	public function testBatchCatchesUnexpectedExceptions(): void {
		$this->placements([['feedUrls' => ['https://a.example.com/']]]);
		$cacheMapper = $this->createMock(FeedCacheMapper::class);
		$cacheMapper->method('upsertUrl')->willThrowException(new RuntimeException('db down'));
		$service = new FeedRefreshService(
			sources: new FeedRefreshSources(
				clientService: $this->clientService,
				urlValidator: $this->urlValidator,
				cacheMapper: $cacheMapper,
				config: $this->config
			),
			appConfig: $this->appConfig,
			placementMapper: $this->placementMapper,
			logger: $this->logger
		);
		$this->logger->expects($this->once())->method('error')->with(
			'FeedRefreshService unexpected refreshFeed exception',
			['app' => 'launchpad', 'feedUrl' => 'https://a.example.com/', 'exception' => 'db down']
		);

		$summary = $service->refreshAll();

		$this->assertSame([1, 0, 1], [$summary['processedCount'], $summary['successCount'], $summary['failureCount']]);
	}//end testBatchCatchesUnexpectedExceptions()
}//end class
