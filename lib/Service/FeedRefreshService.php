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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
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
		$run = new FeedRefreshRun(mapper: $this->sources->cacheMapper, feedUrl: $feedUrl);

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
			$response = (new ConditionalFeedRequest(sources: $this->sources, appConfig: $this->appConfig))->send(
				row: $run->row,
				feedUrl: $run->feedUrl
			);
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
	 * @param array $response Envelope from `ConditionalFeedRequest::send()`.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 */
	private function storeItems(FeedRefreshRun $run, array $response): array {
		try {
			$items = (new FeedItemParser())->parse(
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
	 * @param array $response Envelope from `ConditionalFeedRequest::send()`.
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
	 * @param array $response Envelope from `ConditionalFeedRequest::send()` with
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
	 * @param array $response Envelope from `ConditionalFeedRequest::send()`.
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

}//end class
