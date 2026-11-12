<?php

/**
 * Tests for the versioned API base in appinfo/routes.php.
 *
 * @category  Test
 * @package   Unit\AppInfo
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Every /api/ route also answers under /api/v1/, the OpenAPI server URL.
 */
class VersionedRoutesTest extends TestCase {

	public function testEveryApiRouteHasAVersionedTwinWithItsOwnName(): void {
		$routes = (include dirname(__DIR__, 3).'/appinfo/routes.php')['routes'];

		$plain     = [];
		$versioned = [];
		$names     = [];
		foreach ($routes as $route) {
			$key = ($route['verb'] ?? 'GET').' '.$route['url'];
			if (str_starts_with($route['url'], '/api/v1/') === true) {
				$versioned[$key] = $route;
			} else if (str_starts_with($route['url'], '/api/') === true) {
				$plain[$key] = $route;
			}

			$name = strtolower($route['name'].($route['postfix'] ?? ''));
			$this->assertArrayNotHasKey($name, $names, $name.' is registered twice, so Nextcloud keeps only the last one');
			$names[$name] = true;
		}

		$this->assertArrayHasKey('GET /api/openapi.json', $plain);
		$this->assertCount(count($plain), $versioned);
		foreach ($plain as $key => $route) {
			[$verb, $url] = explode(' ', $key, 2);
			$twin         = ($versioned[$verb.' /api/v1/'.substr($url, 5)] ?? null);
			$this->assertNotNull($twin, $key.' has no /api/v1/ twin');
			$this->assertSame($route['name'], $twin['name']);
			$this->assertSame(($route['postfix'] ?? '').'V1', $twin['postfix']);
			$this->assertSame(($route['requirements'] ?? null), ($twin['requirements'] ?? null));
			$this->assertSame(($route['defaults'] ?? null), ($twin['defaults'] ?? null));
		}
	}
}
