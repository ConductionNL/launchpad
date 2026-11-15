<?php

/**
 * LiveTileService
 *
 * Resolves a `livetile` widget placement's configured data source to a
 * value, server-side (REQ-LIVETILE-003). Two source modes:
 *
 *  - `connector` — Integriq's `dashboard-http-datasource` capability is
 *    called through its documented runtime resolve API, ONLY when a
 *    capability probe confirms the app is installed AND the expected
 *    service is resolvable. This file never statically imports an Integriq
 *    class (REQ-LIVETILE-005 "No direct class dependency") — the FQCN is
 *    referenced only as a string, exactly mirroring
 *    {@see WeatherService::WEATHER_STATUS_SERVICE_CLASS}'s reuse of the
 *    optional `weather_status` app. Both the id and the namespace are
 *    resolved through {@see FleetAppId} rather than hardcoded, because the
 *    fleet rename moved both and an instance in the field may still be on
 *    either name.
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
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCA\LaunchPad\Support\FleetAppId;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use Throwable;

/**
 * Service for resolving, caching, formatting, and badging live-data tile
 * readings.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Combines dual-source
 *     resolution (Integriq leaf + allow-listed direct GET), JSONPath-
 *     lite extraction, formatting, badge thresholding, caching, and
 *     stale-fallback in one cohesive unit — mirrors WeatherService's shape
 *     for the same class of capability.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   Same cause as the complexity
 *     above: the dual-source resolution necessarily reaches Integriq, the
 *     HTTP client, the cache and the config. The collaborators are the feature.
 * @spec                                             openspec/specs/live-data-tile-widget/spec.md
 */
class LiveTileService {
	use ReadsJsonCache;
	use ReadsPlacementContent;

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
	 * Canonical name of the optional connector leaf. Never used as a literal
	 * app id: {@see FleetAppId} maps it onto whichever of `integriq` /
	 * `openconnector` this instance actually registered, so the probe works
	 * on a migrated instance and on one still running an older release.
	 *
	 * @var string
	 */
	private const CONNECTOR_APP = 'integriq';

	/**
	 * Class name of the connector's dashboard data-source resolver, RELATIVE
	 * to the app's PSR-4 root and referenced only as a string so this file
	 * never hard-requires the class to exist (REQ-LIVETILE-005 "No direct
	 * class dependency"). {@see FleetAppId::classCandidates()} prefixes it
	 * with `OCA\Integriq` and then `OCA\OpenConnector`.
	 *
	 * Verified against integriq `development` (2026-09-09): the class lives
	 * at `lib/Service/Datasource/DashboardDatasourceService.php` and shipped
	 * under `OCA\OpenConnector\Service\Datasource` before the rename. The
	 * previous value here named `Service\DashboardDataSourceService` — an
	 * extra namespace segment short and a capital `S` out — which has never
	 * existed under either name.
	 *
	 * @var string
	 */
	private const CONNECTOR_DATASOURCE_SERVICE_CLASS = 'Service\\Datasource\\DashboardDatasourceService';

	/**
	 * Method the connector's data-source resolver exposes:
	 * `resolve(string $sourceId, string $valueExpr, array $params = [], ?int $ttl = null): array{value: mixed, fetchedAt: string, stale: bool}`.
	 * Guarded with `method_exists()` before every call — a shape mismatch
	 * degrades to "source unavailable" rather than a fatal error.
	 *
	 * @var string
	 */
	private const CONNECTOR_DATASOURCE_METHOD = 'resolve';

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
	 * Maps a source node onto a list, table or key-value display.
	 *
	 * @var LiveTileListMapper
	 */
	private readonly LiveTileListMapper $lists;

