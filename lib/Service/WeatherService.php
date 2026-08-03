<?php

/**
 * WeatherService
 *
 * Resolves a `weather` widget placement's reading server-side
 * (REQ-WEATHER-001..003): reuses the Nextcloud `weather_status` app's
 * provider pattern when it is enabled, otherwise fetches from an
 * admin-configured provider URL (with a server-held API key) via
 * `OCP\Http\Client`. Readings are cached in `ICache`, keyed on
 * location+units+language, with a TTL (default 900s). On upstream failure
 * a previously cached reading is returned marked `stale`; with no cache an
 * error shape is returned so the controller can render a clean error
 * response. Units and forecast language are ALWAYS derived from the
 * requesting user's Nextcloud locale unless the placement author overrides
 * units — this guards against the historical Nextcloud weather bug of
 * hardcoded units / English-only strings (REQ-WEATHER-003).
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
use OCP\IConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for resolving, caching, and normalising weather-widget readings.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Combines provider
 *     resolution (weather_status reuse + fallback provider URL),
 *     locale-derived units/language, caching, and stale-fallback in one
 *     cohesive unit — mirrors NewsWidgetService's shape for the same
 *     class of capability.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   Same cause as the complexity
 *     above: provider resolution reaches weather_status, the HTTP client, the
 *     cache, the config and the l10n factory. The collaborators are the feature.
 * @spec                                             openspec/specs/clock-weather-widgets/spec.md
 */
class WeatherService
{

    /**
     * Default reading cache TTL in seconds (REQ-WEATHER-002). Overridden
     * at runtime by the app-config key `weather_cache_ttl_seconds`.
     *
     * @var integer
     */
    public const DEFAULT_CACHE_TTL = 900;

    /**
     * HTTP connect timeout in seconds for the fallback provider fetch.
     *
     * @var integer
     */
    public const CONNECT_TIMEOUT = 10;

    /**
     * HTTP total request timeout in seconds for the fallback provider
     * fetch.
     *
     * @var integer
     */
    public const REQUEST_TIMEOUT = 15;

    /**
     * IAppConfig key — cache TTL override, in seconds.
     *
     * @var string
     */
    public const CONFIG_KEY_CACHE_TTL = 'weather_cache_ttl_seconds';

    /**
     * IAppConfig key — the fallback provider URL template. May contain the
     * placeholders `{location}`, `{apiKey}`, `{units}`, `{lang}`, which are
     * substituted (URL-encoded) before the request is made.
     *
     * @var string
     */
    public const CONFIG_KEY_PROVIDER_URL = 'weather_provider_url';

    /**
     * IAppConfig key — the fallback provider's server-held API key. Never
     * exposed in any response (REQ-WEATHER-001 "key never exposed"); ONLY
     * substituted into the outbound request URL server-side. MUST be
     * written with `sensitive: true` by whichever admin-settings surface
     * sets it.
     *
     * @var string
     */
    public const CONFIG_KEY_PROVIDER_API_KEY = 'weather_provider_api_key';

    /**
     * FQCN of the `weather_status` app's forecast service, referenced only
     * as a string so this file never hard-requires the class to exist —
     * it is only resolved through the container when the app is enabled
     * (REQ-WEATHER-002 "reuse weather_status when present").
     *
     * @var string
     */
    private const WEATHER_STATUS_SERVICE_CLASS = 'OCA\\WeatherStatus\\Service\\WeatherStatusService';

    /**
     * ISO 3166-1 alpha-2 country codes that use imperial units. Matched
     * against the trailing territory of a Nextcloud locale (e.g. `en_US`).
     * The rest of the world uses metric.
     *
     * @var array<int,string>
     */
    private const IMPERIAL_TERRITORIES = ['US', 'LR', 'MM'];

