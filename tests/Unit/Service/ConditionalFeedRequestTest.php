<?php

/**
 * ConditionalFeedRequestTest
 *
 * Unit tests for ConditionalFeedRequest: the conditional headers, user
 * agent, proxy and timeout options of the feed GET and the normalised
 * response (REQ-FRJ-004, REQ-FRJ-012).
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

use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\FeedCacheMapper;
use OCA\LaunchPad\Service\ConditionalFeedRequest;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCA\LaunchPad\Service\FeedRefreshSources;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConditionalFeedRequest.
 */
#[CoversClass(ConditionalFeedRequest::class)]
#[UsesClass(FeedCache::class)]
#[UsesClass(FeedRefreshSources::class)]
class ConditionalFeedRequestTest extends TestCase {
	/**
	 * HTTP client double.
	 *
	 * @var IClient&MockObject
	 */
	private IClient&MockObject $client;

	/**
	 * System config double.
	 *
	 * @var IConfig&MockObject
	 */
	private IConfig&MockObject $config;

	/**
	 * App config double.
	 *
	 * @var IAppConfig&MockObject
	 */
	private IAppConfig&MockObject $appConfig;

	/**
	 * Options passed to the client's get().
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $sentOptions = null;

	/**
	 * Uri passed to the client's get().
	 *
	 * @var string|null
	 */
	private ?string $sentUri = null;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->client = $this->createMock(IClient::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getValueString')
			->with('launchpad', 'installed_version', '1.0.0')
			->willReturn('2.3.4');
	}//end setUp()

	/**
	 * The request under test, with system values and a canned response.
	 *
	 * @param array<string, string> $system  System config values.
	 * @param IResponse             $response The response the client returns.
	 *
	 * @return ConditionalFeedRequest
	 */
	private function request(array $system, IResponse $response): ConditionalFeedRequest {
		$this->config->method('getSystemValue')->willReturnCallback(
			static fn (string $key, mixed $default=''): mixed => ($system[$key] ?? $default)
		);
		$this->client->method('get')->willReturnCallback(
			function (string $uri, array $options) use ($response): IResponse {
				$this->sentUri = $uri;
				$this->sentOptions = $options;
				return $response;
			}
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);

		$sources = new FeedRefreshSources(
			clientService: $clientService,
			urlValidator: $this->createMock(UrlSafetyValidator::class),
			cacheMapper: $this->createMock(FeedCacheMapper::class),
			config: $this->config,
		);

		return new ConditionalFeedRequest(sources: $sources, appConfig: $this->appConfig);
	}//end request()

	/**
	 * A response double.
	 *
	 * @param int                                   $status  Status code.
	 * @param array<string, string|array<int, string>> $headers Response headers.
	 * @param string                                $body    Body.
	 *
	 * @return IResponse&MockObject
	 */
	private function response(int $status, array $headers, string $body): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getHeaders')->willReturn($headers);
		$response->method('getBody')->willReturn($body);

