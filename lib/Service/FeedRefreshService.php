<?php

/**
 * FeedRefreshService
 *
 * Discovers active news-widget feed URLs, fetches each feed via
 * Nextcloud's `IClientService` with HTTP conditional-get headers,
 * normalises RSS 2.0 / Atom 1.0 items to the news-widget schema, caps at
 * 50 items, and persists the cache row in `oc_launchpad_feed_cache`. Each
 * feed is wrapped in its own try/catch so a single failure does not
 * block the rest (REQ-FRJ-006).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Background-job feed refresh service (REQ-FRJ-001..012).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @spec                                             openspec/specs/background-job-feed-refresh/spec.md
 */
class FeedRefreshService {
	/**
	 * Maximum response body size accepted from a feed server (10 MB).
	 * Larger responses are rejected with `lastFailureReason = "response
	 * too large"` (REQ-FRJ-005, design D3).
	 *
	 * @var integer
	 */
	public const MAX_RESPONSE_SIZE = (10 * 1024 * 1024);

	/**
	 * Default HTTP connect timeout in seconds (design D4).
	 *
	 * @var integer
	 */
	public const CONNECT_TIMEOUT = 10;

	/**
	 * Default HTTP total request timeout in seconds (design D4).
	 *
	 * @var integer
	 */
	public const REQUEST_TIMEOUT = 30;

	/**
	 * Per-tick batch size — feeds beyond this index are deferred to the
	 * next tick via the cursor (REQ-FRJ-008).
	 *
	 * @var integer
	 */
	public const BATCH_SIZE = 500;

	/**
	 * Per-tick wall-clock budget in seconds (REQ-FRJ-008).
	 *
	 * @var integer
	 */
	public const WALL_CLOCK_BUDGET = 300;

	/**
	 * App config key — admin-tunable allow-list of feed hostnames
	 * (REQ-FRJ-011). Empty string (default) means "no restrictions".
	 *
	 * @var string
	 */
	public const CONFIG_KEY_ALLOWED_HOSTS = 'news_widget_allowed_feed_hosts';

	/**
	 * App config key — batch cursor for resuming work across ticks
	 * (REQ-FRJ-008).
	 *
	 * @var string
	 */
	public const CONFIG_KEY_CURSOR = 'feed_refresh_cursor';

	/**
	 * The widget id used by news placements. Discovery filters
	 * placements on this exact id (REQ-FRJ-003).
	 *
	 * @var string
	 */
	public const NEWS_WIDGET_ID = 'launchpad_news';

