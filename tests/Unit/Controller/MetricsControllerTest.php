<?php

/**
 * Unit tests for MetricsController: engine missing, rendered body and failure paths.
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

use OCA\LaunchPad\Controller\MetricsController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\MetricsController
 */
class MetricsControllerTest extends TestCase {
	private function loader(): object {
		return new class {
			public function load(string $appId): object {
				return (object)['appId' => $appId];
			}
		};
	}

	private function engine(?\Throwable $fail = null): object {
		return new class($fail) {
			public function __construct(private ?\Throwable $fail) {
			}

			public function render(object $manifest): string {
				if ($this->fail !== null) {
					throw $this->fail;
				}
				return "launchpad_up{app=\"" . $manifest->appId . "\"} 1\n";
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
		$response = (new MetricsController('launchpad', $this->createMock(IRequest::class), $this->loader()))->index();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertSame("# OpenRegister AppHost observability engine unavailable\n", $response->render());
	}

	public function testRenderedBodyIsServedAsPrometheusText(): void {
		$response = (new MetricsController('launchpad', $this->createMock(IRequest::class), $this->loader(), $this->engine()))->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame("launchpad_up{app=\"launchpad\"} 1\n", $response->render());
		$this->assertSame(MetricsController::CONTENT_TYPE, $this->storedHeaders($response)['Content-Type']);
	}

	public function testEngineFailureAnswers503WithTheMessage(): void {
		$response = (new MetricsController(
			'launchpad',
			$this->createMock(IRequest::class),
			$this->loader(),
			$this->engine(new RuntimeException('boom'))
		))->index();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertSame("# metrics unavailable: boom\n", $response->render());
	}
}
