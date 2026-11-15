<?php

/**
 * HealthPingService
 *
 * Performs an allow-listed, server-side HTTP health check of a tile's
 * configured `healthUrl` (REQ-HPING-002) and classifies the result as
 * `online`, `degraded`, or `offline`. Mirrors {@see LiveTileService}'s
 * shape for the same class of capability:
 *
 *  - The host allow-list (`healthping_allowed_hosts`, IAppConfig) is
 *    enforced FAIL-CLOSED at both save time ({@see self::validateConfig()})
 *    and ping time ({@see self::isHostAllowed()}) — an empty/missing list
 *    permits NO host.
 *  - When the allow-list refuses a host, no request is ever attempted and
 *    the caller falls back to the last-known cached badge (marked
 *    `stale`), or the neutral "no ping performed" shape when no prior
 *    reading exists (REQ-HPING-002 "Allow-list enforced at ping time").
 *  - When the request IS attempted, a transport failure (timeout,
 *    connection refused) or a response whose status does not match
 *    `expectedStatus` is itself a DEFINITIVE, cacheable `offline` reading
 *    (REQ-HPING-002 "Offline on failure or unexpected status") — this is
 *    distinct from the allow-list-refusal case above, which never
 *    produces a reading at all.
 *  - Results are cached in `ICache`, keyed on placement id, with a TTL
 *    equal to the tile's configured interval (default 60s, clamped to a
 *    15s minimum).
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\IAppConfig;
use OCP\ICache;
use Throwable;

/**
 * Service for pinging, classifying, and caching tile health-badge state.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Ping, classify and cache is
 *     one cohesive responsibility, and most of the complexity is the
 *     classification table — a wide but flat mapping of transport outcomes and
 *     status codes onto badge states. Splitting it would separate the table from
 *     the code that is the reason it exists.
 * @spec                                             openspec/specs/service-health-ping/spec.md
 */
class HealthPingService {
	use ReadsJsonCache;

	/**
	 * Default cache TTL / ping interval in seconds when a tile has no (or
	 * an invalid) `pingInterval` configured (REQ-HPING-001 "Interval
	 * bounds").
	 *
	 * @var integer
	 */
	public const DEFAULT_INTERVAL_SECONDS = 60;

	/**
	 * Minimum permitted ping interval in seconds — any configured value
	 * below this is clamped up (REQ-HPING-001).
	 *
	 * @var integer
	 */
	public const MIN_INTERVAL_SECONDS = 15;

	/**
	 * Default HTTP status the target is expected to return when the tile
	 * author has not set an explicit `expectedStatus` — any 2xx/3xx is
	 * accepted.
	 *
	 * @var integer
	 */
	public const DEFAULT_EXPECTED_STATUS_RANGE_LOW = 200;

	/**
	 * Highest HTTP status still treated as "up" when a placement declares no explicit range.
	 *
	 * @var integer
	 */
	public const DEFAULT_EXPECTED_STATUS_RANGE_HIGH = 399;

	/**
	 * HTTP connect timeout in seconds for the health-check request.
	 *
	 * @var integer
	 */
	public const CONNECT_TIMEOUT = 5;

	/**
	 * HTTP total request timeout in seconds for the health-check request.
	 *
	 * @var integer
	 */
	public const REQUEST_TIMEOUT = 10;

	/**
	 * IAppConfig key — JSON array of hostnames permitted as a ping
	 * target. FAIL-CLOSED: empty or missing means NO host is permitted.
	 *
	 * @var string
	 */
	public const CONFIG_KEY_ALLOWED_HOSTS = 'healthping_allowed_hosts';

	/**
	 * IAppConfig key — the latency threshold (milliseconds) above which
	 * an otherwise-matching response is classified `degraded` rather than
	 * `online` (REQ-HPING-002 "Degraded when slow").
	 *
	 * @var string
	 */
	public const CONFIG_KEY_LATENCY_THRESHOLD_MS = 'healthping_latency_threshold_ms';

	/**
	 * Default latency threshold in milliseconds.
	 *
	 * @var integer
	 */
	public const DEFAULT_LATENCY_THRESHOLD_MS = 2000;

	/**
	 * Badge states, in priority order — the only three values REQ-HPING-002
	 * and REQ-HPING-004 recognise.
	 *
	 * @var array<int,string>
	 */
	public const BADGE_STATES = ['online', 'degraded', 'offline'];

	/**
	 * Lazily resolved {@see ICache} backing the per-placement badge cache.
	 *
	 * @var ICache|null
	 */
	private ?ICache $cache = null;