	/**
	 * Constructor.
	 *
	 * @param FeedRefreshSources    $sources The HTTP client service, URL validator, feed cache mapper and config.
	 * @param IAppConfig            $appConfig The app config reader.
	 * @param WidgetPlacementMapper $placementMapper The widget-placement mapper.
	 * @param LoggerInterface       $logger The diagnostic logger.
	 */
	public function __construct(
		private readonly FeedRefreshSources $sources,
		private readonly IAppConfig $appConfig,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Discover the deduplicated, sorted set of active feed URLs from
	 * news-widget placements (REQ-FRJ-003).
	 *
	 * The current schema does not yet carry a `widget_content` column on
	 * `oc_launchpad_widget_placements`; the news widget is a sibling spec.
	 * Until the news widget lands, discovery returns an empty array if
	 * no `launchpad_news` placement is found OR if the placement does not
	 * expose any feed URLs through the JSON-encoded `style_config`
	 * fallback. The contract is forward-compatible: when the news widget
	 * adds feed-URL storage, this method picks it up automatically via
	 * the JSON-decode path.
	 *
	 * @return string[] The deduplicated, sorted feed URL list.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function discoverFeedUrls(): array {
		$rawUrls = [];
		foreach ($this->findNewsPlacements() as $placement) {
			array_push($rawUrls, ...self::configuredFeedUrls(placement: $placement));
		}

		$unique = array_values(array: array_unique(array: $rawUrls));
		sort(array: $unique);

		return $unique;
	}//end discoverFeedUrls()

	/**
	 * The news-widget placements, or none when they cannot be read.
	 *
	 * @return WidgetPlacement[]
	 */
	private function findNewsPlacements(): array {
		try {
			return $this->placementMapper->findByWidgetId(
				widgetId: self::NEWS_WIDGET_ID
			);
		} catch (Throwable $exception) {
			// News widget not yet wired up — treat as zero placements.
			$this->logger->debug(
				message: 'FeedRefreshService discovery skipped',
				context: [
					'app' => Application::APP_ID,
					'exception' => $exception->getMessage(),
				]
			);
			return [];
		}
	}//end findNewsPlacements()

	/**
	 * The non-empty string entries of a placement's `feedUrls` list.
	 *
	 * @param WidgetPlacement $placement A news-widget placement.
	 *
	 * @return string[]
	 */
	private static function configuredFeedUrls(WidgetPlacement $placement): array {
		$config = $placement->getStyleConfigArray();
		$urls = ($config['feedUrls'] ?? []);
		if (is_array(value: $urls) === false) {
			return [];
		}

		$out = [];
		foreach ($urls as $url) {
			if (is_string(value: $url) === true && $url !== '') {
				$out[] = $url;
			}
		}

		return $out;
	}//end configuredFeedUrls()

	/**
	 * Refresh a single feed URL — performs HTTP conditional GET, parses
	 * on 200, normalises items, persists the cache row. All failures
	 * (transport, HTTP 4xx/5xx, parse error, allow-list reject, size
	 * cap) are caught and recorded in `lastFailureReason` (REQ-FRJ-006).
	 *
	 * @param string $feedUrl The feed URL.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function refreshFeed(string $feedUrl): array {
		$run = FeedRefreshRun::start(mapper: $this->sources->cacheMapper, feedUrl: $feedUrl);

		return $this->rejectBeforeFetch(run: $run) ?? $this->fetchAndStore(run: $run);
	}//end refreshFeed()

	/**
	 * Conditional GET, post-fetch guards, parse and persist.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 */
	private function fetchAndStore(FeedRefreshRun $run): array {
		try {
			$response = $this->doConditionalGet(row: $run->row, feedUrl: $run->feedUrl);
		} catch (Throwable $exception) {
			$run->stampFetched();
			return $this->recordFailure(
				run: $run,
				reason: $this->classifyTransportError(exception: $exception)
			);
		}

		$run->stampFetched();

		return $this->rejectResponse(run: $run, response: $response) ?? $this->storeItems(run: $run, response: $response);
	}//end fetchAndStore()

	/**
	 * Parse a fetched body and persist its items and validators, or record
	 * the parse failure.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 * @param array $response Envelope from `doConditionalGet()`.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 */
	private function storeItems(FeedRefreshRun $run, array $response): array {
		try {
			$items = $this->parseFeedBody(
				body: $response['body'],
				feedUrl: $run->feedUrl
			);
		} catch (Throwable $exception) {
			return $this->recordFailure(
				run: $run,
				reason: 'parse error: ' . $exception->getMessage()
			);
		}

		$run->row->encodeItems(items: $items);
		$this->markSucceeded(run: $run, response: $response);

		return $run->result(status: 'ok', itemCount: count(value: $items));
	}//end storeItems()

	/**
	 * Mark the row successfully fetched: success time, no failure reason,
	 * the response's validators; then persist it.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 * @param array $response Envelope from `doConditionalGet()`.
	 *
	 * @return void
	 */
	private function markSucceeded(FeedRefreshRun $run, array $response): void {
		$run->row->setLastSuccessAt($run->now);
		$run->row->setLastFailureReason(null);
		$run->row->setEtag($response['etag']);
		$run->row->setLastModified($response['lastModified']);
		$this->sources->cacheMapper->update(entity: $run->row);
	}//end markSucceeded()

	/**
	 * Persist a per-feed failure and build the `failed` result envelope.
	 *
	 * The reported item count is the one still cached from the previous
	 * successful fetch — a failure never discards items (REQ-FRJ-006).
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 * @param string $reason Human-readable failure reason.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function recordFailure(FeedRefreshRun $run, string $reason): array {
		$run->row->setLastFailureReason($reason);
		$this->sources->cacheMapper->update(entity: $run->row);

		return $run->result(status: 'failed', itemCount: $run->cachedItemCount());
	}//end recordFailure()

	/**
	 * Pre-fetch guards, in order: allow-list, SSRF safety and URL scheme.
	 *
	 * Each rejection stamps `lastFetchedAt` and persists the row. An
	 * allow-list miss reports `skipped`; SSRF and scheme report `failed`.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}|null
	 *                                                                     The early result envelope, or null when the URL may be fetched.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function rejectBeforeFetch(FeedRefreshRun $run): ?array {
		return $this->rejectDisallowedHost(run: $run)
			?? $this->rejectUnsafeUrl(run: $run)
			?? $this->rejectInvalidScheme(run: $run);
	}//end rejectBeforeFetch()

	/**
	 * Allow-list pre-check (REQ-FRJ-011): a host outside the admin
	 * allow-list is skipped, not failed.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}|null
	 */
	private function rejectDisallowedHost(FeedRefreshRun $run): ?array {
		if ($this->isHostAllowed(feedUrl: $run->feedUrl) === true) {
			return null;
		}

		$run->row->setLastFailureReason('host not in allow-list');
		$run->stampFetched();
		$this->sources->cacheMapper->update(entity: $run->row);
		$this->logger->warning(
			message: 'Feed host not in allow-list — skipping fetch',
			context: [
				'app' => Application::APP_ID,
				'feedUrl' => $run->feedUrl,
			]
		);

		return $run->result(status: 'skipped', itemCount: $run->cachedItemCount());
	}//end rejectDisallowedHost()

	/**
	 * H3: SSRF guard — reject URLs that resolve to private/reserved IP
	 * ranges and enforce HTTPS-only. Delegates to UrlSafetyValidator.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}|null
	 */
	private function rejectUnsafeUrl(FeedRefreshRun $run): ?array {
		if ($this->sources->urlValidator->isSafe(url: $run->feedUrl) === true) {
			return null;
		}

		$run->stampFetched();
		$result = $this->recordFailure(
			run: $run,
			reason: 'SSRF guard: private/reserved IP or non-HTTPS URL rejected'
		);
		$this->logger->warning(
			message: 'Feed URL rejected by SSRF guard — private IP or non-HTTPS',
			context: [
				'app' => Application::APP_ID,
				'feedUrl' => $run->feedUrl,
			]
		);

		return $result;
	}//end rejectUnsafeUrl()

	/**
	 * Scheme guard — only HTTP/HTTPS (REQ-FRJ-010 scenario "Invalid feedUrl
	 * scheme rejected" applies at the controller layer; here we
	 * double-check at service layer for defence in depth).
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}|null
	 */
	private function rejectInvalidScheme(FeedRefreshRun $run): ?array {
		$scheme = strtolower(string: (string)parse_url(url: $run->feedUrl, component: PHP_URL_SCHEME));
		if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === true) {
			return null;
		}

		$run->stampFetched();
		return $this->recordFailure(run: $run, reason: 'invalid scheme: ' . $scheme);
	}//end rejectInvalidScheme()