	/**
	 * Constructor.
	 *
	 * @param ReadingSources          $sources The app manager, container, HTTP client service and cache factory.
	 * @param LiveTileSettings        $settings The app config and logger.
	 * @param WidgetPlacementMapper   $placementMapper Resolves placements by id.
	 * @param ConnectionReporter|null $connectionReporter Tells integriq what a direct-URL fetch met, or
	 *                                                    nothing when absent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function __construct(
		private readonly ReadingSources $sources,
		private readonly LiveTileSettings $settings,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly ?ConnectionReporter $connectionReporter = null,
	) {
		$this->lists = new LiveTileListMapper();
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
	public function resolveForPlacement(int $placementId): array {
		try {
			$placement = $this->placementMapper->find(id: $placementId);
		} catch (Throwable $exception) {
			return ['error' => 'placement_not_found'];
		}

		return $this->valueFor(placementId: $placementId, config: $this->readPlacementConfig(placement: $placement));
	}//end resolveForPlacement()

	/**
	 * Serve the cached value while it is inside the tile's refresh
	 * interval, otherwise fetch and cache a fresh one, falling back to
	 * the stale value or the null/stale shape when the fetch fails.
	 *
	 * @param integer             $placementId The widget placement id.
	 * @param array<string,mixed> $config      The placement's resolved config.
	 *
	 * @return array<string,mixed> `{value, formatted, badge, fetchedAt, stale}`.
	 */
	private function valueFor(int $placementId, array $config): array {
		$cacheKey = $this->buildCacheKey(placementId: $placementId, config: $config);
		$cache = $this->getCache();
		$cached = $this->readCache(cache: $cache, cacheKey: $cacheKey);
		if ($this->isFresh(cached: $cached, config: $config) === true) {
			return $this->publicShape(reading: $cached, config: $config, stale: false);
		}

		$fresh = $this->mapped(reading: $this->fetchFresh(config: $config), config: $config);
		if ($fresh === null) {
			return $this->staleOrEmpty(cached: $cached, config: $config);
		}

		$fresh['fetchedAtTs'] = time();
		$cache?->set(key: $cacheKey, value: json_encode($fresh), ttl: $this->refreshOf(config: $config));

		return $this->publicShape(reading: $fresh, config: $config, stale: false);
	}//end valueFor()

	/**
	 * A reading in the shape that is cached: for a list, table or key-value
	 * tile only the mapped fields, so the raw response is neither cached nor
	 * sent to the browser (REQ-LTC-002). A single-value tile is unchanged.
	 *
	 * @param array<string,mixed>|null $reading The fresh reading, or null when the fetch failed.
	 * @param array<string,mixed>      $config  The tile config.
	 *
	 * @return array<string,mixed>|null The reading to cache, or null when it does not fit the display.
	 *
	 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-mapping-happens-on-the-server-and-drops-unmapped-data-req-ltc-002
	 */
	private function mapped(?array $reading, array $config): ?array {
		if ($reading === null || $this->lists->displayOf(config: $config) === 'value') {
			return $reading;
		}

		$mapped = $this->lists->map(
			node: ($reading['rawValue'] ?? null),
			config: $config,
			extract: fn (mixed $item, string $expr): mixed => $this->extractValue(data: $item, expr: $expr)
		);
		if ($mapped === null) {
			return null;
		}

		return ['rawValue' => null, 'mapped' => $mapped];
	}//end mapped()

	/**
	 * The tile's effective refresh interval in seconds.
	 *
	 * @param array<string,mixed> $config The placement's resolved config.
	 *
	 * @return integer The clamped interval.
	 */
	private function refreshOf(array $config): int {
		return $this->clampRefresh(seconds: (int)($config['refresh'] ?? 0));
	}//end refreshOf()

	/**
	 * Whether a cached reading exists and is younger than the tile's
	 * refresh interval; a future stamp counts as stale.
	 *
	 * @param array<string,mixed>|null $cached The cached reading, if any.
	 * @param array<string,mixed>      $config The placement's resolved config.
	 *
	 * @return bool True when the cached reading may be served as fresh.
	 */
	private function isFresh(?array $cached, array $config): bool {
		if ($cached === null) {
			return false;
		}

		$age = (time() - (int)($cached['fetchedAtTs'] ?? 0));

		return $age >= 0 && $age < $this->refreshOf(config: $config);
	}//end isFresh()

