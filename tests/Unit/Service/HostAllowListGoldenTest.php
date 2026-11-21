<?php

/**
 * HostAllowListGoldenTest
 *
 * Characterisation test pinning the allowed AND refused outcomes of the
 * fail-closed outbound host allow-list and the http(s) scheme check that
 * HealthPingService and LiveTileService each apply at save time, so both
 * can share one implementation without changing a decision.
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

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\LiveTileSettings;
use OCA\LaunchPad\Service\PingSettings;
use OCA\LaunchPad\Service\PingTransport;
use OCA\LaunchPad\Service\ReadingSources;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class HostAllowListGoldenTest extends TestCase {
	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		$list = '["api.example.com","Status.Example.org",5]';
		return [
			'listed host' => ['https://api.example.com/x', $list, 'ok'],
			'listed host, other case' => ['HTTP://API.EXAMPLE.COM:8080/x', $list, 'ok'],
			'list entry in other case' => ['https://status.example.org', $list, 'ok'],
			'unlisted host' => ['https://evil.example.net/x', $list, 'host_not_allowed'],
			'subdomain of a listed host' => ['https://a.api.example.com', $list, 'host_not_allowed'],
			'empty list' => ['https://api.example.com', '[]', 'host_not_allowed'],
			'missing list' => ['https://api.example.com', '', 'host_not_allowed'],
			'object list' => ['https://api.example.com', '{"a":"api.example.com"}', 'ok'],
			'scalar list' => ['https://api.example.com', '"api.example.com"', 'host_not_allowed'],
			'broken json' => ['https://api.example.com', '[api.example.com', 'host_not_allowed'],
			'ftp scheme' => ['ftp://api.example.com', $list, 'invalid_url'],
			'no scheme' => ['api.example.com', $list, 'invalid_url'],
			'javascript' => ['javascript:alert(1)', $list, 'invalid_url'],
			'no host' => ['https:///path', $list, 'invalid_url'],
			'userinfo trick' => ['https://api.example.com@evil.example.net/', $list, 'host_not_allowed'],
		];
	}

	private function appConfig(string $raw, string $key): IAppConfig {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app, string $configKey, string $default = ''): string => ($app === 'launchpad' && $configKey === $key ? $raw : $default)
		);
		return $appConfig;
	}

	/**
	 * @dataProvider cases
	 */
	public function testHealthPingSaveCheck(string $url, string $raw, string $expected): void {
		$service = new HealthPingService(
			transport: new PingTransport(
				clientService: $this->createMock(originalClassName: IClientService::class),
				cacheFactory: $this->createMock(originalClassName: ICacheFactory::class)
			),
			settings: new PingSettings(
				appConfig: $this->appConfig(raw: $raw, key: 'healthping_allowed_hosts'),
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class)
		);

		$errors = $service->validateConfig(config: ['healthPingEnabled' => true, 'healthUrl' => $url]);

		$this->assertSame(($expected === 'ok' ? [] : [$expected]), $errors);
	}

	/**
	 * @dataProvider cases
	 */
	public function testLiveTileSaveCheck(string $url, string $raw, string $expected): void {
		$service = new LiveTileService(
			sources: new ReadingSources(
				appManager: $this->createMock(originalClassName: IAppManager::class),
				container: $this->createMock(originalClassName: ContainerInterface::class),
				clientService: $this->createMock(originalClassName: IClientService::class),
				cacheFactory: $this->createMock(originalClassName: ICacheFactory::class)
			),
			settings: new LiveTileSettings(
				appConfig: $this->appConfig(raw: $raw, key: 'livetile_allowed_hosts'),
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class)
		);

		$errors = $service->validateSourceConfig(config: ['sourceMode' => 'url', 'url' => $url]);

		$this->assertSame(($expected === 'ok' ? [] : [$expected]), $errors);
	}

	public function testTheListIsNotReadForAnInvalidScheme(): void {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->expects($this->never())->method('getValueString');
		$service = new HealthPingService(
			transport: new PingTransport(
				clientService: $this->createMock(originalClassName: IClientService::class),
				cacheFactory: $this->createMock(originalClassName: ICacheFactory::class)
			),
			settings: new PingSettings(appConfig: $appConfig, logger: $this->createMock(originalClassName: LoggerInterface::class)),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class)
		);

		$this->assertSame(['invalid_url'], $service->validateConfig(config: ['healthPingEnabled' => true, 'healthUrl' => 'gopher://x']));
	}
}
