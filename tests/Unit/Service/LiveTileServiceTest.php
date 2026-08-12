<?php

/**
 * LiveTileServiceTest
 *
 * Covers REQ-LIVETILE-002 (refresh clamp, save-time allow-list
 * validation), REQ-LIVETILE-003 (JSONPath-lite value extraction, TTL
 * cache hit, stale fallback, allow-list fail-closed at fetch time),
 * REQ-LIVETILE-004 (formatting, threshold badge), and REQ-LIVETILE-005
 * (connector-absent capability probe, no static OpenConnector import).
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
use OCA\LaunchPad\Service\LiveTileService;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * In-memory stand-in for {@see ICache} — PHPUnit mocks can't persist state
 * across calls without heavy `willReturnCallback` wiring, and the TTL
 * cache-hit / stale-fallback scenarios both require a cache that actually
 * remembers what was `set()`.
 */
final class InMemoryFakeCache implements ICache {
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
class LiveTileServiceTest extends TestCase {

	private LiveTileService $service;

	private WidgetPlacementMapper $placementMapper;

	private IAppManager $appManager;

	private ContainerInterface $container;

	private IClientService $clientService;

	private IAppConfig $appConfig;

	private InMemoryFakeCache $cache;

	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->cache = new InMemoryFakeCache();
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->service = new LiveTileService(
			appManager: $this->appManager,
			container: $this->container,
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

	private function allowHost(string $host): void {
		$this->appConfig->method('getValueString')->willReturn(json_encode([$host]));
	}//end allowHost()

	private function mockHttpResponse(int $status, array $body): IResponse {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn(json_encode($body));
		return $response;
	}//end mockHttpResponse()

	// -------------------------------------------------------------
	// REQ-LIVETILE-003: JSONPath-lite value extraction.
	// -------------------------------------------------------------

	public function testExtractsSimplePropertyValue(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 1, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.data.count',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200, body: ['data' => ['count' => 42]]));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 1);

		$this->assertSame(42, $result['value']);
		$this->assertFalse($result['stale']);
	}//end testExtractsSimplePropertyValue()

	public function testExtractsArrayIndexedValue(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 2, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.items[0].name',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn(
			$this->mockHttpResponse(status: 200, body: ['items' => [['name' => 'first'], ['name' => 'second']]])
		);
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 2);

		$this->assertSame('first', $result['value']);
	}//end testExtractsArrayIndexedValue()

	public function testReturnsNullValueWhenExpressionMisses(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 3, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.does.not.exist',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200, body: ['data' => ['count' => 1]]));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 3);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}//end testReturnsNullValueWhenExpressionMisses()

	// -------------------------------------------------------------
	// REQ-LIVETILE-003: TTL cache hit.
	// -------------------------------------------------------------

	public function testCachedWithinTtlDoesNotRefetch(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 4, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())
			->method('get')
			->willReturn($this->mockHttpResponse(status: 200, body: ['count' => 7]));
		$this->clientService->method('newClient')->willReturn($client);

		$first = $this->service->resolveForPlacement(placementId: 4);
		$second = $this->service->resolveForPlacement(placementId: 4);

		$this->assertSame(7, $first['value']);
		$this->assertSame(7, $second['value']);
		$this->assertFalse($second['stale']);
		// `$client->expects($this->once())` above already asserts a single
		// upstream call across both resolutions.
	}//end testCachedWithinTtlDoesNotRefetch()

	// -------------------------------------------------------------
	// REQ-LIVETILE-003: stale fallback.
	// -------------------------------------------------------------

	public function testStaleFallbackServesLastKnownValueOnUpstreamFailure(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 5, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 30,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnOnConsecutiveCalls(
			$this->mockHttpResponse(status: 200, body: ['count' => 9]),
			$this->mockHttpResponse(status: 500, body: [])
		);
		$this->clientService->method('newClient')->willReturn($client);

		$first = $this->service->resolveForPlacement(placementId: 5);
		$this->assertSame(9, $first['value']);
		$this->assertFalse($first['stale']);

		// Force the cache entry to look expired so the second call
		// re-attempts the (now-failing) upstream fetch.
		$this->expireCacheEntryFor(placementId: 5, config: $placement->getContentArray());

		$second = $this->service->resolveForPlacement(placementId: 5);
		$this->assertSame(9, $second['value'], 'stale fallback MUST serve the last-known value');
		$this->assertTrue($second['stale']);
	}//end testStaleFallbackServesLastKnownValueOnUpstreamFailure()

