<?php

/**
 * Unit tests for the standard protocol paths the API contract leaves alone.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Middleware\Support
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Middleware\Support;

use OCA\LaunchPad\Middleware\Support\StandardProtocolPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A path is read relative to the app root, whether it arrives app-relative or
 * as a request path, and only the protocol paths match.
 *
 * @covers \OCA\LaunchPad\Middleware\Support\StandardProtocolPaths
 */
class StandardProtocolPathsTest extends TestCase {

	/**
	 * Paths and their app-relative form.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function relativePaths(): array {
		return [
			'app-relative'          => ['/api/health', '/api/health'],
			'no leading slash'      => ['api/metrics', '/api/metrics'],
			'request path'          => ['/index.php/apps/launchpad/api/health', '/api/health'],
			'pretty request path'   => ['/apps/launchpad/api/things/7', '/api/things/7'],
			'another app'           => ['/apps/other/api/health', '/apps/other/api/health'],
		];
	}//end relativePaths()

	/**
	 * Each path reads as its app-relative form.
	 *
	 * @param string $path     The path.
	 * @param string $relative The expected app-relative path.
	 *
	 * @return void
	 */
	#[DataProvider('relativePaths')]
	public function testAppRelative(string $path, string $relative): void {
		$this->assertSame($relative, StandardProtocolPaths::appRelative(path: $path));
	}//end testAppRelative()

	/**
	 * Health and metrics match in both forms; other API paths and another
	 * app's health path do not.
	 *
	 * @return void
	 */
	public function testOnlyTheProtocolPathsMatch(): void {
		$this->assertTrue(StandardProtocolPaths::matches(path: '/api/health'));
		$this->assertTrue(StandardProtocolPaths::matches(path: '/index.php/apps/launchpad/api/metrics'));
		$this->assertFalse(StandardProtocolPaths::matches(path: '/api/healthz'));
		$this->assertFalse(StandardProtocolPaths::matches(path: '/api/v1/health'));
		$this->assertFalse(StandardProtocolPaths::matches(path: '/apps/other/api/health'));
	}//end testOnlyTheProtocolPathsMatch()
}//end class
