<?php

/**
 * Connection report wiring, seen from the services that make the calls.
 *
 * ConnectionReporterTest proves the reporter on its own. These tests run the
 * REAL services with the REAL reporter behind them, so a caller that stops
 * reporting, reports before refreshing, reports on every page load or lets a
 * report change its own answer goes red here. Only the edges are doubled.
 *
 * @category Tests
 * @package  Unit\Service\Connection
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-002-a-registry-settings-save-asks-integriq-to-look-again
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Connection;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCA\LaunchPad\Service\WeatherService;
use OCA\OpenRegister\AppHost\Service\GenericStoreService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

require_once __DIR__ . '/../../../Stubs/OpenRegisterStubs.php';

/**
 * The six callers of ConnectionReporter.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Builds every caller with its real constructor.
 */
class ConnectionCallersTest extends TestCase {
	use ConnectionReporterFixture;

	/**
	 * Set up the fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->sentEvents  = [];
		$this->configStore = [];
	}//end setUp()

	/**
	 * A cache that never holds anything, so every resolve makes a real call.
	 *
	 * @return ICacheFactory
	 */
	private function emptyCacheFactory(): ICacheFactory {
		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->method('get')->willReturn(null);
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);

		return $factory;
	}//end emptyCacheFactory()

	/**
	 * A client service whose client answers every GET with this status, counting the calls.
	 *
	 * @param integer $status The HTTP status to answer.
	 * @param integer $calls  Counts the GETs.
	 *
	 * @return IClientService
	 */
	private function answeringClients(int $status, int &$calls): IClientService {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn('');

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			static function () use ($response, &$calls): IResponse {
				$calls++;
				return $response;
			}
		);

		$clients = $this->createMock(originalClassName: IClientService::class);
		$clients->method('newClient')->willReturn($client);

		return $clients;
	}//end answeringClients()

	/**
	 * The store service with a discovery engine that answers these outcomes in turn.
	 *
	 * @param IAppConfig              $appConfig The app config the service and reporter share.
	 * @param ConnectionReporter|null $reporter  The reporter, or null for none.
	 * @param array<int, string>      $outcomes  The outcomes the engine answers, in order.
	 *
	 * @return StoreService
	 */
	private function store(IAppConfig $appConfig, ?ConnectionReporter $reporter, array $outcomes): StoreService {
		$discovery = $this->createMock(originalClassName: GenericStoreService::class);
		$discovery->method('search')->willReturnOnConsecutiveCalls(
			...array_map(static fn (string $outcome): array => ['outcome' => $outcome, 'cards' => []], $outcomes)
		);

		return new StoreService(
			discovery: $discovery,
			importService: $this->createMock(originalClassName: ImportService::class),
			appConfig: $appConfig,
			logger: new NullLogger(),
			connectionReporter: $reporter,
		);
	}//end store()

	/**
	 * A health ping service whose placement 7 pings https://svc.example.nl/health.
	 *
	 * @param IAppConfig         $appConfig The app config the service and reporter share.
	 * @param IClientService     $clients   The HTTP clients.
	 * @param ConnectionReporter $reporter  The reporter.
	 *
	 * @return HealthPingService
	 */
	private function healthPing(IAppConfig $appConfig, IClientService $clients, ConnectionReporter $reporter): HealthPingService {
		$placement = new WidgetPlacement();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setId(7);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setContentArray(
			[
				'healthPingEnabled' => true,
				'healthUrl'         => 'https://svc.example.nl/health?token=secret',
				'expectedStatus'    => 200,
				'pingInterval'      => 60,
			]
		);
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('find')->willReturn($placement);

		return new HealthPingService(
			clientService: $clients,
			cacheFactory: $this->emptyCacheFactory(),
			appConfig: $appConfig,
			placementMapper: $placements,
			logger: new NullLogger(),
			connectionReporter: $reporter,
		);
	}//end healthPing()

	/**
	 * A registry save through the store refreshes first, and the next search reports at once.
	 *
	 * @return void
	 */
	public function testARegistrySaveRefreshesBeforeTheNextSearchReports(): void {
		$appConfig = $this->backedAppConfig();
		$store     = $this->store(
			appConfig: $appConfig,
			reporter: $this->recordingReporter(appConfig: $appConfig),
			outcomes: ['store_unreachable', 'ok']
		);
		$this->configStore['registry_url'] = 'https://old.example.nl';

		$this->assertSame(expected: 'store_unreachable', actual: $store->search()['outcome']);

		$this->now += 60;
		$store->updateRegistryConfig(registryUrl: 'https://registry.gemeente.example/index.php');

		$this->assertSame(expected: 'ok', actual: $store->search(query: 'sales')['outcome']);

		$this->assertSame(
			expected: [
				[
					'ConnectionStatusReportedEvent',
					'dashboard-registry',
					'error',
					'The last search could not reach the dashboard registry at old.example.nl.',
				],
				['ConnectionRefreshRequestedEvent', 'dashboard-registry', '', ''],
				[
					'ConnectionStatusReportedEvent',
					'dashboard-registry',
					'configured',
					'The dashboard registry at registry.gemeente.example answered the last search.',
				],
			],
			actual: $this->sentRows()
		);
	}//end testARegistrySaveRefreshesBeforeTheNextSearchReports()

	/**
	 * Without OpenRegister the store answers not_configured and reports the registry unavailable.
	 *
	 * @return void
	 */
	public function testAStoreWithoutOpenRegisterReportsUnavailable(): void {
		$store = new StoreService(
			discovery: null,
			importService: $this->createMock(originalClassName: ImportService::class),
			appConfig: $this->backedAppConfig(),
			logger: new NullLogger(),
			connectionReporter: $this->recordingReporter(),
		);

		$this->assertSame(expected: ['outcome' => 'not_configured', 'cards' => []], actual: $store->search());
		$this->assertSame(
			expected: [
				[
					'ConnectionStatusReportedEvent',
					'dashboard-registry',
					'unavailable',
					'OpenRegister is not enabled, so LaunchPad cannot reach a dashboard registry.',
				],
			],
			actual: $this->sentRows()
		);
	}//end testAStoreWithoutOpenRegisterReportsUnavailable()

	/**
	 * Without integriq the store answers and saves exactly as before, and sends nothing.
	 *
	 * @return void
	 */
	public function testWithoutIntegriqTheStoreAnswersAndSavesAndSendsNothing(): void {
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');
		$appConfig = $this->backedAppConfig();

		$time     = $this->createMock(originalClassName: ITimeFactory::class);
		$reporter = new class($dispatcher, $appConfig, $time, new NullLogger()) extends ConnectionReporter {

			/**
			 * Integriq is not installed, so no class resolves.
			 *
			 * @param string $eventClass The class name asked for.
			 *
			 * @return string|null Always null.
			 */
			protected function resolveEventClass(string $eventClass): ?string {
				return null;
			}//end resolveEventClass()
		};

		$store = $this->store(appConfig: $appConfig, reporter: $reporter, outcomes: ['store_unreachable']);

		$store->updateRegistryConfig(registryUrl: ' https://registry.example.nl ');
		$this->assertSame(expected: ['outcome' => 'store_unreachable', 'cards' => []], actual: $store->search());
		$this->assertSame(expected: ['registry_url' => 'https://registry.example.nl'], actual: $this->configStore);
	}//end testWithoutIntegriqTheStoreAnswersAndSavesAndSendsNothing()

	/**
	 * Fifty health pings within the hour make fifty calls and send one report, host only.
	 *
	 * @return void
	 */
	public function testManyPingsSendOneReportPerWindow(): void {
		$this->configStore['healthping_allowed_hosts'] = '["svc.example.nl"]';
		$appConfig = $this->backedAppConfig();
		$calls     = 0;
		$service   = $this->healthPing(
			appConfig: $appConfig,
			clients: $this->answeringClients(status: 200, calls: $calls),
			reporter: $this->recordingReporter(appConfig: $appConfig)
		);

		for ($load = 0; $load < 50; $load++) {
			$this->assertSame(expected: 'online', actual: $service->resolveForPlacement(placementId: 7)['state']);
			$this->now += 30;
		}

		$this->assertSame(expected: 50, actual: $calls);
		$this->assertSame(
			expected: [
				['ConnectionStatusReportedEvent', 'health-ping', 'configured', 'The health ping target at svc.example.nl answered the last call.'],
			],
			actual: $this->sentRows()
		);
	}//end testManyPingsSendOneReportPerWindow()

	/**
	 * A listener that throws never changes what the ping answers.
	 *
	 * @return void
	 */
	public function testAThrowingListenerLeavesThePingAnswerAlone(): void {
		$this->configStore['healthping_allowed_hosts'] = '["svc.example.nl"]';
		$appConfig  = $this->backedAppConfig();
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->expects($this->once())->method('dispatchTyped')->willThrowException(new RuntimeException('integriq down'));
		$calls = 0;

		$service = $this->healthPing(
			appConfig: $appConfig,
			clients: $this->answeringClients(status: 503, calls: $calls),
			reporter: new ConnectionReporter(
				eventDispatcher: $dispatcher,
				appConfig: $appConfig,
				timeFactory: $this->createMock(originalClassName: ITimeFactory::class),
				logger: $this->createMock(originalClassName: LoggerInterface::class),
			)
		);

		$this->assertSame(expected: 'offline', actual: $service->resolveForPlacement(placementId: 7)['state']);
		$this->assertArrayNotHasKey(key: 'connection_report_health-ping', array: $this->configStore);
	}//end testAThrowingListenerLeavesThePingAnswerAlone()

	/**
	 * An empty fail-closed allow-list refuses the ping and reports the row unconfigured.
	 *
	 * @return void
	 */
	public function testARefusedPingOnAnEmptyAllowListReportsUnconfigured(): void {
		$appConfig = $this->backedAppConfig();
		$calls     = 0;
		$service   = $this->healthPing(
			appConfig: $appConfig,
			clients: $this->answeringClients(status: 200, calls: $calls),
			reporter: $this->recordingReporter(appConfig: $appConfig)
		);

		$this->assertNull(actual: $service->resolveForPlacement(placementId: 7)['state']);

		$this->assertSame(expected: 0, actual: $calls);
		$this->assertSame(
			expected: [
				[
					'ConnectionStatusReportedEvent',
					'health-ping',
					'unconfigured',
					'healthping_allowed_hosts holds no JSON list of hosts, so LaunchPad refused the last call. Set it with occ.',
				],
			],
			actual: $this->sentRows()
		);
	}//end testARefusedPingOnAnEmptyAllowListReportsUnconfigured()

	/**
	 * A calendar fetch that gets no answer reports it, and still throws what it threw.
	 *
	 * @return void
	 */
	public function testAFailedCalendarFetchReportsAndStillThrows(): void {
		$validator = $this->createMock(originalClassName: UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturn(true);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new RuntimeException('connection refused'));
		$clients = $this->createMock(originalClassName: IClientService::class);
		$clients->method('newClient')->willReturn($client);

		$appConfig = $this->backedAppConfig();
		$service   = new CalendarWidgetService(
			appConfig: $appConfig,
			cacheFactory: $this->emptyCacheFactory(),
			clientService: $clients,
			logger: new NullLogger(),
			urlValidator: $validator,
			calendarMgr: null,
			connectionReporter: $this->recordingReporter(appConfig: $appConfig),
		);

		try {
			$service->fetchIcsBody(url: 'https://cal.example.nl/team.ics');
			$this->fail(message: 'The fetch must still throw.');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 'connection refused', actual: $e->getMessage());
		}

		$this->assertSame(
			expected: [
				['ConnectionStatusReportedEvent', 'ics-calendars', 'error', 'The last call to the calendar feed at cal.example.nl got no answer.'],
			],
			actual: $this->sentRows()
		);
	}//end testAFailedCalendarFetchReportsAndStillThrows()

	/**
	 * A feed host that answers 503 reports the row as error, naming the host and nothing else from the URL.
	 *
	 * @return void
	 */
	public function testAFailedFeedFetchReportsOnlyTheHost(): void {
		$validator = $this->createMock(originalClassName: UrlSafetyValidator::class);
		$validator->method('isSafe')->willReturn(true);
		$validator->method('checkAllowList')->willReturn(true);
		$calls     = 0;
		$appConfig = $this->backedAppConfig();

		$service = new NewsWidgetService(
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			clientService: $this->answeringClients(status: 503, calls: $calls),
			appConfig: $appConfig,
			cacheFactory: $this->emptyCacheFactory(),
			logger: new NullLogger(),
			urlValidator: $validator,
			connectionReporter: $this->recordingReporter(appConfig: $appConfig),
		);

		$secretUrl = 'https://user:secret@feeds.example.nl/rss?token=abc';
		$answer    = $service->fetchAndMergeFeeds(feedUrls: [$secretUrl]);

		$this->assertSame(expected: 1, actual: $calls);
		$this->assertSame(expected: [$secretUrl], actual: $answer['failedUrls']);
		$this->assertSame(
			expected: [
				['ConnectionStatusReportedEvent', 'news-feeds', 'error', 'The news feed at feeds.example.nl answered HTTP 503 on the last call.'],
			],
			actual: $this->sentRows()
		);
	}//end testAFailedFeedFetchReportsOnlyTheHost()

	/**
	 * The weather service on a widget with or without a location, and no provider URL.
	 *
	 * @param array<string, string> $content The placement content.
	 *
	 * @return WeatherService
	 */
	private function weather(array $content): WeatherService {
		$placement = $this->createMock(originalClassName: WidgetPlacement::class);
		$placement->method('getContentArray')->willReturn($content);
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('find')->willReturn($placement);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);

		$appConfig = $this->backedAppConfig();

		return new WeatherService(
			appManager: $appManager,
			container: $this->createMock(originalClassName: ContainerInterface::class),
			clientService: $this->createMock(originalClassName: IClientService::class),
			cacheFactory: $this->emptyCacheFactory(),
			appConfig: $appConfig,
			placementMapper: $placements,
			config: $this->createMock(originalClassName: IConfig::class),
			logger: new NullLogger(),
			connectionReporter: $this->recordingReporter(appConfig: $appConfig),
		);
	}//end weather()

	/**
	 * A weather widget with a location and no provider URL reports the row unconfigured.
	 *
	 * @return void
	 */
	public function testAWeatherWidgetWithALocationAndNoProviderUrlReportsUnconfigured(): void {
		$this->assertSame(
			expected: ['error' => 'weather_unavailable'],
			actual: $this->weather(content: ['location' => 'Utrecht'])->resolveForPlacement(placementId: 1, userId: 'alice')
		);

		$this->assertSame(
			expected: [
				[
					'ConnectionStatusReportedEvent',
					'weather',
					'unconfigured',
					'A weather widget with a location found no provider URL. Set weather_provider_url with occ.',
				],
			],
			actual: $this->sentRows()
		);
	}//end testAWeatherWidgetWithALocationAndNoProviderUrlReportsUnconfigured()

	/**
	 * A weather widget without a location reads the weather status app, so an empty URL reports nothing.
	 *
	 * @return void
	 */
	public function testAWeatherWidgetWithoutALocationReportsNothing(): void {
		$this->weather(content: [])->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(expected: [], actual: $this->sentEvents);
	}//end testAWeatherWidgetWithoutALocationReportsNothing()
}//end class
