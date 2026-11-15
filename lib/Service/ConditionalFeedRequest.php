<?php

/**
 * ConditionalFeedRequest
 *
 * The conditional HTTP GET of the background feed refresh: cached
 * validators, user agent, proxy and the normalised response (REQ-FRJ-004,
 * REQ-FRJ-012).
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
use OCA\LaunchPad\Db\FeedCache;
use OCP\IAppConfig;

/**
 * Sends the conditional GET of one feed refresh.
 *
 * @spec openspec/specs/background-job-feed-refresh/spec.md
 */
final class ConditionalFeedRequest {

	/**
	 * HTTP connect timeout in seconds (design D4).
	 *
	 * @var integer
	 */
	private const CONNECT_TIMEOUT = FeedRefreshService::CONNECT_TIMEOUT;

	/**
	 * HTTP total request timeout in seconds (design D4).
	 *
	 * @var integer
	 */
	private const REQUEST_TIMEOUT = FeedRefreshService::REQUEST_TIMEOUT;

	/**
	 * Constructor
	 *
	 * @param FeedRefreshSources $sources   The HTTP client service and system config.
	 * @param IAppConfig         $appConfig The app config reader (installed version).
	 */
	public function __construct(
		private readonly FeedRefreshSources $sources,
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Perform the conditional HTTP GET. Returns a normalised response
	 * dict with keys: status, reason, body, etag, lastModified.
	 *
	 * @param FeedCache $row The cache row (for ETag/Last-Modified).
	 * @param string $feedUrl The feed URL.
	 *
	 * @return array{status: int, reason: string, body: string, etag: ?string, lastModified: ?string}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function send(
		FeedCache $row,
		string $feedUrl,
	): array {
		$client = $this->sources->clientService->newClient();
		$options = $this->requestOptions(headers: $this->conditionalHeaders(row: $row));

		$response = $client->get(uri: $feedUrl, options: $options);

		$headersOut = $response->getHeaders();

		return [
			'status' => (int)$response->getStatusCode(),
			'reason' => $this->extractReason(response: $response),
			'body' => (string)$response->getBody(),
			'etag' => $this->firstHeader(headers: $headersOut, name: 'ETag'),
			'lastModified' => $this->firstHeader(headers: $headersOut, name: 'Last-Modified'),
		];
	}//end send()

	/**
	 * Request headers: user agent, accepted feed types and the row's cached
	 * validators (ETag, Last-Modified) when present.
	 *
	 * @param FeedCache $row The cache row.
	 *
	 * @return array<string, string>
	 */
	private function conditionalHeaders(FeedCache $row): array {
		$headers = [
			'User-Agent' => $this->buildUserAgent(),
			'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5',
		];

		if ($row->getEtag() !== null && $row->getEtag() !== '') {
			$headers['If-None-Match'] = (string)$row->getEtag();
		}

		if ($row->getLastModified() !== null && $row->getLastModified() !== '') {
			$headers['If-Modified-Since'] = (string)$row->getLastModified();
		}

		return $headers;
	}//end conditionalHeaders()

	/**
	 * Client options of the conditional GET, with the system proxy applied.
	 *
	 * @param array<string, string> $headers Request headers.
	 *
	 * @return array<string, mixed>
	 */
	private function requestOptions(array $headers): array {
		$options = [
			'connect_timeout' => self::CONNECT_TIMEOUT,
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => $headers,
			// 304 must NOT be treated as an HTTP exception by Guzzle.
			'http_errors' => false,
			// C5: disable automatic redirect following to prevent SSRF via
			// open-redirect. Feed URLs are validated by urlValidator before
			// the first request; a 302 to an internal host (e.g. cloud
			// metadata endpoint) would bypass that guard. Mirror the explicit
			// pattern in NewsWidgetService and CalendarWidgetService.
			'allow_redirects' => false,
		];

		$proxy = (string)$this->sources->config->getSystemValue('proxy', '');
		if ($proxy !== '') {
			$options['proxy'] = $proxy;
			$proxyAuth = (string)$this->sources->config->getSystemValue('proxyuserpwd', '');
			if ($proxyAuth !== '') {
				$options['headers']['Proxy-Authorization'] = 'Basic ' . base64_encode(string: $proxyAuth);
			}
		}

		return $options;
	}//end requestOptions()

	/**
	 * Build the User-Agent header per REQ-FRJ-012.
	 *
	 * @return string The User-Agent string.
	 */
	private function buildUserAgent(): string {
		$appVersion = $this->appConfig->getValueString(
			Application::APP_ID,
			'installed_version',
			'1.0.0'
		);
		$instanceUrl = (string)$this->sources->config->getSystemValue(
			'overwrite.cli.url',
			''
		);
		if ($instanceUrl === '') {
			$instanceUrl = 'https://localhost';
		}

		return sprintf(
			'Mozilla/5.0 (compatible; LaunchPad/%s; +%s/apps/launchpad)',
			$appVersion,
			rtrim(string: $instanceUrl, characters: '/')
		);
	}//end buildUserAgent()

	/**
	 * Best-effort extraction of the HTTP reason phrase from a Guzzle
	 * (or NC-wrapped) response object.
	 *
	 * @param object $response The response object.
	 *
	 * @return string The reason phrase, or empty string when unknown.
	 */
	private function extractReason(object $response): string {
		if (method_exists(object_or_class: $response, method: 'getReasonPhrase') === true) {
			return (string)$response->getReasonPhrase();
		}

		return '';
	}//end extractReason()

	/**
	 * Pull the first value of a header from an associative
	 * `name => string|string[]` headers map.
	 *
	 * @param array<string, string|array<int, string>> $headers The headers map.
	 * @param string $name The header name.
	 *
	 * @return string|null The first header value, or null when absent.
	 */
	private function firstHeader(array $headers, string $name): ?string {
		foreach ($headers as $key => $value) {
			if (strcasecmp(string1: $key, string2: $name) !== 0) {
				continue;
			}

			if (is_array(value: $value) === true) {
				return ($value[0] ?? null);
			}

			return (string)$value;
		}

		return null;
	}//end firstHeader()
}//end class
