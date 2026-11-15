<?php

/**
 * Unit tests for HealthController: engine missing, healthy result, CORS and failure paths.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\HealthController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\HealthController
 */
class HealthControllerTest extends TestCase {
	private function loader(bool $cors, ?\Throwable $fail = null): object {
		return new class($cors, $fail) {
			public array $loaded = [];

			public function __construct(private bool $cors, private ?\Throwable $fail) {
			}

			public function load(string $appId): object {
				$this->loaded[] = $appId;
				if ($this->fail !== null) {
					throw $this->fail;
				}
				return (object)['cors' => $this->cors];
			}

			public function appVersion(string $appId): string {
				return '1.2.3';
			}
		};
	}

	private function executor(): object {
		return new class {
			public ?object $manifest = null;

			public function execute(object $manifest): object {
				$this->manifest = $manifest;
				return (object)['status' => 'degraded', 'checks' => [['name' => 'db', 'ok' => false]], 'httpStatusCode' => 207];
			}
		};
	}

	/**
	 * The headers the controller set, read without booting Nextcloud.
	 */
	private function storedHeaders(\OCP\AppFramework\Http\Response $response): array {
		$property = new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers');
		return (array)$property->getValue($response);
	}

	public function testMissingEngineAnswers503(): void {
		$controller = new HealthController('launchpad', $this->createMock(IRequest::class));

		$response = $controller->index();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertSame([
			'status' => 'unavailable',
			'app' => 'launchpad',
			'error' => 'OpenRegister AppHost observability engine unavailable',
			'checks' => [],
		], $response->getData());
	}

	public function testOnlyALoaderWithoutExecutorIsStillUnavailable(): void {
		$controller = new HealthController('launchpad', $this->createMock(IRequest::class), $this->loader(false));

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $controller->index()->getStatus());
	}

	public function testResultIsReportedWithItsOwnStatusCode(): void {
		$loader = $this->loader(false);
		$executor = $this->executor();
		$controller = new HealthController('launchpad', $this->createMock(IRequest::class), $loader, $executor);

		$response = $controller->index();

		$this->assertSame(207, $response->getStatus());
		$this->assertSame([
			'status' => 'degraded',
			'app' => 'launchpad',
			'version' => '1.2.3',
			'checks' => [['name' => 'db', 'ok' => false]],
		], $response->getData());
		$this->assertSame(['launchpad'], $loader->loaded);
		$this->assertNotNull($executor->manifest);
		$this->assertArrayNotHasKey('Access-Control-Allow-Origin', $this->storedHeaders($response));
	}

	public function testCorsManifestAddsTheCorsHeaders(): void {
		$controller = new HealthController('launchpad', $this->createMock(IRequest::class), $this->loader(true), $this->executor());

		$headers = $this->storedHeaders($controller->index());

		$this->assertSame('*', $headers['Access-Control-Allow-Origin']);
		$this->assertSame('GET, OPTIONS', $headers['Access-Control-Allow-Methods']);
	}

	public function testLoaderFailureAnswers503WithTheMessage(): void {
		$controller = new HealthController(
			'launchpad',
			$this->createMock(IRequest::class),
			$this->loader(false, new RuntimeException('manifest missing')),
			$this->executor()
		);

		$response = $controller->index();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertSame(['status' => 'unavailable', 'app' => 'launchpad', 'error' => 'manifest missing', 'checks' => []], $response->getData());
	}
}
