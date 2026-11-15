<?php

/**
 * PlacementReadingControllersGoldenTest
 *
 * Golden record of the health-ping, live-tile and weather endpoints: the
 * sign-in and placement guards, the resolver's error and failure answers,
 * the logged failures, and the config validation endpoints.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\HealthPingController;
use OCA\LaunchPad\Controller\LiveTileController;
use OCA\LaunchPad\Controller\SessionLog;
use OCA\LaunchPad\Controller\WeatherController;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\WeatherService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Every endpoint of the three placement-reading controllers, case by case.
 */
class PlacementReadingControllersGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/placement-readings.json';

	/**
	 * Service calls and log lines of the current call.
	 *
	 * @var array<int, mixed>
	 */
	private array $trace = [];

	/**
	 * Every endpoint × case answers, calls and logs as recorded.
	 *
	 * @return void
	 */
	public function testEndpointsMatchTheRecording(): void {
		$cases = [
			'anonymous' => [null, 1, 'ok', 'x'],
			'cannot view' => ['alice', 2, 'ok', 'x'],
			'resolver throws' => ['alice', 1, 'throws', ['a' => 1]],
			'resolver error' => ['alice', 1, 'error', ['a' => 1]],
			'ok' => ['alice', 1, 'ok', ['a' => 1]],
		];
		$endpoints = [
			'health show' => static fn ($c): object => $c['health']->show(placementId: $c['placement']),
			'health validate' => static fn ($c): object => $c['health']->validate(),
			'live show' => static fn ($c): object => $c['live']->show(placementId: $c['placement']),
			'live connector status' => static fn ($c): object => $c['live']->connectorStatus(),
			'live validate source' => static fn ($c): object => $c['live']->validateSource(),
			'weather show' => static fn ($c): object => $c['weather']->show(placementId: $c['placement']),
		];

		$actual = [];
		foreach ($endpoints as $endpoint => $call) {
			foreach ($cases as $case => [$uid, $placement, $mode, $config]) {
				$this->trace = [];
				$controllers = $this->controllers(uid: $uid, mode: $mode, config: $config);
				$controllers['placement'] = $placement;
				$response = $call($controllers);
				$actual[$endpoint.' / '.$case] = [$response->getStatus(), $response->getData(), $this->trace];
			}
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), json_decode((string) json_encode($actual), true));
	}//end testEndpointsMatchTheRecording()

	/**
	 * The three controllers over recording service mocks.
	 *
	 * @param string|null $uid    The session user.
	 * @param string      $mode   What the resolvers do: ok, error or throws.
	 * @param mixed       $config The posted `config` parameter.
	 *
	 * @return array<string, object>
	 */
	private function controllers(?string $uid, string $mode, mixed $config): array {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}
		$session->method('getUser')->willReturn($user);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context): void {
				$this->trace[] = ['log', $message, $context];
			}
		);

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canViewPlacement')->willReturnCallback(
			function (string $userId, int $placementId): bool {
				$this->trace[] = ['canViewPlacement', $userId, $placementId];
				return $placementId !== 2;
			}
		);

		$resolve = function (string $service, array $args) use ($mode): array {
			$this->trace[] = [$service, $args];
			if ($mode === 'throws') {
				throw new RuntimeException('down');
			}
			return ($mode === 'error') ? ['error' => 'not_configured'] : ['value' => 42, 'stale' => false];
		};

		$health = $this->createMock(HealthPingService::class);
		$health->method('resolveForPlacement')->willReturnCallback(fn (int $id): array => $resolve('health', [$id]));
		$health->method('validateConfig')->willReturnCallback(
			function (array $config): array {
				$this->trace[] = ['validateConfig', $config];
				return ($config === []) ? [] : ['bad host'];
			}
		);
		$live = $this->createMock(LiveTileService::class);
		$live->method('resolveForPlacement')->willReturnCallback(fn (int $id): array => $resolve('live', [$id]));
		$live->method('isConnectorAvailable')->willReturn(true);
		$live->method('validateSourceConfig')->willReturnCallback(
			function (array $config): array {
				$this->trace[] = ['validateSourceConfig', $config];
				return ($config === []) ? [] : ['bad host'];
			}
		);
		$weather = $this->createMock(WeatherService::class);
		$weather->method('resolveForPlacement')->willReturnCallback(fn (int $id, string $userId): array => $resolve('weather', [$id, $userId]));

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key): mixed => ($key === 'config') ? $config : null);

		$log = new SessionLog(userSession: $session, logger: $logger);
		return [
			'health' => new HealthPingController(request: $request, healthPingService: $health, permissionService: $permissions, session: $log),
			'live' => new LiveTileController(request: $request, liveTileService: $live, permissionService: $permissions, session: $log),
			'weather' => new WeatherController(request: $request, weatherService: $weather, permissionService: $permissions, session: $log),
		];
	}//end controllers()
}//end class