		return $response;
	}//end response()

	/**
	 * A row without validators: base headers, timeouts, no redirects, no proxy.
	 *
	 * @return void
	 */
	public function testPlainRequestOptions(): void {
		$row = new FeedCache();

		$this->request(
			system: ['overwrite.cli.url' => 'https://cloud.example/'],
			response: $this->response(200, [], '<rss/>')
		)->send(row: $row, feedUrl: 'https://news.example/feed');

		$this->assertSame('https://news.example/feed', $this->sentUri);
		$this->assertSame(
			[
				'connect_timeout' => FeedRefreshService::CONNECT_TIMEOUT,
				'timeout' => FeedRefreshService::REQUEST_TIMEOUT,
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (compatible; LaunchPad/2.3.4; +https://cloud.example/apps/launchpad)',
					'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5',
				],
				'http_errors' => false,
				'allow_redirects' => false,
			],
			$this->sentOptions
		);
	}//end testPlainRequestOptions()

	/**
	 * Cached validators become If-None-Match / If-Modified-Since.
	 *
	 * @return void
	 */
	public function testCachedValidatorsAreSent(): void {
		$row = new FeedCache();
		$row->setEtag('"abc"');
		$row->setLastModified('Mon, 02 Jan 2026 00:00:00 GMT');

		$this->request(system: [], response: $this->response(304, [], ''))
			->send(row: $row, feedUrl: 'https://news.example/feed');

		$this->assertSame('"abc"', $this->sentOptions['headers']['If-None-Match']);
		$this->assertSame('Mon, 02 Jan 2026 00:00:00 GMT', $this->sentOptions['headers']['If-Modified-Since']);
	}//end testCachedValidatorsAreSent()

	/**
	 * Empty validators are not sent; no instance URL falls back to localhost.
	 *
	 * @return void
	 */
	public function testEmptyValidatorsAreNotSentAndUserAgentFallsBack(): void {
		$row = new FeedCache();
		$row->setEtag('');
		$row->setLastModified('');

		$this->request(system: [], response: $this->response(200, [], ''))
			->send(row: $row, feedUrl: 'https://news.example/feed');

		$this->assertArrayNotHasKey('If-None-Match', $this->sentOptions['headers']);
		$this->assertArrayNotHasKey('If-Modified-Since', $this->sentOptions['headers']);
		$this->assertSame(
			'Mozilla/5.0 (compatible; LaunchPad/2.3.4; +https://localhost/apps/launchpad)',
			$this->sentOptions['headers']['User-Agent']
		);
	}//end testEmptyValidatorsAreNotSentAndUserAgentFallsBack()

	/**
	 * The system proxy and its credentials are applied.
	 *
	 * @return void
	 */
	public function testProxyAndProxyAuthAreApplied(): void {
		$this->request(
			system: ['proxy' => 'proxy.example:3128', 'proxyuserpwd' => 'u:p'],
			response: $this->response(200, [], '')
		)->send(row: new FeedCache(), feedUrl: 'https://news.example/feed');

		$this->assertSame('proxy.example:3128', $this->sentOptions['proxy']);
		$this->assertSame('Basic ' . base64_encode('u:p'), $this->sentOptions['headers']['Proxy-Authorization']);
	}//end testProxyAndProxyAuthAreApplied()

	/**
	 * A proxy without credentials adds no Proxy-Authorization header.
	 *
	 * @return void
	 */
	public function testProxyWithoutAuth(): void {
		$this->request(system: ['proxy' => 'proxy.example:3128'], response: $this->response(200, [], ''))
			->send(row: new FeedCache(), feedUrl: 'https://news.example/feed');

		$this->assertSame('proxy.example:3128', $this->sentOptions['proxy']);
		$this->assertArrayNotHasKey('Proxy-Authorization', $this->sentOptions['headers']);
	}//end testProxyWithoutAuth()

	/**
	 * The response is normalised: status, body and the first value of the
	 * validator headers, matched case-insensitively.
	 *
	 * @return void
	 */
	public function testResponseIsNormalised(): void {
		$result = $this->request(
			system: [],
			response: $this->response(200, ['etag' => ['"v2"', '"v3"'], 'LAST-MODIFIED' => 'Tue, 03 Jan 2026 00:00:00 GMT'], '<rss/>')
		)->send(row: new FeedCache(), feedUrl: 'https://news.example/feed');

		$this->assertSame(
			[
				'status' => 200,
				'reason' => '',
				'body' => '<rss/>',
				'etag' => '"v2"',
				'lastModified' => 'Tue, 03 Jan 2026 00:00:00 GMT',
			],
			$result
		);
	}//end testResponseIsNormalised()

	/**
	 * Missing validator headers come back as null.
	 *
	 * @return void
	 */
	public function testMissingValidatorHeadersAreNull(): void {
		$result = $this->request(system: [], response: $this->response(304, ['Content-Type' => 'text/xml'], ''))
			->send(row: new FeedCache(), feedUrl: 'https://news.example/feed');

		$this->assertSame(304, $result['status']);
		$this->assertNull($result['etag']);
		$this->assertNull($result['lastModified']);
	}//end testMissingValidatorHeadersAreNull()
}//end class
