<?php

/**
 * LiveTileService branch characterisation test
 *
 * Pins the source-config validation, the connector and direct-URL fetch
 * outcomes (with their logs and connection reports), the JSONPath-lite
 * extraction, the threshold badge and the cache behaviour of the
 * live-tile service, so its methods can be split into named steps
 * without changing what they return, log or report.
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
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\LiveTileSettings;
use OCA\LaunchPad\Service\ReadingSources;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class LiveTileServiceBranchesTest extends TestCase {
	private WidgetPlacementMapper $placementMapper;
	private IAppManager $appManager;
	private ContainerInterface $container;
	private IClientService $clientService;
	private IAppConfig $appConfig;
	private ICacheFactory $cacheFactory;
	private ICache $cache;
	private LoggerInterface $logger;
	private ConnectionReporter $reporter;

	protected function setUp(): void {
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->cache = $this->createMock(originalClassName: ICache::class);
		$this->cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->reporter = $this->createMock(originalClassName: ConnectionReporter::class);
		$this->appConfig->method('getValueString')->willReturn(json_encode(['example.com']));
	}//end setUp()

	/**
	 * Build the service under test with the current mock set.
	 *
	 * @return LiveTileService
	 */
	private function service(): LiveTileService {
		return new LiveTileService(
			new ReadingSources($this->appManager, $this->container, $this->clientService, $this->cacheFactory),
			new LiveTileSettings($this->appConfig, $this->logger),
			$this->placementMapper,
			$this->reporter,
		);
	}//end service()

	/**
	 * Point every placement lookup at the given config.
	 *
	 * @param array<string,mixed> $config The placement config.
	 *
	 * @return void
	 */
	private function placementWith(array $config): void {
		$placement = new WidgetPlacement();
		$placement->setId(7);
		$placement->setContentArray($config);
		$this->placementMapper->method('find')->willReturn($placement);
	}//end placementWith()

	/**
	 * Make the HTTP client answer with the given status and body.
	 *
	 * @param int    $status The status.
	 * @param string $body   The body.
	 *
	 * @return void
	 */
	private function httpAnswers(int $status, string $body): void {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);
	}//end httpAnswers()

	/**
	 * Resolve a url-mode tile for the given expression against the body.
	 *
	 * @param string $expr The value expression.
	 * @param string $body The JSON body.
	 *
	 * @return array<string,mixed>
	 */
	private function urlTile(string $expr, string $body): array {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => $expr]);
		$this->httpAnswers(200, $body);

		return $this->service()->resolveForPlacement(placementId: 7);
	}//end urlTile()

	/**
	 * Make the connector app enabled with the given service double.
	 *
	 * @param mixed $service The container's answer.
	 *
	 * @return void
	 */
	private function connectorWith(mixed $service): void {
		$this->appManager->method('isInstalled')->willReturnCallback(static fn (string $id): bool => $id === 'integriq');
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->container->method('has')->willReturn(true);
		$this->container->method('get')->willReturn($service);
	}//end connectorWith()

	/**
	 * Validation inputs and the error codes they produce.
	 *
	 * @return array<string,array{0:array<string,mixed>,1:list<string>}>
	 */
	public static function sourceConfigs(): array {
		return [
			'no mode'            => [[], ['invalid_source_mode']],
			'unknown mode'       => [['sourceMode' => 'ftp', 'url' => ''], ['invalid_source_mode']],
			'url missing'        => [['sourceMode' => 'url', 'url' => '  '], ['url_required']],
			'url bad scheme'     => [['sourceMode' => 'url', 'url' => 'ftp://example.com/x'], ['invalid_url']],
			'url host refused'   => [['sourceMode' => 'url', 'url' => 'https://evil.test/x'], ['host_not_allowed']],
			'url allowed'        => [['sourceMode' => 'url', 'url' => ' https://example.com/x '], []],
			'connector both bad' => [['sourceMode' => 'connector', 'sourceId' => ' '], ['source_id_required', 'connector_unavailable']],
			'connector no app'   => [['sourceMode' => 'connector', 'sourceId' => 's1'], ['connector_unavailable']],
		];
	}

	/**
	 * @dataProvider sourceConfigs
	 */
	public function testValidateSourceConfig(array $config, array $expected): void {
		$this->appManager->method('isInstalled')->willReturn(false);

		$this->assertSame($expected, $this->service()->validateSourceConfig(config: $config));
	}

	public function testValidConnectorConfigHasNoErrors(): void {
		$this->connectorWith(new \stdClass());

		$errors = $this->service()->validateSourceConfig(config: ['sourceMode' => 'connector', 'sourceId' => 's1']);

		$this->assertSame([], $errors);
	}

	public function testUnknownFetchModeDegradesToNullStale(): void {
		$this->placementWith(['sourceMode' => 'ftp']);
		$this->clientService->expects($this->never())->method('newClient');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(['value' => null, 'formatted' => null, 'badge' => null, 'fetchedAt' => null, 'stale' => true], $result);
	}

	public function testDefaultModeIsUrl(): void {
		$this->placementWith(['url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers(200, '{"n":5}');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(5, $result['value']);
	}

	/**
	 * Connector answers that give no value.
	 *
	 * @return array<string,array{0:mixed}>
	 */
	public static function emptyConnectorAnswers(): array {
		return [
			'not an array'  => ['x'],
			'no value key'  => [['other' => 1]],
		];
	}

	/**
	 * @dataProvider emptyConnectorAnswers
	 */
	public function testConnectorAnswerWithoutValueIsNull(mixed $answer): void {
		$this->connectorWith(new class($answer) {
			public function __construct(private mixed $answer) {
			}

			public function resolve(string $sourceId, string $valueExpr): mixed {
				return $this->answer;
			}
		});
		$this->placementWith(['sourceMode' => 'connector', 'sourceId' => 's1', 'valueExpr' => '$.v']);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}

	public function testConnectorReceivesSourceIdAndExpression(): void {
		$seen = new \ArrayObject();
		$this->connectorWith(new class($seen) {
			public function __construct(private \ArrayObject $seen) {
			}

			public function resolve(string $sourceId, string $valueExpr): array {
				$this->seen->append([$sourceId, $valueExpr]);
				return ['value' => null];
			}
		});
		$this->placementWith(['sourceMode' => 'connector', 'sourceId' => 's9']);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame([['s9', '']], $seen->getArrayCopy());
		$this->assertNull($result['value']);
		$this->assertNull($result['formatted']);
	}

	public function testConnectorWithoutSourceIdIsNotCalled(): void {
		$this->connectorWith(new class() {
			public function resolve(string $sourceId, string $valueExpr): array {
				throw new RuntimeException('must not be called');
			}
		});
		$this->placementWith(['sourceMode' => 'connector', 'valueExpr' => '$.v']);
		$this->logger->expects($this->never())->method('info');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	public function testConnectorServiceUnresolvableIsNull(): void {
		$this->appManager->method('isInstalled')->willReturnCallback(static fn (string $id): bool => $id === 'integriq');
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->container->method('has')->willReturn(true);
		$this->container->method('get')->willThrowException(new RuntimeException('nope'));
		$this->placementWith(['sourceMode' => 'connector', 'sourceId' => 's1']);
		$this->logger->expects($this->never())->method('info');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	public function testConnectorServiceWithoutResolveIsNull(): void {
		$this->connectorWith(new \stdClass());
		$this->placementWith(['sourceMode' => 'connector', 'sourceId' => 's1']);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	public function testConnectorCallFailureIsLogged(): void {
		$this->connectorWith(new class() {
			public function resolve(string $sourceId, string $valueExpr): array {
				throw new RuntimeException('connector broke');
			}
		});
		$this->placementWith(['sourceMode' => 'connector', 'sourceId' => 's1']);
		$this->logger->expects($this->once())->method('info')->with(
			'LiveTileService: connector resolve call failed',
			['app' => 'launchpad', 'exception' => 'connector broke']
		);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	/**
	 * URL configs that never open a connection.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function unfetchableUrls(): array {
		return ['empty' => ['  '], 'bad scheme' => ['ftp://example.com/x']];
	}

	/**
	 * @dataProvider unfetchableUrls
	 */
	public function testUnfetchableUrlIsNullWithoutReport(string $url): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => $url, 'valueExpr' => '$.n']);
		$this->clientService->expects($this->never())->method('newClient');
		$this->reporter->expects($this->never())->method($this->anything());
		$this->logger->expects($this->never())->method('warning');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	public function testRefusedHostIsLoggedAndReported(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://evil.test/x', 'valueExpr' => '$.n']);
		$this->clientService->expects($this->never())->method('newClient');
		$this->logger->expects($this->once())->method('warning')->with(
			'LiveTileService: host not on livetile_allowed_hosts, refusing fetch (fail-closed)',
			['app' => 'launchpad']
		);
		$this->reporter->expects($this->once())->method('reportAllowListRefusal')->with('live-tiles');
		$this->reporter->expects($this->never())->method('reportCall');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	public function testTransportFailureIsLoggedAndReportedWithoutStatus(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new RuntimeException('down'));
		$this->clientService->method('newClient')->willReturn($client);
		$this->logger->expects($this->once())->method('info')->with(
			'LiveTileService: direct-URL fetch failed',
			['app' => 'launchpad', 'exception' => 'down']
		);
		$this->reporter->expects($this->once())->method('reportCall')->with('live-tiles', 'https://example.com/api', null);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
	}

	/**
	 * Responses that are reported with their status but give no value.
	 *
	 * @return array<string,array{0:int,1:string}>
	 */
	public static function valuelessResponses(): array {
		return [
			'199'       => [199, '{"n":1}'],
			'300'       => [300, '{"n":1}'],
			'503'       => [503, '{"n":1}'],
			'not json'  => [200, 'nope'],
			'scalar'    => [200, '3'],
			'miss'      => [200, '{"m":1}'],
			'null leaf' => [200, '{"n":null}'],
		];
	}

	/**
	 * @dataProvider valuelessResponses
	 */
	public function testResponseIsReportedWithStatusBeforeBeingRead(int $status, string $body): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers($status, $body);
		$this->reporter->expects($this->once())->method('reportCall')->with('live-tiles', 'https://example.com/api', $status);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertNull($result['value']);
		$this->assertTrue($result['stale']);
	}

	/**
	 * Expressions and the value they extract from a fixed body.
	 *
	 * @return array<string,array{0:string,1:mixed}>
	 */
	public static function expressions(): array {
		return [
			'empty'           => ['', null],
			'no dollar'       => ['a.b', null],
			'nested'          => ['$.a.b', 2],
			'index'           => ['$.list[1]', 20],
			'index too far'   => ['$.list[2]', null],
			'index on object' => ['$.a[0]', null],
			'prop on scalar'  => ['$.a.b.c', null],
			'garbage tail'    => ['$.a.b!', null],
			'no tokens'       => ['$abc', null],
		];
	}

	/**
	 * @dataProvider expressions
	 */
	public function testExpressionExtraction(string $expr, mixed $expected): void {
		$result = $this->urlTile($expr, '{"a":{"b":2},"list":[10,20]}');

		$this->assertSame($expected, $result['value']);
	}

	/**
	 * Badge configs, values and the badge they produce.
	 *
	 * @return array<string,array{0:mixed,1:array<string,mixed>,2:mixed}>
	 */
	public static function badges(): array {
		$tiers = ['thresholds' => [
			['max' => 100, 'state' => 'alert', 'label' => 'High'],
			['max' => 10, 'state' => 'ok'],
			['max' => 50, 'state' => 'bogus', 'label' => 'Mid'],
		]];
		return [
			'no thresholds'   => [5, [], null],
			'empty list'      => [5, ['thresholds' => []], null],
			'not a list'      => [5, ['thresholds' => 'x'], null],
			'text value'      => ['abc', $tiers, null],
			'lowest tier'     => [10, $tiers, ['state' => 'ok', 'label' => 'Ok']],
			'unknown state'   => [11, $tiers, ['state' => 'ok', 'label' => 'Mid']],
			'top tier'        => [100, $tiers, ['state' => 'alert', 'label' => 'High']],
			'beyond all'      => [101, $tiers, ['state' => 'alert', 'label' => 'High']],
			'only non-array'  => [5, ['thresholds' => ['x']], null],
			'single tier'     => [9, ['thresholds' => [['max' => 1, 'state' => 'warn']]], ['state' => 'warn', 'label' => 'Warn']],
		];
	}

	/**
	 * @dataProvider badges
	 */
	public function testBadge(mixed $value, array $badge, mixed $expected): void {
		$this->placementWith([
			'sourceMode' => 'url',
			'url' => 'https://example.com/api',
			'valueExpr' => '$.v',
			'badge' => $badge,
		]);
		$this->httpAnswers(200, json_encode(['v' => $value]));

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame($expected, $result['badge']);
	}

	public function testFreshValueIsCachedForTheClampedRefresh(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n', 'refresh' => 45]);
		$this->httpAnswers(200, '{"n":3}');
		$this->cache->method('get')->willReturn(null);
		$this->cache->expects($this->once())->method('set')->with(
			$this->stringStartsWith('value_7_'),
			$this->isType('string'),
			45
		);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(3, $result['value']);
		$this->assertSame('3', $result['formatted']);
		$this->assertFalse($result['stale']);
	}

	public function testDefaultRefreshIsUsedWhenUnset(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers(200, '{"n":3}');
		$this->cache->method('get')->willReturn(null);
		$this->cache->expects($this->once())->method('set')->with($this->anything(), $this->anything(), 300);

		$this->service()->resolveForPlacement(placementId: 7);
	}

	public function testFutureStampedCacheIsRefetched(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers(200, '{"n":4}');
		$this->cache->method('get')->willReturn(json_encode(['rawValue' => 1, 'fetchedAtTs' => (time() + 1000)]));

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(4, $result['value']);
	}

	public function testExpiredCacheIsServedStaleWhenUpstreamFails(): void {
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers(500, '');
		$this->cache->method('get')->willReturn(json_encode(['rawValue' => 1, 'fetchedAtTs' => (time() - 1000)]));

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(1, $result['value']);
		$this->assertTrue($result['stale']);
	}

	public function testFreshValueWithoutCacheIsServed(): void {
		$this->cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willThrowException(new RuntimeException('no cache'));
		$this->placementWith(['sourceMode' => 'url', 'url' => 'https://example.com/api', 'valueExpr' => '$.n']);
		$this->httpAnswers(200, '{"n":6}');

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame(6, $result['value']);
		$this->assertFalse($result['stale']);
	}
}//end class
