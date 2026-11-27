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
		$declared = require dirname(path: __DIR__, levels: 3).'/appinfo/routes.php';
		foreach ($declared['routes'] as $route) {
			if ($route['name'] === 'page#deepLink' && $route['url'] === '/{deepLink}') {
				return '#^(?:'.$route['requirements']['deepLink'].')$#';
			}
		}

		self::fail(message: 'no /{deepLink} route in appinfo/routes.php');
	}//end deepLinkPattern()

	/**
	 * A decoded traversal under resource/ and any other resource path is not a deep link.
	 *
	 * @return void
	 */
	public function testResourcePathsAreNotDeepLinks(): void {
		$pattern = $this->deepLinkPattern();

		self::assertSame(expected: 0, actual: preg_match(pattern: $pattern, subject: 'resource/../../etc/passwd'));
		self::assertSame(expected: 0, actual: preg_match(pattern: $pattern, subject: 'resource/does-not-exist.png'));
		self::assertSame(expected: 0, actual: preg_match(pattern: $pattern, subject: 'resource'));
	}//end testResourcePathsAreNotDeepLinks()

	/**
	 * API paths stay out, and dashboard slug chains are still deep links.
	 *
	 * @return void
	 */
	public function testApiStaysOutAndSlugChainsStayIn(): void {
		$pattern = $this->deepLinkPattern();

		self::assertSame(expected: 0, actual: preg_match(pattern: $pattern, subject: 'api/dashboards'));
		self::assertSame(expected: 1, actual: preg_match(pattern: $pattern, subject: 'team/sales'));
		self::assertSame(expected: 1, actual: preg_match(pattern: $pattern, subject: 'resources-overview'));
		self::assertSame(expected: 1, actual: preg_match(pattern: $pattern, subject: 'apiary'));
	}//end testApiStaysOutAndSlugChainsStayIn()
}//end class
