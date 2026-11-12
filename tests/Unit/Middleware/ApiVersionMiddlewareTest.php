<?php

/**
 * Tests for the API-Version response header.
 *
 * @category  Test
 * @package   Unit\Middleware
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Middleware;

use OCA\LaunchPad\Middleware\ApiVersionMiddleware;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Which responses get the header, and with what value.
 *
 * @covers \OCA\LaunchPad\Middleware\ApiVersionMiddleware
 */
class ApiVersionMiddlewareTest extends TestCase {

	private function middleware(?string $path, string $version = '1.2.3'): ApiVersionMiddleware {
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn($path ?? false);

		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppVersion')->with('launchpad')->willReturn($version);

		return new ApiVersionMiddleware($request, $apps);
	}

	private function after(ApiVersionMiddleware $middleware, JSONResponse $response): array {
		$this->assertSame($response, $middleware->afterController($this->createMock(Controller::class), 'index', $response));

		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}

	public function testAnApiResponseNamesTheInstalledVersion(): void {
		$this->assertSame('1.2.3', $this->after($this->middleware('/apps/launchpad/api/health'), new JSONResponse([]))['API-Version']);
	}

	public function testAVersionedApiResponseNamesItToo(): void {
		$this->assertSame('1.2.3', $this->after($this->middleware('/apps/launchpad/api/v1/dashboards'), new JSONResponse([], 404))['API-Version']);
	}

	public function testAPageResponseIsLeftAlone(): void {
		$this->assertArrayNotHasKey('API-Version', $this->after($this->middleware('/apps/launchpad/s/abc'), new JSONResponse([])));
	}

	public function testARequestWithoutAPathIsLeftAlone(): void {
		$this->assertArrayNotHasKey('API-Version', $this->after($this->middleware(null), new JSONResponse([])));
	}

	public function testAnUnknownVersionAddsNoHeader(): void {
		$this->assertArrayNotHasKey('API-Version', $this->after($this->middleware('/apps/launchpad/api/x', ''), new JSONResponse([])));
	}
}
