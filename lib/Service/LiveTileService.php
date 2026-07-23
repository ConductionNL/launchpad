<?php

/**
 * LiveTileService
 *
 * Resolves a `livetile` widget placement's configured data source to a
 * value, server-side (REQ-LIVETILE-003). Two source modes:
 *
 *  - `connector` — OpenConnector's `dashboard-http-datasource` capability
 *    is called through its documented runtime source-run API, ONLY when a
 *    capability probe confirms the app is installed AND the expected
 *    service is resolvable. This file never statically imports an
 *    OpenConnector class (REQ-LIVETILE-005 "No direct class dependency") —
 *    the FQCN is referenced only as a string, exactly mirroring
 *    {@see WeatherService::WEATHER_STATUS_SERVICE_CLASS}'s reuse of the
 *    optional `weather_status` app.
 *  - `url` — a server-side allow-listed HTTP GET, extracting a value via a
 *    JSONPath-lite expression (`$.a.b`, `$.a[0].b`). The allow-list
 *    (`livetile_allowed_hosts`, IAppConfig) is enforced FAIL-CLOSED: an
 *    empty/missing list permits NO host (unlike
 *    {@see UrlSafetyValidator::checkAllowList()}, which is fail-OPEN for
 *    the lower-sensitivity news/calendar feed URLs — a live-data tile can
 *    be pointed at an arbitrary internal endpoint by any dashboard author,
 *    so the safe default here is deny-by-default).
 *
 * Results are cached in `ICache`, keyed on placement id + a hash of the
 * resolved config, with a per-config TTL (default 300s, clamped to a
 * 30s minimum). On upstream failure a previously cached value is served
 * marked `stale`; with no cache available the value is `null` and `stale`
 * is `true` — the endpoint/widget never crash (REQ-LIVETILE-003).
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

use DateTime;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for resolving, caching, formatting, and badging live-data tile
 * readings.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Combines dual-source
 *     resolution (OpenConnector leaf + allow-listed direct GET), JSONPath-
 *     lite extraction, formatting, badge thresholding, caching, and
 *     stale-fallback in one cohesive unit — mirrors WeatherService's shape
 *     for the same class of capability.
 * @spec                                             openspec/specs/live-data-tile-widget/spec.md
 */
class LiveTileService
{

    /**
     * Default value-cache TTL in seconds when a placement has no (or an
     * invalid) `refresh` configured (REQ-LIVETILE-002 "Refresh interval
     * bounds").
     *
     * @var integer
     */
    public const DEFAULT_REFRESH_SECONDS = 300;

    /**
     * Minimum permitted refresh interval in seconds — any configured value
     * below this is clamped up (REQ-LIVETILE-002).
     *
     * @var integer
     */
    public const MIN_REFRESH_SECONDS = 30;

    /**
     * HTTP connect timeout in seconds for the direct-URL fetch.
     *
     * @var integer
     */
    public const CONNECT_TIMEOUT = 10;

    /**
     * HTTP total request timeout in seconds for the direct-URL fetch.
     *
     * @var integer
     */
    public const REQUEST_TIMEOUT = 15;

    /**
     * IAppConfig key — JSON array of hostnames permitted for `url` source
     * mode. FAIL-CLOSED: empty or missing means NO host is permitted.
     *
     * @var string
     */
    public const CONFIG_KEY_ALLOWED_HOSTS = 'livetile_allowed_hosts';

    /**
     * App id of the optional OpenConnector leaf.
     *
     * @var string
     */
    private const OPENCONNECTOR_APP_ID = 'openconnector';

    /**
     * FQCN of OpenConnector's dashboard data-source resolver, referenced
     * only as a string so this file never hard-requires the class to
     * exist (REQ-LIVETILE-005 "No direct class dependency") — resolved
     * through the container only when the capability probe passes.
     *
     * @var string
     */
    private const OPENCONNECTOR_DATASOURCE_SERVICE_CLASS = 'OCA\\OpenConnector\\Service\\DashboardDataSourceService';

    /**
     * Method OpenConnector's data-source resolver is expected to expose:
     * `resolveDashboardValue(string $sourceId, string $valueExpr): array{value: mixed}`.
     * Guarded with `method_exists()` before every call — a shape mismatch
     * degrades to "source unavailable" rather than a fatal error.
     *
     * @var string
     */
    private const OPENCONNECTOR_DATASOURCE_METHOD = 'resolveDashboardValue';