	/**
	 * Post-fetch guards: not-modified, non-2xx status and the size cap.
	 *
	 * Assumes the caller has already stamped `lastFetchedAt` on the row.
	 *
	 * @param FeedRefreshRun $run The refresh in progress.
	 * @param array $response Envelope from `doConditionalGet()` with
	 *                        keys `status`, `reason` and `body`.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}|null
	 *                                                                     The early result envelope, or null when the body should be parsed.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function rejectResponse(FeedRefreshRun $run, array $response): ?array {
		if ((int)$response['status'] === 304) {
			// Items untouched — only lastFetchedAt is updated
			// (REQ-FRJ-004 "items untouched, only lastFetchedAt updated").
			$this->sources->cacheMapper->update(entity: $run->row);
			return $run->result(status: 'not-modified', itemCount: $run->cachedItemCount());
		}

		$reason = self::responseFailureReason(response: $response);
		if ($reason === null) {
			return null;
		}

		return $this->recordFailure(run: $run, reason: $reason);
	}//end rejectResponse()

	/**
	 * Why a non-304 response cannot be parsed: a non-2xx status, or a body
	 * over the size cap (REQ-FRJ-005 design D3); null when it can.
	 *
	 * @param array $response Envelope from `doConditionalGet()`.
	 *
	 * @return string|null
	 */
	private static function responseFailureReason(array $response): ?string {
		$statusCode = (int)$response['status'];
		if ($statusCode < 200 || $statusCode >= 300) {
			return $statusCode . ' ' . $response['reason'];
		}

		if (strlen(string: $response['body']) > self::MAX_RESPONSE_SIZE) {
			return 'response too large';
		}

		return null;
	}//end responseFailureReason()

