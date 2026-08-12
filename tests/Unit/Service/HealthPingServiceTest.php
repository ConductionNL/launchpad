<?php

/**
 * HealthPingServiceTest
 *
 * Covers REQ-HPING-001 (interval clamp, save-time allow-list validation),
 * REQ-HPING-002 (online/degraded/offline classification, allow-list
 * fail-closed at ping time), and REQ-HPING-003 (TTL cache hit, stale
 * fallback on allow-list refusal, background refresh of due entries).
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

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\HealthPingService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * In-memory stand-in for {@see ICache} — PHPUnit mocks can't persist state
 * across calls without heavy `willReturnCallback` wiring, and the TTL
 * cache-hit / stale-fallback scenarios both require a cache that actually
 * remembers what was `set()`.
 */
final class HealthPingInMemoryFakeCache implements ICache {
	/** @var array<string,mixed> */
	private array $store = [];

	public function get(string $key): mixed {
		return $this->store[$key] ?? null;
	}

	public function set(string $key, $value, int $ttl = 0): bool {
		$this->store[$key] = $value;
		return true;
	}

	public function hasKey(string $key): bool {
		return array_key_exists($key, $this->store);
	}

	public function remove(string $key): bool {
		unset($this->store[$key]);
		return true;
	}

	public function clear(string $prefix = ''): bool {
		$this->store = [];
		return true;
	}

	public static function isAvailable(): bool {
		return true;
	}
}

#[Small]
class HealthPingServiceTest extends TestCase {

	private HealthPingService $service;

	private WidgetPlacementMapper $placementMapper;

	private IClientService $clientService;

	private IAppConfig $appConfig;

