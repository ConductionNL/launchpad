<?php

/**
 * NewsWidgetService fetch / merge characterisation test
 *
 * Pins the cache-first fetch, the SSRF / allow-list guards, the HTTP
 * status, empty-body and size-cap rejections, the connection reports and
 * the merge / dedupe / sort / limit pipeline of fetchAndMergeFeeds(), plus
 * the feed-shape and summary-sanitiser branches of parseRssFeed().
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

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCA\LaunchPad\Service\FeedFetchTools;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[Small]
class NewsWidgetServiceFetchTest extends TestCase {

	private const RSS = '<?xml version="1.0"?>
<rss version="2.0"><channel><title>Chan</title>
<item><title>Old</title><guid>g-old</guid><pubDate>Mon, 01 May 2026 10:00:00 +0000</pubDate><description>a</description></item>
<item><title>New</title><guid>g-new</guid><pubDate>Mon, 01 May 2026 18:00:00 +0000</pubDate><description>b</description></item>
</channel></rss>';

	private const RSS_DUP = '<?xml version="1.0"?>
<rss version="2.0"><channel><title>Other</title>
<item><title>Dup</title><guid>g-new</guid><pubDate>Mon, 01 May 2026 20:00:00 +0000</pubDate></item>
<item><title>Mid</title><guid>g-mid</guid><pubDate>Mon, 01 May 2026 12:00:00 +0000</pubDate></item>
</channel></rss>';

	private UrlSafetyValidator&MockObject $validator;

	private IClientService&MockObject $clientService;

	private IClient&MockObject $client;

	private ICache&MockObject $cache;

	private IAppConfig&MockObject $appConfig;

	private LoggerInterface&MockObject $logger;

	private ConnectionReporter&MockObject $reporter;

	private NewsWidgetService $service;

	protected function setUp(): void {
		$this->validator = $this->createMock(originalClassName: UrlSafetyValidator::class);
		$this->validator->method('isSafe')->willReturn(true);
		$this->validator->method('checkAllowList')->willReturn(true);
		$this->client = $this->createMock(originalClassName: IClient::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->cache = $this->createMock(originalClassName: ICache::class);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->appConfig->method('getValueInt')->willReturn(120);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->reporter = $this->createMock(originalClassName: ConnectionReporter::class);

		$this->service = new NewsWidgetService(
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			fetch: new FeedFetchTools(
				clientService: $this->clientService,
				cacheFactory: $cacheFactory,
				urlValidator: $this->validator,
				connectionReporter: $this->reporter
			),
			appConfig: $this->appConfig,
			logger: $this->logger
		);
	}//end setUp()

	private function respond(int $status, string $body): void {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$this->client->method('get')->willReturn($response);
	}//end respond()

	public function testCacheHitSkipsTheNetwork(): void {
		$this->cache->method('get')->with('feed_' . sha1('https://a.example.com/rss'))->willReturn(self::RSS);
		$this->clientService->expects($this->never())->method('newClient');
		$this->reporter->expects($this->never())->method('reportCall');

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(['g-new', 'g-old'], array_column($result['items'], 'guid'));
		$this->assertSame('Chan', $result['items'][0]['sourceTitle']);
		$this->assertSame(0, $result['feedsFailed']);
	}//end testCacheHitSkipsTheNetwork()

	public function testLiveFetchIsCachedAndReported(): void {
		$this->cache->method('get')->willReturn('');
		$this->respond(status: 200, body: self::RSS);
		$this->client->expects($this->once())->method('get')->with(
			'https://a.example.com/rss',
			[
				'timeout' => 10,
				'connect_timeout' => 10,
				'verify' => true,
				'allow_redirects' => false,
			]
		);
		$this->cache->expects($this->once())->method('set')
			->with('feed_' . sha1('https://a.example.com/rss'), self::RSS, 120);
		$this->reporter->expects($this->once())->method('reportCall')
			->with(ConnectionReporter::KEY_NEWS_FEEDS, 'https://a.example.com/rss', 200);
		$this->appConfig->expects($this->once())->method('getValueInt')
			->with('launchpad', 'news_widget_feed_cache_ttl_seconds', 3600);

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertCount(2, $result['items']);
		$this->assertSame([], $result['failedUrls']);
	}//end testLiveFetchIsCachedAndReported()

	public function testNonSuccessStatusFailsTheFeed(): void {
		$this->respond(status: 404, body: self::RSS);
		$this->cache->expects($this->never())->method('set');
		$this->reporter->expects($this->once())->method('reportCall')
			->with(ConnectionReporter::KEY_NEWS_FEEDS, 'https://a.example.com/rss', 404);
		$this->logger->expects($this->once())->method('warning')
			->with('NewsWidget: feed fetch returned HTTP 404 for https://a.example.com/rss');

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame([], $result['items']);
		$this->assertSame(['https://a.example.com/rss'], $result['failedUrls']);
	}//end testNonSuccessStatusFailsTheFeed()

	public function testStatusBelowTwoHundredFailsTheFeed(): void {
		$this->respond(status: 199, body: self::RSS);

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(1, $result['feedsFailed']);
	}//end testStatusBelowTwoHundredFailsTheFeed()

	public function testEmptyBodyFailsTheFeedWithoutCaching(): void {
		$this->respond(status: 204, body: '');
		$this->cache->expects($this->never())->method('set');
		$this->logger->expects($this->never())->method('warning');

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(1, $result['feedsFailed']);
	}//end testEmptyBodyFailsTheFeedWithoutCaching()

	public function testOversizedBodyFailsTheFeed(): void {
		$this->respond(status: 200, body: str_repeat('x', 1048577));
		$this->cache->expects($this->never())->method('set');
		$this->logger->expects($this->once())->method('warning')
			->with('NewsWidget: feed response exceeds 1MB cap, skipping https://a.example.com/rss');

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(1, $result['feedsFailed']);
	}//end testOversizedBodyFailsTheFeed()

	public function testClientExceptionIsLoggedAndReported(): void {
		$error = new RuntimeException('boom');
		$this->client->method('get')->willThrowException($error);
		$this->reporter->method('httpStatusOf')->with($error)->willReturn(503);
		$this->reporter->expects($this->once())->method('reportCall')
			->with(ConnectionReporter::KEY_NEWS_FEEDS, 'https://a.example.com/rss', 503);
		$this->logger->expects($this->once())->method('warning')
			->with('NewsWidget: feed fetch failed for https://a.example.com/rss', ['exception' => $error]);

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(['https://a.example.com/rss'], $result['failedUrls']);
	}//end testClientExceptionIsLoggedAndReported()

	public function testCacheWriteExceptionIsTreatedAsFetchFailure(): void {
		$this->respond(status: 200, body: self::RSS);
		$this->cache->method('set')->willThrowException(new RuntimeException('cache down'));

		$result = $this->service->fetchAndMergeFeeds(feedUrls: ['https://a.example.com/rss'], limit: 10);

		$this->assertSame(1, $result['feedsFailed']);
	}//end testCacheWriteExceptionIsTreatedAsFetchFailure()

	public function testUnsafeAndDisallowedUrlsFailInOrder(): void {
		$validator = $this->createMock(originalClassName: UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturnCallback(static fn (string $url): bool => $url !== 'https://unsafe.example.com/');
		$validator->method('checkAllowList')->willReturnCallback(static fn (string $url): bool => $url !== 'https://denied.example.com/');
		$service = new NewsWidgetService(
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			fetch: new FeedFetchTools(
				clientService: $this->clientService,
				cacheFactory: $this->createMock(originalClassName: ICacheFactory::class),
				urlValidator: $validator
			),
			appConfig: $this->appConfig,
			logger: $this->logger
		);
		$this->clientService->expects($this->never())->method('newClient');
		$messages = [];
		$this->logger->method('warning')->willReturnCallback(
			static function (string $message, array $context) use (&$messages): void {
				$messages[] = [$message, $context];
			}
		);

		$result = $service->fetchAndMergeFeeds(
			feedUrls: ['https://unsafe.example.com/', 'https://denied.example.com/'],
			limit: 10
		);

		$this->assertSame(['https://unsafe.example.com/', 'https://denied.example.com/'], $result['failedUrls']);
		$this->assertSame(2, $result['feedsFailed']);
		$this->assertSame(
			[
				['NewsWidget: URL rejected by SSRF guard', ['url' => 'https://unsafe.example.com/']],
				['NewsWidget: URL skipped by allow-list', ['url' => 'https://denied.example.com/']],
			],
			$messages
		);
	}//end testUnsafeAndDisallowedUrlsFailInOrder()

	public function testFeedsAreMergedDedupedSortedAndLimited(): void {
		$this->cache->method('get')->willReturnCallback(
			static fn (string $key): string => $key === 'feed_' . sha1('https://a.example.com/rss') ? self::RSS : self::RSS_DUP
		);

		$result = $this->service->fetchAndMergeFeeds(
			feedUrls: ['https://a.example.com/rss', 'https://b.example.com/rss'],
			limit: 2
		);

		$this->assertSame(['g-new', 'g-mid'], array_column($result['items'], 'guid'));
		$this->assertSame('Chan', $result['items'][0]['sourceTitle']);
		$this->assertSame(0, $result['feedsFailed']);
	}//end testFeedsAreMergedDedupedSortedAndLimited()

	public function testBareChannelRootAndTitleFallback(): void {
		$items = $this->service->parseRssFeed(
			feedContent: '<channel><item><title>T</title><pubDate>P</pubDate>'
				. '<enclosure url="https://img.example.com/a.png" type="image/png"/></item></channel>',
			sourceUrl: 'https://s.example.com/',
			sourceTitle: 'fallback'
		);

		$this->assertCount(1, $items);
		$this->assertSame('fallback', $items[0]['sourceTitle']);
		$this->assertSame(sha1('T|P|https://s.example.com/'), $items[0]['guid']);
		$this->assertSame('https://img.example.com/a.png', $items[0]['thumbnailUrl']);
	}//end testBareChannelRootAndTitleFallback()

	public function testAtomFeedWithoutTitleKeepsFallback(): void {
		$items = $this->service->parseRssFeed(
			feedContent: '<feed><entry><title>A</title><published>P</published><content>c</content></entry></feed>',
			sourceUrl: 'https://s.example.com/',
			sourceTitle: 'fallback'
		);

		$this->assertSame(
			[
				'guid' => sha1('A|P|https://s.example.com/'),
				'title' => 'A',
				'summary' => 'c',
				'link' => '',
				'pubDate' => 'P',
				'sourceUrl' => 'https://s.example.com/',
				'sourceTitle' => 'fallback',
				'thumbnailUrl' => null,
			],
			$items[0]
		);
	}//end testAtomFeedWithoutTitleKeepsFallback()

	public function testUnsupportedRootIsLoggedAndEmpty(): void {
		$this->logger->expects($this->once())->method('info')
			->with('NewsWidget: unsupported feed root <html> for https://s.example.com/');

		$items = $this->service->parseRssFeed(
			feedContent: '<html><body/></html>',
			sourceUrl: 'https://s.example.com/',
			sourceTitle: 'fallback'
		);

		$this->assertSame([], $items);
	}//end testUnsupportedRootIsLoggedAndEmpty()

	public function testRssRootWithoutChannelIsUnsupported(): void {
		$this->logger->expects($this->once())->method('info')
			->with('NewsWidget: unsupported feed root <rss> for https://s.example.com/');

		$this->assertSame(
			[],
			$this->service->parseRssFeed(feedContent: '<rss/>', sourceUrl: 'https://s.example.com/', sourceTitle: 'f')
		);
	}//end testRssRootWithoutChannelIsUnsupported()

	public function testBlankPayloadIsEmptyWithoutParsing(): void {
		$this->logger->expects($this->never())->method('info');

		$this->assertSame(
			[],
			$this->service->parseRssFeed(feedContent: "  \n", sourceUrl: 'https://s.example.com/', sourceTitle: 'f')
		);
	}//end testBlankPayloadIsEmptyWithoutParsing()

	public function testSanitiserRewritesDataHrefAndKeepsTextNodes(): void {
		$html = $this->service->sanitiseSummaryHtml(
			html: 'Lead <a href=" DATA:text/html,x">x</a> <a href="https://ok.example.com/">ok</a> tail'
		);

		$this->assertSame(
			'Lead <a href="#" rel="noopener noreferrer">x</a> '
				. '<a href="https://ok.example.com/" rel="noopener noreferrer">ok</a> tail',
			$html
		);
	}//end testSanitiserRewritesDataHrefAndKeepsTextNodes()

	public function testSanitiserReturnsEmptyForEmptyInput(): void {
		$this->assertSame('', $this->service->sanitiseSummaryHtml(html: ''));
	}//end testSanitiserReturnsEmptyForEmptyInput()

	public function testDeduplicateKeepsEveryItemWithoutGuid(): void {
		$items = [
			['guid' => '', 'title' => 'a'],
			['title' => 'b'],
			['guid' => 'x', 'title' => 'c'],
			['guid' => '', 'title' => 'd'],
			['guid' => 'x', 'title' => 'e'],
			['guid' => 7, 'title' => 'f'],
			['guid' => '7', 'title' => 'g'],
		];

		$this->assertSame(
			['a', 'b', 'c', 'd', 'f'],
			array_column($this->service->deduplicateItems(items: $items), 'title')
		);
	}//end testDeduplicateKeepsEveryItemWithoutGuid()

	public function testEnclosureWithNonImageTypeHasNoThumbnail(): void {
		$items = $this->service->parseRssFeed(
			feedContent: '<rss><channel><item><title>T</title><enclosure url="https://a.example.com/x.mp3" type="audio/mpeg"/></item>'
				. '<item><title>U</title><enclosure url="https://a.example.com/y"/></item>'
				. '<item><title>V</title><enclosure type="image/png"/></item></channel></rss>',
			sourceUrl: 'https://s.example.com/',
			sourceTitle: 'f'
		);

		$this->assertSame([null, 'https://a.example.com/y', null], array_column($items, 'thumbnailUrl'));
	}//end testEnclosureWithNonImageTypeHasNoThumbnail()
}//end class
