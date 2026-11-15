<?php

/**
 * NewsWidgetService
 *
 * Fetches, parses, sanitises, and merges RSS / Atom feed items for the
 * LaunchPad news widget (REQ-NEWS-001..011). The `background-job-feed-refresh`
 * sibling change is not yet on this branch — per REQ-NEWS-004 the widget
 * gracefully degrades to synchronous on-demand fetches and caches the
 * raw feed payload via Nextcloud's `ICache` for `news_widget_feed_cache_ttl_seconds`
 * (default 3600s).
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

use DOMDocument;
use DOMNode;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Throwable;

/**
 * Service for fetching, parsing, merging, deduplicating, sanitising, and
 * filtering RSS / Atom feed items for the news widget.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Combines feed
 *     fetching, parsing (RSS + Atom), allow-listing, sanitisation,
 *     deduplication, sorting, caching and graceful-degradation logic in
 *     one cohesive unit. Splitting into multiple services would
 *     fragment the REQ-NEWS-001..011 contract across files without
 *     reducing the surface that the news-widget capability has to
 *     present to callers.
 */
class NewsWidgetService {

	/**
	 * Default per-feed cache TTL in seconds (60 minutes). Overridden at
	 * runtime by the app-config key
	 * `launchpad.news_widget_feed_cache_ttl_seconds`.
	 */
	private const DEFAULT_CACHE_TTL = 3600;

	/**
	 * Hard ceiling on the per-request item count. Server-side cap that
	 * enforces a safety rail regardless of client-supplied `limit` —
	 * see design D4 in `openspec/changes/news-widget/design.md`.
	 */
	private const HARD_ITEM_CEILING = 200;

	/**
	 * Maximum accepted feed response size in bytes (1 MB). Rejects
	 * oversized payloads before they reach the XML parser (C1 SSRF
	 * DoS guard — REQ-NEWS-008).
	 */
	private const MAX_RESPONSE_SIZE_BYTES = 1048576;

	/**
	 * IAppConfig key for the JSON-encoded allow-list of feed hostnames.
	 */
	private const CONFIG_KEY_ALLOWED_HOSTS = 'news_widget_allowed_feed_hosts';

	/**
	 * HTML tags retained by the summary sanitiser (REQ-NEWS-005).
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_SUMMARY_TAGS = [
		'p',
		'a',
		'strong',
		'em',
		'br',
		'ul',
		'ol',
		'li',
	];

	/**
	 * Per-feed HTTP fetch timeout in seconds (REQ-NEWS-008).
	 */
	private const FETCH_TIMEOUT_SECONDS = 10;

	/**
	 * Default of each boolean config field (REQ-NEWS-002).
	 *
	 * @var array<string, bool>
	 */
	private const BOOL_FIELD_DEFAULTS = [
		'showThumbnails' => true,
		'showSummary' => true,
	];

	/**
	 * Default, lower bound and upper bound of each integer config field
	 * (REQ-NEWS-002).
	 *
	 * @var array<string, array{0: int, 1: int, 2: int}>
	 */
	private const INT_FIELD_RANGES = [
		'itemLimit' => [10, 1, 50],
		'summaryMaxChars' => [200, 0, 5000],
	];

	/**
	 * HTTP client options of a live feed fetch.
	 *
	 * C1/H3: redirect-following is disabled so an attacker cannot chain a
	 * public URL to an internal redirect target (SSRF via open redirect).
	 *
	 * @var array<string, int|bool>
	 */
	private const FETCH_OPTIONS = [
		'timeout' => self::FETCH_TIMEOUT_SECONDS,
		'connect_timeout' => self::FETCH_TIMEOUT_SECONDS,
		'verify' => true,
		'allow_redirects' => false,
	];