	/**
	 * Refresh all discovered feed URLs (or a single one when
	 * `$onlyUrl` is provided). Honours the per-tick wall-clock budget
	 * and the cursor-based batch state (REQ-FRJ-008).
	 *
	 * @param string|null $onlyUrl Optional single URL to refresh.
	 *
	 * @return array{processedCount: int, successCount: int, failureCount: int, durationMs: int}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function refreshAll(?string $onlyUrl = null): array {
		$startedAt = (int)(microtime(as_float: true) * 1000);

		$urls = $this->discoverFeedUrls();
		if ($onlyUrl !== null) {
			$urls = [$onlyUrl];
		}

		if (count(value: $urls) === 0) {
			$this->logger->info(
				message: 'No news widget placements found; nothing to refresh',
				context: ['app' => Application::APP_ID]
			);
			return self::tickSummary(tally: ['processed' => 0, 'success' => 0, 'failure' => 0], startedAt: $startedAt);
		}

		if ($onlyUrl !== null) {
			return self::tickSummary(tally: $this->refreshBatch(urls: $urls, startIndex: 0), startedAt: $startedAt);
		}

		return self::tickSummary(tally: $this->refreshFromCursor(urls: $urls), startedAt: $startedAt);
	}//end refreshAll()

	/**
	 * Run one tick of the full sweep: start after the persisted cursor,
	 * then advance or clear it (REQ-FRJ-008).
	 *
	 * @param array $urls The sorted, deduplicated feed URL set.
	 *
	 * @return array{processed: int, success: int, failure: int} This tick's tally.
	 */
	private function refreshFromCursor(array $urls): array {
		$startIndex = $this->resolveStartIndex(urls: $urls);
		$tally = $this->refreshBatch(urls: $urls, startIndex: $startIndex);
		$this->persistCursor(urls: $urls, startIndex: $startIndex, processed: $tally['processed']);

		return $tally;
	}//end refreshFromCursor()

	/**
	 * The refreshAll() summary of a tick's tally.
	 *
	 * @param array{processed: int, success: int, failure: int} $tally This tick's tally.
	 * @param int $startedAt Tick start time in epoch milliseconds.
	 *
	 * @return array{processedCount: int, successCount: int, failureCount: int, durationMs: int}
	 */
	private static function tickSummary(array $tally, int $startedAt): array {
		return [
			'processedCount' => $tally['processed'],
			'successCount' => $tally['success'],
			'failureCount' => $tally['failure'],
			'durationMs' => ((int)(microtime(as_float: true) * 1000) - $startedAt),
		];
	}//end tickSummary()