	private HealthPingInMemoryFakeCache $cache;

	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->cache = new HealthPingInMemoryFakeCache();
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->service = new HealthPingService(
			clientService: $this->clientService,
			cacheFactory: $cacheFactory,
			appConfig: $this->appConfig,
			placementMapper: $this->placementMapper,
			logger: $this->logger,
		);
	}//end setUp()

	/**
	 * @param array<string,mixed> $config
	 */
	private function placementWithConfig(int $id, array $config): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setContentArray($config);
		return $placement;
	}//end placementWithConfig()

	private function baseConfig(array $overrides = []): array {
		return array_merge([
			'healthPingEnabled' => true,
			'healthUrl' => 'https://example.com/health',
			'expectedStatus' => 200,
			'pingInterval' => 300,
		], $overrides);
	}//end baseConfig()

	/**
	 * Configures the mock app config: allow-list = `[$host]`, latency
	 * threshold = `$thresholdMs` (default a very low value so a
	 * `usleep()`-delayed mock response deterministically classifies
	 * `degraded`).
	 */
	private function allowHost(string $host, int $thresholdMs = HealthPingService::DEFAULT_LATENCY_THRESHOLD_MS): void {
		$this->appConfig->method('getValueString')->willReturn(json_encode([$host]));
		$this->appConfig->method('getValueInt')->willReturn($thresholdMs);
	}//end allowHost()

	private function mockHttpResponse(int $status): IResponse {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		return $response;
	}//end mockHttpResponse()

	// -------------------------------------------------------------
	// REQ-HPING-002: classification.
	// -------------------------------------------------------------

	public function testOnlineWhenStatusMatchesWithinLatencyThreshold(): void {
		$this->allowHost(host: 'example.com', thresholdMs: 2000);
		$placement = $this->placementWithConfig(id: 1, config: $this->baseConfig());
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 1);

		$this->assertSame('online', $result['state']);
		$this->assertFalse($result['stale']);
		$this->assertIsInt($result['latencyMs']);
	}//end testOnlineWhenStatusMatchesWithinLatencyThreshold()

	public function testDegradedWhenSlow(): void {
		// Threshold set to 1ms; the mock client sleeps 5ms before
		// returning, deterministically exceeding it without flakiness.
		$this->allowHost(host: 'example.com', thresholdMs: 1);
		$placement = $this->placementWithConfig(id: 2, config: $this->baseConfig());
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(function () {
			usleep(5000);
			return $this->mockHttpResponse(status: 200);
		});
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 2);

		$this->assertSame('degraded', $result['state']);
		$this->assertFalse($result['stale']);
	}//end testDegradedWhenSlow()

	public function testOfflineOnUnexpectedStatus(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 3, config: $this->baseConfig(overrides: ['expectedStatus' => 200]));
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 503));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 3);

		$this->assertSame('offline', $result['state']);
		$this->assertFalse($result['stale'], 'a completed ping IS a fresh reading, even when offline');
	}//end testOfflineOnUnexpectedStatus()

	public function testOfflineOnTransportFailure(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 4, config: $this->baseConfig());
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new \Exception('connection refused'));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 4);

		$this->assertSame('offline', $result['state']);
		$this->assertFalse($result['stale']);
	}//end testOfflineOnTransportFailure()

	// -------------------------------------------------------------
	// REQ-HPING-002: allow-list enforced fail-closed at ping time.
	// -------------------------------------------------------------

	public function testFetchRefusedWhenHostNotOnAllowList(): void {
		// No allow-list configured — FAIL-CLOSED means every host is refused.
		$this->appConfig->method('getValueString')->willReturn('');
		$this->appConfig->method('getValueInt')->willReturn(HealthPingService::DEFAULT_LATENCY_THRESHOLD_MS);
		$placement = $this->placementWithConfig(id: 5, config: $this->baseConfig(overrides: ['healthUrl' => 'https://not-allowed.example.com/health']));
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->never())->method('get');
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 5);

		$this->assertNull($result['state']);
		$this->assertTrue($result['stale']);
	}//end testFetchRefusedWhenHostNotOnAllowList()

	public function testValidateConfigRejectsUrlNotOnAllowListAtSaveTime(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$errors = $this->service->validateConfig(config: $this->baseConfig());

		$this->assertContains('host_not_allowed', $errors);
	}//end testValidateConfigRejectsUrlNotOnAllowListAtSaveTime()

	public function testValidateConfigAcceptsUrlOnAllowList(): void {
		$this->allowHost(host: 'example.com');

		$errors = $this->service->validateConfig(config: $this->baseConfig());

		$this->assertSame([], $errors);
	}//end testValidateConfigAcceptsUrlOnAllowList()

	public function testValidateConfigSkipsWhenPingDisabled(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$errors = $this->service->validateConfig(config: $this->baseConfig(overrides: ['healthPingEnabled' => false]));

		$this->assertSame([], $errors, 'a disabled ping block must never be rejected for its (unused) URL');
	}//end testValidateConfigSkipsWhenPingDisabled()

	// -------------------------------------------------------------
	// REQ-HPING-003: TTL cache hit.
	// -------------------------------------------------------------

	public function testCachedWithinTtlDoesNotRepingUpstream(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 6, config: $this->baseConfig(overrides: ['pingInterval' => 300]));
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())
			->method('get')
			->willReturn($this->mockHttpResponse(status: 200));
		$this->clientService->method('newClient')->willReturn($client);

		$first = $this->service->resolveForPlacement(placementId: 6);
		$second = $this->service->resolveForPlacement(placementId: 6);

		$this->assertSame('online', $first['state']);
		$this->assertSame('online', $second['state']);
		$this->assertFalse($second['stale']);
		// `$client->expects($this->once())` above already asserts a single
		// upstream call across both resolutions.
	}//end testCachedWithinTtlDoesNotRepingUpstream()

	// -------------------------------------------------------------
	// REQ-HPING-003: stale fallback when the allow-list refuses a
	// previously-configured host (REQ-HPING-002 scenario 4).
	// -------------------------------------------------------------

	public function testStaleFallbackServesLastKnownBadgeWhenHostRemovedFromAllowList(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 7, config: $this->baseConfig(overrides: ['pingInterval' => 30]));
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200));
		$this->clientService->method('newClient')->willReturn($client);

		$first = $this->service->resolveForPlacement(placementId: 7);
		$this->assertSame('online', $first['state']);
		$this->assertFalse($first['stale']);

		// Host removed from the allow-list AFTER the placement was
		// configured — expire the cache entry so the next call re-attempts
		// the (now fail-closed refused) ping.
		$this->expireCacheEntryFor(placementId: 7);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		// Rebuild the service with a fresh appConfig mock reporting an
		// empty allow-list, but the SAME cache instance (constructor takes
		// an ICacheFactory, not the cache directly, so a fresh factory
		// pointed at the same in-memory store keeps state).
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->appConfig->method('getValueString')->willReturn('');
		$this->appConfig->method('getValueInt')->willReturn(HealthPingService::DEFAULT_LATENCY_THRESHOLD_MS);
		$this->service = new HealthPingService(
			clientService: $this->clientService,
			cacheFactory: $cacheFactory,
			appConfig: $this->appConfig,
			placementMapper: $this->placementMapper,
			logger: $this->logger,
		);

		$client->expects($this->never())->method('get');

		$second = $this->service->resolveForPlacement(placementId: 7);
		$this->assertSame('online', $second['state'], 'stale fallback MUST serve the last-known reading');
		$this->assertTrue($second['stale']);
	}//end testStaleFallbackServesLastKnownBadgeWhenHostRemovedFromAllowList()

	/**
	 * Rewrites the in-memory cache entry's internal `checkedAtTs` to a
	 * point far enough in the past that the configured interval has
	 * elapsed, without waiting in real time.
	 */
	private function expireCacheEntryFor(int $placementId): void {
		$key = 'badge_' . $placementId;
		$raw = $this->cache->get($key);
		$this->assertIsString($raw, 'precondition: a cache entry must exist to expire');
		$decoded = json_decode($raw, true);
		$decoded['checkedAtTs'] = time() - 3600;
		$this->cache->set($key, json_encode($decoded));
	}//end expireCacheEntryFor()

	// -------------------------------------------------------------
	// REQ-HPING-001: interval bounds.
	// -------------------------------------------------------------

	public function testClampIntervalBelowMinimumIsRaisedToFifteen(): void {
		$this->assertSame(HealthPingService::MIN_INTERVAL_SECONDS, $this->service->clampInterval(seconds: 5));
	}//end testClampIntervalBelowMinimumIsRaisedToFifteen()

	public function testClampIntervalUnsetDefaultsToSixty(): void {
		$this->assertSame(HealthPingService::DEFAULT_INTERVAL_SECONDS, $this->service->clampInterval(seconds: 0));
	}//end testClampIntervalUnsetDefaultsToSixty()

	public function testClampIntervalAboveMinimumIsUnchanged(): void {
		$this->assertSame(120, $this->service->clampInterval(seconds: 120));
	}//end testClampIntervalAboveMinimumIsUnchanged()

	// -------------------------------------------------------------
	// REQ-HPING-003: background refresh of due entries.
	// -------------------------------------------------------------

	public function testRefreshDuePlacementsOnlyRefreshesDuePingEnabledPlacements(): void {
		$this->allowHost(host: 'example.com');

		$due = $this->placementWithConfig(id: 10, config: $this->baseConfig(overrides: ['pingInterval' => 15]));
		$notDue = $this->placementWithConfig(id: 11, config: $this->baseConfig(overrides: ['pingInterval' => 3600]));
		$disabled = $this->placementWithConfig(id: 12, config: $this->baseConfig(overrides: ['healthPingEnabled' => false]));
		$noConfig = $this->placementWithConfig(id: 13, config: []);

		$this->placementMapper->method('findAll')->willReturn([$due, $notDue, $disabled, $noConfig]);

		// Pre-seed a fresh (not-due) cache entry for placement 11.
		$this->cache->set('badge_11', json_encode(['state' => 'online', 'latencyMs' => 5, 'checkedAtTs' => time()]));

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($this->mockHttpResponse(status: 200));
		$this->clientService->method('newClient')->willReturn($client);

		$refreshed = $this->service->refreshDuePlacements();

		$this->assertSame(1, $refreshed, 'only placement 10 is ping-enabled AND due');
		$cached10 = json_decode($this->cache->get('badge_10'), true);
		$this->assertSame('online', $cached10['state']);
	}//end testRefreshDuePlacementsOnlyRefreshesDuePingEnabledPlacements()

	public function testRefreshDuePlacementsSkipsNonAllowListedHosts(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->appConfig->method('getValueInt')->willReturn(HealthPingService::DEFAULT_LATENCY_THRESHOLD_MS);

		$due = $this->placementWithConfig(id: 20, config: $this->baseConfig(overrides: ['pingInterval' => 15]));
		$this->placementMapper->method('findAll')->willReturn([$due]);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->never())->method('get');
		$this->clientService->method('newClient')->willReturn($client);

		$refreshed = $this->service->refreshDuePlacements();

		$this->assertSame(0, $refreshed);
		$this->assertNull($this->cache->get('badge_20'));
	}//end testRefreshDuePlacementsSkipsNonAllowListedHosts()

	// -------------------------------------------------------------
	// REQ-HPING-003: placement not found / not configured.
	// -------------------------------------------------------------

	public function testUnknownPlacementReturnsErrorShape(): void {
		$this->placementMapper->method('find')->willThrowException(new \Exception('not found'));

		$result = $this->service->resolveForPlacement(placementId: 999);

		$this->assertSame('placement_not_found', $result['error']);
	}//end testUnknownPlacementReturnsErrorShape()

	public function testPingDisabledPlacementReturnsNotConfiguredError(): void {
		$placement = $this->placementWithConfig(id: 21, config: ['healthPingEnabled' => false]);
		$this->placementMapper->method('find')->willReturn($placement);

		$result = $this->service->resolveForPlacement(placementId: 21);

		$this->assertSame('not_configured', $result['error']);
	}//end testPingDisabledPlacementReturnsNotConfiguredError()
}//end class
