<?php

/**
 * Unit tests for the route half of `composer lint:openapi`
 * (governance-rest-api-reference D3).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Scripts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Scripts;

use OCA\LaunchPad\Scripts\OpenApiRouteCoverage;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/scripts/OpenApiRouteCoverage.php';

/**
 * The route table and the OpenAPI document must describe the same operations.
 *
 * @coversNothing
 */
class OpenApiRouteCoverageTest extends TestCase {

	/**
	 * A document with the given operations, each `[verb, path, stability|null]`.
	 *
	 * @param list<array{0: string, 1: string, 2: string|null}> $operations The operations.
	 *
	 * @return array<string, mixed>
	 */
	private function document(array $operations): array {
		$paths = [];
		foreach ($operations as [$verb, $path, $stability]) {
			$operation = ['responses' => ['200' => ['description' => 'OK']]];
			if ($stability !== null) {
				$operation['x-launchpad-stability'] = $stability;
			}

			$paths[$path][$verb] = $operation;
		}

		return ['openapi' => '3.1.0', 'paths' => $paths];
	}//end document()

	/**
	 * Matching routes and operations pass, whatever their parameter names.
	 *
	 * @return void
	 */
	public function testMatchingRoutesAndOperationsPass(): void {
		$routes = ['routes' => [
			['name' => 'dashboardApi#index', 'url' => '/api/v1/dashboards', 'verb' => 'GET'],
			['name' => 'dashboardApi#show', 'url' => '/api/v1/dashboards/{id}', 'verb' => 'GET'],
			['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
			['name' => 'dashboardApi#legacy', 'url' => '/api/dashboard', 'verb' => 'GET'],
		]];
		$document = $this->document([
			['get', '/dashboards', 'public'],
			['get', '/dashboards/{uuid}', 'internal'],
		]);

		self::assertSame([], OpenApiRouteCoverage::problems(routes: $routes, document: $document));
	}//end testMatchingRoutesAndOperationsPass()

	/**
	 * A fixture with a route that has no operation fails, naming the route.
	 *
	 * @return void
	 */
	public function testAnUndocumentedRouteFails(): void {
		$routes = ['routes' => [
			['name' => 'dashboardApi#index', 'url' => '/api/v1/dashboards', 'verb' => 'GET'],
			['name' => 'tileApi#create', 'url' => '/api/v1/tiles', 'verb' => 'POST'],
		]];
		$document = $this->document([['get', '/dashboards', 'public']]);

		self::assertSame(
			['undocumented route: POST /tiles (tileApi#create)'],
			OpenApiRouteCoverage::problems(routes: $routes, document: $document)
		);
	}//end testAnUndocumentedRouteFails()

	/**
	 * An operation without a stability, or with another value, fails.
	 *
	 * @return void
	 */
	public function testAnOperationWithoutAStabilityFails(): void {
		$routes = ['routes' => [
			['name' => 'a#me', 'url' => '/api/v1/analytics/me', 'verb' => 'GET'],
			['name' => 'a#set', 'url' => '/api/v1/analytics/me', 'verb' => 'PUT'],
		]];
		$document = $this->document([
			['get', '/analytics/me', null],
			['put', '/analytics/me', 'beta'],
		]);

		self::assertSame(
			[
				'no x-launchpad-stability (public or internal): GET /analytics/me',
				'no x-launchpad-stability (public or internal): PUT /analytics/me',
			],
			OpenApiRouteCoverage::problems(routes: $routes, document: $document)
		);
	}//end testAnOperationWithoutAStabilityFails()

	/**
	 * An operation documented for a route that does not exist fails.
	 *
	 * @return void
	 */
	public function testAnOperationWithoutARouteFails(): void {
		$document = $this->document([['delete', '/dashboards/{id}', 'public']]);

		self::assertSame(
			['documented operation without a route: DELETE /dashboards/{}'],
			OpenApiRouteCoverage::problems(routes: ['routes' => []], document: $document)
		);
	}//end testAnOperationWithoutARouteFails()

	/**
	 * The catch-all API fallback route is not an operation.
	 *
	 * @return void
	 */
	public function testTheApiFallbackRouteIsNoOperation(): void {
		$routes = ['routes' => [
			['name' => 'api#fallback', 'url' => '/api/v1/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+']],
		]];

		self::assertNull(OpenApiRouteCoverage::routeKey(route: $routes['routes'][0]));
		self::assertSame([], OpenApiRouteCoverage::problems(routes: $routes, document: $this->document([])));
	}//end testTheApiFallbackRouteIsNoOperation()

	/**
	 * The committed openapi.json documents every route the app registers.
	 *
	 * @return void
	 */
	public function testTheCommittedDocumentCoversTheAppsRoutes(): void {
		$root     = dirname(__DIR__, 3);
		$routes   = require $root.'/appinfo/routes.php';
		$document = json_decode((string) file_get_contents($root.'/openapi.json'), true);

		self::assertIsArray($document);
		self::assertSame([], OpenApiRouteCoverage::problems(routes: $routes, document: $document));
	}//end testTheCommittedDocumentCoversTheAppsRoutes()
}//end class
