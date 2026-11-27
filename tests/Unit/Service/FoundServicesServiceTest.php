<?php

/**
 * FoundServicesServiceTest
 *
 * REQ-TILE-044: the administrator's list of services to discover is stored
 * normalised and refused when an entry has no name or no web address; only
 * the listed addresses are asked; the ones that answered are reported with
 * their state, port and first-found time, which is kept across answers and
 * dropped with the address.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\FoundServicesService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Small]
class FoundServicesServiceTest extends TestCase {

	/**
	 * The stored app config values.
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * The addresses asked, in order.
	 *
	 * @var array<int, string>
	 */
	private array $asked = [];

	/**
	 * The options of the last request.
	 *
	 * @var array<string, mixed>
	 */
	private array $lastOptions = [];

	/**
	 * Build the service with an in-memory config, no cache and a client answering per address.
	 *
	 * @param array<string, int|null> $answers Status per address; null throws (no answer).
	 * @param integer                 $now     The clock.
	 *
	 * @return FoundServicesService
	 */
	private function service(array $answers = [], int $now = 1795757445): FoundServicesService {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => $this->values[$key] ?? $default
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->values[$key] = $value;
				return true;
			}
		);

		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $uri, array $options) use ($answers): IResponse {
				$this->asked[] = $uri;
				$this->lastOptions = $options;
				$status = $answers[$uri] ?? null;
				if ($status === null) {
					throw new RuntimeException('connection refused');
				}

				$response = $this->createMock(originalClassName: IResponse::class);
				$response->method('getStatusCode')->willReturn($status);
				return $response;
			}
		);
		$clients = $this->createMock(originalClassName: IClientService::class);
		$clients->method('newClient')->willReturn($client);

		$caches = $this->createMock(originalClassName: ICacheFactory::class);
		$caches->method('createDistributed')->willThrowException(new RuntimeException('no cache'));

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturn($now);

		return new FoundServicesService(
			appConfig: $config,
			clientService: $clients,
			cacheFactory: $caches,
			timeFactory: $time
		);
	}//end service()

	/**
	 * The list is stored with each address normalised, and a repeated address once.
	 *
	 * @return void
	 */
	public function testTheListIsStoredNormalised(): void {
		$stored = $this->service()->setServices(
			services: [
				['name' => ' Grafana ', 'url' => 'HTTP://LocalHost:3000/'],
				['name' => 'Grafana again', 'url' => 'http://localhost:3000'],
				['name' => 'Uptime Kuma', 'url' => 'https://status.example.org/dashboard/'],
			]
		);

		self::assertSame(
			expected: [
				['name' => 'Grafana', 'url' => 'http://localhost:3000'],
				['name' => 'Uptime Kuma', 'url' => 'https://status.example.org/dashboard'],
			],
			actual: $stored
		);
		self::assertSame(expected: $stored, actual: $this->service()->getServices());
	}//end testTheListIsStoredNormalised()

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function refusedLists(): array {
		return [
			'not a list'        => [['name' => 'Grafana', 'url' => 'http://localhost:3000']],
			'a string'          => ['http://localhost:3000'],
			'no name'           => [[['name' => ' ', 'url' => 'http://localhost:3000']]],
			'name too long'     => [[['name' => str_repeat('a', 81), 'url' => 'http://localhost:3000']]],
			'no address'        => [[['name' => 'Grafana']]],
			'not web'           => [[['name' => 'Files', 'url' => 'file:///etc/passwd']]],
			'no host'           => [[['name' => 'Grafana', 'url' => 'http://']]],
			'user in address'   => [[['name' => 'Grafana', 'url' => 'http://admin:secret@localhost:3000']]],
			'entry not an object' => [['http://localhost:3000']],
			'too many'          => [array_fill(0, 51, ['name' => 'x', 'url' => 'http://localhost'])],
		];
	}//end refusedLists()

	/**
	 * An entry without a name or a web address refuses the whole list and stores nothing.
	 *
	 * @param mixed $services The submitted list.
	 *
	 * @return void
	 */
	#[DataProvider('refusedLists')]
	public function testAnInvalidListIsRefused(mixed $services): void {
		$this->expectException(exception: InvalidArgumentException::class);
		try {
			$this->service()->setServices(services: $services);
		} finally {
			self::assertArrayNotHasKey(key: FoundServicesService::CONFIG_KEY, array: $this->values);
		}
	}//end testAnInvalidListIsRefused()

	/**
	 * Grafana answers on port 3000 and is listed as running with its port and found time;
	 * a listed service that never answered is not listed; nothing else is asked.
	 *
	 * @return void
	 */
	public function testAServiceThatAnswersIsFound(): void {
		$this->service()->setServices(
			services: [
				['name' => 'Grafana', 'url' => 'http://localhost:3000'],
				['name' => 'Uptime Kuma', 'url' => 'http://localhost:3001'],
			]
		);

		$found = $this->service(answers: ['http://localhost:3000' => 302])->found();

		self::assertSame(
			expected: [['name' => 'Grafana', 'url' => 'http://localhost:3000', 'port' => 3000, 'state' => 'running', 'foundAt' => 1795757445]],
			actual: $found
		);
		self::assertSame(expected: ['http://localhost:3000', 'http://localhost:3001'], actual: $this->asked);
		self::assertFalse(condition: $this->lastOptions['allow_redirects']);
		self::assertFalse(condition: $this->lastOptions['http_errors']);
		self::assertTrue(condition: $this->lastOptions['nextcloud']['allow_local_address']);
	}//end testAServiceThatAnswersIsFound()

	/**
	 * The first-found time is kept; a found service that stops answering stays listed as not responding.
	 *
	 * @return void
	 */
	public function testTheFoundTimeIsKeptAndTheStateFollowsTheAnswer(): void {
		$this->service()->setServices(services: [['name' => 'Grafana', 'url' => 'https://grafana.example.org']]);
		$this->service(answers: ['https://grafana.example.org' => 200], now: 1000)->found();

		$later = $this->service(answers: ['https://grafana.example.org' => 503], now: 2000)->found();

		self::assertSame(
			expected: [['name' => 'Grafana', 'url' => 'https://grafana.example.org', 'port' => 443, 'state' => 'not_responding', 'foundAt' => 1000]],
			actual: $later
		);
	}//end testTheFoundTimeIsKeptAndTheStateFollowsTheAnswer()

	/**
	 * Removing an address from the list drops its found time; an empty list asks nothing.
	 *
	 * @return void
	 */
	public function testRemovingAServiceDropsItsFoundTime(): void {
		$this->service()->setServices(services: [['name' => 'Grafana', 'url' => 'http://localhost:3000']]);
		$this->service(answers: ['http://localhost:3000' => 200])->found();
		self::assertStringContainsString(needle: 'localhost:3000', haystack: $this->values[FoundServicesService::FOUND_KEY]);

		$this->service()->setServices(services: []);
		$this->asked = [];

		self::assertSame(expected: '{}', actual: $this->values[FoundServicesService::FOUND_KEY]);
		self::assertSame(expected: [], actual: $this->service(answers: ['http://localhost:3000' => 200])->found());
		self::assertSame(expected: [], actual: $this->asked);
	}//end testRemovingAServiceDropsItsFoundTime()

	/**
	 * The port is the one the address names, else its scheme's.
	 *
	 * @return void
	 */
	public function testThePortOfAnAddress(): void {
		self::assertSame(expected: 3000, actual: FoundServicesService::portOf(url: 'http://localhost:3000'));
		self::assertSame(expected: 80, actual: FoundServicesService::portOf(url: 'http://localhost'));
		self::assertSame(expected: 443, actual: FoundServicesService::portOf(url: 'https://grafana.example.org'));
	}//end testThePortOfAnAddress()
}//end class
