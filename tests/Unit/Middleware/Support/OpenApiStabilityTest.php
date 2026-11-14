<?php

/**
 * Unit tests that keep every operation of openapi.json marked public or internal.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Middleware\Support
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/governance-rest-api-reference/specs
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Middleware\Support;

use PHPUnit\Framework\TestCase;

/**
 * Every operation carries `x-launchpad-stability` (design D2). A public
 * operation only changes with a deprecation notice, so the public set is
 * pinned here: moving an operation in or out of it is a deliberate edit.
 *
 * @coversNothing
 */
class OpenApiStabilityTest extends TestCase {

	/**
	 * The operations promised to integrators: dashboard CRUD and listing,
	 * placements and tiles, sharing, public links, export and import.
	 *
	 * @var array<int,string>
	 */
	private const PUBLIC = [
		'DELETE /dashboard/share/{shareId}',
		'DELETE /dashboard/{id}',
		'DELETE /dashboards/{uuid}/public-shares/{id}',
		'DELETE /tiles/{id}',
		'DELETE /widgets/{placementId}',
		'GET /dashboard',
		'GET /dashboard/{id}',
		'GET /dashboard/{id}/shares',
		'GET /dashboards',
		'GET /dashboards/visible',
		'GET /dashboards/{uuid}/public-shares',
		'GET /tiles',
		'POST /admin/export',
		'POST /admin/import',
		'POST /dashboard',
		'POST /dashboard/{dashboardId}/tile',
		'POST /dashboard/{dashboardId}/widgets',
		'POST /dashboard/{id}/shares',
		'POST /dashboards/{uuid}/public-share',
		'POST /tiles',
		'PUT /dashboard/{id}',
		'PUT /dashboard/{id}/shares',
		'PUT /tiles/{id}',
		'PUT /widgets/{placementId}',
	];

	/**
	 * Every operation is either public or internal, and exactly the pinned ones are public.
	 *
	 * @return void
	 */
	public function testEveryOperationIsPublicOrInternal(): void {
		$document = json_decode((string)file_get_contents(dirname(__DIR__, 4) . '/openapi.json'), true);
		$public = [];
		$count = 0;
		foreach ($document['paths'] as $path => $item) {
			foreach ($item as $verb => $operation) {
				if (in_array($verb, ['get', 'put', 'post', 'delete', 'patch', 'options', 'head'], true) === false) {
					continue;
				}

				$count++;
				$stability = ($operation['x-launchpad-stability'] ?? null);
				$this->assertContains($stability, ['public', 'internal'], strtoupper($verb) . ' ' . $path);
				if ($stability === 'public') {
					$public[] = strtoupper($verb) . ' ' . $path;
				}
			}
		}

		sort($public);
		$this->assertGreaterThan(count(self::PUBLIC), $count);
		$this->assertSame(self::PUBLIC, $public);

	}//end testEveryOperationIsPublicOrInternal()
}//end class