	/**
	 * Constructor.
	 *
	 * @param PingTransport           $transport The HTTP client and cache factory.
	 * @param PingSettings            $settings The app config and logger.
	 * @param WidgetPlacementMapper   $placementMapper Resolves placements by id / enumerates all placements.
	 * @param ConnectionReporter|null $connectionReporter Tells integriq what a ping met, or nothing when absent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function __construct(
		private readonly PingTransport $transport,
		private readonly PingSettings $settings,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly ?ConnectionReporter $connectionReporter = null,
	) {
	}//end __construct()

	/**
	 * Resolve the health badge for one placement (REQ-HPING-003 "Endpoint
	 * serves the cached badge"). Serves a fresh cache hit without
	 * re-pinging; otherwise attempts a fresh ping and caches the result.
	 * When the ping is refused by the allow-list (fail-closed) or the
	 * placement carries no ping config, falls back to the last-known
	 * cached badge marked `stale`, or a neutral "never pinged" shape.
	 * Never throws.
	 *
	 * @param integer $placementId The widget placement id.
	 *
	 * @return array<string,mixed> `{state, checkedAt, latencyMs, stale}` or `{error: string}`.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function resolveForPlacement(int $placementId): array {
		try {
			$placement = $this->placementMapper->find(id: $placementId);
		} catch (Throwable $exception) {
			return ['error' => 'placement_not_found'];
		}

		$config = $this->readPlacementConfig(placement: $placement);
		if (($config['healthPingEnabled'] ?? false) !== true) {
			return ['error' => 'not_configured'];
		}

		return $this->resolveForConfig(placementId: $placementId, config: $config);
	}//end resolveForPlacement()

	/**
	 * Core resolve/cache logic shared by {@see self::resolveForPlacement()}
	 * and {@see self::refreshDuePlacements()}.
	 *
	 * @param integer $placementId The widget placement id.
	 * @param array<string,mixed> $config The placement's health-ping config.
	 *
	 * @return array<string,mixed> `{state, checkedAt, latencyMs, stale}`.
	 */
	private function resolveForConfig(int $placementId, array $config): array {
		$interval = $this->clampInterval(seconds: (int)($config['pingInterval'] ?? 0));
		$cacheKey = $this->buildCacheKey(placementId: $placementId);
		$cache = $this->getCache();
		$cached = $this->readCache(cache: $cache, cacheKey: $cacheKey);

		if ($cached !== null) {
			$age = (time() - (int)($cached['checkedAtTs'] ?? 0));
			if ($age >= 0 && $age < $interval) {
				return $this->publicShape(reading: $cached, stale: false);
			}
		}

		$fresh = $this->attemptPing(config: $config);

		if ($fresh !== null) {
			$fresh['checkedAtTs'] = time();
			if ($cache !== null) {
				$cache->set(key: $cacheKey, value: json_encode($fresh), ttl: $interval);
			}

			return $this->publicShape(reading: $fresh, stale: false);
		}

		if ($cached !== null) {
			// Allow-list refused the ping — REQ-HPING-002 "no ping was
			// performed rather than a false 'up' state" / REQ-HPING-003
			// "Stale fallback on refresh failure": degrade gracefully to
			// the last-known reading rather than fabricate a new one.
			return $this->publicShape(reading: $cached, stale: true);
		}

		return [
			'state' => null,
			'checkedAt' => null,
			'latencyMs' => null,
			'stale' => true,
		];
	}//end resolveForConfig()