    /**
     * Badge threshold states, in priority order for icon/label fallback.
     *
     * @var array<int,string>
     */
    private const BADGE_STATES = ['ok', 'warn', 'alert'];

    /**
     * Lazily resolved {@see ICache} backing the per-reading cache.
     *
     * @var ICache|null
     */
    private ?ICache $cache = null;

    /**
     * Constructor.
     *
     * @param IAppManager           $appManager      Detects whether `openconnector` is enabled.
     * @param ContainerInterface    $container       App container used to optionally resolve
     *                                                OpenConnector's data-source service
     *                                                (REQ-LIVETILE-005 capability probe).
     * @param IClientService        $clientService    HTTP client factory for the direct-URL fetch.
     * @param ICacheFactory         $cacheFactory     Backing factory for the distributed value cache.
     * @param IAppConfig            $appConfig        Admin config: allow-listed hosts.
     * @param WidgetPlacementMapper $placementMapper  Resolves placements by id.
     * @param LoggerInterface       $logger           PSR logger.
     */
    public function __construct(
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container,
        private readonly IClientService $clientService,
        private readonly ICacheFactory $cacheFactory,
        private readonly IAppConfig $appConfig,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Resolve the live-tile value for one placement. Never throws — every
     * failure path returns either a stale cached value or the `{value:
     * null, stale: true}` shape (REQ-LIVETILE-003 "Upstream failure
     * degrades gracefully").
     *
     * @param integer $placementId The widget placement id.
     *
     * @return array<string,mixed> `{value, formatted, badge, fetchedAt, stale}` or `{error: string}`.
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
     */
    public function resolveForPlacement(int $placementId): array
    {
        try {
            $placement = $this->placementMapper->find(id: $placementId);
        } catch (Throwable $exception) {
            return ['error' => 'placement_not_found'];
        }

        $config  = $this->readPlacementConfig(placement: $placement);
        $refresh = $this->clampRefresh(seconds: (int) ($config['refresh'] ?? 0));

        $cacheKey = $this->buildCacheKey(placementId: $placementId, config: $config);
        $cache    = $this->getCache();
        $cached   = $this->readCache(cache: $cache, cacheKey: $cacheKey);

        if ($cached !== null) {
            $age = (time() - (int) ($cached['fetchedAtTs'] ?? 0));
            if ($age >= 0 && $age < $refresh) {
                return $this->publicShape(reading: $cached, config: $config, stale: false);
            }
        }

        $fresh = $this->fetchFresh(config: $config);

        if ($fresh !== null) {
            $fresh['fetchedAtTs'] = time();
            if ($cache !== null) {
                $cache->set(key: $cacheKey, value: json_encode($fresh), ttl: $refresh);
            }

            return $this->publicShape(reading: $fresh, config: $config, stale: false);
        }

        if ($cached !== null) {
            // Upstream failed (or the allow-list/capability probe refused
            // the fetch) but a previous value exists — degrade gracefully
            // rather than error (REQ-LIVETILE-003).
            return $this->publicShape(reading: $cached, config: $config, stale: true);
        }

        return [
            'value'     => null,
            'formatted' => null,
            'badge'     => null,
            'fetchedAt' => null,
            'stale'     => true,
        ];
    }//end resolveForPlacement()

    /**
     * Validate a candidate live-tile source config at save time
     * (REQ-LIVETILE-002). FAIL-CLOSED for `url` mode: a URL whose host is
     * not on the (possibly empty) allow-list is always rejected.
     *
     * @param array<string,mixed> $config The candidate `{sourceMode, url|sourceId, valueExpr, refresh}` config.
     *
     * @return string[] Validation error codes; empty when the config is valid.
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
     */
    public function validateSourceConfig(array $config): array
    {
        $errors     = [];
        $sourceMode = (string) ($config['sourceMode'] ?? '');

        if (in_array(needle: $sourceMode, haystack: ['connector', 'url'], strict: true) === false) {
            $errors[] = 'invalid_source_mode';
            return $errors;
        }

        if ($sourceMode === 'url') {
            $url = trim(string: (string) ($config['url'] ?? ''));
            if ($url === '') {
                $errors[] = 'url_required';
            } elseif ($this->hasValidScheme(url: $url) === false) {
                $errors[] = 'invalid_url';
            } elseif ($this->isHostAllowed(url: $url) === false) {
                // FAIL-CLOSED (REQ-LIVETILE-002 "rejected at save time").
                $errors[] = 'host_not_allowed';
            }
        }

        if ($sourceMode === 'connector') {
            if (trim(string: (string) ($config['sourceId'] ?? '')) === '') {
                $errors[] = 'source_id_required';
            }

            if ($this->isConnectorAvailable() === false) {
                $errors[] = 'connector_unavailable';
            }
        }

        return $errors;
    }//end validateSourceConfig()

    /**
     * Whether the OpenConnector `dashboard-http-datasource` capability is
     * currently resolvable — app enabled AND the expected service present
     * in the container. Never throws (REQ-LIVETILE-005).
     *
     * @return boolean
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
     */
    public function isConnectorAvailable(): bool
    {
        try {
            if ($this->appManager->isEnabledForUser(appId: self::OPENCONNECTOR_APP_ID) === false) {
                return false;
            }

            return $this->container->has(id: self::OPENCONNECTOR_DATASOURCE_SERVICE_CLASS);
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'LiveTileService: OpenConnector capability probe failed, treating as absent',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return false;
        }
    }//end isConnectorAvailable()

    /**
     * Dispatch to the configured source mode's fetch path. Returns `null`
     * — never throws — on any failure so the caller can fall through to a
     * stale cache or the null/stale shape.
     *
     * @param array<string,mixed> $config The placement's resolved config.
     *
     * @return array<string,mixed>|null `{rawValue: mixed}` or `null`.
     */
    private function fetchFresh(array $config): ?array
    {
        $sourceMode = (string) ($config['sourceMode'] ?? 'url');

        if ($sourceMode === 'connector') {
            return $this->fetchFromConnector(config: $config);
        }

        if ($sourceMode === 'url') {
            return $this->fetchFromUrl(config: $config);
        }

        return null;
    }//end fetchFresh()

    /**
     * Resolve via OpenConnector's `dashboard-http-datasource` capability
     * (REQ-LIVETILE-005). Returns `null` — never throws — when the
     * capability probe fails, the service's expected method is absent, or
     * the call itself fails; the caller then falls back to a stale cached
     * value or the "unavailable" null/stale shape, which the widget
     * renders as an informative state (REQ-LIVETILE-005 "existing
     * connector-mode tiles MUST render an informative 'data source
     * unavailable' state, not crash").
     *
     * @param array<string,mixed> $config The placement's resolved config (`sourceId`, `valueExpr`).
     *
     * @return array<string,mixed>|null `{rawValue: mixed}` or `null`.
     */
    private function fetchFromConnector(array $config): ?array
    {
        if ($this->isConnectorAvailable() === false) {
            return null;
        }

        $sourceId  = (string) ($config['sourceId'] ?? '');
        $valueExpr = (string) ($config['valueExpr'] ?? '');
        if ($sourceId === '') {
            return null;
        }

        try {
            $service = $this->container->get(id: self::OPENCONNECTOR_DATASOURCE_SERVICE_CLASS);
            if (method_exists(object_or_class: $service, method: self::OPENCONNECTOR_DATASOURCE_METHOD) === false) {
                return null;
            }

            $result = $service->{self::OPENCONNECTOR_DATASOURCE_METHOD}($sourceId, $valueExpr);
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'LiveTileService: OpenConnector source-run call failed',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return null;
        }

        if (is_array(value: $result) === false || array_key_exists(key: 'value', array: $result) === false) {
            return null;
        }

        return ['rawValue' => $result['value']];
    }//end fetchFromConnector()

    /**
     * Resolve via a server-side, allow-listed HTTP GET (REQ-LIVETILE-003).
     * Fails closed: an invalid scheme or a host not on
     * `livetile_allowed_hosts` refuses the fetch entirely (never even
     * opens a connection). Returns `null` — never throws — on any
     * failure.
     *
     * @param array<string,mixed> $config The placement's resolved config (`url`, `valueExpr`).
     *
     * @return array<string,mixed>|null `{rawValue: mixed}` or `null`.
     */
    private function fetchFromUrl(array $config): ?array
    {
        $url = trim(string: (string) ($config['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if ($this->hasValidScheme(url: $url) === false) {
            return null;
        }

        if ($this->isHostAllowed(url: $url) === false) {
            $this->logger->warning(
                message: 'LiveTileService: host not on livetile_allowed_hosts, refusing fetch (fail-closed)',
                context: ['app' => Application::APP_ID]
            );
            return null;
        }

        try {
            $client   = $this->clientService->newClient();
            $response = $client->get(uri: $url, options: [
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout'         => self::REQUEST_TIMEOUT,
                'http_errors'     => false,
                // No auto-redirect — a 3xx to an unexpected host would
                // bypass the allow-list check above.
                'allow_redirects' => false,
            ]);
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'LiveTileService: direct-URL fetch failed',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return null;
        }

        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode(json: (string) $response->getBody(), associative: true);
        if (is_array(value: $decoded) === false) {
            return null;
        }

        $value = $this->extractValue(data: $decoded, expr: (string) ($config['valueExpr'] ?? ''));
        if ($value === null) {
            return null;
        }

        return ['rawValue' => $value];
    }//end fetchFromUrl()

    /**
     * Extract a value from a decoded JSON structure via a JSONPath-lite
     * expression — supports `$.a.b` (property access) and `$.a[0].b`
     * (numeric index access). No arbitrary code is evaluated. Returns
     * `null` on any malformed expression, missing key, or out-of-range
     * index.
     *
     * @param mixed  $data The decoded JSON value (array/scalar tree).
     * @param string $expr The JSONPath-lite expression, e.g. `$.data.open_count`.
     *
     * @return mixed|null The extracted value, or `null`.
     */
    private function extractValue(mixed $data, string $expr): mixed
    {
        $expr = trim(string: $expr);
        if ($expr === '' || $expr[0] !== '$') {
            return null;
        }

        $rest = substr(string: $expr, offset: 1);
        if ($rest === '') {
            return $data;
        }

        $tokens = [];
        $matched = preg_match_all(
            pattern: '/\.([A-Za-z0-9_]+)|\[(\d+)\]/',
            subject: $rest,
            matches: $tokens,
            flags: PREG_SET_ORDER
        );

        if ($matched === false || $matched === 0) {
            return null;
        }

        // Reject expressions with unrecognised characters between tokens
        // (defence-in-depth — the regex above already only matches the
        // two supported forms, this guards against a partially-matched
        // trailing garbage segment being silently ignored).
        $consumed = implode(separator: '', array: array_map(
            callback: static fn (array $token): string => $token[0],
            array: $tokens
        ));
        if ($consumed !== $rest) {
            return null;
        }

        $current = $data;
        foreach ($tokens as $token) {
            if ($token[1] !== '') {
                $key = $token[1];
                if (is_array(value: $current) === false || array_key_exists(key: $key, array: $current) === false) {
                    return null;
                }

                $current = $current[$key];
                continue;
            }

            $index = (int) $token[2];
            if (is_array(value: $current) === false || array_key_exists(key: $index, array: $current) === false) {
                return null;
            }

            $current = $current[$index];
        }

        return $current;
    }//end extractValue()

    /**
     * Format a raw resolved value for display (REQ-LIVETILE-004): prefix,
     * optional thousands separator, suffix. Non-numeric values are
     * returned as their string cast, unformatted.
     *
     * @param mixed                $value  The raw resolved value.
     * @param array<string,mixed>  $format `{prefix?: string, suffix?: string, thousands?: bool}`.
     *
     * @return string The formatted display string.
     */
    private function formatValue(mixed $value, array $format): string
    {
        $prefix = (string) ($format['prefix'] ?? '');
        $suffix = (string) ($format['suffix'] ?? '');

        if (is_numeric(value: $value) === false) {
            return $prefix.((string) $value).$suffix;
        }

        $number    = (float) $value;
        $decimals  = ((float) (int) $number === $number) ? 0 : 2;
        $thousands = (bool) ($format['thousands'] ?? false);

        $body = $thousands === true
            ? number_format(num: $number, decimals: $decimals)
            : (string) round(num: $number, precision: $decimals);

        return $prefix.$body.$suffix;
    }//end formatValue()

    /**
     * Resolve the threshold badge for a raw value (REQ-LIVETILE-004
     * "Threshold badge is not colour-only"). Thresholds are evaluated in
     * ascending `max` order; the first threshold whose `max` the value
     * does not exceed wins. Returns `null` when no thresholds are
     * configured or the value is non-numeric.
     *
     * @param mixed                $value The raw resolved value.
     * @param array<string,mixed>  $badge `{thresholds?: array<int,array{max:int|float,state:string,label:string}>}`.
     *
     * @return array<string,string>|null `{state, label}` or `null`.
     */
    private function resolveBadge(mixed $value, array $badge): ?array
    {
        $thresholds = $badge['thresholds'] ?? null;
        if (is_array(value: $thresholds) === false || $thresholds === [] || is_numeric(value: $value) === false) {
            return null;
        }

        $number = (float) $value;
        $sorted = $thresholds;
        usort(
            array: $sorted,
            callback: static fn (array $a, array $b): int => ((float) ($a['max'] ?? 0)) <=> ((float) ($b['max'] ?? 0))
        );

        foreach ($sorted as $threshold) {
            if (is_array(value: $threshold) === false) {
                continue;
            }

            $max = (float) ($threshold['max'] ?? 0);
            if ($number <= $max) {
                return $this->badgeShape(threshold: $threshold);
            }
        }

        // Value exceeds every threshold — use the highest-max (last)
        // threshold, which is conventionally the "alert" tier.
        $last = end($sorted);
        return is_array(value: $last) === true ? $this->badgeShape(threshold: $last) : null;
    }//end resolveBadge()

    /**
     * Shape one threshold entry into the public badge contract.
     *
     * @param array<string,mixed> $threshold `{state?, label?}`.
     *
     * @return array<string,string> `{state, label}`.
     */
    private function badgeShape(array $threshold): array
    {
        $state = (string) ($threshold['state'] ?? 'ok');
        if (in_array(needle: $state, haystack: self::BADGE_STATES, strict: true) === false) {
            $state = 'ok';
        }

        $label = (string) ($threshold['label'] ?? ucfirst(string: $state));

        return ['state' => $state, 'label' => $label];
    }//end badgeShape()

    /**
     * Validate a URL's scheme is `http` or `https`.
     *
     * @param string $url The URL to check.
     *
     * @return boolean
     */
    private function hasValidScheme(string $url): bool
    {
        $scheme = strtolower(string: (string) parse_url(url: $url, component: PHP_URL_SCHEME));
        return in_array(needle: $scheme, haystack: ['http', 'https'], strict: true);
    }//end hasValidScheme()

    /**
     * Check a URL's host against `livetile_allowed_hosts`. FAIL-CLOSED: an
     * empty, missing, or unparseable allow-list permits NO host — the
     * admin must explicitly opt hosts in.
     *
     * @param string $url The URL to check.
     *
     * @return boolean True only when the host is explicitly allow-listed.
     */
    private function isHostAllowed(string $url): bool
    {
        $host = parse_url(url: $url, component: PHP_URL_HOST);
        if (is_string(value: $host) === false || $host === '') {
            return false;
        }

        $raw = $this->appConfig->getValueString(
            app: Application::APP_ID,
            key: self::CONFIG_KEY_ALLOWED_HOSTS,
            default: ''
        );

        $decoded = json_decode(json: $raw, associative: true);
        if (is_array(value: $decoded) === false || $decoded === []) {
            // FAIL-CLOSED — no configured list means no host is allowed.
            return false;
        }

        $needle = strtolower(string: $host);
        foreach ($decoded as $allowed) {
            if (is_string(value: $allowed) === true && strtolower(string: $allowed) === $needle) {
                return true;
            }
        }

        return false;
    }//end isHostAllowed()

    /**
     * Read the placement's live-tile config, falling back to the legacy
     * `style_config.content` slot for pre-column rows (mirrors
     * {@see WeatherService::readPlacementConfig()}).
     *
     * @param object $placement The {@see \OCA\LaunchPad\Db\WidgetPlacement} entity.
     *
     * @return array<string,mixed>
     */
    private function readPlacementConfig(object $placement): array
    {
        if (method_exists(object_or_class: $placement, method: 'getContentArray') === true) {
            $content = $placement->getContentArray();
            if (is_array(value: $content) === true && $content !== []) {
                return $content;
            }
        }

        if (method_exists(object_or_class: $placement, method: 'getStyleConfigArray') === true) {
            $legacy = $placement->getStyleConfigArray();
            if (isset($legacy['content']) === true && is_array(value: $legacy['content']) === true) {
                return $legacy['content'];
            }

            if (is_array(value: $legacy) === true) {
                return $legacy;
            }
        }

        return [];
    }//end readPlacementConfig()

    /**
     * Shape an internal reading array (which also carries the internal
     * `fetchedAtTs` unix timestamp / `rawValue`) into the public response
     * contract (REQ-LIVETILE-003): `{value, formatted, badge, fetchedAt,
     * stale}`. NEVER includes the source URL, headers, or credentials.
     *
     * @param array<string,mixed> $reading The internal reading.
     * @param array<string,mixed> $config  The placement's resolved config (drives format/badge).
     * @param boolean              $stale   Whether this is a stale (cache-expired-but-served) reading.
     *
     * @return array<string,mixed>
     */
    private function publicShape(array $reading, array $config, bool $stale): array
    {
        $rawValue    = $reading['rawValue'] ?? null;
        $fetchedAtTs = (int) ($reading['fetchedAtTs'] ?? time());

        $formatted = $rawValue !== null
            ? $this->formatValue(value: $rawValue, format: (array) ($config['format'] ?? []))
            : null;
        $badge = $rawValue !== null
            ? $this->resolveBadge(value: $rawValue, badge: (array) ($config['badge'] ?? []))
            : null;

        return [
            'value'     => $rawValue,
            'formatted' => $formatted,
            'badge'     => $badge,
            'fetchedAt' => (new DateTime('@'.$fetchedAtTs))->format(format: DATE_ATOM),
            'stale'     => $stale,
        ];
    }//end publicShape()

    /**
     * Build the value cache key — placement id + a hash of the resolved
     * config (proposal.md "Caching keyed on placement id + config hash").
     *
     * @param integer              $placementId The widget placement id.
     * @param array<string,mixed>  $config      The placement's resolved config.
     *
     * @return string The cache key.
     */
    private function buildCacheKey(int $placementId, array $config): string
    {
        return 'value_'.$placementId.'_'.hash(algo: 'sha256', data: (string) json_encode($config));
    }//end buildCacheKey()

    /**
     * Read + JSON-decode a cache entry. Returns `null` on a miss or a
     * corrupt entry.
     *
     * @param ICache|null $cache    The cache instance, or `null` when the cache
     *                              subsystem is unavailable.
     * @param string      $cacheKey The cache key.
     *
     * @return array<string,mixed>|null
     */
    private function readCache(?ICache $cache, string $cacheKey): ?array
    {
        if ($cache === null) {
            return null;
        }

        $raw = $cache->get(key: $cacheKey);
        if (is_string(value: $raw) === false) {
            return null;
        }

        $decoded = json_decode(json: $raw, associative: true);
        return is_array(value: $decoded) === true ? $decoded : null;
    }//end readCache()

    /**
     * Lazily resolve the distributed cache. Returns `null` when the cache
     * subsystem is unavailable (e.g. unit tests with a stub factory).
     *
     * @return ICache|null
     */
    private function getCache(): ?ICache
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = $this->cacheFactory->createDistributed(prefix: 'launchpad_livetile_');
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'LiveTileService: cache subsystem unavailable, falling back to direct fetch',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            $this->cache = null;
        }

        return $this->cache;
    }//end getCache()

    /**
     * Clamp a configured refresh interval: values `<= 0` (unset) default
     * to {@see self::DEFAULT_REFRESH_SECONDS}; any positive value below
     * {@see self::MIN_REFRESH_SECONDS} is raised to that minimum
     * (REQ-LIVETILE-002 "Refresh interval bounds").
     *
     * @param integer $seconds The raw configured refresh interval, or `0`/negative when unset.
     *
     * @return integer The clamped refresh interval in seconds.
     */
    private function clampRefresh(int $seconds): int
    {
        if ($seconds <= 0) {
            return self::DEFAULT_REFRESH_SECONDS;
        }

        return max($seconds, self::MIN_REFRESH_SECONDS);
    }//end clampRefresh()
}//end class
