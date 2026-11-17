<?php

/**
 * LiveTileListsTest
 * A list, table or key-value tile through LiveTileService: only mapped
 * fields reach the response and the cache (REQ-LTC-002), the config is
 * validated at save time for both modes (REQ-LTC-001).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\LiveTileListMapper;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

#[Small]
#[CoversClass(LiveTileService::class)]
#[UsesClass(LiveTileListMapper::class)]
class LiveTileListsTest extends TestCase {

	/** @var array<string,string> What the service wrote to the cache. */
	private array $cached = [];

	private IAppManager $appManager;

	private ContainerInterface $container;

	private IClientService $clientService;

	private WidgetPlacementMapper $placementMapper;

	private function service(): LiveTileService {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => ($this->cached[$key] ?? null));
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value): bool {
			$this->cached[$key] = $value;
			return true;
		});
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturn(json_encode(['topdesk.example']));

		return new LiveTileService(
			sources: new ReadingSources(appManager: $this->appManager, container: $this->container, clientService: $this->clientService, cacheFactory: $cacheFactory),
			settings: new LiveTileSettings(appConfig: $appConfig, logger: $this->createMock(LoggerInterface::class)),
			placementMapper: $this->placementMapper
		);
	}//end service()

	protected function setUp(): void {
		$this->appManager = $this->createMock(IAppManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
	}//end setUp()

	/**
	 * @param array<string,mixed> $config
	 */
	private function placement(array $config): void {
		$placement = new WidgetPlacement();
		$placement->setId(7);
		$placement->setContentArray($config);
		$this->placementMapper->method('find')->willReturn($placement);
	}//end placement()

	/**
	 * @param array<mixed> $body
	 */
	private function respond(array $body): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode($body));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);
	}//end respond()

	/**
	 * @return array<string,mixed>
	 */
	private function listConfig(): array {
		return [
			'sourceMode' => 'url',
			'url' => 'https://topdesk.example/api/incidents',
			'valueExpr' => '$.incidents',
			'display' => 'list',
			'limit' => 5,
			'mapping' => [
				['label' => 'Incident', 'path' => 'briefDescription', 'role' => 'title'],
				['label' => 'Open', 'path' => 'url', 'role' => 'link'],
				['label' => 'Reported', 'path' => 'callDate', 'role' => 'date'],
			],
			'refresh' => 300,
		];
	}//end listConfig()

	public function testAUrlListTileReturnsOnlyMappedRowsAndCachesNoRawData(): void {
		$incidents = [];
		for ($i = 1; $i <= 8; $i++) {
			$incidents[] = ['briefDescription' => 'Printer '.$i, 'url' => 'https://topdesk.example/i/'.$i, 'callDate' => '2026-11-0'.$i, 'callerEmail' => 'caller'.$i.'@example.nl'];
		}

		$this->respond(body: ['incidents' => $incidents]);
		$this->placement(config: $this->listConfig());

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame('list', $result['display']);
		$this->assertCount(5, $result['items']);
		$this->assertSame(['label' => 'Incident', 'role' => 'title', 'value' => 'Printer 1'], $result['items'][0][0]);
		$this->assertNull($result['value']);
		$this->assertFalse($result['stale']);
		$this->assertStringNotContainsString('callerEmail', (string)json_encode($result));
		$this->assertStringNotContainsString('callerEmail', implode('', $this->cached));
		$this->assertStringNotContainsString('Printer 6', implode('', $this->cached));
	}//end testAUrlListTileReturnsOnlyMappedRowsAndCachesNoRawData()

	public function testAConnectorNodeIsMappedTheSameWay(): void {
		$this->appManager->method('isInstalled')->willReturnCallback(static fn (string $app): bool => $app === 'integriq');
		$this->appManager->method('isEnabledForUser')->willReturnCallback(static fn (string $app): bool => $app === 'integriq');
		$this->container->method('has')->willReturn(true);
		$this->container->method('get')->willReturn(new class {
			public function resolve(string $sourceId, string $valueExpr): array {
				return ['value' => ['open' => 4, 'team' => 'Servicedesk', 'callerEmail' => 'x@example.nl']];
			}
		});
		$this->placement(config: [
			'sourceMode' => 'connector',
			'sourceId' => 'topdesk',
			'valueExpr' => '$.summary',
			'display' => 'keyvalue',
			'mapping' => [['label' => 'Open', 'path' => 'open', 'role' => 'field'], ['label' => 'Team', 'path' => 'team', 'role' => 'field']],
		]);

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertSame('keyvalue', $result['display']);
		$this->assertSame(
			[['label' => 'Open', 'role' => 'field', 'value' => 4], ['label' => 'Team', 'role' => 'field', 'value' => 'Servicedesk']],
			$result['pairs']
		);
	}//end testAConnectorNodeIsMappedTheSameWay()

	public function testANodeThatIsNotAListDegradesToStale(): void {
		$this->respond(body: ['incidents' => ['briefDescription' => 'one object']]);
		$this->placement(config: $this->listConfig());

		$result = $this->service()->resolveForPlacement(placementId: 7);

		$this->assertTrue($result['stale']);
		$this->assertNull($result['value']);
		$this->assertArrayNotHasKey('items', $result);
	}//end testANodeThatIsNotAListDegradesToStale()

	public function testTheDisplayIsValidatedAtSaveTimeForBothModes(): void {
		$this->assertSame(['mapping_required'], $this->service()->validateSourceConfig(config: ['display' => 'table'] + array_diff_key($this->listConfig(), ['mapping' => 1])));
		$this->assertSame([], $this->service()->validateSourceConfig(config: $this->listConfig()));
		$this->assertSame(['url_required', 'invalid_limit'], $this->service()->validateSourceConfig(config: ['sourceMode' => 'url', 'limit' => 99]));
	}//end testTheDisplayIsValidatedAtSaveTimeForBothModes()
}//end class