	/**
	 * Refresh every due, ping-enabled placement in the instance
	 * (REQ-HPING-003 "Background refresh of due entries"). Skips
	 * placements whose host is not allow-listed (the fail-closed ping
	 * attempt naturally refuses and leaves the cache untouched) and
	 * isolates each placement's failure so one broken tile never blocks
	 * the rest. Never throws.
	 *
	 * @return integer The number of placements actually refreshed.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function refreshDuePlacements(): int {
		$refreshed = 0;

		try {
			$placements = $this->placementMapper->findAll();
		} catch (Throwable $exception) {
			$this->settings->logger->warning(
				message: 'HealthPingService: could not enumerate placements for refresh',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			return 0;
		}

		foreach ($placements as $placement) {
			try {
				$config = $this->readPlacementConfig(placement: $placement);
				if (($config['healthPingEnabled'] ?? false) !== true) {
					continue;
				}

				if ($this->isDue(placementId: $placement->getId(), config: $config) === false) {
					continue;
				}

				$result = $this->resolveForConfig(placementId: $placement->getId(), config: $config);
				if ($result['stale'] === false) {
					// Only a completed ping (fresh reading) counts as an
					// actual refresh — an allow-list refusal falls back to
					// `stale: true` without ever contacting the host.
					$refreshed++;
				}
			} catch (Throwable $exception) {
				$this->settings->logger->info(
					message: 'HealthPingService: refresh failed for one placement, continuing',
					context: [
						'app' => Application::APP_ID,
						'placementId' => $placement->getId(),
						'exception' => $exception->getMessage(),
					]
				);
			}//end try
		}//end foreach

		return $refreshed;
	}//end refreshDuePlacements()

	/**
	 * Validate a candidate health-ping config at save time
	 * (REQ-HPING-001 "Host not on the allow-list is rejected at save").
	 * FAIL-CLOSED: a `healthUrl` whose host is not on the (possibly
	 * empty) allow-list is always rejected. Returns no errors when
	 * `healthPingEnabled` is not `true` — an author may save an untouched
	 * (disabled) ping block freely.
	 *
	 * @param array<string,mixed> $config The candidate `{healthPingEnabled, healthUrl, expectedStatus,
	 *                                    pingInterval}` config.
	 *
	 * @return string[] Validation error codes; empty when the config is valid.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function validateConfig(array $config): array {
		if (($config['healthPingEnabled'] ?? false) !== true) {
			return [];
		}

		$errors = [];
		$url = trim(string: (string)($config['healthUrl'] ?? ''));

		if ($url === '') {
			$errors[] = 'health_url_required';
			return $errors;
		}

		if (HostAllowList::hasHttpScheme(url: $url) === false) {
			$errors[] = 'invalid_url';
			return $errors;
		}

		if ($this->isHostAllowed(url: $url) === false) {
			// FAIL-CLOSED (REQ-HPING-001 "rejected at save time").
			$errors[] = 'host_not_allowed';
		}

		return $errors;
	}//end validateConfig()

	/**
	 * Clamp a configured ping interval: values `<= 0` (unset) default to
	 * {@see self::DEFAULT_INTERVAL_SECONDS}; any positive value below
	 * {@see self::MIN_INTERVAL_SECONDS} is raised to that minimum
	 * (REQ-HPING-001 "Interval bounds").
	 *
	 * @param integer $seconds The raw configured interval, or `0`/negative when unset.
	 *
	 * @return integer The clamped interval in seconds.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function clampInterval(int $seconds): int {
		if ($seconds <= 0) {
			return self::DEFAULT_INTERVAL_SECONDS;
		}

		return max($seconds, self::MIN_INTERVAL_SECONDS);
	}//end clampInterval()

	/**
	 * Whether a cached badge for one placement is due for refresh — no
	 * cached entry, or the cached entry is older than the placement's
	 * configured (clamped) interval.
	 *
	 * @param integer $placementId The widget placement id.
	 * @param array<string,mixed> $config The placement's health-ping config.
	 *
	 * @return boolean
	 */
	private function isDue(int $placementId, array $config): bool {
		$interval = $this->clampInterval(seconds: (int)($config['pingInterval'] ?? 0));
		$cached = $this->readCache(cache: $this->getCache(), cacheKey: $this->buildCacheKey(placementId: $placementId));
		if ($cached === null) {
			return true;
		}

		$age = (time() - (int)($cached['checkedAtTs'] ?? 0));
		return ($age < 0 || $age >= $interval);
	}//end isDue()

	/**
	 * Attempt the allow-listed, server-side health request and classify
	 * the outcome (REQ-HPING-002). Returns `null` — WITHOUT ever opening a
	 * connection — when the host is not on the allow-list (fail-closed);
	 * every other outcome (success, wrong status, transport failure) is a
	 * definitive, cacheable classification.
	 *
	 * @param array<string,mixed> $config The placement's health-ping config.
	 *
	 * @return array<string,mixed>|null `{state, latencyMs}` or `null` when refused.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	private function attemptPing(array $config): ?array {
		$url = trim(string: (string)($config['healthUrl'] ?? ''));
		if ($url === '' || HostAllowList::hasHttpScheme(url: $url) === false) {
			return null;
		}

		if ($this->isHostAllowed(url: $url) === false) {
			$this->settings->logger->warning(
				message: 'HealthPingService: host not on healthping_allowed_hosts, refusing ping (fail-closed)',
				context: ['app' => Application::APP_ID]
			);
			$this->connectionReporter?->reportAllowListRefusal(key: ConnectionReporter::KEY_HEALTH_PING);
			return null;
		}

		$expectedStatus = (int)($config['expectedStatus'] ?? 0);
		$threshold = $this->latencyThreshold();
		$startedAt = microtime(as_float: true);

		try {
			$client = $this->transport->clientService->newClient();
			$response = (new NoRedirectGet(connectTimeout: self::CONNECT_TIMEOUT, timeout: self::REQUEST_TIMEOUT))
				->send(client: $client, url: $url);
		} catch (Throwable $exception) {
			// REQ-HPING-002 "Offline on failure" — a connection failure or
			// timeout IS a definitive reading, not a missed attempt.
			$latencyMs = (int)round((microtime(as_float: true) - $startedAt) * 1000);
			$this->settings->logger->info(
				message: 'HealthPingService: ping transport failure, classifying offline',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_HEALTH_PING, url: $url, httpStatus: null);
			return ['state' => 'offline', 'latencyMs' => $latencyMs];
		}//end try

		$latencyMs = (int)round((microtime(as_float: true) - $startedAt) * 1000);
		$status = (int)$response->getStatusCode();
		$this->connectionReporter?->reportCall(key: ConnectionReporter::KEY_HEALTH_PING, url: $url, httpStatus: $status);

		if ($this->matchesExpectedStatus(status: $status, expectedStatus: $expectedStatus) === false) {
			return ['state' => 'offline', 'latencyMs' => $latencyMs];
		}

		if ($latencyMs > $threshold) {
			return ['state' => 'degraded', 'latencyMs' => $latencyMs];
		}

		return ['state' => 'online', 'latencyMs' => $latencyMs];
	}//end attemptPing()

	/**
	 * Whether an HTTP status code satisfies the tile's expected status.
	 * With no explicit `expectedStatus` configured (`<= 0`), any status in
	 * the default 200-399 "reachable" range is accepted; otherwise an
	 * EXACT match is required.
	 *
	 * @param integer $status The observed HTTP status code.
	 * @param integer $expectedStatus The configured expected status, or `0`/negative when unset.
	 *
	 * @return boolean
	 */
	private function matchesExpectedStatus(int $status, int $expectedStatus): bool {
		if ($expectedStatus <= 0) {
			return ($status >= self::DEFAULT_EXPECTED_STATUS_RANGE_LOW && $status <= self::DEFAULT_EXPECTED_STATUS_RANGE_HIGH);
		}

		return $status === $expectedStatus;
	}//end matchesExpectedStatus()

