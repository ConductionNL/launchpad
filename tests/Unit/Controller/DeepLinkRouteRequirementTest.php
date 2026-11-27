<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `/{deepLink}` catch-all leaves API and resource paths to their own
 * routes, so a decoded traversal under `/resource/` is a 404 (REQ-RES-006).
 *
 * @spec openspec/specs/resource-uploads/spec.md#requirement-public-resource-serving-endpoint-req-res-006
 */

declare(strict_types=1);

namespace Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * The deep-link route's requirement, read from appinfo/routes.php.
 */
class DeepLinkRouteRequirementTest extends TestCase {

	/**
	 * The deep-link requirement as a full-match regex, the way the router applies it.
	 *
	 * @return string
	 */
	private function deepLinkPattern(): string {
		$declared = require dirname(__DIR__, 3).'/appinfo/routes.php';
		foreach ($declared['routes'] as $route) {
			if ($route['name'] === 'page#deepLink' && $route['url'] === '/{deepLink}') {
				return '#^(?:'.$route['requirements']['deepLink'].')$#';
			}
		}

		self::fail('no /{deepLink} route in appinfo/routes.php');
	}//end deepLinkPattern()

	/**
	 * A decoded traversal under resource/ and any other resource path is not a deep link.
	 *
	 * @return void
	 */
	public function testResourcePathsAreNotDeepLinks(): void {
		$pattern = $this->deepLinkPattern();

		self::assertSame(0, preg_match($pattern, 'resource/../../etc/passwd'));
		self::assertSame(0, preg_match($pattern, 'resource/does-not-exist.png'));
		self::assertSame(0, preg_match($pattern, 'resource'));
	}//end testResourcePathsAreNotDeepLinks()

	/**
	 * API paths stay out, and dashboard slug chains are still deep links.
	 *
	 * @return void
	 */
	public function testApiStaysOutAndSlugChainsStayIn(): void {
		$pattern = $this->deepLinkPattern();

		self::assertSame(0, preg_match($pattern, 'api/dashboards'));
		self::assertSame(1, preg_match($pattern, 'team/sales'));
		self::assertSame(1, preg_match($pattern, 'resources-overview'));
		self::assertSame(1, preg_match($pattern, 'apiary'));
	}//end testApiStaysOutAndSlugChainsStayIn()
}//end class
