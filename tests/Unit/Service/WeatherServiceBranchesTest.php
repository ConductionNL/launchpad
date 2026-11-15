<?php

/**
 * WeatherService branch characterisation test
 *
 * Pins the weather_status reuse path, the provider-URL outcomes and the
 * connection reports of the weather-widget resolution service, so the
 * methods behind them can be split into named steps without changing
 * what they return, log or report. Every upstream call is mocked.
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
use OCA\LaunchPad\Service\ReadingSources;
use OCA\LaunchPad\Service\WeatherService;
use OCA\LaunchPad\Service\WeatherSettings;
use OCA\LaunchPad\Service\WeatherStore;
use OCP\App\IAppManager;
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
use RuntimeException;

class WeatherServiceBranchesTest extends TestCase {
	private IAppManager $appManager;
	private ContainerInterface $container;
	private IClientService $clientService;
	private ICacheFactory $cacheFactory;
	private IAppConfig $appConfig;
	private WidgetPlacementMapper $placementMapper;
	private IConfig $config;
	private LoggerInterface $logger;
	private ICache $cache;
	private ConnectionReporter $reporter;

	/**
	 * Provider URL template the provider-path tests configure.
	 *
	 * @var string
	 */
	private string $template = 'https://provider.example/w?q={location}&k={apiKey}&u={units}&l={lang}';

	protected function setUp(): void {
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->cache = $this->createMock(originalClassName: ICache::class);
		$this->reporter = $this->createMock(originalClassName: ConnectionReporter::class);

		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->appConfig->method('getValueInt')->willReturn(900);
		$this->config->method('getUserValue')->willReturn('');
		$this->config->method('getSystemValue')->willReturnCallback(
			static fn (string $key, $default = '') => $default
		);
		$this->cache->method('get')->willReturn(null);
	}//end setUp()

	/**
	 * Build the service under test with the current mock set.
	 *
	 * @return WeatherService
	 */
	private function service(): WeatherService {
		return new WeatherService(
			new ReadingSources(
				$this->appManager,
				$this->container,
				$this->clientService,
				$this->cacheFactory,
			),
			new WeatherSettings($this->appConfig, $this->config),
			new WeatherStore($this->placementMapper, $this->logger),
			$this->reporter,
		);
	}//end service()

	/**
	 * Point placement 1 at the given widget config.
	 *
	 * @param array<string,mixed> $content The placement config.
	 *
	 * @return void
	 */
	private function placementWith(array $content): void {
		$placement = $this->createMock(originalClassName: WidgetPlacement::class);
		$placement->method('getContentArray')->willReturn($content);
		$this->placementMapper->method('find')->willReturn($placement);
	}//end placementWith()

	/**
	 * Configure the provider template and API key.
	 *
	 * @param string $template The provider URL template.
	 *
	 * @return void
	 */
	private function providerTemplate(string $template): void {
		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use ($template): string {
				if ($key === 'weather_provider_url') {
					return $template;
				}

				return 'key with space';
			}
		);
	}//end providerTemplate()

	/**
	 * Make the provider answer with the given status and body.
	 *
	 * @param int    $status The HTTP status.
	 * @param string $body   The response body.
	 *
	 * @return IClient The client mock.
	 */
	private function providerAnswers(int $status, string $body): IClient {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);

		return $client;
	}//end providerAnswers()

	/**
	 * Install a weather_status service double answering the given forecast.
	 *
	 * @param object $service The service double.
	 *
	 * @return void
	 */
	private function weatherStatus(object $service): void {
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->container->method('has')->willReturn(true);
		$this->container->method('get')->willReturn($service);
	}//end weatherStatus()

	/**
	 * A weather_status double with the given forecast and location.
	 *
	 * @param mixed $forecast The forecast answer.
	 * @param array $location The location answer.
	 *
	 * @return object
	 */
	private function forecastService(mixed $forecast, array $location): object {
		return new class($forecast, $location) {
			public function __construct(
				private mixed $forecast,
				private array $location,
			) {
			}

			public function getForecast(): mixed {
				return $this->forecast;
			}

			public function getLocation(): array {
				return $this->location;
			}
		};
	}//end forecastService()

	/**
	 * A met.no timeseries entry.
	 *
	 * @param array<string,mixed> $data The entry's data block.
	 *
	 * @return array<int,mixed>
	 */
	private function timeseries(array $data): array {
		return [['data' => $data]];
	}//end timeseries()

	public function testWeatherStatusDisabledGivesUnavailable(): void {
		$this->placementWith([]);
		$this->appManager->method('isEnabledForUser')->willReturn(false);
		$this->container->expects($this->never())->method('has');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testWeatherStatusServiceMissingGivesUnavailable(): void {
		$this->placementWith([]);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->container->method('has')->willReturn(false);
		$this->container->expects($this->never())->method('get');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testWeatherStatusServiceWithoutForecastMethodGivesUnavailable(): void {
		$this->placementWith([]);
		$this->weatherStatus(new class() {
			public function getLocation(): array {
				return [];
			}
		});

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testWeatherStatusServiceWithoutLocationMethodGivesUnavailable(): void {
		$this->placementWith([]);
		$this->weatherStatus(new class() {
			public function getForecast(): array {
				return [['data' => []]];
			}
		});

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	/**
	 * Unusable forecast answers.
	 *
	 * @return array<string,array{0:mixed}>
	 */
	public static function unusableForecasts(): array {
		return [
			'not an array' => ['nope'],
			'empty'        => [[]],
			'error key'    => [['error' => 'no location']],
			'no entry'     => [[1 => 'x']],
			'no details'   => [[['data' => ['instant' => []]]]],
			'no temp'      => [[['data' => ['instant' => ['details' => ['wind' => 1]]]]]],
			'entry scalar' => [['x']],
		];
	}

	/**
	 * @dataProvider unusableForecasts
	 */
	public function testUnusableForecastGivesUnavailable(mixed $forecast): void {
		$this->placementWith([]);
		$this->weatherStatus($this->forecastService($forecast, []));

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testWeatherStatusThrowingIsLoggedAndUnavailable(): void {
		$this->placementWith([]);
		$this->appManager->method('isEnabledForUser')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects($this->once())->method('info')->with(
			'WeatherService: weather_status provider unavailable, falling back',
			['app' => 'launchpad', 'exception' => 'boom']
		);

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testWeatherStatusMetricReadingUsesNextHourSymbolAndAddress(): void {
		$this->placementWith([]);
		$this->weatherStatus($this->forecastService(
			$this->timeseries([
				'instant' => ['details' => ['air_temperature' => 10]],
				'next_1_hours' => ['summary' => ['symbol_code' => 'partlycloudy_day']],
				'next_6_hours' => ['summary' => ['symbol_code' => 'rain']],
			]),
			['address' => 'Utrecht, NL']
		));
		$this->cache->expects($this->once())->method('set')->with(
			$this->stringStartsWith('reading_'),
			$this->isType('string'),
			900
		);

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('Utrecht, NL', $result['location']);
		$this->assertSame(10.0, $result['tempValue']);
		$this->assertSame('metric', $result['units']);
		$this->assertSame('partly-cloudy', $result['condition']);
		$this->assertSame('Partly Cloudy', $result['conditionText']);
		$this->assertSame('en', $result['language']);
		$this->assertFalse($result['stale']);
	}

	public function testWeatherStatusImperialReadingFallsBackToSixHourSymbolAndDefaultLabel(): void {
		$this->placementWith(['unitsOverride' => 'imperial']);
		$this->weatherStatus($this->forecastService(
			$this->timeseries([
				'instant' => ['details' => ['air_temperature' => 10]],
				'next_6_hours' => ['summary' => ['symbol_code' => 'nosuchsymbol_night']],
			]),
			[]
		));

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('Current location', $result['location']);
		$this->assertSame(50.0, $result['tempValue']);
		$this->assertSame('imperial', $result['units']);
		$this->assertSame('unknown', $result['condition']);
		$this->assertSame('Unknown conditions', $result['conditionText']);
	}

	public function testWeatherStatusReadingWithoutSymbolIsUnknown(): void {
		$this->placementWith([]);
		$this->weatherStatus($this->forecastService(
			$this->timeseries(['instant' => ['details' => ['air_temperature' => '3.5']]]),
			['address' => '']
		));

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('Current location', $result['location']);
		$this->assertSame(3.5, $result['tempValue']);
		$this->assertSame('unknown', $result['condition']);
	}

	public function testFreshReadingWithoutCacheIsServedUncached(): void {
		$this->cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willThrowException(new RuntimeException('no cache'));
		$this->placementWith([]);
		$this->weatherStatus($this->forecastService(
			$this->timeseries(['instant' => ['details' => ['air_temperature' => 1]]]),
			[]
		));

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(1.0, $result['tempValue']);
		$this->assertFalse($result['stale']);
	}

	public function testUnconfiguredProviderIsReported(): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate('');
		$this->clientService->expects($this->never())->method('newClient');
		$this->reporter->expects($this->once())->method('reportWeatherNotConfigured');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testNonWebSchemeIsLoggedReportedAndRejected(): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate('ftp://provider.example/{location}');
		$this->clientService->expects($this->never())->method('newClient');
		$this->logger->expects($this->once())->method('warning')->with(
			'WeatherService: provider URL has an invalid scheme, rejecting',
			['app' => 'launchpad']
		);
		$this->reporter->expects($this->once())->method('reportWeatherInvalidUrl');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testTransportFailureIsLoggedAndReportedWithoutStatus(): void {
		$this->placementWith(['location' => 'Den Haag']);
		$this->providerTemplate($this->template);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new RuntimeException('down'));
		$this->clientService->method('newClient')->willReturn($client);
		$this->logger->expects($this->once())->method('info')->with(
			'WeatherService: provider fetch failed',
			['app' => 'launchpad', 'exception' => 'down']
		);
		$this->reporter->expects($this->once())->method('reportCall')->with(
			'weather',
			'https://provider.example/w?q=Den%20Haag&k=key%20with%20space&u=metric&l=en',
			null
		);

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	/**
	 * Statuses outside 2xx.
	 *
	 * @return array<string,array{0:int}>
	 */
	public static function failingStatuses(): array {
		return ['199' => [199], '300' => [300], '404' => [404], '500' => [500]];
	}

	/**
	 * @dataProvider failingStatuses
	 */
	public function testNonSuccessStatusIsReportedWithStatus(int $status): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate($this->template);
		$this->providerAnswers($status, '{"main":{"temp":1}}');
		$this->reporter->expects($this->never())->method('reportWeatherUnreadable');
		$this->reporter->expects($this->once())->method('reportCall')->with(
			'weather',
			$this->stringStartsWith('https://provider.example/w?q=Utrecht'),
			$status
		);

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	/**
	 * Bodies the provider path cannot read.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function unreadableBodies(): array {
		return [
			'not json'       => ['<html>'],
			'scalar json'    => ['42'],
			'no temperature' => ['{"main":{"humidity":3}}'],
			'text temp'      => ['{"temp":"warm"}'],
		];
	}

	/**
	 * @dataProvider unreadableBodies
	 */
	public function testUnreadableBodyIsReported(string $body): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, $body);
		$this->reporter->expects($this->once())->method('reportWeatherUnreadable')->with(
			$this->stringStartsWith('https://provider.example/w?q=Utrecht')
		);
		$this->reporter->expects($this->never())->method('reportCall');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame(['error' => 'weather_unavailable'], $result);
	}

	public function testProviderReadingUsesDescriptionNameAndMappedCondition(): void {
		$this->placementWith(['location' => 'Utrecht', 'unitsOverride' => 'imperial']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, '{"main":{"temp":51.5},"name":"Utrecht City","weather":[{"main":"Clouds","description":"broken clouds"}]}');
		$this->reporter->expects($this->once())->method('reportCall')->with(
			'weather',
			'https://provider.example/w?q=Utrecht&k=key%20with%20space&u=imperial&l=en',
			200
		);

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('Utrecht City', $result['location']);
		$this->assertSame(51.5, $result['tempValue']);
		$this->assertSame('imperial', $result['units']);
		$this->assertSame('cloudy', $result['condition']);
		$this->assertSame('Broken clouds', $result['conditionText']);
		$this->assertSame('en', $result['language']);
	}

	public function testProviderReadingFallsBackToLocationAndHumanisedCondition(): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(299, '{"temp":"7","name":"","weather":[{"main":"Mist"}]}');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('Utrecht', $result['location']);
		$this->assertSame(7.0, $result['tempValue']);
		$this->assertSame('fog', $result['condition']);
		$this->assertSame('Fog', $result['conditionText']);
	}

	public function testProviderReadingWithoutWeatherEntryIsUnknown(): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, '{"main":{"temp":2},"weather":"none"}');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('unknown', $result['condition']);
		$this->assertSame('Unknown conditions', $result['conditionText']);
	}

	public function testProviderReadingWithUnmappedConditionIsUnknownButKeepsDescription(): void {
		$this->placementWith(['location' => 'Utrecht']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, '{"main":{"temp":2},"weather":[{"main":"Volcano","description":"ash"}]}');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('unknown', $result['condition']);
		$this->assertSame('Ash', $result['conditionText']);
	}

	public function testSystemLocaleDecidesUnitsWhenUserHasNone(): void {
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->config->method('getUserValue')->willReturn('');
		$this->config->method('getSystemValue')->willReturnCallback(
			static fn (string $key, $default = '') => $key === 'default_locale' ? 'en-LR' : $default
		);
		$this->placementWith(['location' => 'Monrovia']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, '{"main":{"temp":80}}');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('imperial', $result['units']);
	}

	public function testLocaleWithoutTerritoryIsMetric(): void {
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app, string $key, $default = '') => $key === 'locale' ? 'nl' : ''
		);
		$this->config->method('getSystemValue')->willReturnCallback(
			static fn (string $key, $default = '') => $default
		);
		$this->placementWith(['location' => 'Utrecht', 'unitsOverride' => 'kelvin']);
		$this->providerTemplate($this->template);
		$this->providerAnswers(200, '{"main":{"temp":8}}');

		$result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

		$this->assertSame('metric', $result['units']);
	}
}//end class