	/**
	 * Resolve the batch start offset from the persisted cursor.
	 *
	 * Resumes at the entry *after* the stored URL; a cursor no longer in
	 * the set is stale and restarts the sweep from 0 (REQ-FRJ-008).
	 *
	 * @param array $urls The sorted, deduplicated feed URL set.
	 *
	 * @return int Index into `$urls` to start this tick from.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function resolveStartIndex(array $urls): int {
		$cursor = $this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_KEY_CURSOR,
			''
		);
		if ($cursor === '') {
			return 0;
		}

		$cursorPos = array_search(needle: $cursor, haystack: $urls, strict: true);
		// Cursor stale (URL no longer in set) — restart.
		if ($cursorPos === false) {
			return 0;
		}

		return ((int)$cursorPos + 1);
	}//end resolveStartIndex()

	/**
	 * Refresh one tick's worth of feeds, starting at `$startIndex`.
	 *
	 * Stops at whichever bound is hit first: end of set, batch size, or
	 * wall-clock budget (REQ-FRJ-008). `ok` and `not-modified` count as
	 * successes; everything else counts as a failure.
	 *
	 * @param array $urls The sorted, deduplicated feed URL set.
	 * @param int $startIndex Index to start this tick from.
	 *
	 * @return array{processed: int, success: int, failure: int} This tick's tally.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function refreshBatch(array $urls, int $startIndex): array {
		$tally = ['processed' => 0, 'success' => 0, 'failure' => 0];

		$deadline = (microtime(as_float: true) + self::WALL_CLOCK_BUDGET);
		$batchLimit = ($startIndex + self::BATCH_SIZE);
		$urlCount = count(value: $urls);

		for ($index = $startIndex; $index < $urlCount; $index++) {
			if ($index >= $batchLimit || microtime(as_float: true) >= $deadline) {
				break;
			}

			$outcome = $this->refreshCounted(feedUrl: $urls[$index]);
			$tally['processed']++;
			$tally[$outcome]++;
		}

		return $tally;
	}//end refreshBatch()

	/**
	 * Refresh one feed and say how the tally counts it: `ok` and
	 * `not-modified` are a `success`, anything else — including an
	 * exception escaping refreshFeed() — a `failure`.
	 *
	 * @param string $feedUrl The feed URL.
	 *
	 * @return string `success` or `failure`.
	 */
	private function refreshCounted(string $feedUrl): string {
		try {
			$result = $this->refreshFeed(feedUrl: $feedUrl);
		} catch (Throwable $exception) {
			// RefreshFeed swallows all per-feed exceptions — this is
			// a defence-in-depth catch in case of a bug there.
			$this->logger->error(
				message: 'FeedRefreshService unexpected refreshFeed exception',
				context: [
					'app' => Application::APP_ID,
					'feedUrl' => $feedUrl,
					'exception' => $exception->getMessage(),
				]
			);
			return 'failure';
		}

		if ($result['status'] === 'ok' || $result['status'] === 'not-modified') {
			return 'success';
		}

		return 'failure';
	}//end refreshCounted()

	/**
	 * Advance or clear the sweep cursor after a tick.
	 *
	 * Cleared at the tail of the set so the next tick restarts from the
	 * top; otherwise set to the last URL processed (REQ-FRJ-008).
	 *
	 * @param array $urls The sorted, deduplicated feed URL set.
	 * @param int $startIndex Index this tick started from.
	 * @param int $processed Number of feeds processed this tick.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	private function persistCursor(
		array $urls,
		int $startIndex,
		int $processed,
	): void {
		$urlCount = count(value: $urls);
		$endIndex = ($startIndex + $processed);

		if ($endIndex >= $urlCount) {
			// Completed the full set — clear cursor.
			$this->appConfig->deleteKey(
				Application::APP_ID,
				self::CONFIG_KEY_CURSOR
			);
			return;
		}

		// More to do next tick — persist cursor at last URL processed.
		$lastIndex = ($endIndex - 1);
		if ($lastIndex >= 0 && $lastIndex < $urlCount) {
			$this->appConfig->setValueString(
				Application::APP_ID,
				self::CONFIG_KEY_CURSOR,
				$urls[$lastIndex]
			);
		}
	}//end persistCursor()

	/**
	 * Perform the conditional HTTP GET. Returns a normalised response
	 * dict with keys: status, reason, body, etag, lastModified.
	 *
	 * @param FeedCache $row The cache row (for ETag/Last-Modified).
	 * @param string $feedUrl The feed URL.
	 *
	 * @return array{status: int, reason: string, body: string, etag: ?string, lastModified: ?string}
	 */
	private function doConditionalGet(
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
	}//end doConditionalGet()

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
	 * Parse an RSS 2.0 / Atom 1.0 feed body and return normalised items
	 * sorted newest-first (REQ-FRJ-005).
	 *
	 * @param string $body The XML body.
	 * @param string $feedUrl The feed URL (for sourceUrl/sourceTitle).
	 *
	 * @return array<int, array<string, mixed>> The normalised items.
	 *
	 * @throws RuntimeException When parsing fails.
	 */
	private function parseFeedBody(string $body, string $feedUrl): array {
		$xml = $this->loadFeedXml(body: $body);
		$source = new FeedSource(url: $feedUrl, title: $this->extractFeedTitle(xml: $xml, feedUrl: $feedUrl));
		$items = $this->extractItems(xml: $xml, source: $source);

		// Sort newest-first by pubDate.
		usort(
			array: $items,
			callback: static fn (array $left, array $right): int => self::compareByPubDate(
				left: $left,
				right: $right
			)
		);

		return $items;
	}//end parseFeedBody()

