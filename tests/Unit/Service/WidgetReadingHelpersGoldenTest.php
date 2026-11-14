<?php

/**
 * WidgetReadingHelpersGoldenTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\WeatherService;
use OCP\ICache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins what the cache and placement-config readers of the weather, live
 * tile and health ping services return for each kind of stored value.
 * The fixture was recorded with LAUNCHPAD_GOLDEN_RECORD=1.
 *
 * @covers \OCA\LaunchPad\Service\HealthPingService
 * @covers \OCA\LaunchPad\Service\LiveTileService
 * @covers \OCA\LaunchPad\Service\WeatherService
 * @covers \OCA\LaunchPad\Service\ReadsJsonCache
 * @covers \OCA\LaunchPad\Service\ReadsPlacementContent
 */
class WidgetReadingHelpersGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/widget-reading-helpers.json';

	/**
	 * Raw cache values, by case name.
	 */
	private const CACHE_VALUES = [
		'no cache' => null,
		'miss' => ['raw' => null],
		'int' => ['raw' => 7],
		'array' => ['raw' => ['a' => 1]],
		'corrupt' => ['raw' => '{"a":'],
		'json scalar' => ['raw' => '"text"'],
		'json number' => ['raw' => '12'],
		'json empty list' => ['raw' => '[]'],
		'json object' => ['raw' => '{"value":3,"nested":{"b":[1,2]}}'],
		'json list' => ['raw' => '[1,"two",null]'],
	];

	/**
	 * Placement shapes, by case name: [content, style config]; a missing
	 * key means the placement has no such getter.
	 */
	private const PLACEMENTS = [
		'no getters' => [],
		'content only' => ['content' => ['location' => 'Utrecht']],
		'empty content, no style' => ['content' => []],
		'empty content, style with content' => ['content' => [], 'style' => ['content' => ['url' => 'https://a.example'], 'x' => 1]],
		'empty content, style without content' => ['content' => [], 'style' => ['x' => 1]],
		'empty content, style content not array' => ['content' => [], 'style' => ['content' => 'text']],
		'empty content, empty style' => ['content' => [], 'style' => []],
		'style only' => ['style' => ['content' => ['k' => 'v']]],
		'both filled' => ['content' => ['a' => 1], 'style' => ['content' => ['b' => 2]]],
	];

	/**
	 * Every service and reader with every input.
	 *
	 * @return array<string, array{0: class-string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach ([HealthPingService::class, LiveTileService::class, WeatherService::class] as $class) {
			foreach (array_keys(self::CACHE_VALUES) as $name) {
				$cases[$class . ' readCache ' . $name] = [$class, 'readCache', $name];
			}

			if ($class === HealthPingService::class) {
				continue;
			}

			foreach (array_keys(self::PLACEMENTS) as $name) {
				$cases[$class . ' readPlacementConfig ' . $name] = [$class, 'readPlacementConfig', $name];
			}
		}

		return $cases;
	}

	/**
	 * The reader returns the recorded result.
	 *
	 * @param class-string $class  The service.
	 * @param string       $method The private reader.
	 * @param string       $name   The input case.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testReaderMatchesTheRecording(string $class, string $method, string $name): void {
		$service = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		$reader = new ReflectionMethod($class, $method);
		if ($method === 'readCache') {
			$args = ['cache' => $this->cache(name: $name), 'cacheKey' => 'k'];
		} else {
			$args = ['placement' => $this->placement(name: $name)];
		}

		$actual = $reader->invokeArgs($service, $args);
		$key = $class . ' ' . $method . ' ' . $name;
		if (getenv('LAUNCHPAD_GOLDEN_RECORD') === '1') {
			$recorded = is_file(self::FIXTURE) ? json_decode((string)file_get_contents(self::FIXTURE), true) : [];
			$recorded[$key] = ['result' => $actual];
			ksort($recorded);
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true)[$key] ?? null;
		$this->assertNotNull($expected, 'no recording for ' . $key);
		$this->assertSame($expected['result'], $actual);
	}

	/**
	 * A cache answering the case's raw value for key `k`, or null.
	 *
	 * @param string $name The case.
	 *
	 * @return ICache|null The cache.
	 */
	private function cache(string $name): ?ICache {
		$value = self::CACHE_VALUES[$name];
		if ($value === null) {
			return null;
		}

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(static fn (string $key) => $key === 'k' ? $value['raw'] : 'wrong key');
		return $cache;
	}

	/**
	 * An object with the case's getters.
	 *
	 * @param string $name The case.
	 *
	 * @return object The placement stand-in.
	 */
	private function placement(string $name): object {
		$shape = self::PLACEMENTS[$name];
		if (array_key_exists('content', $shape) && array_key_exists('style', $shape)) {
			return new class($shape['content'], $shape['style']) {
				public function __construct(private array $content, private array $style) {
				}

				public function getContentArray(): array {
					return $this->content;
				}

				public function getStyleConfigArray(): array {
					return $this->style;
				}
			};
		}

		if (array_key_exists('content', $shape)) {
			return new class($shape['content']) {
				public function __construct(private array $content) {
				}

				public function getContentArray(): array {
					return $this->content;
				}
			};
		}

		if (array_key_exists('style', $shape)) {
			return new class($shape['style']) {
				public function __construct(private array $style) {
				}

				public function getStyleConfigArray(): array {
					return $this->style;
				}
			};
		}

		return new \stdClass();
	}
}