    /**
     * Met.no `symbol_code` prefixes (weather_status's upstream) mapped to
     * our normalised `condition` codes. Suffixes like `_day`/`_night`/
     * `_polartwilight` are stripped before lookup.
     *
     * @var array<string,string>
     */
    private const METNO_CONDITION_MAP = [
        'clearsky'         => 'clear',
        'fair'             => 'clear',
        'partlycloudy'     => 'partly-cloudy',
        'cloudy'           => 'cloudy',
        'fog'              => 'fog',
        'lightrain'        => 'rain',
        'rain'             => 'rain',
        'lightrainshowers' => 'rain',
        'rainshowers'      => 'rain',
        'heavyrain'        => 'heavy-rain',
        'heavyrainshowers' => 'heavy-rain',
        'lightsnow'        => 'snow',
        'snow'             => 'snow',
        'snowshowers'      => 'snow',
        'heavysnow'        => 'snow',
        'sleet'            => 'snow',
        'thunder'          => 'thunderstorm',
        'rainandthunder'   => 'thunderstorm',
        'sleetandthunder'  => 'thunderstorm',
    ];

    /**
     * OpenWeatherMap-family `weather[0].main` values mapped to our
     * normalised `condition` codes (the common default shape for the
     * configurable-provider-URL fallback).
     *
     * @var array<string,string>
     */
    private const OWM_CONDITION_MAP = [
        'clear'        => 'clear',
        'clouds'       => 'cloudy',
        'mist'         => 'fog',
        'fog'          => 'fog',
        'haze'         => 'fog',
        'drizzle'      => 'rain',
        'rain'         => 'rain',
        'snow'         => 'snow',
        'thunderstorm' => 'thunderstorm',
        'tornado'      => 'windy',
        'squall'       => 'windy',
    ];

    /**
     * Lazily resolved {@see ICache} backing the per-reading cache.
     *
     * @var ICache|null
     */
    private ?ICache $cache = null;

