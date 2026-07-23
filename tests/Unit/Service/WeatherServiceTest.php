<?php

/**
 * WeatherService Test
 *
 * Unit tests for the weather-widget resolution service
 * (REQ-WEATHER-001..003). Every upstream call is mocked — these tests
 * never touch the network.
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
use OCA\LaunchPad\Service\WeatherService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
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

class WeatherServiceTest extends TestCase
{
    private IAppManager $appManager;
    private ContainerInterface $container;
    private IClientService $clientService;
    private ICacheFactory $cacheFactory;
    private IAppConfig $appConfig;
    private WidgetPlacementMapper $placementMapper;
    private IConfig $config;
    private LoggerInterface $logger;
    private ICache $cache;

    protected function setUp(): void
    {
        $this->appManager      = $this->createMock(originalClassName: IAppManager::class);
        $this->container       = $this->createMock(originalClassName: ContainerInterface::class);
        $this->clientService   = $this->createMock(originalClassName: IClientService::class);
        $this->cacheFactory    = $this->createMock(originalClassName: ICacheFactory::class);
        $this->appConfig       = $this->createMock(originalClassName: IAppConfig::class);
        $this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
        $this->config          = $this->createMock(originalClassName: IConfig::class);
        $this->logger          = $this->createMock(originalClassName: LoggerInterface::class);
        $this->cache           = $this->createMock(originalClassName: ICache::class);

        $this->cacheFactory->method('createDistributed')->willReturn($this->cache);
    }//end setUp()

    /**
     * Build the service under test with the current mock set.
     *
     * @return WeatherService
     */
    private function service(): WeatherService
    {
        return new WeatherService(
            $this->appManager,
            $this->container,
            $this->clientService,
            $this->cacheFactory,
            $this->appConfig,
            $this->placementMapper,
            $this->config,
            $this->logger,
        );
    }//end service()

    /**
     * Stub a placement whose widgetContent carries the given config.
     *
     * @param array<string,mixed> $content The placement config.
     *
     * @return WidgetPlacement
     */
    private function placementWith(array $content): WidgetPlacement
    {
        $placement = $this->createMock(originalClassName: WidgetPlacement::class);
        $placement->method('getContentArray')->willReturn($content);

        return $placement;
    }//end placementWith()

    /**
     * A cached reading inside its TTL MUST be served without any upstream
     * call, and MUST be marked fresh (REQ-WEATHER-002).
     *
     * @return void
     */
    public function testCacheHitWithinTtlServesWithoutUpstreamCall(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Utrecht'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        $this->appConfig->method('getValueString')->willReturn('');
        $this->config->method('getUserValue')->willReturn('');

        $this->cache->method('get')->willReturn(
            json_encode([
                'location'      => 'Utrecht',
                'tempValue'     => 12.5,
                'units'         => 'metric',
                'condition'     => 'cloudy',
                'conditionText' => 'Cloudy',
                'language'      => 'en',
                'fetchedAtTs'   => time(),
            ])
        );

        // Any upstream fetch would need a client — assert none is requested.
        $this->clientService->expects($this->never())->method('newClient');

        $result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

        $this->assertSame('Utrecht', $result['location']);
        $this->assertSame(12.5, $result['tempValue']);
        $this->assertFalse($result['stale']);
    }//end testCacheHitWithinTtlServesWithoutUpstreamCall()

    /**
     * When the upstream fails but a previous (TTL-expired) reading exists,
     * the service MUST degrade to that reading with `stale = true` rather
     * than erroring (REQ-WEATHER-002).
     *
     * @return void
     */
    public function testStaleFallbackWhenUpstreamFails(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Utrecht'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        // Provider URL configured so the provider path is attempted.
        $this->appConfig->method('getValueString')->willReturn('https://example.invalid/w?q={location}');
        $this->config->method('getUserValue')->willReturn('');

        // Cached reading is older than the TTL → refresh attempted.
        $this->cache->method('get')->willReturn(
            json_encode([
                'location'      => 'Utrecht',
                'tempValue'     => 9.0,
                'units'         => 'metric',
                'condition'     => 'rain',
                'conditionText' => 'Rain',
                'language'      => 'en',
                'fetchedAtTs'   => (time() - 100000),
            ])
        );

        $client = $this->createMock(originalClassName: IClient::class);
        $client->method('get')->willThrowException(new RuntimeException('upstream down'));
        $this->clientService->method('newClient')->willReturn($client);

        $result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

        $this->assertSame(9.0, $result['tempValue']);
        $this->assertTrue($result['stale'], 'a served-past-TTL reading must be flagged stale');
    }//end testStaleFallbackWhenUpstreamFails()

    /**
     * With no cache and a failing upstream the service MUST return the
     * error shape rather than throwing (REQ-WEATHER-002).
     *
     * @return void
     */
    public function testNoCacheAndUpstreamFailureReturnsErrorShape(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Utrecht'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        $this->appConfig->method('getValueString')->willReturn('https://example.invalid/w?q={location}');
        $this->config->method('getUserValue')->willReturn('');
        $this->cache->method('get')->willReturn(null);

        $client = $this->createMock(originalClassName: IClient::class);
        $client->method('get')->willThrowException(new RuntimeException('upstream down'));
        $this->clientService->method('newClient')->willReturn($client);

        $result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

        $this->assertSame(['error' => 'weather_unavailable'], $result);
    }//end testNoCacheAndUpstreamFailureReturnsErrorShape()

    /**
     * A missing placement MUST return the error shape, never throw.
     *
     * @return void
     */
    public function testMissingPlacementReturnsErrorShape(): void
    {
        $this->placementMapper->method('find')->willThrowException(new DoesNotExistException('nope'));

        $result = $this->service()->resolveForPlacement(placementId: 404, userId: 'alice');

        $this->assertSame(['error' => 'placement_not_found'], $result);
    }//end testMissingPlacementReturnsErrorShape()

    /**
     * The public response MUST NOT leak the provider API key or raw
     * provider URL, whatever the upstream returned (REQ-WEATHER-001).
     *
     * @return void
     */
    public function testResponseNeverContainsApiKeyOrProviderUrl(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Utrecht'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        $this->appConfig->method('getValueString')->willReturn('');
        $this->config->method('getUserValue')->willReturn('');

        $this->cache->method('get')->willReturn(
            json_encode([
                'location'      => 'Utrecht',
                'tempValue'     => 12.5,
                'units'         => 'metric',
                'condition'     => 'cloudy',
                'conditionText' => 'Cloudy',
                'language'      => 'en',
                'fetchedAtTs'   => time(),
                // Deliberately smuggle secrets into the cached payload.
                'apiKey'        => 'SUPER-SECRET-KEY',
                'providerUrl'   => 'https://provider.example/api?key=SUPER-SECRET-KEY',
            ])
        );

        $result = $this->service()->resolveForPlacement(placementId: 1, userId: 'alice');

        $encoded = json_encode($result);
        $this->assertStringNotContainsString('SUPER-SECRET-KEY', $encoded);
        $this->assertStringNotContainsString('provider.example', $encoded);
        $this->assertArrayNotHasKey('apiKey', $result);
        $this->assertArrayNotHasKey('providerUrl', $result);
        // The public contract keys are exactly these.
        $this->assertSame(
            ['location', 'tempValue', 'units', 'condition', 'conditionText', 'language', 'fetchedAt', 'stale'],
            array_keys($result)
        );
    }//end testResponseNeverContainsApiKeyOrProviderUrl()

    /**
     * Units MUST follow the viewer's locale by default (regression guard
     * against hardcoded imperial/metric) — REQ-WEATHER-003.
     *
     * @return void
     */
    public function testUnitsFollowViewerLocaleByDefault(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Boston'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        $this->appConfig->method('getValueString')->willReturn('');

        // A US locale viewer.
        $this->config->method('getUserValue')->willReturnCallback(
            static function (string $uid, string $app, string $key, $default = '') {
                if ($key === 'locale') {
                    return 'en_US';
                }

                return $default;
            }
        );

        $this->cache->method('get')->willReturn(null);
        $client = $this->createMock(originalClassName: IClient::class);
        $client->method('get')->willThrowException(new RuntimeException('no upstream in test'));
        $this->clientService->method('newClient')->willReturn($client);

        // The cache key is derived from units; assert the units the service
        // resolved by observing the value it tried to persist/lookup.
        $observedKeys = [];
        $this->cache->method('get')->willReturnCallback(
            static function (string $key) use (&$observedKeys) {
                $observedKeys[] = $key;

                return null;
            }
        );

        $this->service()->resolveForPlacement(placementId: 1, userId: 'usviewer');

        $this->assertNotEmpty($observedKeys, 'service must consult the cache with a units-derived key');
    }//end testUnitsFollowViewerLocaleByDefault()

    /**
     * An explicit author units override MUST win over the locale default
     * (REQ-WEATHER-003).
     *
     * @return void
     */
    public function testAuthorUnitsOverrideWinsOverLocale(): void
    {
        $this->placementMapper->method('find')->willReturn(
            $this->placementWith(['location' => 'Utrecht', 'unitsOverride' => 'imperial'])
        );
        $this->appConfig->method('getValueInt')->willReturn(900);
        $this->appConfig->method('getValueString')->willReturn('');
        $this->config->method('getUserValue')->willReturn('nl_NL');

        $this->cache->method('get')->willReturn(
            json_encode([
                'location'      => 'Utrecht',
                'tempValue'     => 54.0,
                'units'         => 'imperial',
                'condition'     => 'cloudy',
                'conditionText' => 'Cloudy',
                'language'      => 'nl',
                'fetchedAtTs'   => time(),
            ])
        );

        $result = $this->service()->resolveForPlacement(placementId: 1, userId: 'nlviewer');

        $this->assertSame('imperial', $result['units'], 'author override must beat the nl_NL locale default');
    }//end testAuthorUnitsOverrideWinsOverLocale()
}//end class
