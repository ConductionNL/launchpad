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
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
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
class WeatherService {
	use ReadsJsonCache;
	use ReadsPlacementContent;

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
		'clearsky' => 'clear',
		'fair' => 'clear',
		'partlycloudy' => 'partly-cloudy',
		'cloudy' => 'cloudy',
		'fog' => 'fog',
		'lightrain' => 'rain',
		'rain' => 'rain',
		'lightrainshowers' => 'rain',
		'rainshowers' => 'rain',
		'heavyrain' => 'heavy-rain',
		'heavyrainshowers' => 'heavy-rain',
		'lightsnow' => 'snow',
		'snow' => 'snow',
		'snowshowers' => 'snow',
		'heavysnow' => 'snow',
		'sleet' => 'snow',
		'thunder' => 'thunderstorm',
		'rainandthunder' => 'thunderstorm',
		'sleetandthunder' => 'thunderstorm',
	];

	/**
	 * OpenWeatherMap-family `weather[0].main` values mapped to our
	 * normalised `condition` codes (the common default shape for the
	 * configurable-provider-URL fallback).
	 *
	 * @var array<string,string>
	 */
	private const OWM_CONDITION_MAP = [
		'clear' => 'clear',
		'clouds' => 'cloudy',
		'mist' => 'fog',
		'fog' => 'fog',
		'haze' => 'fog',
		'drizzle' => 'rain',
		'rain' => 'rain',
		'snow' => 'snow',
		'thunderstorm' => 'thunderstorm',
		'tornado' => 'windy',
		'squall' => 'windy',
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
	 * @param ReadingSources          $sources The app manager, container, HTTP client service and cache factory.
	 * @param WeatherSettings         $settings The app and system config.
	 * @param WeatherStore            $store The placement mapper and logger.
	 * @param ConnectionReporter|null $connectionReporter Tells integriq what a provider reading met, or
	 *                                                    nothing when absent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function __construct(
		private readonly ReadingSources $sources,
		private readonly WeatherSettings $settings,
		private readonly WeatherStore $store,
		private readonly ?ConnectionReporter $connectionReporter = null,
	) {
	}//end __construct()

	/**
	 * Resolve the weather reading for one placement, on behalf of one
	 * viewing user. Never throws — every failure path returns either a
	 * stale cached reading or the `{error: ...}` shape (REQ-WEATHER-002
	 * "upstream failure degrades gracefully").
	 *
	 * @param integer $placementId The widget placement id.
	 * @param string $userId The viewing user's UID (drives locale-derived units/language).
	 *
	 * @return array<string,mixed> `{location, tempValue, units, condition, conditionText, language, fetchedAt, stale}` or `{error: string}`.
	 *
	 * @spec openspec/specs/clock-weather-widgets/spec.md
	 */
	public function resolveForPlacement(int $placementId, string $userId): array {
		try {
			$placement = $this->store->placementMapper->find(id: $placementId);
		} catch (Throwable $exception) {
			return ['error' => 'placement_not_found'];
		}

		$config = $this->readPlacementConfig(placement: $placement);

		return $this->readingFor(query: $this->queryFor(config: $config, userId: $userId));
	}//end resolveForPlacement()

	/**
	 * Build the query for one placement config and viewer: the trimmed
	 * author location, the units and the forecast language.
	 *
	 * @param array<string,mixed> $config The placement config.
	 * @param string              $userId The viewing user's UID.
	 *
	 * @return WeatherQuery The query.
	 */
	private function queryFor(array $config, string $userId): WeatherQuery {
		$location = trim(string: (string)($config['location'] ?? ''));
		$override = (string)($config['unitsOverride'] ?? '');

		return new WeatherQuery(
			location: $location,
			units: $this->resolveUnits(userId: $userId, override: $override),
			language: $this->resolveLanguage(userId: $userId)
		);
	}//end queryFor()

	/**
	 * Serve the cached reading while it is fresh, otherwise fetch and
	 * cache a fresh one, falling back to the stale reading or the error
	 * shape when the upstream fails.
	 *
	 * @param WeatherQuery $query The query.
	 *
	 * @return array<string,mixed> The public reading or `{error: string}`.
	 */
	private function readingFor(WeatherQuery $query): array {
		$cacheKey = $this->buildCacheKey(query: $query);
		$cache = $this->getCache();
		$cached = $this->readCache(cache: $cache, cacheKey: $cacheKey);
		if ($this->isCacheFresh(cached: $cached) === true) {
			return $this->publicShape(reading: $cached, stale: false);
		}

		$fresh = $this->fetchFresh(query: $query);
		if ($fresh === null) {
			return $this->staleOrUnavailable(cached: $cached);
		}

		$fresh['fetchedAtTs'] = time();
		$cache?->set(key: $cacheKey, value: json_encode($fresh), ttl: $this->cacheTtl());

		return $this->publicShape(reading: $fresh, stale: false);
	}//end readingFor()

	/**
	 * Answer when the upstream failed: a previous reading, flagged stale,
	 * degrades gracefully rather than error (REQ-WEATHER-002).
	 *
	 * @param array<string,mixed>|null $cached The cached reading, if any.
	 *
	 * @return array<string,mixed> The stale reading or `{error: 'weather_unavailable'}`.
	 */
	private function staleOrUnavailable(?array $cached): array {
		if ($cached === null) {
			return ['error' => 'weather_unavailable'];
		}

		return $this->publicShape(reading: $cached, stale: true);
	}//end staleOrUnavailable()

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
	private function isCacheFresh(?array $cached): bool {
		if ($cached === null) {
			return false;
		}

		$age = (time() - (int)($cached['fetchedAtTs'] ?? 0));

		return $age >= 0 && $age < $this->cacheTtl();
	}//end isCacheFresh()

	/**
	 * Fetch a fresh reading from whichever upstream suits the placement.
	 *
	 * With no author-configured location, the viewer's own weather_status
	 * personal location is preferred; otherwise the configured provider URL
	 * is used.
	 *
	 * @param WeatherQuery $query The location (or `''`), units and language.
	 *
	 * @return array<string,mixed>|null The reading, or null when unavailable.
	 */
	private function fetchFresh(WeatherQuery $query): ?array {
		if ($query->location === '') {
			return $this->fetchFromWeatherStatus(units: $query->units);
		}

		return $this->fetchFromProviderUrl(query: $query);
	}//end fetchFresh()

	/**
	 * Derive the units to use: the author's explicit override when set,
	 * otherwise metric/imperial from the viewing user's Nextcloud locale
	 * (REQ-WEATHER-003).
	 *
	 * @param string $userId The viewing user's UID.
	 * @param string $override `metric`, `imperial`, or `''` (follow locale).
	 *
	 * @return string `metric` or `imperial`.
	 */
	private function resolveUnits(string $userId, string $override): string {
		if ($override === 'metric' || $override === 'imperial') {
			return $override;
		}

		$territory = $this->localeTerritory(userId: $userId);

		if (in_array(needle: $territory, haystack: self::IMPERIAL_TERRITORIES, strict: true) === true) {
			return 'imperial';
		}

		return 'metric';
	}//end resolveUnits()

	/**
	 * The upper-cased territory part of the viewing user's locale (the
	 * system default locale when the user has none), or `''`.
	 *
	 * @param string $userId The viewing user's UID.
	 *
	 * @return string The territory code, e.g. `US`.
	 */
	private function localeTerritory(string $userId): string {
		$locale = (string)$this->settings->config->getUserValue(userId: $userId, appName: 'core', key: 'locale', default: '');
		if ($locale === '') {
			$locale = (string)$this->settings->config->getSystemValue(key: 'default_locale', default: 'en');
		}

		$parts = preg_split(pattern: '/[_-]/', subject: $locale);
		if ($parts === false) {
			$parts = [];
		}

		return strtoupper(string: (string)($parts[1] ?? ''));
	}//end localeTerritory()

	/**
	 * Derive the forecast language from the viewing user's Nextcloud
	 * language preference (REQ-WEATHER-003), falling back to the system
	 * default, then `en`.
	 *
	 * @param string $userId The viewing user's UID.
	 *
	 * @return string A two-letter (or `xx_YY`) language code.
	 */
	private function resolveLanguage(string $userId): string {
		$lang = (string)$this->settings->config->getUserValue(userId: $userId, appName: 'core', key: 'lang', default: '');
		if ($lang === '') {
			$lang = (string)$this->settings->config->getSystemValue(key: 'default_language', default: 'en');
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
	private function fetchFromWeatherStatus(string $units): ?array {
		try {
			$service = $this->weatherStatusService();
			$forecast = $this->usableForecastOf(service: $service);
			if ($service === null || $forecast === null) {
				return null;
			}

			$locationInfo = $service->getLocation();
		} catch (Throwable $exception) {
			$this->logWeatherStatusFailure(exception: $exception);
			return null;
		}

		return $this->normaliseMetNoForecast(forecast: $forecast, locationInfo: $locationInfo, units: $units);
	}//end fetchFromWeatherStatus()

	/**
	 * The weather_status forecast service, when the app is enabled, its
	 * service resolves and it offers both `getForecast()` and
	 * `getLocation()`.
	 *
	 * @return object|null The service, or null when it cannot be used.
	 */
	private function weatherStatusService(): ?object {
		if ($this->sources->appManager->isEnabledForUser(appId: 'weather_status') === false) {
			return null;
		}

		if ($this->sources->container->has(id: self::WEATHER_STATUS_SERVICE_CLASS) === false) {
			return null;
		}

		$service = $this->sources->container->get(id: self::WEATHER_STATUS_SERVICE_CLASS);
		if ($this->offersForecast(service: $service) === false) {
			return null;
		}

		return $service;
	}//end weatherStatusService()

	/**
	 * Whether a resolved weather_status service offers both
	 * `getForecast()` and `getLocation()`.
	 *
	 * @param mixed $service The resolved service.
	 *
	 * @return bool True when both methods exist.
	 */
	private function offersForecast(mixed $service): bool {
		return method_exists(object_or_class: $service, method: 'getForecast') === true
			&& method_exists(object_or_class: $service, method: 'getLocation') === true;
	}//end offersForecast()

	/**
	 * The service's forecast when it is a non-empty list without an
	 * `error` key; null when there is no service or the answer is unusable.
	 *
	 * @param object|null $service The weather_status service, if usable.
	 *
	 * @return array<int|string,mixed>|null The forecast, or null.
	 */
	private function usableForecastOf(?object $service): ?array {
		if ($service === null) {
			return null;
		}

		$forecast = $service->getForecast();
		if ($this->isUsableForecast(forecast: $forecast) === false) {
			return null;
		}

		return $forecast;
	}//end usableForecastOf()

	/**
	 * Whether a weather_status forecast answer is a non-empty list without
	 * an `error` key.
	 *
	 * @param mixed $forecast The `getForecast()` answer.
	 *
	 * @return bool True when the forecast can be normalised.
	 */
	private function isUsableForecast(mixed $forecast): bool {
		return is_array(value: $forecast) === true && $forecast !== [] && isset($forecast['error']) === false;
	}//end isUsableForecast()

	/**
	 * Log that weather_status could not be used, so the provider path is
	 * tried next.
	 *
	 * @param Throwable $exception What went wrong.
	 *
	 * @return void
	 */
	private function logWeatherStatusFailure(Throwable $exception): void {
		$this->store->logger->info(
			message: 'WeatherService: weather_status provider unavailable, falling back',
			context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
		);
	}//end logWeatherStatusFailure()

	/**
	 * Normalise met.no's compact-format timeseries (weather_status's
	 * `getForecast()` return shape) into our reading shape, using the
	 * nearest ("current") timeseries entry.
	 *
	 * @param array<int,mixed> $forecast The timeseries list.
	 * @param array<string,mixed> $locationInfo weather_status's `getLocation()` result.
	 * @param string $units `metric` or `imperial`.
	 *
	 * @return array<string,mixed>|null
	 */
	private function normaliseMetNoForecast(array $forecast, array $locationInfo, string $units): ?array {
		$details = $this->metNoInstantDetails(current: ($forecast[0] ?? null));
		if ($details === null) {
			return null;
		}

		$condition = $this->mapMetNoSymbol(symbolCode: $this->metNoSymbolCode(current: $forecast[0]));

		return [
			'location' => $this->labelOr(label: (string)($locationInfo['address'] ?? ''), fallback: 'Current location'),
			'tempValue' => $this->celsiusIn(celsius: (float)$details['air_temperature'], units: $units),
			'units' => $units,
			'condition' => $condition,
			'conditionText' => $this->humaniseCondition(code: $condition),
			// Met.no/weather_status carries no per-language forecast text —
			// the humanised fallback above is always English (REQ-WEATHER-003
			// "English MUST be the fallback when the provider has no
			// localisation"). `language` still reports what was requested so
			// the frontend never re-guesses.
			'language' => 'en',
		];
	}//end normaliseMetNoForecast()

	/**
	 * The instant details of a met.no timeseries entry, when the entry is
	 * an array whose details carry an air temperature.
	 *
	 * @param mixed $current The timeseries entry.
	 *
	 * @return array<string,mixed>|null The details, or null when unusable.
	 */
	private function metNoInstantDetails(mixed $current): ?array {
		if (is_array(value: $current) === false) {
			return null;
		}

		$details = ($current['data']['instant']['details'] ?? null);
		if (is_array(value: $details) === false || isset($details['air_temperature']) === false) {
			return null;
		}

		return $details;
	}//end metNoInstantDetails()

	/**
	 * The next-hour symbol code of a met.no timeseries entry, falling back
	 * to the next-six-hours one, then `''`.
	 *
	 * @param array<string,mixed> $current The timeseries entry.
	 *
	 * @return string The raw symbol code.
	 */
	private function metNoSymbolCode(array $current): string {
		return (string)(
			$current['data']['next_1_hours']['summary']['symbol_code'] ?? $current['data']['next_6_hours']['summary']['symbol_code'] ?? ''
		);
	}//end metNoSymbolCode()

	/**
	 * A Celsius temperature in the requested units.
	 *
	 * @param float  $celsius The temperature in Celsius.
	 * @param string $units   `metric` or `imperial`.
	 *
	 * @return float Celsius, or Fahrenheit when `imperial`.
	 */
	private function celsiusIn(float $celsius, string $units): float {
		if ($units === 'imperial') {
			return (($celsius * 9 / 5) + 32);
		}

		return $celsius;
	}//end celsiusIn()

	/**
	 * A location label, or the fallback when it is empty.
	 *
	 * @param string $label    The label the upstream gave.
	 * @param string $fallback The label to use when it gave none.
	 *
	 * @return string The label to show.
	 */
	private function labelOr(string $label, string $fallback): string {
		if ($label === '') {
			return $fallback;
		}

		return $label;
	}//end labelOr()

	/**
	 * Map a met.no `symbol_code` (e.g. `partlycloudy_day`) to our
	 * normalised condition code, stripping the day/night/twilight suffix
	 * before lookup.
	 *
	 * @param string $symbolCode The raw symbol code.
	 *
	 * @return string One of `self::METNO_CONDITION_MAP`'s values, or `unknown`.
	 */
	private function mapMetNoSymbol(string $symbolCode): string {
		$prefix = preg_replace(pattern: '/_(day|night|polartwilight)$/', replacement: '', subject: $symbolCode);
		return self::METNO_CONDITION_MAP[$prefix] ?? 'unknown';
	}//end mapMetNoSymbol()

	/**
	 * Fetch from the admin-configured fallback provider URL
	 * (REQ-WEATHER-002 "Fallback to configurable provider URL"). Returns
	 * `null` — never throws — on any failure: no template configured,
	 * invalid scheme, transport error, non-2xx, or unparseable body.
	 *
	 * What the fetch met is reported to integriq's connection registry, at
	 * most once an hour while it stays the same (adopt-connection-registry).
	 * The report never changes the reading.
	 *
	 * @param WeatherQuery $query The author-configured location, units and language.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	private function fetchFromProviderUrl(WeatherQuery $query): ?array {
		$url = $this->providerUrl(query: $query);
		if ($url === null) {
			return null;
		}

		$response = $this->sendProviderRequest(url: $url);
		if ($response === null) {
			return null;
		}

		return $this->readProviderResponse(response: $response, call: new WeatherProviderCall(url: $url, query: $query));
	}//end fetchFromProviderUrl()

	/**
	 * The provider URL for a query: the configured template with the
	 * location, API key, units and language filled in. Null, reported,
	 * when no template is configured or the URL is not http(s).
	 *
	 * @param WeatherQuery $query The query.
	 *
	 * @return string|null The URL, or null when there is none to call.
	 */
	private function providerUrl(WeatherQuery $query): ?string {
		$template = $this->providerSetting(key: self::CONFIG_KEY_PROVIDER_URL);
		if ($template === '') {
			$this->connectionReporter?->reportWeatherNotConfigured();
			return null;
		}

		$url = $this->expandProviderTemplate(template: $template, query: $query);
		if ($this->rejectsScheme(url: $url) === true) {
			return null;
		}

		return $url;
	}//end providerUrl()

	/**
	 * Read one provider setting from the app config, `''` when unset.
	 *
	 * @param string $key The config key.
	 *
	 * @return string The value.
	 */
	private function providerSetting(string $key): string {
		return $this->settings->appConfig->getValueString(
			app: Application::APP_ID,
			key: $key,
			default: ''
		);
	}//end providerSetting()

	/**
	 * Fill the provider template's `{location}`, `{apiKey}`, `{units}` and
	 * `{lang}` placeholders.
	 *
	 * @param string       $template The configured template.
	 * @param WeatherQuery $query    The query.
	 *
	 * @return string The URL.
	 */
	private function expandProviderTemplate(string $template, WeatherQuery $query): string {
		$apiKey = $this->providerSetting(key: self::CONFIG_KEY_PROVIDER_API_KEY);

		return str_replace(
			search: ['{location}', '{apiKey}', '{units}', '{lang}'],
			replace: [rawurlencode(string: $query->location), rawurlencode(string: $apiKey), $query->units, $query->language],
			subject: $template
		);
	}//end expandProviderTemplate()

	/**
	 * Whether a provider URL is refused for not being http(s); a refusal
	 * is logged and reported.
	 *
	 * @param string $url The expanded provider URL.
	 *
	 * @return bool True when the URL must not be called.
	 */
	private function rejectsScheme(string $url): bool {
		$scheme = strtolower(string: (string)parse_url(url: $url, component: PHP_URL_SCHEME));
		if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === true) {
			return false;
		}

		$this->store->logger->warning(
			message: 'WeatherService: provider URL has an invalid scheme, rejecting',
			context: ['app' => Application::APP_ID]
		);
		$this->connectionReporter?->reportWeatherInvalidUrl();

		return true;
	}//end rejectsScheme()

	/**
	 * Send the provider GET; a transport failure is logged, reported
	 * without a status and answered with null.
	 *
	 * @param string $url The provider URL.
	 *
	 * @return IResponse|null The response, or null when the call failed.
	 */
	private function sendProviderRequest(string $url): ?IResponse {
		try {
			$client = $this->sources->clientService->newClient();
			return (new NoRedirectGet(connectTimeout: self::CONNECT_TIMEOUT, timeout: self::REQUEST_TIMEOUT))
				->send(client: $client, url: $url);
		} catch (Throwable $exception) {
			$this->store->logger->info(
				message: 'WeatherService: provider fetch failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_WEATHER, url: $url, httpStatus: null);
			return null;
		}
	}//end sendProviderRequest()

	/**
	 * Turn a provider response into a reading, reporting what the call
	 * met: a non-2xx status, an unreadable body, or the successful call.
	 *
	 * @param IResponse           $response The provider response.
	 * @param WeatherProviderCall $call     The URL and query of the call.
	 *
	 * @return array<string,mixed>|null The reading, or null.
	 */
	private function readProviderResponse(IResponse $response, WeatherProviderCall $call): ?array {
		$status = (int)$response->getStatusCode();
		if ($status < 200 || $status >= 300) {
			$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_WEATHER, url: $call->url, httpStatus: $status);
			return null;
		}

		$reading = $this->readProviderBody(body: (string)$response->getBody(), query: $call->query);
		if ($reading === null) {
			$this->connectionReporter?->reportWeatherUnreadable(url: $call->url);
			return null;
		}

		$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_WEATHER, url: $call->url, httpStatus: $status);

		return $reading;
	}//end readProviderResponse()

	/**
	 * Decode a provider body and normalise it, null when it is not a JSON
	 * object or carries no usable temperature.
	 *
	 * @param string       $body  The response body.
	 * @param WeatherQuery $query The query.
	 *
	 * @return array<string,mixed>|null The reading, or null.
	 */
	private function readProviderBody(string $body, WeatherQuery $query): ?array {
		$decoded = json_decode(json: $body, associative: true);
		if (is_array(value: $decoded) === false) {
			return null;
		}

		return $this->normaliseProviderPayload(payload: $decoded, query: $query);
	}//end readProviderBody()

	/**
	 * Normalise an OpenWeatherMap-family JSON payload (the common default
	 * shape for the configurable-provider fallback) into our reading
	 * shape. Returns `null` when the payload carries no usable
	 * temperature field.
	 *
	 * @param array<string,mixed> $payload The decoded JSON body.
	 * @param WeatherQuery        $query   The configured location (label fallback), units and language.
	 *
	 * @return array<string,mixed>|null
	 */
	private function normaliseProviderPayload(array $payload, WeatherQuery $query): ?array {
		if (is_numeric(value: $this->providerTemperature(payload: $payload)) === false) {
			return null;
		}

		return $this->providerReading(payload: $payload, query: $query);
	}//end normaliseProviderPayload()

	/**
	 * The temperature field of a provider payload: `main.temp`, else `temp`.
	 *
	 * @param array<string,mixed> $payload The decoded JSON body.
	 *
	 * @return mixed The raw temperature, or null.
	 */
	private function providerTemperature(array $payload): mixed {
		return ($payload['main']['temp'] ?? ($payload['temp'] ?? null));
	}//end providerTemperature()

	/**
	 * Build the reading of a provider payload that carries a temperature.
	 *
	 * @param array<string,mixed> $payload The decoded JSON body.
	 * @param WeatherQuery        $query   The query.
	 *
	 * @return array<string,mixed> The reading.
	 */
	private function providerReading(array $payload, WeatherQuery $query): array {
		$weather0 = $this->firstWeatherEntry(payload: $payload);
		$condition = self::OWM_CONDITION_MAP[strtolower(string: (string)($weather0['main'] ?? ''))] ?? 'unknown';

		return [
			'location' => $this->labelOr(label: (string)($payload['name'] ?? ''), fallback: $query->location),
			'tempValue' => (float)$this->providerTemperature(payload: $payload),
			'units' => $query->units,
			'condition' => $condition,
			'conditionText' => $this->providerConditionText(description: (string)($weather0['description'] ?? ''), condition: $condition),
			'language' => $query->language,
		];
	}//end providerReading()

	/**
	 * The first `weather` entry of a provider payload, `[]` when it has none.
	 *
	 * @param array<string,mixed> $payload The decoded JSON body.
	 *
	 * @return array<string,mixed> The entry.
	 */
	private function firstWeatherEntry(array $payload): array {
		$weather0 = ($payload['weather'][0] ?? null);
		if (is_array(value: $weather0) === false) {
			return [];
		}

		return $weather0;
	}//end firstWeatherEntry()

	/**
	 * The provider's own description, sentence-cased, or the English
	 * fallback for the condition when it gave none.
	 *
	 * @param string $description The provider description, or `''`.
	 * @param string $condition   The normalised condition code.
	 *
	 * @return string The condition text.
	 */
	private function providerConditionText(string $description, string $condition): string {
		if ($description !== '') {
			return ucfirst(string: $description);
		}

		return $this->humaniseCondition(code: $condition);
	}//end providerConditionText()

	/**
	 * Title-cased English fallback description for a condition code, used
	 * when a provider carries no localised description string
	 * (REQ-WEATHER-003 "English MUST be the fallback").
	 *
	 * @param string $code One of the normalised condition codes.
	 *
	 * @return string A human-readable English label.
	 */
	private function humaniseCondition(string $code): string {
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
	 * @param WeatherQuery $query The configured (or resolved) location, units and language.
	 *
	 * @return string The cache key.
	 */
	private function buildCacheKey(WeatherQuery $query): string {
		return 'reading_' . hash(algo: 'sha256', data: strtolower(string: $query->location) . '|' . $query->units . '|' . $query->language);
	}//end buildCacheKey()

	/**
	 * Shape an internal reading array (which also carries the internal
	 * `fetchedAtTs` unix timestamp) into the public response contract
	 * (REQ-WEATHER-001): `{location, tempValue, units, condition,
	 * conditionText, language, fetchedAt, stale}`. NEVER includes the
	 * provider API key or URL.
	 *
	 * @param array<string,mixed> $reading The internal reading.
	 * @param boolean $stale Whether this is a stale (cache-expired-but-served) reading.
	 *
	 * @return array<string,mixed>
	 */
	private function publicShape(array $reading, bool $stale): array {
		$fetchedAtTs = (int)($reading['fetchedAtTs'] ?? time());

		return [
			'location' => (string)($reading['location'] ?? ''),
			'tempValue' => (float)($reading['tempValue'] ?? 0),
			'units' => (string)($reading['units'] ?? 'metric'),
			'condition' => (string)($reading['condition'] ?? 'unknown'),
			'conditionText' => (string)($reading['conditionText'] ?? ''),
			'language' => (string)($reading['language'] ?? 'en'),
			'fetchedAt' => (new DateTime('@' . $fetchedAtTs))->format(format: DATE_ATOM),
			'stale' => $stale,
		];
	}//end publicShape()

	/**
	 * Lazily resolve the distributed cache. Returns `null` when the cache
	 * subsystem is unavailable (e.g. unit tests with a stub factory).
	 *
	 * @return ICache|null
	 */
	private function getCache(): ?ICache {
		if ($this->cache !== null) {
			return $this->cache;
		}

		try {
			$this->cache = $this->sources->cacheFactory->createDistributed(prefix: 'launchpad_weather_');
		} catch (Throwable $exception) {
			$this->store->logger->info(
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
	private function cacheTtl(): int {
		$ttl = $this->settings->appConfig->getValueInt(
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