	/**
	 * Resolve the configured latency-degraded threshold in milliseconds,
	 * clamped to a sane positive minimum.
	 *
	 * @return integer
	 */
	private function latencyThreshold(): int {
		$threshold = $this->settings->appConfig->getValueInt(
			app: Application::APP_ID,
			key: self::CONFIG_KEY_LATENCY_THRESHOLD_MS,
			default: self::DEFAULT_LATENCY_THRESHOLD_MS
		);

		if ($threshold > 0) {
			return $threshold;
		}

		return self::DEFAULT_LATENCY_THRESHOLD_MS;
	}//end latencyThreshold()

	/**
	 * Check a URL's host against `healthping_allowed_hosts`. FAIL-CLOSED:
	 * an empty, missing, or unparseable allow-list permits NO host — the
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
	 * Read a placement's health-ping config from its `content` JSON blob
	 * (no schema change — REQ-HPING-001).
	 *
	 * @param WidgetPlacement $placement The placement entity.
	 *
	 * @return array<string,mixed>
	 */
	private function readPlacementConfig(WidgetPlacement $placement): array {
		// No is_array() probe and no [] fallback: getContentArray() is declared
		// `: array` and already returns [] when the column is null, so the check
		// was always true (PHPStan 2: function.alreadyNarrowedType) and the
		// fallback unreachable.
		return $placement->getContentArray();
	}//end readPlacementConfig()

	/**
	 * Shape an internal reading (which also carries the internal
	 * `checkedAtTs` unix timestamp) into the public response contract
	 * (REQ-HPING-003): `{state, checkedAt, latencyMs, stale}`. NEVER
	 * includes the health URL, request headers, or upstream response
	 * body.
	 *
	 * @param array<string,mixed> $reading The internal reading.
	 * @param boolean $stale Whether this is a stale (cache-expired-but-served) reading.
	 *
	 * @return array<string,mixed>
	 */
	private function publicShape(array $reading, bool $stale): array {
		$checkedAtTs = (int)($reading['checkedAtTs'] ?? time());

		$latencyMs = null;
		if (isset($reading['latencyMs']) === true) {
			$latencyMs = (int)$reading['latencyMs'];
		}

		return [
			'state' => $reading['state'] ?? null,
			'checkedAt' => (new DateTime('@' . $checkedAtTs))->format(format: DATE_ATOM),
			'latencyMs' => $latencyMs,
			'stale' => $stale,
		];
	}//end publicShape()

	/**
	 * Build the badge cache key — one entry per placement.
	 *
	 * @param integer $placementId The widget placement id.
	 *
	 * @return string
	 */
	private function buildCacheKey(int $placementId): string {
		return 'badge_' . $placementId;
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
			$this->cache = $this->transport->cacheFactory->createDistributed(prefix: 'launchpad_healthping_');
		} catch (Throwable $exception) {
			$this->settings->logger->info(
				message: 'HealthPingService: cache subsystem unavailable, falling back to direct ping',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			$this->cache = null;
		}

		return $this->cache;
	}//end getCache()
}//end class