	/**
	 * Lazily resolved {@see ICache} backing the per-feed payload cache.
	 * Created on first use so unit tests can construct the service
	 * with a stub `ICacheFactory` that never gets called.
	 *
	 * @var ICache|null
	 */
	private ?ICache $cache = null;

	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placementMapper Resolves placements by id.
	 * @param FeedFetchTools        $fetch The HTTP client service, cache factory, URL validator and
	 *                                     connection reporter.
	 * @param IAppConfig            $appConfig Reads admin settings: `news_widget_feed_cache_ttl_seconds` and
	 *                                         `news_widget_allowed_feed_hosts`.
	 * @param LoggerInterface       $logger PSR logger.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly FeedFetchTools $fetch,
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Resolve a placement and return the merged + sanitised feed items
	 * for it. Honours the placement's metadata filter — when the filter
	 * does not match, an empty array is returned without performing any
	 * HTTP fetch (REQ-NEWS-007).
	 *
	 * @param integer $placementId Placement entity id.
	 * @param integer $limit Max items to return; clamped to
	 *                       [1, HARD_ITEM_CEILING].
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     feedsFailed: int,
	 *     failedUrls: array<int, string>
	 * }
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function getItemsForPlacement(int $placementId, int $limit = 10): array {
		$clamped = $this->clampLimit(limit: $limit);
		$placement = $this->findPlacement(placementId: $placementId);
		if ($placement === null) {
			return $this->emptyResponse();
		}

		$config = $this->extractNewsConfig(placement: $placement);

		// REQ-NEWS-007: if a metadata filter is configured and does not
		// match the dashboard, short-circuit before any feed fetch.
		if ($config['metadataFilter'] !== null
			&& $this->checkMetadataFilter(
				dashboardId: (int)$placement->getDashboardId(),
				metadataFilter: $config['metadataFilter']
			) === false
		) {
			return $this->emptyResponse();
		}

		return $this->fetchAndMergeFeeds(feedUrls: $config['feedUrls'], limit: $clamped);
	}//end getItemsForPlacement()

	/**
	 * The placement with this id, or null when it does not exist or cannot
	 * be read (logged).
	 *
	 * @param integer $placementId Placement entity id.
	 *
	 * @return WidgetPlacement|null
	 */
	private function findPlacement(int $placementId): ?WidgetPlacement {
		try {
			return $this->placementMapper->find(id: $placementId);
		} catch (DoesNotExistException $e) {
			return null;
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'NewsWidget: failed to resolve placement ' . $placementId,
				context: ['exception' => $e]
			);
			return null;
		}
	}//end findPlacement()

	/**
	 * Parse the placement's persisted JSON content into the canonical
	 * news config shape, applying defaults where fields are missing or
	 * malformed (REQ-NEWS-002).
	 *
	 * @param WidgetPlacement $placement The placement whose
	 *                                   `styleConfig` JSON column carries
	 *                                   the news widget configuration.
	 *
	 * @return array{
	 *     feedUrls: array<int, string>,
	 *     layout: string,
	 *     itemLimit: int,
	 *     showThumbnails: bool,
	 *     showSummary: bool,
	 *     summaryMaxChars: int,
	 *     dateFormat: string,
	 *     metadataFilter: array{fieldKey: string, value: string}|null
	 * }
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function extractNewsConfig(WidgetPlacement $placement): array {
		// The unified Add-Widget modal stores per-type config (feedUrls, …) in
		// the `content` column; `style_config` only carries chrome
		// (background/title). Read `content` first, falling back to
		// `style_config` for any legacy placement that stored config there.
		$decoded = $this->readConfigBlob(placement: $placement);

		return [
			'feedUrls' => $this->extractFeedUrls(decoded: $decoded),
			'layout' => $this->extractLayout(decoded: $decoded),
			'itemLimit' => $this->extractIntInRange(decoded: $decoded, key: 'itemLimit'),
			'showThumbnails' => $this->extractBool(decoded: $decoded, key: 'showThumbnails'),
			'showSummary' => $this->extractBool(decoded: $decoded, key: 'showSummary'),
			'summaryMaxChars' => $this->extractIntInRange(decoded: $decoded, key: 'summaryMaxChars'),
			'dateFormat' => $this->extractDateFormat(decoded: $decoded),
			'metadataFilter' => $this->extractMetadataFilter(decoded: $decoded),
		];
	}//end extractNewsConfig()

	/**
	 * Decoded config of a placement: its `content` column, or its legacy
	 * `style_config` column when `content` holds no feed URLs and the
	 * legacy column is not empty.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return array<string, mixed> Decoded content map (empty when invalid).
	 */
	private function readConfigBlob(WidgetPlacement $placement): array {
		$decoded = $this->decodeStyleConfigBlob(raw: $placement->getContent());
		if ($decoded !== [] && $this->extractFeedUrls(decoded: $decoded) !== []) {
			return $decoded;
		}

		$legacy = $this->decodeStyleConfigBlob(raw: $placement->getStyleConfig());
		if ($legacy !== []) {
			return $legacy;
		}

		return $decoded;
	}//end readConfigBlob()

	/**
	 * Parse the widget's persisted JSON blob and unwrap the outer
	 * `{type, content}` envelope when present so callers always get
	 * the flat content map.
	 *
	 * @param string|null $raw Raw JSON string from the placement.
	 *
	 * @return array<string, mixed> Decoded content map (empty when invalid).
	 */
	private function decodeStyleConfigBlob(?string $raw): array {
		if (is_string(value: $raw) === false || $raw === '') {
			return [];
		}

		$parsed = json_decode(json: $raw, associative: true);
		if (is_array(value: $parsed) === false) {
			return [];
		}

		if (isset($parsed['content']) === true && is_array(value: $parsed['content']) === true) {
			return $parsed['content'];
		}

		return $parsed;
	}//end decodeStyleConfigBlob()

	/**
	 * Extract the configured feed URL list, dropping non-HTTP(S) entries.
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 *
	 * @return array<int, string>
	 */
	private function extractFeedUrls(array $decoded): array {
		$candidates = $decoded['feedUrls'] ?? null;
		if (is_array(value: $candidates) === false) {
			return [];
		}

		$out = [];
		foreach ($candidates as $candidate) {
			if (self::isHttpsUrl(candidate: $candidate) === true) {
				$out[] = $candidate;
			}
		}

		return $out;
	}//end extractFeedUrls()

	/**
	 * Whether a configured feed URL is a non-empty string starting with
	 * `https://` (any case).
	 *
	 * C1: accept HTTPS only — plain HTTP leaks feed content in transit and
	 * bypasses the SSRF guard's scheme check.
	 *
	 * @param mixed $candidate Configured entry.
	 *
	 * @return boolean
	 */
	private static function isHttpsUrl(mixed $candidate): bool {
		if (is_string(value: $candidate) === false || $candidate === '') {
			return false;
		}

		$lower = strtolower(string: $candidate);
		return str_starts_with(haystack: $lower, needle: 'https://');
	}//end isHttpsUrl()

	/**
	 * Extract the layout enum (`list` | `grid` | `carousel`).
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 *
	 * @return string
	 */
	private function extractLayout(array $decoded): string {
		$value = $decoded['layout'] ?? null;
		if (is_string(value: $value) === true
			&& in_array(needle: $value, haystack: ['list', 'grid', 'carousel'], strict: true) === true
		) {
			return $value;
		}

		return 'list';
	}//end extractLayout()

	/**
	 * Extract a clamped int field; its default and range come from
	 * INT_FIELD_RANGES.
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 * @param string $key Field key (a key of INT_FIELD_RANGES).
	 *
	 * @return integer
	 */
	private function extractIntInRange(array $decoded, string $key): int {
		[$default, $min, $max] = self::INT_FIELD_RANGES[$key];
		$value = $decoded[$key] ?? null;
		if (is_int(value: $value) === false) {
			return $default;
		}

		return max($min, min($max, $value));
	}//end extractIntInRange()

	/**
	 * Extract a boolean field; its default (used when the key is missing)
	 * comes from BOOL_FIELD_DEFAULTS.
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 * @param string $key Field key (a key of BOOL_FIELD_DEFAULTS).
	 *
	 * @return boolean
	 */
	private function extractBool(array $decoded, string $key): bool {
		if (array_key_exists(key: $key, array: $decoded) === false) {
			return self::BOOL_FIELD_DEFAULTS[$key];
		}

		return $decoded[$key] === true;
	}//end extractBool()

	/**
	 * Extract the date-format enum, defaulting to `relative`.
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 *
	 * @return string `relative` or `absolute`.
	 */
	private function extractDateFormat(array $decoded): string {
		if (($decoded['dateFormat'] ?? null) === 'absolute') {
			return 'absolute';
		}

		return 'relative';
	}//end extractDateFormat()

	/**
	 * Extract the optional metadata filter `{fieldKey, value}` shape.
	 *
	 * @param array<string, mixed> $decoded Decoded config map.
	 *
	 * @return array{fieldKey: string, value: string}|null
	 */
	private function extractMetadataFilter(array $decoded): ?array {
		$filter = $decoded['metadataFilter'] ?? null;
		if (is_array(value: $filter) === false) {
			return null;
		}

		$key = $filter['fieldKey'] ?? null;
		$val = $filter['value'] ?? null;
		if (is_string(value: $key) === false || $key === '' || is_string(value: $val) === false) {
			return null;
		}

		return ['fieldKey' => $key, 'value' => $val];
	}//end extractMetadataFilter()

	/**
	 * Fetch each URL (cache-first), parse, merge, deduplicate, sort, and
	 * cap at $limit items. Failures of individual URLs are tolerated;
	 * the response carries a count + list of failed URLs so the UI can
	 * surface a corner badge (REQ-NEWS-008 / REQ-NEWS-010).
	 *
	 * @param array<int, string> $feedUrls List of feed URLs (already
	 *                                     sanitised against http(s) by
	 *                                     {@see extractNewsConfig()}).
	 * @param integer $limit Hard cap on returned item count
	 *                       (already clamped).
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     feedsFailed: int,
	 *     failedUrls: array<int, string>
	 * }
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function fetchAndMergeFeeds(array $feedUrls, int $limit = 10): array {
		if ($feedUrls === []) {
			return $this->emptyResponse();
		}

		$collected = $this->collectFeedItems(feedUrls: $feedUrls);

		return [
			'items' => $this->rankItems(items: $collected['items'], limit: $limit),
			'feedsFailed' => count(value: $collected['failedUrls']),
			// No array_values(): failedUrls is already a list.
			'failedUrls' => $collected['failedUrls'],
		];
	}//end fetchAndMergeFeeds()

	/**
	 * Fetch and parse every feed URL in order, tolerating failures.
	 *
	 * @param array<int, string> $feedUrls List of feed URLs.
	 *
	 * @return array{items: array<int, array<string, mixed>>, failedUrls: array<int, string>}
	 */
	private function collectFeedItems(array $feedUrls): array {
		$allItems = [];
		$failedUrls = [];
		foreach ($feedUrls as $url) {
			$parsed = $this->fetchFeedItems(url: $url);
			if ($parsed === null) {
				$failedUrls[] = $url;
				continue;
			}

			array_push($allItems, ...$parsed);
		}

		return ['items' => $allItems, 'failedUrls' => $failedUrls];
	}//end collectFeedItems()

	/**
	 * Deduplicate, sort newest first and cap the merged items.
	 *
	 * @param array<int, array<string, mixed>> $items Merged items.
	 * @param integer $limit Hard cap on returned item count.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rankItems(array $items, int $limit): array {
		$deduped = $this->deduplicateItems(items: $items);
		$sorted = $this->sortItemsByDate(items: $deduped);

		return array_slice(array: $sorted, offset: 0, length: $limit);
	}//end rankItems()

	/**
	 * Guard, fetch and parse one feed URL.
	 *
	 * @param string $url Feed URL.
	 *
	 * @return array<int, array<string, mixed>>|null Parsed items, or null
	 *                                               when the feed failed.
	 */
	private function fetchFeedItems(string $url): ?array {
		if ($this->isFeedUrlAccepted(url: $url) === false) {
			return null;
		}

		$payload = $this->fetchFeedPayload(url: $url);
		if ($payload === null) {
			return null;
		}

		try {
			return $this->parseRssFeed(feedContent: $payload, sourceUrl: $url, sourceTitle: $url);
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'NewsWidget: feed parse failed for ' . $url,
				context: ['exception' => $e]
			);
			return null;
		}
	}//end fetchFeedItems()

	/**
	 * Whether a feed URL passes the SSRF guard and the admin allow-list,
	 * logging the reason when it does not.
	 *
	 * C1: SSRF guard — reject non-HTTPS, private IPs, and any host not in
	 * the admin allow-list (default-deny when list is non-empty; open when
	 * list is empty per REQ-NEWS-006).
	 *
	 * @param string $url Feed URL.
	 *
	 * @return boolean True when the URL may be fetched.
	 */
	private function isFeedUrlAccepted(string $url): bool {
		if ($this->fetch->urlValidator->isSafe(url: $url) === false) {
			$this->logger->warning(
				message: 'NewsWidget: URL rejected by SSRF guard',
				context: ['url' => $url]
			);
			return false;
		}

		if ($this->checkAllowList(url: $url) === false) {
			$this->logger->warning(
				message: 'NewsWidget: URL skipped by allow-list',
				context: ['url' => $url]
			);
			return false;
		}

		return true;
	}//end isFeedUrlAccepted()

	/**
	 * Parse a raw feed payload (RSS 2.0 or Atom 1.0) into the canonical
	 * item shape. Items without a guid receive a synthetic
	 * `sha1(title|pubDate|sourceUrl)` identifier (REQ-NEWS-003).
	 *
	 * @param string $feedContent Raw XML payload.
	 * @param string $sourceUrl URL the payload was fetched from.
	 * @param string $sourceTitle Display name for the feed; replaced by
	 *                            the channel/feed title when present.
	 *
	 * @return array<int, array<string, mixed>> Parsed items.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function parseRssFeed(string $feedContent, string $sourceUrl, string $sourceTitle): array {
		if (trim(string: $feedContent) === '') {
			return [];
		}

		$xml = $this->loadFeedXml(feedContent: $feedContent, sourceUrl: $sourceUrl);
		if ($xml === null) {
			return [];
		}

		$source = new FeedSource(url: $sourceUrl, title: $sourceTitle);

		// Atom 1.0: <feed><entry/>…</feed>.
		if ($xml->getName() === 'feed') {
			return $this->normaliseAtomFeed(feed: $xml, source: $source->titledBy(node: $xml));
		}

		return $this->normaliseRssFeed(xml: $xml, fallback: $source);
	}//end parseRssFeed()

	/**
	 * Convert every `<entry>` of an Atom feed into the canonical item shape.
	 *
	 * @param SimpleXMLElement $feed The Atom `<feed>` root.
	 * @param FeedSource $source Feed URL and resolved display name.
	 *
	 * @return array<int, array<string, mixed>> Parsed items.
	 */
	private function normaliseAtomFeed(SimpleXMLElement $feed, FeedSource $source): array {
		$items = [];
		foreach ($feed->entry as $entry) {
			$items[] = $this->normaliseAtomEntry(entry: $entry, source: $source);
		}

		return $items;
	}//end normaliseAtomFeed()

	/**
	 * Convert every `<item>` of an RSS 2.0 channel into the canonical item
	 * shape, accepting the bare `<channel>` root some publishers ship.
	 *
	 * @param SimpleXMLElement $xml The parsed feed document.
	 * @param FeedSource $fallback Feed URL and the display name used when
	 *                             the channel has no title.
	 *
	 * @return array<int, array<string, mixed>> Parsed items, empty for an
	 *                                          unsupported root.
	 */
	private function normaliseRssFeed(SimpleXMLElement $xml, FeedSource $fallback): array {
		$rootName = $xml->getName();
		$channel = self::resolveRssChannel(xml: $xml, rootName: $rootName);
		if ($channel === null) {
			$this->logger->info(
				message: 'NewsWidget: unsupported feed root <' . $rootName . '> for ' . $fallback->url
			);
			return [];
		}

		$source = $fallback->titledBy(node: $channel);
		$items = [];
		foreach ($channel->item as $item) {
			$items[] = $this->normaliseRssItem(item: $item, source: $source);
		}

		return $items;
	}//end normaliseRssFeed()

	/**
	 * Parse a raw feed payload, logging and returning null when the XML is
	 * not well-formed so the caller can skip the feed.
	 *
	 * C2: LIBXML_NOENT is deliberately NOT passed — it resolves (not
	 * disables) entities, enabling XXE. LIBXML_NONET blocks external
	 * DTD/entity fetches.
	 *
	 * @param string $feedContent Raw XML payload.
	 * @param string $sourceUrl URL the payload came from (for logging).
	 *
	 * @return SimpleXMLElement|null Parsed document, or null when malformed.
	 */
	private function loadFeedXml(string $feedContent, string $sourceUrl): ?SimpleXMLElement {
		$previousErrors = libxml_use_internal_errors(use_errors: true);
		$xml = simplexml_load_string(
			data: $feedContent,
			class_name: SimpleXMLElement::class,
			options: LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors(use_errors: $previousErrors);

		if ($xml === false) {
			$this->logger->info(
				message: 'NewsWidget: could not parse feed XML for ' . $sourceUrl
			);
			return null;
		}

		return $xml;
	}//end loadFeedXml()

	/**
	 * Locate the RSS `<channel>` node for either supported RSS shape.
	 *
	 * Accepts the standard `<rss><channel>` nesting and the bare
	 * `<channel>` root some publishers ship without an `<rss>` wrapper.
	 *
	 * @param SimpleXMLElement $xml The parsed feed document.
	 * @param string $rootName Name of the document root element.
	 *
	 * @return SimpleXMLElement|null The channel node, or null when the
	 *                               root is not a supported RSS shape.
	 */
	private static function resolveRssChannel(SimpleXMLElement $xml, string $rootName): ?SimpleXMLElement {
		if ($rootName === 'rss' && isset($xml->channel) === true) {
			return $xml->channel;
		}

		if ($rootName === 'channel') {
			return $xml;
		}

		return null;
	}//end resolveRssChannel()

	/**
	 * Deduplicate parsed items by `guid`, keeping the first occurrence
	 * (REQ-NEWS-003).
	 *
	 * @param array<int, array<string, mixed>> $items Items to dedupe.
	 *
	 * @return array<int, array<string, mixed>> Deduped items.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function deduplicateItems(array $items): array {
		$seen = [];
		$out = [];
		foreach ($items as $item) {
			// Items missing a guid bypass the dedupe map but still flow
			// through so we don't lose them; the synthetic guid in
			// normaliseRssItem / normaliseAtomEntry ensures this path is
			// rarely hit.
			$guid = self::itemGuid(item: $item);
			if ($guid !== '' && isset($seen[$guid]) === true) {
				continue;
			}

			if ($guid !== '') {
				$seen[$guid] = true;
			}

			$out[] = $item;
		}

		return $out;
	}//end deduplicateItems()

	/**
	 * An item's guid as a string, or '' when it has none.
	 *
	 * @param array<string, mixed> $item Item.
	 *
	 * @return string
	 */
	private static function itemGuid(array $item): string {
		if (isset($item['guid']) === true) {
			return (string)$item['guid'];
		}

		return '';
	}//end itemGuid()

	/**
	 * Sort items by ISO 8601 `pubDate`, descending (newest first).
	 * Items with an invalid or missing pubDate sink to the end, in
	 * input order, so the visible feed never silently drops them.
	 *
	 * @param array<int, array<string, mixed>> $items Items to sort.
	 *
	 * @return array<int, array<string, mixed>> Sorted items.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function sortItemsByDate(array $items): array {
		$sortable = $items;
		usort(array: $sortable, callback: self::compareByPubDateDesc(...));

		return $sortable;
	}//end sortItemsByDate()

	/**
	 * Order two items newest first; items without a parseable pubDate
	 * sink below dated ones and keep their relative order.
	 *
	 * @param array<string, mixed> $a First item.
	 * @param array<string, mixed> $b Second item.
	 *
	 * @return integer
	 */
	private static function compareByPubDateDesc(array $a, array $b): int {
		$aTs = self::pubDateTimestamp(item: $a);
		$bTs = self::pubDateTimestamp(item: $b);
		if ($aTs === false && $bTs === false) {
			return 0;
		}

		if ($aTs === false) {
			return 1;
		}

		if ($bTs === false) {
			return -1;
		}

		return ($bTs <=> $aTs);
	}//end compareByPubDateDesc()

	/**
	 * Unix timestamp of an item's pubDate, or false when absent or unparseable.
	 *
	 * @param array<string, mixed> $item Item.
	 *
	 * @return integer|false
	 */
	private static function pubDateTimestamp(array $item): int|false {
		if (isset($item['pubDate']) === false) {
			return false;
		}

		return strtotime(datetime: (string)$item['pubDate']);
	}//end pubDateTimestamp()

	/**
	 * Allow-list the small set of inline tags safe to render inside a
	 * feed item summary, strip everything else, and force `rel` on
	 * surviving anchor tags (REQ-NEWS-005).
	 *
	 * @param string $html Raw summary HTML from a feed item.
	 *
	 * @return string Sanitised summary HTML.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function sanitiseSummaryHtml(string $html): string {
		if ($html === '') {
			return '';
		}

		$allowed = '<' . implode(separator: '><', array: self::ALLOWED_SUMMARY_TAGS) . '>';
		$stripped = strip_tags(string: $html, allowed_tags: $allowed);

		// After tag stripping, any `javascript:` href that survives must
		// be neutralised; reuse PHP's HTML parser via DOMDocument so we
		// can rewrite href attributes safely without false-positives on
		// body text containing the substring.
		$document = self::loadSummaryDocument(html: $stripped);
		self::neutraliseAnchors(document: $document);

		// Re-serialise just the wrapper's children so we don't leak
		// <html><body> from DOMDocument's auto-wrapping.
		$body = $document->getElementsByTagName(qualifiedName: 'div')->item(index: 0);
		if ($body === null) {
			return $stripped;
		}

		return self::serialiseChildren(document: $document, parent: $body);
	}//end sanitiseSummaryHtml()

	/**
	 * Parse stripped summary HTML inside a `<div>` wrapper, silencing
	 * libxml's warnings for the duration of the parse.
	 *
	 * C2: LIBXML_NOENT removed — resolves entities (XXE risk).
	 *
	 * @param string $html Tag-stripped summary HTML.
	 *
	 * @return DOMDocument
	 */
	private static function loadSummaryDocument(string $html): DOMDocument {
		$previousErrors = libxml_use_internal_errors(use_errors: true);
		$document = new DOMDocument();
		$document->loadHTML(
			source: '<?xml encoding="UTF-8"><div>' . $html . '</div>',
			options: LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors(use_errors: $previousErrors);

		return $document;
	}//end loadSummaryDocument()

	/**
	 * Point `javascript:` and `data:` anchors at `#` and force `rel` on
	 * every anchor.
	 *
	 * @param DOMDocument $document Parsed summary document.
	 *
	 * @return void
	 */
	private static function neutraliseAnchors(DOMDocument $document): void {
		$anchors = $document->getElementsByTagName(qualifiedName: 'a');
		foreach ($anchors as $anchor) {
			$href = $anchor->getAttribute(qualifiedName: 'href');
			$normalised = strtolower(string: trim(string: $href));
			if (str_starts_with(haystack: $normalised, needle: 'javascript:') === true
				|| str_starts_with(haystack: $normalised, needle: 'data:') === true
			) {
				$anchor->setAttribute(qualifiedName: 'href', value: '#');
			}

			$anchor->setAttribute(qualifiedName: 'rel', value: 'noopener noreferrer');
		}
	}//end neutraliseAnchors()

	/**
	 * Serialise the child nodes of a parent element as HTML.
	 *
	 * @param DOMDocument $document Document the parent belongs to.
	 * @param DOMNode $parent The wrapper element.
	 *
	 * @return string
	 */
	private static function serialiseChildren(DOMDocument $document, DOMNode $parent): string {
		$out = '';
		foreach ($parent->childNodes as $child) {
			$serialised = $document->saveHTML(node: $child);
			if (is_string(value: $serialised) === true) {
				$out .= $serialised;
			}
		}

		return $out;
	}//end serialiseChildren()

	/**
	 * Whether the supplied URL's hostname is permitted by the admin
	 * allow-list. Empty / unset list means all hosts are allowed
	 * (REQ-NEWS-006). Delegates to UrlSafetyValidator.
	 *
	 * @param string $url Candidate feed URL.
	 *
	 * @return boolean True when the host is allowed.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function checkAllowList(string $url): bool {
		return $this->fetch->urlValidator->checkAllowList(
			url: $url,
			appId: Application::APP_ID,
			configKey: self::CONFIG_KEY_ALLOWED_HOSTS
		);
	}//end checkAllowList()

	/**
	 * Hook for the (out-of-branch) `dashboard-metadata-fields` capability.
	 * Until that capability ships, the metadata store is unavailable and
	 * the filter result is "no metadata defined" — which means a
	 * configured filter never matches and the widget returns no items
	 * (REQ-NEWS-007 scenarios "Metadata field missing" and "spec not
	 * yet implemented").
	 *
	 * @param integer $dashboardId The dashboard whose
	 *                             metadata to consult.
	 * @param array{fieldKey: string, value: string} $metadataFilter The filter to apply.
	 *
	 * @return boolean True when the filter passes (allow fetch); false otherwise.
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function checkMetadataFilter(int $dashboardId, array $metadataFilter): bool {
		unset($dashboardId);
		unset($metadataFilter);

		// REQ-NEWS-007: gracefully treat missing dashboard-metadata-fields
		// implementation as "no fields defined", which causes any
		// configured equality filter to fail.
		return false;
	}//end checkMetadataFilter()

	/**
	 * Clamp a caller-requested `limit` to the supported range [1, 200].
	 * The hard ceiling guards against runaway feeds. Out-of-range values
	 * collapse to the closer bound rather than returning an error so the
	 * widget always renders something.
	 *
	 * @param integer $limit Caller-requested limit.
	 *
	 * @return integer Clamped limit.
	 */
	private function clampLimit(int $limit): int {
		if ($limit < 1) {
			return 1;
		}

		return min(self::HARD_ITEM_CEILING, $limit);
	}//end clampLimit()

	/**
	 * Cache-first feed fetch. Returns `null` on any failure so the
	 * caller can record the URL as failed without short-circuiting the
	 * other URLs in the batch.
	 *
	 * A live fetch (not a cache hit) is reported to integriq's connection
	 * registry, at most once an hour while it stays the same
	 * (adopt-connection-registry). The report never changes the payload.
	 *
	 * @param string $url Feed URL to fetch.
	 *
	 * @return string|null Raw feed payload, or null on failure.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	private function fetchFeedPayload(string $url): ?string {
		$cache = $this->getCache();
		$cached = $this->readCachedPayload(cache: $cache, cacheKey: self::feedCacheKey(url: $url));
		if ($cached !== null) {
			return $cached;
		}

		try {
			return $this->fetchAndCachePayload(url: $url, cache: $cache);
		} catch (Throwable $e) {
			$this->reportFetchFailure(url: $url, exception: $e);
			return null;
		}
	}//end fetchFeedPayload()

	/**
	 * Cache key of one feed URL's payload.
	 *
	 * @param string $url Feed URL.
	 *
	 * @return string
	 */
	private static function feedCacheKey(string $url): string {
		return 'feed_' . sha1(string: $url);
	}//end feedCacheKey()

	/**
	 * Fetch a feed live and store an accepted payload in the cache for the
	 * configured TTL.
	 *
	 * @param string $url Feed URL.
	 * @param ICache|null $cache The resolved cache, or null when unavailable.
	 *
	 * @return string|null Raw feed payload, or null when rejected.
	 */
	private function fetchAndCachePayload(string $url, ?ICache $cache): ?string {
		$body = $this->fetchLiveBody(url: $url);
		if ($body !== null && $cache !== null) {
			$ttl = $this->appConfig->getValueInt(
				app: Application::APP_ID,
				key: 'news_widget_feed_cache_ttl_seconds',
				default: self::DEFAULT_CACHE_TTL
			);
			$cache->set(key: self::feedCacheKey(url: $url), value: $body, ttl: $ttl);
		}

		return $body;
	}//end fetchAndCachePayload()

	/**
	 * GET a feed URL without following redirects and report the status to
	 * the connection registry.
	 *
	 * @param string $url Feed URL.
	 *
	 * @return string|null Raw feed payload, or null when the status, an
	 *                     empty body or the size cap rejects it.
	 */
	private function fetchLiveBody(string $url): ?string {
		$client = $this->fetch->clientService->newClient();
		$response = $client->get(uri: $url, options: self::FETCH_OPTIONS);

		$statusCode = $response->getStatusCode();
		if ($statusCode < 200 || $statusCode >= 300) {
			$this->logger->warning(
				message: 'NewsWidget: feed fetch returned HTTP ' . $statusCode . ' for ' . $url
			);
			$this->fetch->connectionReporter?->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: $url, httpStatus: $statusCode);
			return null;
		}

		$this->fetch->connectionReporter?->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: $url, httpStatus: $statusCode);

		return $this->acceptBody(url: $url, body: (string)$response->getBody());
	}//end fetchLiveBody()

	/**
	 * The response body, or null when it is empty or over the size cap.
	 *
	 * @param string $url Feed URL (for logging).
	 * @param string $body Response body.
	 *
	 * @return string|null
	 */
	private function acceptBody(string $url, string $body): ?string {
		if ($body === '') {
			return null;
		}

		// C1: body cap — reject oversized payloads before XML parse.
		if (strlen(string: $body) > self::MAX_RESPONSE_SIZE_BYTES) {
			$this->logger->warning(
				message: 'NewsWidget: feed response exceeds 1MB cap, skipping ' . $url
			);
			return null;
		}

		return $body;
	}//end acceptBody()

	/**
	 * Log a failed feed fetch and report it to the connection registry.
	 *
	 * @param string $url Feed URL.
	 * @param Throwable $exception What the fetch threw.
	 *
	 * @return void
	 */
	private function reportFetchFailure(string $url, Throwable $exception): void {
		$this->logger->warning(
			message: 'NewsWidget: feed fetch failed for ' . $url,
			context: ['exception' => $exception]
		);
		$this->fetch->connectionReporter?->reportCall(
			key: ConnectionReporter::KEY_NEWS_FEEDS,
			url: $url,
			httpStatus: $this->fetch->connectionReporter->httpStatusOf(exception: $exception)
		);
	}//end reportFetchFailure()

	/**
	 * Read a previously cached feed payload.
	 *
	 * Treats a missing cache subsystem, a cache miss, a non-string entry
	 * and an empty entry alike: all mean "no usable payload", so the
	 * caller falls through to a live fetch.
	 *
	 * @param ICache|null $cache The resolved cache, or null when the
	 *                           subsystem is unavailable.
	 * @param string $cacheKey Cache key for this feed URL.
	 *
	 * @return string|null Cached payload, or null when there is none.
	 */
	private function readCachedPayload(?ICache $cache, string $cacheKey): ?string {
		if ($cache === null) {
			return null;
		}

		$cached = $cache->get(key: $cacheKey);
		if (is_string(value: $cached) === true && $cached !== '') {
			return $cached;
		}

		return null;
	}//end readCachedPayload()

	/**
	 * Lazily resolve the distributed cache. Returns `null` when the
	 * Nextcloud cache subsystem is unavailable (e.g. unit tests with a
	 * stub factory that returns `null`).
	 *
	 * @return ICache|null
	 */
	private function getCache(): ?ICache {
		if ($this->cache !== null) {
			return $this->cache;
		}

		try {
			$this->cache = $this->fetch->cacheFactory->createDistributed(prefix: 'launchpad_news_');
		} catch (Throwable $e) {
			$this->logger->info(
				message: 'NewsWidget: cache subsystem unavailable, falling back to direct fetch',
				context: ['exception' => $e]
			);
			$this->cache = null;
		}

		return $this->cache;
	}//end getCache()

	/**
	 * Text of the first child element present out of `$names`.
	 *
	 * Encodes the "optional child, empty string when absent" convention
	 * the feed normalisers apply to every scalar field. A child that
	 * exists but is empty still wins over a later name in the list.
	 *
	 * @param SimpleXMLElement $node Parent node.
	 * @param array $names Child element names, tried in order.
	 *
	 * @return string Text of the first present child, or '' when none is.
	 */
	private static function childText(SimpleXMLElement $node, array $names): string {
		foreach ($names as $name) {
			if (isset($node->$name) === true) {
				return (string)$node->$name;
			}
		}

		return '';
	}//end childText()

	/**
	 * Convert a single Atom `<entry>` into the canonical item shape.
	 *
	 * @param SimpleXMLElement $entry The Atom entry node.
	 * @param FeedSource $source The feed URL and display name.
	 *
	 * @return array<string, mixed> Canonical item.
	 */
	private function normaliseAtomEntry(SimpleXMLElement $entry, FeedSource $source): array {
		return $this->canonicalItem(
			fields: [
				'guid' => self::childText(node: $entry, names: ['id']),
				'title' => self::childText(node: $entry, names: ['title']),
				'summary' => self::childText(node: $entry, names: ['summary', 'content']),
				'link' => self::atomLink(entry: $entry),
				'pubDate' => self::childText(node: $entry, names: ['updated', 'published']),
				'thumbnailUrl' => null,
			],
			source: $source
		);
	}//end normaliseAtomEntry()

	/**
	 * The `href` of an Atom entry's `<link href="…"/>`, or '' without one.
	 *
	 * @param SimpleXMLElement $entry The Atom entry node.
	 *
	 * @return string
	 */
	private static function atomLink(SimpleXMLElement $entry): string {
		if (isset($entry->link) === false) {
			return '';
		}

		$href = $entry->link['href'] ?? null;
		if ($href === null) {
			return '';
		}

		return (string)$href;
	}//end atomLink()

	/**
	 * Convert a single RSS `<item>` into the canonical item shape.
	 *
	 * @param SimpleXMLElement $item The RSS item node.
	 * @param FeedSource $source The feed URL and display name.
	 *
	 * @return array<string, mixed> Canonical item.
	 */
	private function normaliseRssItem(SimpleXMLElement $item, FeedSource $source): array {
		return $this->canonicalItem(
			fields: [
				'guid' => self::childText(node: $item, names: ['guid']),
				'title' => self::childText(node: $item, names: ['title']),
				'summary' => self::childText(node: $item, names: ['description']),
				'link' => self::childText(node: $item, names: ['link']),
				'pubDate' => self::childText(node: $item, names: ['pubDate']),
				'thumbnailUrl' => self::rssThumbnail(item: $item),
			],
			source: $source
		);
	}//end normaliseRssItem()

	/**
	 * The URL of an RSS item's image enclosure (an enclosure without a
	 * type counts as an image), or null without one.
	 *
	 * @param SimpleXMLElement $item The RSS item node.
	 *
	 * @return string|null
	 */
	private static function rssThumbnail(SimpleXMLElement $item): ?string {
		if (isset($item->enclosure) === false) {
			return null;
		}

		$url = $item->enclosure['url'] ?? null;
		$type = $item->enclosure['type'] ?? null;
		if ($url !== null && self::isImageEnclosureType(type: $type) === true) {
			return (string)$url;
		}

		return null;
	}//end rssThumbnail()

	/**
	 * Whether an enclosure type marks an image; a missing type counts as one.
	 *
	 * @param SimpleXMLElement|null $type The enclosure's `type` attribute.
	 *
	 * @return boolean
	 */
	private static function isImageEnclosureType(?SimpleXMLElement $type): bool {
		return $type === null || str_starts_with(haystack: (string)$type, needle: 'image/') === true;
	}//end isImageEnclosureType()

	/**
	 * Complete the fields read from one feed entry into the canonical item
	 * shape: a synthetic `sha1(title|pubDate|sourceUrl)` guid when the
	 * entry has none, the sanitised summary and the feed source.
	 *
	 * @param array<string, string|null> $fields Fields read from the entry: guid, title, summary, link,
	 *                                           pubDate (strings) and thumbnailUrl (string or null).
	 * @param FeedSource $source The feed URL and display name.
	 *
	 * @return array<string, mixed> Canonical item.
	 */
	private function canonicalItem(array $fields, FeedSource $source): array {
		$fields['guid'] = self::guidOf(fields: $fields, source: $source);
		$fields['summary'] = $this->sanitiseSummaryHtml(html: $fields['summary']);

		return $source->item(fields: $fields);
	}//end canonicalItem()

	/**
	 * The entry's own guid, or `sha1(title|pubDate|sourceUrl)` when empty.
	 *
	 * @param array{guid: string, title: string, pubDate: string} $fields Fields read from the entry.
	 * @param FeedSource $source The feed URL and display name.
	 *
	 * @return string
	 */
	private static function guidOf(array $fields, FeedSource $source): string {
		if ($fields['guid'] !== '') {
			return $fields['guid'];
		}

		return sha1(string: $fields['title'] . '|' . $fields['pubDate'] . '|' . $source->url);
	}//end guidOf()

	/**
	 * Canonical empty response so callers don't repeat the literal.
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     feedsFailed: int,
	 *     failedUrls: array<int, string>
	 * }
	 */
	private function emptyResponse(): array {
		return [
			'items' => [],
			'feedsFailed' => 0,
			'failedUrls' => [],
		];
	}//end emptyResponse()
}//end class