	/**
	 * Answer when the fetch failed: the previous value flagged stale, or
	 * the null/stale shape when there is none.
	 *
	 * @param array<string,mixed>|null $cached The cached reading, if any.
	 * @param array<string,mixed>      $config The placement's resolved config.
	 *
	 * @return array<string,mixed> `{value, formatted, badge, fetchedAt, stale}`.
	 */
	private function staleOrEmpty(?array $cached, array $config): array {
		if ($cached !== null) {
			// Upstream failed (or the allow-list/capability probe refused
			// the fetch) but a previous value exists — degrade gracefully
			// rather than error (REQ-LIVETILE-003).
			return $this->publicShape(reading: $cached, config: $config, stale: true);
		}

		return [
			'value' => null,
			'formatted' => null,
			'badge' => null,
			'fetchedAt' => null,
			'stale' => true,
		];
	}//end staleOrEmpty()

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
	public function validateSourceConfig(array $config): array {
		$sourceMode = (string)($config['sourceMode'] ?? '');

		if (in_array(needle: $sourceMode, haystack: ['connector', 'url'], strict: true) === false) {
			return ['invalid_source_mode'];
		}

		if ($sourceMode === 'url') {
			$errors = $this->urlConfigErrors(url: trim(string: (string)($config['url'] ?? '')));
		} else {
			$errors = $this->connectorConfigErrors(config: $config);
		}

		// REQ-LTC-001: the display, mapping and limit, checked for both modes.
		return array_merge($errors, $this->lists->configErrors(config: $config));
	}//end validateSourceConfig()

	/**
	 * The save-time error for a `url`-mode source: missing, not http(s),
	 * or a host off the allow-list.
	 *
	 * @param string $url The trimmed configured URL.
	 *
	 * @return string[] At most one error code.
	 */
	private function urlConfigErrors(string $url): array {
		if ($url === '') {
			return ['url_required'];
		}

		if (HostAllowList::hasHttpScheme(url: $url) === false) {
			return ['invalid_url'];
		}

		if ($this->isHostAllowed(url: $url) === false) {
			// FAIL-CLOSED (REQ-LIVETILE-002 "rejected at save time").
			return ['host_not_allowed'];
		}

		return [];
	}//end urlConfigErrors()

	/**
	 * The save-time errors for a `connector`-mode source: a missing
	 * source id and an unavailable connector capability.
	 *
	 * @param array<string,mixed> $config The candidate config.
	 *
	 * @return string[] The error codes.
	 */
	private function connectorConfigErrors(array $config): array {
		$errors = [];
		if (trim(string: (string)($config['sourceId'] ?? '')) === '') {
			$errors[] = 'source_id_required';
		}

		if ($this->isConnectorAvailable() === false) {
			$errors[] = 'connector_unavailable';
		}

		return $errors;
	}//end connectorConfigErrors()

