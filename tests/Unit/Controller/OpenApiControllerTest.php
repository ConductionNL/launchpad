<?php

/**
 * Tests for the OpenAPI document endpoint.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\OpenApiController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

/**
 * The served document, its version, and the missing-document answer.
 *
 * @covers \OCA\LaunchPad\Controller\OpenApiController
 */
class OpenApiControllerTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir().'/launchpad-openapi-'.bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown(): void {
		@unlink($this->dir.'/openapi.json');
		@rmdir($this->dir);
	}

	private function controller(string $appPath): OpenApiController {
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->with('launchpad')->willReturn($appPath);
		$apps->method('getAppVersion')->with('launchpad')->willReturn('1.2.3');

		return new OpenApiController('launchpad', $this->createMock(IRequest::class), $apps);
	}

	/**
	 * The headers set on a response, without the defaults getHeaders() merges in from the server.
	 */
	private function headers(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}

	public function testTheDocumentIsServedWithTheInstalledVersion(): void {
		file_put_contents($this->dir.'/openapi.json', '{"openapi":"3.0.3","info":{"title":"launchpad","version":"0.0.0"},"paths":{},"components":{"schemas":{}}}');

		$response = $this->controller($this->dir)->show();
		$data     = $response->getData();

		$this->assertSame(200, $response->getStatus());
		$this->assertInstanceOf(stdClass::class, $data);
		$this->assertSame('{"openapi":"3.0.3","info":{"title":"launchpad","version":"1.2.3"},"paths":{},"components":{"schemas":{}}}', json_encode($data));
		$this->assertSame('*', $this->headers($response)['Access-Control-Allow-Origin']);
	}

	public function testAMissingDocumentIs404(): void {
		$response = $this->controller($this->dir)->show();

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'The OpenAPI document is not installed'], $response->getData());
		$this->assertSame('*', $this->headers($response)['Access-Control-Allow-Origin']);
	}

	public function testAFileWithoutAnInfoObjectIs404(): void {
		file_put_contents($this->dir.'/openapi.json', '["not", "a", "document"]');

		$this->assertSame(404, $this->controller($this->dir)->show()->getStatus());
	}

	public function testTheShippedDocumentIsServed(): void {
		$response = $this->controller(dirname(__DIR__, 3))->show();
		$data     = $response->getData();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('1.2.3', $data->info->version);
		$this->assertObjectHasProperty('/openapi.json', $data->paths);
		$this->assertObjectHasProperty('contact', $data->info);
		$this->assertStringEndsWith('/api/v1', $data->servers[0]->url);
	}
}