	/**
	 * Parse the raw XML body, converting libxml's error channel into an
	 * exception.
	 *
	 * C2: LIBXML_NOENT is deliberately NOT passed — it resolves (not
	 * disables) entities, enabling XXE; LIBXML_NONET blocks external
	 * DTD/entity fetches. L1: LIBXML_NOCDATA is deliberately NOT passed —
	 * it folds CDATA into text nodes, masking downstream sanitisation.
	 *
	 * @param string $body The raw XML body.
	 *
	 * @return SimpleXMLElement The parsed document.
	 *
	 * @throws RuntimeException When the body is not well-formed XML; the
	 *                          message carries the first libxml error.
	 */
	private function loadFeedXml(string $body): SimpleXMLElement {
		$previous = libxml_use_internal_errors(use_errors: true);
		try {
			$xml = simplexml_load_string(
				data: $body,
				class_name: 'SimpleXMLElement',
				options: LIBXML_NONET
			);
			if ($xml === false) {
				$errors = libxml_get_errors();
				libxml_clear_errors();
				$message = 'malformed XML';
				if (isset($errors[0]) === true) {
					$message = trim(string: $errors[0]->message);
				}

				throw new RuntimeException(message: $message);
			}
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors(use_errors: $previous);
		}//end try

		return $xml;
	}//end loadFeedXml()

	/**
	 * Collect the normalised entries from an RSS 2.0 or Atom 1.0 document.
	 *
	 * RSS is probed first; a document carrying neither shape yields an
	 * empty list, so an empty feed is not a parse failure.
	 *
	 * @param SimpleXMLElement $xml The parsed feed document.
	 * @param FeedSource $source The feed URL and resolved channel/feed title.
	 *
	 * @return array<int, array<string, mixed>> Normalised items, unsorted.
	 */
	private function extractItems(SimpleXMLElement $xml, FeedSource $source): array {
		$items = [];

		// RSS 2.0 — <channel><item>...
		if (isset($xml->channel) === true && isset($xml->channel->item) === true) {
			foreach ($xml->channel->item as $item) {
				$items[] = $this->normaliseRssItem(item: $item, source: $source);
			}
		} elseif (isset($xml->entry) === true) {
			// Atom 1.0 — <feed><entry>...
			foreach ($xml->entry as $entry) {
				$items[] = $this->normaliseAtomEntry(entry: $entry, source: $source);
			}
		}

		return $items;
	}//end extractItems()

	/**
	 * Newest-first comparator over the normalised `pubDate` field.
	 *
	 * A missing or unparseable date sorts as epoch 0, i.e. last.
	 *
	 * @param array $left Left-hand normalised item.
	 * @param array $right Right-hand normalised item.
	 *
	 * @return int Negative, zero or positive per the usort contract.
	 */
	private static function compareByPubDate(array $left, array $right): int {
		$leftRaw = strtotime(datetime: (string)($left['pubDate'] ?? ''));
		$rightRaw = strtotime(datetime: (string)($right['pubDate'] ?? ''));
		$leftTs = 0;
		if ($leftRaw !== false) {
			$leftTs = $leftRaw;
		}

		$rightTs = 0;
		if ($rightRaw !== false) {
			$rightTs = $rightRaw;
		}

		return ($rightTs <=> $leftTs);
	}//end compareByPubDate()