	public function testNoCacheAndNoUpstreamReturnsNullStaleShape(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 6, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 30,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 500, body: []));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 6);

		$this->assertSame(['value' => null, 'formatted' => null, 'badge' => null, 'fetchedAt' => null, 'stale' => true], $result);
	}//end testNoCacheAndNoUpstreamReturnsNullStaleShape()

	/**
	 * Rewrites the in-memory cache entry's internal `fetchedAtTs` to a
	 * point far enough in the past that the configured refresh interval
	 * has elapsed, without waiting in real time.
	 *
	 * @param integer $placementId
	 * @param array<string,mixed> $config
	 */
	private function expireCacheEntryFor(int $placementId, array $config): void {
		$key = 'value_' . $placementId . '_' . hash('sha256', (string)json_encode($config));
		$raw = $this->cache->get($key);
		$this->assertIsString($raw, 'precondition: a cache entry must exist to expire');
		$decoded = json_decode($raw, true);
		$decoded['fetchedAtTs'] = time() - 3600;
		$this->cache->set($key, json_encode($decoded));
	}//end expireCacheEntryFor()

	// -------------------------------------------------------------
	// REQ-LIVETILE-002/003: allow-list fail-closed.
	// -------------------------------------------------------------

	public function testFetchRefusedWhenHostNotOnAllowList(): void {
		// No allow-list configured — FAIL-CLOSED means every host is refused.
		$this->appConfig->method('getValueString')->willReturn('');
		$placement = $this->placementWithConfig(id: 7, config: [
			'sourceMode' => 'url',
			'url' => 'https://not-allowed.example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->never())->method('get');
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}//end testFetchRefusedWhenHostNotOnAllowList()

	public function testFetchRefusedWhenHostRemovedFromAllowListAfterConfiguration(): void {
		// Allow-list configured but for a DIFFERENT host.
		$this->appConfig->method('getValueString')->willReturn(json_encode(['other.example.com']));
		$placement = $this->placementWithConfig(id: 8, config: [
			'sourceMode' => 'url',
			'url' => 'https://removed.example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->never())->method('get');
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 8);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}//end testFetchRefusedWhenHostRemovedFromAllowListAfterConfiguration()

	public function testValidateSourceConfigRejectsUrlNotOnAllowListAtSaveTime(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$errors = $this->service->validateSourceConfig(config: [
			'sourceMode' => 'url',
			'url' => 'https://anywhere.example.com/api',
		]);

		$this->assertContains('host_not_allowed', $errors);
	}//end testValidateSourceConfigRejectsUrlNotOnAllowListAtSaveTime()

	public function testValidateSourceConfigAcceptsUrlOnAllowList(): void {
		$this->allowHost(host: 'ok.example.com');

		$errors = $this->service->validateSourceConfig(config: [
			'sourceMode' => 'url',
			'url' => 'https://ok.example.com/api',
		]);

		$this->assertSame([], $errors);
	}//end testValidateSourceConfigAcceptsUrlOnAllowList()

	// -------------------------------------------------------------
	// REQ-LIVETILE-004: formatting + badge.
	// -------------------------------------------------------------

	public function testFormatsValueWithPrefixSuffixAndThousandsSeparator(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 9, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 300,
			'format' => ['prefix' => '€', 'suffix' => ' open', 'thousands' => true],
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200, body: ['count' => 1234]));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 9);

		$this->assertSame('€1,234 open', $result['formatted']);
	}//end testFormatsValueWithPrefixSuffixAndThousandsSeparator()

	public function testBadgeConveysAlertStateWithLabel(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 10, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 300,
			'badge' => [
				'thresholds' => [
					['max' => 10, 'state' => 'ok', 'label' => 'Healthy'],
					['max' => 50, 'state' => 'warn', 'label' => 'Elevated'],
					['max' => 1000000, 'state' => 'alert', 'label' => 'Critical'],
				],
			],
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($this->mockHttpResponse(status: 200, body: ['count' => 99]));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->resolveForPlacement(placementId: 10);

		$this->assertSame(['state' => 'alert', 'label' => 'Critical'], $result['badge']);
	}//end testBadgeConveysAlertStateWithLabel()

	// -------------------------------------------------------------
	// REQ-LIVETILE-005: connector-absent capability probe.
	// -------------------------------------------------------------

	public function testConnectorAbsentDegradesToNullStaleWithNoCache(): void {
		$this->appManager->method('isEnabledForUser')->with('openconnector')->willReturn(false);
		$placement = $this->placementWithConfig(id: 11, config: [
			'sourceMode' => 'connector',
			'sourceId' => 'src-1',
			'valueExpr' => '$.open',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$result = $this->service->resolveForPlacement(placementId: 11);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}//end testConnectorAbsentDegradesToNullStaleWithNoCache()

	public function testIsConnectorAvailableFalseWhenAppDisabled(): void {
		$this->appManager->method('isEnabledForUser')->with('openconnector')->willReturn(false);
		$this->assertFalse($this->service->isConnectorAvailable());
	}//end testIsConnectorAvailableFalseWhenAppDisabled()

	public function testIsConnectorAvailableTrueWhenAppEnabledAndServicePresent(): void {
		$this->appManager->method('isEnabledForUser')->with('openconnector')->willReturn(true);
		$this->container->method('has')->willReturn(true);
		$this->assertTrue($this->service->isConnectorAvailable());
	}//end testIsConnectorAvailableTrueWhenAppEnabledAndServicePresent()

	public function testValidateSourceConfigFlagsConnectorUnavailable(): void {
		$this->appManager->method('isEnabledForUser')->willReturn(false);

		$errors = $this->service->validateSourceConfig(config: [
			'sourceMode' => 'connector',
			'sourceId' => 'src-1',
		]);

		$this->assertContains('connector_unavailable', $errors);
	}//end testValidateSourceConfigFlagsConnectorUnavailable()

	public function testResolvesViaConnectorWhenAvailable(): void {
		$this->appManager->method('isEnabledForUser')->with('openconnector')->willReturn(true);
		$this->container->method('has')->willReturn(true);

		$connectorService = new class {
			public function resolveDashboardValue(string $sourceId, string $valueExpr): array {
				return ['value' => 77];
			}
		};
		$this->container->method('get')->willReturn($connectorService);

		$placement = $this->placementWithConfig(id: 12, config: [
			'sourceMode' => 'connector',
			'sourceId' => 'src-1',
			'valueExpr' => '$.open',
			'refresh' => 300,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$result = $this->service->resolveForPlacement(placementId: 12);

		$this->assertSame(77, $result['value']);
		$this->assertFalse($result['stale']);
	}//end testResolvesViaConnectorWhenAvailable()

	// -------------------------------------------------------------
	// REQ-LIVETILE-002: refresh interval bounds (behavioural — via TTL).
	// -------------------------------------------------------------

	public function testRefreshBelowMinimumIsClampedToThirtySeconds(): void {
		$this->allowHost(host: 'example.com');
		$placement = $this->placementWithConfig(id: 13, config: [
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.count',
			'refresh' => 5,
		]);
		$this->placementMapper->method('find')->willReturn($placement);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->expects($this->once())->method('get')->willReturn($this->mockHttpResponse(status: 200, body: ['count' => 1]));
		$this->clientService->method('newClient')->willReturn($client);

		// Immediately re-resolving MUST still be a cache hit: even though
		// the author configured `refresh=5`, the clamp raises the
		// effective TTL to 30s, so within a few milliseconds of the first
		// call the cached entry is still fresh.
		$this->service->resolveForPlacement(placementId: 13);
		$second = $this->service->resolveForPlacement(placementId: 13);

		$this->assertFalse($second['stale']);
	}//end testRefreshBelowMinimumIsClampedToThirtySeconds()

	// -------------------------------------------------------------
	// REQ-LIVETILE-003: placement not found.
	// -------------------------------------------------------------

	public function testUnknownPlacementReturnsErrorShape(): void {
		$this->placementMapper->method('find')->willThrowException(new \Exception('not found'));

		$result = $this->service->resolveForPlacement(placementId: 999);

		$this->assertSame('placement_not_found', $result['error']);
	}//end testUnknownPlacementReturnsErrorShape()
}//end class