	/**
	 * Whether Integriq's `dashboard-http-datasource` capability is
	 * currently resolvable — app enabled AND the expected service present
	 * in the container. Never throws (REQ-LIVETILE-005).
	 *
	 * @return boolean
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) FleetAppId is a stateless resolver.
	 *
	 * @spec openspec/specs/live-data-tile-widget/spec.md
	 */
	public function isConnectorAvailable(): bool {
		try {
			$enabled = FleetAppId::isEnabledForUser(
				appManager: $this->sources->appManager,
				canonical: self::CONNECTOR_APP
			);
			if ($enabled === false) {
				return false;
			}

			return FleetAppId::hasService(
				container: $this->sources->container,
				canonical: self::CONNECTOR_APP,
				relative: self::CONNECTOR_DATASOURCE_SERVICE_CLASS
			);
		} catch (Throwable $exception) {
			$this->settings->logger->info(
				message: 'LiveTileService: connector capability probe failed, treating as absent',
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
	private function fetchFresh(array $config): ?array {
		$sourceMode = (string)($config['sourceMode'] ?? 'url');

		if ($sourceMode === 'connector') {
			return $this->fetchFromConnector(config: $config);
		}

		if ($sourceMode === 'url') {
			return $this->fetchFromUrl(config: $config);
		}

		return null;
	}//end fetchFresh()

	/**
	 * Resolve via Integriq's `dashboard-http-datasource` capability
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
	private function fetchFromConnector(array $config): ?array {
		if ($this->isConnectorAvailable() === false) {
			return null;
		}

		$sourceId = (string)($config['sourceId'] ?? '');
		$valueExpr = (string)($config['valueExpr'] ?? '');
		if ($sourceId === '') {
			return null;
		}

		return $this->connectorReading(result: $this->callConnector(sourceId: $sourceId, valueExpr: $valueExpr));
	}//end fetchFromConnector()

	/**
	 * The reading of a connector answer that carries a `value` key.
	 *
	 * @param mixed $result The connector's answer.
	 *
	 * @return array<string,mixed>|null `{rawValue: mixed}` or `null`.
	 */
	private function connectorReading(mixed $result): ?array {
		if (is_array(value: $result) === false || array_key_exists(key: 'value', array: $result) === false) {
			return null;
		}

		return ['rawValue' => $result['value']];
	}//end connectorReading()

	/**
	 * Call the connector's resolve method; null when the service or its
	 * method is absent, or the call fails (logged).
	 *
	 * @param string $sourceId  The connector source id.
	 * @param string $valueExpr The value expression.
	 *
	 * @return mixed The connector's answer, or null.
	 */
	private function callConnector(string $sourceId, string $valueExpr): mixed {
		try {
			$service = $this->connectorService();
			if ($service === null || method_exists(object_or_class: $service, method: self::CONNECTOR_DATASOURCE_METHOD) === false) {
				return null;
			}

			return $service->{self::CONNECTOR_DATASOURCE_METHOD}($sourceId, $valueExpr);
		} catch (Throwable $exception) {
			$this->settings->logger->info(
				message: 'LiveTileService: connector resolve call failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			return null;
		}
	}//end callConnector()

	/**
	 * The connector's datasource service, under whichever id the
	 * connector app is installed.
	 *
	 * @return object|null The service, or null when none resolves.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) FleetAppId is a stateless resolver.
	 */
	private function connectorService(): ?object {
		return FleetAppId::getService(
			container: $this->sources->container,
			canonical: self::CONNECTOR_APP,
			relative: self::CONNECTOR_DATASOURCE_SERVICE_CLASS
		);
	}//end connectorService()

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
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	private function fetchFromUrl(array $config): ?array {
		$url = trim(string: (string)($config['url'] ?? ''));
		if ($this->isFetchableUrl(url: $url) === false) {
			return null;
		}

		$response = $this->sendTileRequest(url: $url);
		if ($response === null) {
			return null;
		}

		if ($this->acceptsStatus(status: (int)$response->getStatusCode(), url: $url) === false) {
			return null;
		}

		return $this->valueFromBody(body: (string)$response->getBody(), config: $config);
	}//end fetchFromUrl()

	/**
	 * Whether a configured URL may be fetched: non-empty, http(s), and on
	 * the allow-list. A host off the allow-list is logged and reported.
	 *
	 * @param string $url The trimmed configured URL.
	 *
	 * @return bool True when the GET may be sent.
	 */
	private function isFetchableUrl(string $url): bool {
		if ($url === '' || HostAllowList::hasHttpScheme(url: $url) === false) {
			return false;
		}

		if ($this->isHostAllowed(url: $url) === false) {
			$this->reportRefusedHost();
			return false;
		}

		return true;
	}//end isFetchableUrl()

	/**
	 * Log and report a fetch refused for a host off the allow-list.
	 *
	 * @return void
	 */
	private function reportRefusedHost(): void {
		$this->settings->logger->warning(
			message: 'LiveTileService: host not on livetile_allowed_hosts, refusing fetch (fail-closed)',
			context: ['app' => Application::APP_ID]
		);
		$this->connectionReporter?->reportAllowListRefusal(key: ConnectionReporter::KEY_LIVE_TILES);
	}//end reportRefusedHost()

	/**
	 * Send the tile's GET; a transport failure is logged, reported without
	 * a status and answered with null.
	 *
	 * @param string $url The allow-listed URL.
	 *
	 * @return IResponse|null The response, or null when the call failed.
	 */
	private function sendTileRequest(string $url): ?IResponse {
		try {
			$client = $this->sources->clientService->newClient();
			return (new NoRedirectGet(connectTimeout: self::CONNECT_TIMEOUT, timeout: self::REQUEST_TIMEOUT))
				->send(client: $client, url: $url);
		} catch (Throwable $exception) {
			$this->settings->logger->info(
				message: 'LiveTileService: direct-URL fetch failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_LIVE_TILES, url: $url, httpStatus: null);
			return null;
		}
	}//end sendTileRequest()

	/**
	 * Report the call with its status, then tell whether the status is 2xx.
	 *
	 * @param integer $status The HTTP status.
	 * @param string  $url    The URL called.
	 *
	 * @return bool True for a 2xx status.
	 */
	private function acceptsStatus(int $status, string $url): bool {
		$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_LIVE_TILES, url: $url, httpStatus: $status);

		return $status >= 200 && $status < 300;
	}//end acceptsStatus()

	/**
	 * Decode a response body and extract the tile's value from it.
	 *
	 * @param string              $body   The response body.
	 * @param array<string,mixed> $config The placement's resolved config (`valueExpr`).
	 *
	 * @return array<string,mixed>|null `{rawValue: mixed}` or `null`.
	 */
	private function valueFromBody(string $body, array $config): ?array {
		$decoded = json_decode(json: $body, associative: true);
		if (is_array(value: $decoded) === false) {
			return null;
		}

		$value = $this->extractValue(data: $decoded, expr: (string)($config['valueExpr'] ?? ''));
		if ($value === null) {
			return null;
		}

		return ['rawValue' => $value];
	}//end valueFromBody()

	/**
	 * Extract a value from a decoded JSON structure via a JSONPath-lite
	 * expression — supports `$.a.b` (property access) and `$.a[0].b`
	 * (numeric index access). No arbitrary code is evaluated. Returns
	 * `null` on any malformed expression, missing key, or out-of-range
	 * index.
	 *
	 * @param mixed $data The decoded JSON value (array/scalar tree).
	 * @param string $expr The JSONPath-lite expression, e.g. `$.data.open_count`.
	 *
	 * @return mixed|null The extracted value, or `null`.
	 */
	private function extractValue(mixed $data, string $expr): mixed {
		$expr = trim(string: $expr);
		if ($expr === '' || $expr[0] !== '$') {
			return null;
		}

		$rest = substr(string: $expr, offset: 1);
		if ($rest === '') {
			return $data;
		}

		$tokens = $this->tokenisePath(path: $rest);
		if ($tokens === null) {
			return null;
		}

		return $this->walkPath(data: $data, tokens: $tokens);
	}//end extractValue()

	/**
	 * Follow path tokens down a decoded JSON tree; null as soon as a step
	 * meets a non-array or a missing key.
	 *
	 * @param mixed                          $data   The decoded JSON value.
	 * @param array<int, array<int, string>> $tokens The tokenised path.
	 *
	 * @return mixed The value at the end of the path, or null.
	 */
	private function walkPath(mixed $data, array $tokens): mixed {
		$current = $data;
		foreach ($tokens as $token) {
			$key = $this->tokenKey(token: $token);
			if (is_array(value: $current) === false || array_key_exists(key: $key, array: $current) === false) {
				return null;
			}

			$current = $current[$key];
		}

		return $current;
	}//end walkPath()

	/**
	 * The array key one path token addresses: the integer of a `[123]`
	 * token, or the property name of a `.prop` token.
	 *
	 * @param array<int, string> $token One PREG_SET_ORDER match set.
	 *
	 * @return int|string The key.
	 */
	private function tokenKey(array $token): int|string {
		// Alternation: a `[123]` match leaves group 1 as the empty
		// string and fills group 2; a `.prop` match matches group 1
		// and omits group 2 entirely. Discriminate on group 2 — its
		// presence is what actually distinguishes the two forms.
		$indexToken = ($token[2] ?? '');
		if ($indexToken !== '') {
			return (int)$indexToken;
		}

		return $token[1];
	}//end tokenKey()

	/**
	 * Split a JSONPath-lite path body into its `.prop` / `[index]` tokens.
	 *
	 * Rejects the whole expression when the tokens do not account for every
	 * character of `$path` — defence-in-depth, so a partially-matched
	 * trailing garbage segment can never be silently ignored.
	 *
	 * @param string $path The expression body (the part after the leading `$`).
	 *
	 * @return array<int, array<int, string>>|null The PREG_SET_ORDER match
	 *                                             sets, or null when the path
	 *                                             is not fully recognised.
	 */
	private function tokenisePath(string $path): ?array {
		$tokens = [];
		$matched = preg_match_all(
			pattern: '/\.([A-Za-z0-9_]+)|\[(\d+)\]/',
			subject: $path,
			matches: $tokens,
			flags: PREG_SET_ORDER
		);

		if ($matched === false || $matched === 0) {
			return null;
		}

		$consumed = implode(
			separator: '',
			array: array_map(
				callback: static fn (array $token): string => $token[0],
				array: $tokens
			)
		);

		if ($consumed !== $path) {
			return null;
		}

		return $tokens;
	}//end tokenisePath()

	/**
	 * Format a raw resolved value for display (REQ-LIVETILE-004): prefix,
	 * optional thousands separator, suffix. Non-numeric values are
	 * returned as their string cast, unformatted.
	 *
	 * @param mixed $value The raw resolved value.
	 * @param array<string,mixed> $format `{prefix?: string, suffix?: string, thousands?: bool}`.
	 *
	 * @return string The formatted display string.
	 */
	private function formatValue(mixed $value, array $format): string {
		$prefix = (string)($format['prefix'] ?? '');
		$suffix = (string)($format['suffix'] ?? '');

		if (is_numeric(value: $value) === false) {
			return $prefix . ((string)$value) . $suffix;
		}

		$number = (float)$value;
		$decimals = 2;
		if ((float)(int)$number === $number) {
			$decimals = 0;
		}

		$thousands = (bool)($format['thousands'] ?? false);

		$body = (string)round(num: $number, precision: $decimals);
		if ($thousands === true) {
			$body = number_format(num: $number, decimals: $decimals);
		}

		return $prefix . $body . $suffix;
	}//end formatValue()

	/**
	 * Resolve the threshold badge for a raw value (REQ-LIVETILE-004
	 * "Threshold badge is not colour-only"). Thresholds are evaluated in
	 * ascending `max` order; the first threshold whose `max` the value
	 * does not exceed wins. Returns `null` when no thresholds are
	 * configured or the value is non-numeric.
	 *
	 * @param mixed $value The raw resolved value.
	 * @param array<string,mixed> $badge `{thresholds?: array<int,array{max:int|float,state:string,label:string}>}`.
	 *
	 * @return array<string,string>|null `{state, label}` or `null`.
	 */
	private function resolveBadge(mixed $value, array $badge): ?array {
		$thresholds = $badge['thresholds'] ?? null;
		if (is_array(value: $thresholds) === false || $thresholds === [] || is_numeric(value: $value) === false) {
			return null;
		}

		return $this->badgeFor(number: (float)$value, sorted: $this->sortedThresholds(thresholds: $thresholds));
	}//end resolveBadge()

	/**
	 * The thresholds in ascending `max` order.
	 *
	 * @param array<int|string,mixed> $thresholds The configured thresholds.
	 *
	 * @return array<int,mixed> The sorted thresholds.
	 */
	private function sortedThresholds(array $thresholds): array {
		$sorted = $thresholds;
		usort(
			array: $sorted,
			callback: static fn (array $a, array $b): int => ((float)($a['max'] ?? 0)) <=> ((float)($b['max'] ?? 0))
		);

		return $sorted;
	}//end sortedThresholds()

	/**
	 * The badge of the first sorted threshold whose `max` the number does
	 * not exceed, else of the highest tier.
	 *
	 * @param float            $number The numeric value.
	 * @param array<int,mixed> $sorted The thresholds in ascending `max` order.
	 *
	 * @return array<string,string>|null `{state, label}` or `null`.
	 */
	private function badgeFor(float $number, array $sorted): ?array {
		foreach ($sorted as $threshold) {
			if (is_array(value: $threshold) === false) {
				continue;
			}

			if ($number <= (float)($threshold['max'] ?? 0)) {
				return $this->badgeShape(threshold: $threshold);
			}
		}

		return $this->highestTierBadge(sorted: $sorted);
	}//end badgeFor()

	/**
	 * The badge of the highest-max (last) threshold, used when the value
	 * exceeds every threshold; that tier is conventionally the "alert" one.
	 *
	 * @param array<int,mixed> $sorted The thresholds in ascending `max` order.
	 *
	 * @return array<string,string>|null `{state, label}` or `null`.
	 */
	private function highestTierBadge(array $sorted): ?array {
		$last = end($sorted);
		if (is_array(value: $last) === true) {
			return $this->badgeShape(threshold: $last);
		}

		return null;
	}//end highestTierBadge()

	/**
	 * Shape one threshold entry into the public badge contract.
	 *
	 * @param array<string,mixed> $threshold `{state?, label?}`.
	 *
	 * @return array<string,string> `{state, label}`.
	 */
	private function badgeShape(array $threshold): array {
		$state = (string)($threshold['state'] ?? 'ok');
		if (in_array(needle: $state, haystack: self::BADGE_STATES, strict: true) === false) {
			$state = 'ok';
		}

		$label = (string)($threshold['label'] ?? ucfirst(string: $state));

		return ['state' => $state, 'label' => $label];
	}//end badgeShape()

	/**
	 * Check a URL's host against `livetile_allowed_hosts`. FAIL-CLOSED: an
	 * empty, missing, or unparseable allow-list permits NO host — the
	 * admin must explicitly opt hosts in.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return boolean True only when the host is explicitly allow-listed.
	 */
	private function isHostAllowed(string $url): bool {
		$allowList = new HostAllowList(appConfig: $this->settings->appConfig, configKey: self::CONFIG_KEY_ALLOWED_HOSTS);
		return $allowList->permits(url: $url);
	}//end isHostAllowed()

	/**
	 * Shape an internal reading array (which also carries the internal
	 * `fetchedAtTs` unix timestamp / `rawValue`) into the public response
	 * contract (REQ-LIVETILE-003): `{value, formatted, badge, fetchedAt,
	 * stale}`. NEVER includes the source URL, headers, or credentials.
	 *
	 * @param array<string,mixed> $reading The internal reading.
	 * @param array<string,mixed> $config The placement's resolved config (drives format/badge).
	 * @param boolean $stale Whether this is a stale (cache-expired-but-served) reading.
	 *
	 * @return array<string,mixed>
	 */
	private function publicShape(array $reading, array $config, bool $stale): array {
		$rawValue = $reading['rawValue'] ?? null;
		$fetchedAtTs = (int)($reading['fetchedAtTs'] ?? time());

		$formatted = null;
		$badge = null;
		if ($rawValue !== null) {
			$formatted = $this->formatValue(value: $rawValue, format: (array)($config['format'] ?? []));
			$badge = $this->resolveBadge(value: $rawValue, badge: (array)($config['badge'] ?? []));
		}

		return [
			'value' => $rawValue,
			'formatted' => $formatted,
			'badge' => $badge,
			'fetchedAt' => (new DateTime('@' . $fetchedAtTs))->format(format: DATE_ATOM),
			'stale' => $stale,
		] + $this->displayPart(reading: $reading, config: $config);
	}//end publicShape()

	/**
	 * The display and its mapped rows or pairs, for a list, table or
	 * key-value tile; nothing for a single-value tile. Only the mapped data,
	 * never the raw node (REQ-LTC-001).
	 *
	 * @param array<string,mixed> $reading The internal reading.
	 * @param array<string,mixed> $config  The placement's resolved config.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-mapping-happens-on-the-server-and-drops-unmapped-data-req-ltc-002
	 */
	private function displayPart(array $reading, array $config): array {
		$display = $this->lists->displayOf(config: $config);
		if ($display === 'value') {
			return [];
		}

		return ['display' => $display] + (array)($reading['mapped'] ?? []);
	}//end displayPart()

	/**
	 * Build the value cache key — placement id + a hash of the resolved
	 * config (proposal.md "Caching keyed on placement id + config hash").
	 *
	 * @param integer $placementId The widget placement id.
	 * @param array<string,mixed> $config The placement's resolved config.
	 *
	 * @return string The cache key.
	 */
	private function buildCacheKey(int $placementId, array $config): string {
		return 'value_' . $placementId . '_' . hash(algo: 'sha256', data: (string)json_encode($config));
	}//end buildCacheKey()

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
			$this->cache = $this->sources->cacheFactory->createDistributed(prefix: 'launchpad_livetile_');
		} catch (Throwable $exception) {
			$this->settings->logger->info(
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
	private function clampRefresh(int $seconds): int {
		if ($seconds <= 0) {
			return self::DEFAULT_REFRESH_SECONDS;
		}

		return max($seconds, self::MIN_REFRESH_SECONDS);
	}//end clampRefresh()
}//end class