	/**
	 * Extract the channel-level title (RSS) or feed-level title (Atom),
	 * falling back to the URL hostname.
	 *
	 * @param SimpleXMLElement $xml The parsed feed.
	 * @param string $feedUrl The feed URL.
	 *
	 * @return string The source title.
	 */
	private function extractFeedTitle(
		SimpleXMLElement $xml,
		string $feedUrl,
	): string {
		$title = self::declaredTitle(xml: $xml);
		if ($title !== '') {
			return $title;
		}

		$host = (string)parse_url(url: $feedUrl, component: PHP_URL_HOST);
		if ($host !== '') {
			return $host;
		}

		return $feedUrl;
	}//end extractFeedTitle()

	/**
	 * The trimmed channel title (RSS), else the trimmed feed title (Atom),
	 * or '' when neither is present and non-blank.
	 *
	 * @param SimpleXMLElement $xml The parsed feed.
	 *
	 * @return string
	 */
	private static function declaredTitle(SimpleXMLElement $xml): string {
		if (isset($xml->channel) === true && isset($xml->channel->title) === true) {
			$title = trim(string: (string)$xml->channel->title);
			if ($title !== '') {
				return $title;
			}
		}

		if (isset($xml->title) === true) {
			return trim(string: (string)$xml->title);
		}

		return '';
	}//end declaredTitle()

	/**
	 * Normalise a single RSS 2.0 item to the news-widget schema.
	 *
	 * @param SimpleXMLElement $item The RSS item element.
	 * @param FeedSource $source The feed URL and channel title.
	 *
	 * @return array<string, mixed>
	 */
	private function normaliseRssItem(SimpleXMLElement $item, FeedSource $source): array {
		return self::itemRecord(
			fields: [
				'id' => trim(string: (string)($item->guid ?? '')),
				'title' => trim(string: (string)($item->title ?? '')),
				'summary' => trim(string: (string)($item->description ?? '')),
				'link' => trim(string: (string)($item->link ?? '')),
				'pubDate' => trim(string: (string)($item->pubDate ?? '')),
				'thumbnailUrl' => self::rssThumbnail(item: $item),
			],
			source: $source
		);
	}//end normaliseRssItem()

	/**
	 * The URL of an RSS item's enclosure when its type is `image/*`, else null.
	 *
	 * @param SimpleXMLElement $item The RSS item element.
	 *
	 * @return string|null
	 */
	private static function rssThumbnail(SimpleXMLElement $item): ?string {
		if (isset($item->enclosure) === false) {
			return null;
		}

		$attrs = $item->enclosure->attributes();
		if ($attrs === null || isset($attrs['type']) === false) {
			return null;
		}

		if (str_starts_with(haystack: (string)$attrs['type'], needle: 'image/') === true) {
			return (string)$attrs['url'];
		}

		return null;
	}//end rssThumbnail()

	/**
	 * Normalise a single Atom 1.0 entry to the news-widget schema.
	 *
	 * @param SimpleXMLElement $entry The Atom entry element.
	 * @param FeedSource $source The feed URL and feed title.
	 *
	 * @return array<string, mixed>
	 */
	private function normaliseAtomEntry(SimpleXMLElement $entry, FeedSource $source): array {
		return self::itemRecord(
			fields: [
				'id' => trim(string: (string)($entry->id ?? '')),
				'title' => trim(string: (string)($entry->title ?? '')),
				'summary' => self::firstNonBlank(node: $entry, names: ['summary', 'content']),
				'link' => self::atomAlternateLink(entry: $entry),
				'pubDate' => self::firstNonBlank(node: $entry, names: ['published', 'updated']),
				'thumbnailUrl' => null,
			],
			source: $source
		);
	}//end normaliseAtomEntry()