    /**
     * Constructor.
     *
     * @param IAppManager           $appManager      Detects whether `weather_status` is enabled.
     * @param ContainerInterface    $container       App container used to optionally resolve
     *                                               `weather_status`'s forecast service
     *                                               (REQ-WEATHER-002 provider reuse).
     * @param IClientService        $clientService   HTTP client factory for the fallback provider fetch.
     * @param ICacheFactory         $cacheFactory    Backing factory for the distributed reading cache.
     * @param IAppConfig            $appConfig       Admin config: cache TTL, provider URL, API key.
     * @param WidgetPlacementMapper $placementMapper Resolves placements by id.
     * @param IConfig               $config          Reads the requesting user's locale/language and
     *                                               the system default.
     * @param LoggerInterface       $logger          PSR logger.
     */
    public function __construct(
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container,
        private readonly IClientService $clientService,
        private readonly ICacheFactory $cacheFactory,
        private readonly IAppConfig $appConfig,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Resolve the weather reading for one placement, on behalf of one
     * viewing user. Never throws — every failure path returns either a
     * stale cached reading or the `{error: ...}` shape (REQ-WEATHER-002
     * "upstream failure degrades gracefully").
     *
     * @param integer $placementId The widget placement id.
     * @param string  $userId      The viewing user's UID (drives locale-derived units/language).
     *
     * @return array<string,mixed> `{location, tempValue, units, condition, conditionText, language, fetchedAt, stale}` or `{error: string}`.
     *
     * @spec openspec/specs/clock-weather-widgets/spec.md
     */
    public function resolveForPlacement(int $placementId, string $userId): array
    {
        try {
            $placement = $this->placementMapper->find(id: $placementId);
        } catch (Throwable $exception) {
            return ['error' => 'placement_not_found'];
        }

        $config   = $this->readPlacementConfig(placement: $placement);
        $location = trim(string: (string) ($config['location'] ?? ''));
        $override = (string) ($config['unitsOverride'] ?? '');

        $units    = $this->resolveUnits(userId: $userId, override: $override);
        $language = $this->resolveLanguage(userId: $userId);

        $cacheKey = $this->buildCacheKey(location: $location, units: $units, language: $language);
        $cache    = $this->getCache();
        $cached   = $this->readCache(cache: $cache, cacheKey: $cacheKey);

        if ($this->isCacheFresh(cached: $cached) === true) {
            return $this->publicShape(reading: $cached, stale: false);
        }

        $fresh = $this->fetchFresh(location: $location, units: $units, language: $language);
        if ($fresh !== null) {
            $fresh['fetchedAtTs'] = time();
            if ($cache !== null) {
                $cache->set(key: $cacheKey, value: json_encode($fresh), ttl: $this->cacheTtl());
            }

            return $this->publicShape(reading: $fresh, stale: false);
        }

        if ($cached !== null) {
            // Upstream failed but a previous reading exists — degrade
            // gracefully rather than error (REQ-WEATHER-002).
            return $this->publicShape(reading: $cached, stale: true);
        }

        return ['error' => 'weather_unavailable'];
    }//end resolveForPlacement()

    /**
     * Whether a cached reading exists and is still inside its TTL.
     *
     * A negative age (clock skew, or a reading stamped in the future) is
     * treated as stale so a bad timestamp can never pin a stale reading.
     *
     * @param array<string,mixed>|null $cached The cached reading, if any.
     *
     * @return bool True when `$cached` may be served as fresh.
     */
    private function isCacheFresh(?array $cached): bool
    {
        if ($cached === null) {
            return false;
        }

        $age = (time() - (int) ($cached['fetchedAtTs'] ?? 0));

        return $age >= 0 && $age < $this->cacheTtl();
    }//end isCacheFresh()

    /**
     * Fetch a fresh reading from whichever upstream suits the placement.
     *
     * With no author-configured location, the viewer's own weather_status
     * personal location is preferred; otherwise the configured provider URL
     * is used.
     *
     * @param string $location The author-configured location, or `''`.
     * @param string $units    `'metric'` or `'imperial'`.
     * @param string $language The resolved forecast language.
     *
     * @return array<string,mixed>|null The reading, or null when unavailable.
     */
    private function fetchFresh(string $location, string $units, string $language): ?array
    {
        if ($location === '') {
            return $this->fetchFromWeatherStatus(units: $units);
        }

        return $this->fetchFromProviderUrl(location: $location, units: $units, language: $language);
    }//end fetchFresh()

    /**
     * Read the placement's `{location, unitsOverride}` config, falling
     * back to the legacy `style_config.content` slot for pre-column rows
     * (mirrors {@see \OCA\LaunchPad\Controller\FilesWidgetController::loadConfig()}).
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
     * Derive the units to use: the author's explicit override when set,
     * otherwise metric/imperial from the viewing user's Nextcloud locale
     * (REQ-WEATHER-003).
     *
     * @param string $userId   The viewing user's UID.
     * @param string $override `metric`, `imperial`, or `''` (follow locale).
     *
     * @return string `metric` or `imperial`.
     */
    private function resolveUnits(string $userId, string $override): string
    {
        if ($override === 'metric' || $override === 'imperial') {
            return $override;
        }

        $locale = (string) $this->config->getUserValue(userId: $userId, appName: 'core', key: 'locale', default: '');
        if ($locale === '') {
            $locale = (string) $this->config->getSystemValue(key: 'default_locale', default: 'en');
        }

        $parts = preg_split(pattern: '/[_-]/', subject: $locale);
        if ($parts === false) {
            $parts = [];
        }

        $territory = strtoupper(string: (string) ($parts[1] ?? ''));

        if (in_array(needle: $territory, haystack: self::IMPERIAL_TERRITORIES, strict: true) === true) {
            return 'imperial';
        }

        return 'metric';
    }//end resolveUnits()

    /**
     * Derive the forecast language from the viewing user's Nextcloud
     * language preference (REQ-WEATHER-003), falling back to the system
     * default, then `en`.
     *
     * @param string $userId The viewing user's UID.
     *
     * @return string A two-letter (or `xx_YY`) language code.
     */
    private function resolveLanguage(string $userId): string
    {
        $lang = (string) $this->config->getUserValue(userId: $userId, appName: 'core', key: 'lang', default: '');
        if ($lang === '') {
            $lang = (string) $this->config->getSystemValue(key: 'default_language', default: 'en');
        }

        if ($lang !== '') {
            return $lang;
        }

        return 'en';
    }//end resolveLanguage()

    /**
     * Attempt to resolve a reading via the `weather_status` app's own
     * forecast service (REQ-WEATHER-002 "reuse weather_status when
     * present"). Returns `null` — never throws — when the app is
     * disabled, its service cannot be resolved, or the forecast call
     * fails or is empty, so the caller falls through to the provider-URL
     * path.
     *
     * @param string $units `metric` or `imperial` — met.no returns Celsius;
     *                      converted to Fahrenheit when `imperial`.
     *
     * @return array<string,mixed>|null `{location, tempValue, units, condition, conditionText, language}` or `null`.
     */
    private function fetchFromWeatherStatus(string $units): ?array
    {
        try {
            if ($this->appManager->isEnabledForUser(appId: 'weather_status') === false) {
                return null;
            }

            if ($this->container->has(id: self::WEATHER_STATUS_SERVICE_CLASS) === false) {
                return null;
            }

            $service = $this->container->get(id: self::WEATHER_STATUS_SERVICE_CLASS);
            if (method_exists(object_or_class: $service, method: 'getForecast') === false
                || method_exists(object_or_class: $service, method: 'getLocation') === false
            ) {
                return null;
            }

            $forecast = $service->getForecast();
            if (is_array(value: $forecast) === false || $forecast === [] || isset($forecast['error']) === true) {
                return null;
            }

            $locationInfo = $service->getLocation();
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'WeatherService: weather_status provider unavailable, falling back',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return null;
        }//end try

        return $this->normaliseMetNoForecast(forecast: $forecast, locationInfo: $locationInfo, units: $units);
    }//end fetchFromWeatherStatus()

    /**
     * Normalise met.no's compact-format timeseries (weather_status's
     * `getForecast()` return shape) into our reading shape, using the
     * nearest ("current") timeseries entry.
     *
     * @param array<int,mixed>    $forecast     The timeseries list.
     * @param array<string,mixed> $locationInfo weather_status's `getLocation()` result.
     * @param string              $units        `metric` or `imperial`.
     *
     * @return array<string,mixed>|null
     */
    private function normaliseMetNoForecast(array $forecast, array $locationInfo, string $units): ?array
    {
        $current = $forecast[0] ?? null;
        if (is_array(value: $current) === false) {
            return null;
        }

        $details = ($current['data']['instant']['details'] ?? null);
        if (is_array(value: $details) === false || isset($details['air_temperature']) === false) {
            return null;
        }

        $tempC = (float) $details['air_temperature'];
        $temp  = $tempC;
        if ($units === 'imperial') {
            $temp = (($tempC * 9 / 5) + 32);
        }

        $symbolCode = (string) (
            $current['data']['next_1_hours']['summary']['symbol_code'] ?? $current['data']['next_6_hours']['summary']['symbol_code'] ?? ''
        );
        $condition  = $this->mapMetNoSymbol(symbolCode: $symbolCode);

        $label = (string) ($locationInfo['address'] ?? '');
        if ($label === '') {
            $label = 'Current location';
        }

        return [
            'location'      => $label,
            'tempValue'     => $temp,
            'units'         => $units,
            'condition'     => $condition,
            'conditionText' => $this->humaniseCondition(code: $condition),
            // Met.no/weather_status carries no per-language forecast text —
            // the humanised fallback above is always English (REQ-WEATHER-003
            // "English MUST be the fallback when the provider has no
            // localisation"). `language` still reports what was requested so
            // the frontend never re-guesses.
            'language'      => 'en',
        ];
    }//end normaliseMetNoForecast()

    /**
     * Map a met.no `symbol_code` (e.g. `partlycloudy_day`) to our
     * normalised condition code, stripping the day/night/twilight suffix
     * before lookup.
     *
     * @param string $symbolCode The raw symbol code.
     *
     * @return string One of `self::METNO_CONDITION_MAP`'s values, or `unknown`.
     */
    private function mapMetNoSymbol(string $symbolCode): string
    {
        $prefix = preg_replace(pattern: '/_(day|night|polartwilight)$/', replacement: '', subject: $symbolCode);
        return self::METNO_CONDITION_MAP[$prefix] ?? 'unknown';
    }//end mapMetNoSymbol()

    /**
     * Fetch from the admin-configured fallback provider URL
     * (REQ-WEATHER-002 "Fallback to configurable provider URL"). Returns
     * `null` — never throws — on any failure: no template configured,
     * invalid scheme, transport error, non-2xx, or unparseable body.
     *
     * @param string $location The author-configured location string.
     * @param string $units    `metric` or `imperial`.
     * @param string $language The resolved forecast language.
     *
     * @return array<string,mixed>|null
     */
    private function fetchFromProviderUrl(string $location, string $units, string $language): ?array
    {
        $template = $this->appConfig->getValueString(
            app: Application::APP_ID,
            key: self::CONFIG_KEY_PROVIDER_URL,
            default: ''
        );
        if ($template === '') {
            return null;
        }

        $apiKey = $this->appConfig->getValueString(
            app: Application::APP_ID,
            key: self::CONFIG_KEY_PROVIDER_API_KEY,
            default: ''
        );

        $url = str_replace(
            search: ['{location}', '{apiKey}', '{units}', '{lang}'],
            replace: [rawurlencode(string: $location), rawurlencode(string: $apiKey), $units, $language],
            subject: $template
        );

        $scheme = strtolower(string: (string) parse_url(url: $url, component: PHP_URL_SCHEME));
        if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === false) {
            $this->logger->warning(
                message: 'WeatherService: provider URL has an invalid scheme, rejecting',
                context: ['app' => Application::APP_ID]
            );
            return null;
        }

        try {
            $client   = $this->clientService->newClient();
            $response = $client->get(
                    uri: $url,
                    options: [
                        'connect_timeout' => self::CONNECT_TIMEOUT,
                        'timeout'         => self::REQUEST_TIMEOUT,
                        'http_errors'     => false,
                // C5 (mirrors FeedRefreshService): no auto-redirect — a 3xx to
                // an unexpected host would bypass the scheme check above.
                        'allow_redirects' => false,
                    ]
                    );
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'WeatherService: provider fetch failed',
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

        return $this->normaliseProviderPayload(payload: $decoded, location: $location, units: $units, language: $language);
    }//end fetchFromProviderUrl()

    /**
     * Normalise an OpenWeatherMap-family JSON payload (the common default
     * shape for the configurable-provider fallback) into our reading
     * shape. Returns `null` when the payload carries no usable
     * temperature field.
     *
     * @param array<string,mixed> $payload  The decoded JSON body.
     * @param string              $location The configured location (label fallback).
     * @param string              $units    `metric` or `imperial`.
     * @param string              $language The resolved forecast language.
     *
     * @return array<string,mixed>|null
     */
    private function normaliseProviderPayload(array $payload, string $location, string $units, string $language): ?array
    {
        $temp = ($payload['main']['temp'] ?? ($payload['temp'] ?? null));
        if (is_numeric(value: $temp) === false) {
            return null;
        }

        $description = '';
        $condition   = 'unknown';
        $weather0    = ($payload['weather'][0] ?? null);
        if (is_array(value: $weather0) === true) {
            $description = (string) ($weather0['description'] ?? '');
            $main        = strtolower(string: (string) ($weather0['main'] ?? ''));
            $condition   = self::OWM_CONDITION_MAP[$main] ?? 'unknown';
        }

        $label = (string) ($payload['name'] ?? '');
        if ($label === '') {
            $label = $location;
        }

        $conditionText = $this->humaniseCondition(code: $condition);
        if ($description !== '') {
            $conditionText = ucfirst(string: $description);
        }

        return [
            'location'      => $label,
            'tempValue'     => (float) $temp,
            'units'         => $units,
            'condition'     => $condition,
            'conditionText' => $conditionText,
            'language'      => $language,
        ];
    }//end normaliseProviderPayload()

    /**
     * Title-cased English fallback description for a condition code, used
     * when a provider carries no localised description string
     * (REQ-WEATHER-003 "English MUST be the fallback").
     *
     * @param string $code One of the normalised condition codes.
     *
     * @return string A human-readable English label.
     */
    private function humaniseCondition(string $code): string
    {
        if ($code === 'unknown' || $code === '') {
            return 'Unknown conditions';
        }

        return ucwords(string: str_replace(search: '-', replace: ' ', subject: $code));
    }//end humaniseCondition()

    /**
     * Build the reading cache key — location + units + language, per
     * REQ-WEATHER-002 ("cached ... for the same location + units +
     * language").
     *
     * @param string $location The configured (or resolved) location.
     * @param string $units    `metric` or `imperial`.
     * @param string $language The resolved forecast language.
     *
     * @return string The cache key.
     */
    private function buildCacheKey(string $location, string $units, string $language): string
    {
        return 'reading_'.hash(algo: 'sha256', data: strtolower(string: $location).'|'.$units.'|'.$language);
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
        if (is_array(value: $decoded) === true) {
            return $decoded;
        }

        return null;
    }//end readCache()

    /**
     * Shape an internal reading array (which also carries the internal
     * `fetchedAtTs` unix timestamp) into the public response contract
     * (REQ-WEATHER-001): `{location, tempValue, units, condition,
     * conditionText, language, fetchedAt, stale}`. NEVER includes the
     * provider API key or URL.
     *
     * @param array<string,mixed> $reading The internal reading.
     * @param boolean             $stale   Whether this is a stale (cache-expired-but-served) reading.
     *
     * @return array<string,mixed>
     */
    private function publicShape(array $reading, bool $stale): array
    {
        $fetchedAtTs = (int) ($reading['fetchedAtTs'] ?? time());

        return [
            'location'      => (string) ($reading['location'] ?? ''),
            'tempValue'     => (float) ($reading['tempValue'] ?? 0),
            'units'         => (string) ($reading['units'] ?? 'metric'),
            'condition'     => (string) ($reading['condition'] ?? 'unknown'),
            'conditionText' => (string) ($reading['conditionText'] ?? ''),
            'language'      => (string) ($reading['language'] ?? 'en'),
            'fetchedAt'     => (new DateTime('@'.$fetchedAtTs))->format(format: DATE_ATOM),
            'stale'         => $stale,
        ];
    }//end publicShape()

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
            $this->cache = $this->cacheFactory->createDistributed(prefix: 'launchpad_weather_');
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'WeatherService: cache subsystem unavailable, falling back to direct fetch',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            $this->cache = null;
        }

        return $this->cache;
    }//end getCache()

    /**
     * Resolve the configured cache TTL in seconds, clamped to a sane
     * positive minimum.
     *
     * @return integer The TTL in seconds.
     */
    private function cacheTtl(): int
    {
        $ttl = $this->appConfig->getValueInt(
            app: Application::APP_ID,
            key: self::CONFIG_KEY_CACHE_TTL,
            default: self::DEFAULT_CACHE_TTL
        );

        if ($ttl > 0) {
            return $ttl;
        }

        return self::DEFAULT_CACHE_TTL;
    }//end cacheTtl()
}//end class