	/**
	 * The first child, out of `$names`, whose trimmed text is not empty;
	 * '' when none is.
	 *
	 * @param SimpleXMLElement $node Parent element.
	 * @param string[] $names Child names, tried in order.
	 *
	 * @return string
	 */
	private static function firstNonBlank(SimpleXMLElement $node, array $names): string {
		foreach ($names as $name) {
			$text = trim(string: (string)($node->$name ?? ''));
			if ($text !== '') {
				return $text;
			}
		}

		return '';
	}//end firstNonBlank()

	/**
	 * The href of an Atom entry's first non-empty `alternate` link (a link
	 * without `rel` counts as alternate), or '' without one.
	 *
	 * @param SimpleXMLElement $entry The Atom entry element.
	 *
	 * @return string
	 */
	private static function atomAlternateLink(SimpleXMLElement $entry): string {
		if (isset($entry->link) === false) {
			return '';
		}

		foreach ($entry->link as $linkElement) {
			$attrs = $linkElement->attributes();
			if ($attrs === null) {
				continue;
			}

			$rel = (string)($attrs['rel'] ?? 'alternate');
			$href = (string)($attrs['href'] ?? '');
			if ($rel === 'alternate' && $href !== '') {
				return $href;
			}
		}

		return '';
	}//end atomAlternateLink()

	/**
	 * Complete the fields read from one entry into the news-widget item:
	 * the entry id, or `sha256(title . pubDate . feedUrl)` when it has
	 * none, plus the feed source.
	 *
	 * @param array<string, string|null> $fields Fields read from the entry: id, title, summary, link, pubDate
	 *                                           (strings) and thumbnailUrl (string or null).
	 * @param FeedSource $source The feed URL and title.
	 *
	 * @return array<string, mixed>
	 */
	private static function itemRecord(array $fields, FeedSource $source): array {
		$guid = $fields['id'];
		if ($guid === '') {
			$guid = hash(algo: 'sha256', data: ($fields['title'] . $fields['pubDate'] . $source->url));
		}

		return [
			'guid' => $guid,
			'title' => $fields['title'],
			'summary' => $fields['summary'],
			'link' => $fields['link'],
			'pubDate' => $fields['pubDate'],
			'sourceUrl' => $source->url,
			'sourceTitle' => $source->title,
			'thumbnailUrl' => $fields['thumbnailUrl'],
		];
	}//end itemRecord()

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
	 * Whether the URL's host matches the admin allow-list.
	 *
	 * Empty allow-list (default) accepts all hosts.
	 *
	 * @param string $feedUrl The feed URL.
	 *
	 * @return bool True if the host is allowed.
	 */
	private function isHostAllowed(string $feedUrl): bool {
		$allowedRaw = $this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_KEY_ALLOWED_HOSTS,
			''
		);
		if ($allowedRaw === '') {
			return true;
		}

		$host = strtolower(
			string: (string)parse_url(url: $feedUrl, component: PHP_URL_HOST)
		);
		if ($host === '') {
			return false;
		}

		$allowed = array_map(
			callback: static fn (string $entry): string => strtolower(
				string: trim(string: $entry)
			),
			array: explode(separator: ',', string: $allowedRaw)
		);

		return in_array(needle: $host, haystack: $allowed, strict: true);
	}//end isHostAllowed()

	/**
	 * Map a transport-layer exception to a stable failure-reason string
	 * (REQ-FRJ-006). The string MUST be parseable / matchable by
	 * monitoring tooling — keeping the prefix taxonomy stable.
	 *
	 * @param Throwable $exception The caught exception.
	 *
	 * @return string The failure reason.
	 */
	private function classifyTransportError(Throwable $exception): string {
		$message = $exception->getMessage();
		$needle = strtolower(string: $message);
		if (str_contains(haystack: $needle, needle: 'timeout') === true
			|| str_contains(haystack: $needle, needle: 'timed out') === true
		) {
			return 'timeout: ' . $message;
		}

		return 'transport error: ' . $message;
	}//end classifyTransportError()

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
