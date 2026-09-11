<?php

/**
 * StoreWiringTest
 *
 * Guards the two wiring defects this capability was written to fix, both of
 * which shipped on `development` and neither of which any unit test could see.
 *
 * The store page was declared in `src/manifest.json` while `appinfo/routes.php`
 * declared no store route, so `CnStorePage` 404'd on mount (REQ-STORE-001). And
 * the `store` block declared `openregister.configset` types, which select the
 * engine's federated configuration path and cannot produce a dashboard
 * (REQ-STORE-008).
 *
 * Both live in files no service test loads, which is exactly why they need a
 * test that reads the files.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Route and manifest wiring for the dashboard store.
 */
class StoreWiringTest extends TestCase {
	/**
	 * The decoded route table.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function routes(): array {
		$table = require dirname(__DIR__, 3) . '/appinfo/routes.php';

		return $table['routes'];
	}//end routes()

	/**
	 * The decoded frontend manifest.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(): array {
		$raw = file_get_contents(dirname(__DIR__, 3) . '/src/manifest.json');
		$decoded = json_decode((string)$raw, true);
		$this->assertIsArray($decoded, 'src/manifest.json must be valid JSON');

		return $decoded;
	}//end manifest()

	/**
	 * REQ-STORE-001: the endpoints CnStorePage calls must be routed.
	 *
	 * @return void
	 */
	public function testTheStorePageCanReachItsEndpoints(): void {
		$urls = [];
		foreach ($this->routes() as $route) {
			$urls[$route['name']] = $route['url'];
		}

		$this->assertSame('/api/store/items', ($urls['store#search'] ?? null));
		$this->assertSame('/api/store/items/{slug}/install', ($urls['store#install'] ?? null));
	}//end testTheStorePageCanReachItsEndpoints()

	/**
	 * REQ-STORE-001: a malformed slug fails at the router, not at the registry.
	 *
	 * @return void
	 */
	public function testTheInstallRouteConstrainsTheSlug(): void {
		$pattern = null;
		foreach ($this->routes() as $route) {
			if ($route['name'] === 'store#install') {
				$pattern = ($route['requirements']['slug'] ?? null);
			}
		}

		$this->assertIsString($pattern);
		$this->assertSame(1, preg_match('/^' . $pattern . '$/', 'sales-overview'));
		$this->assertSame(0, preg_match('/^' . $pattern . '$/', '../../etc/passwd'));
		$this->assertSame(0, preg_match('/^' . $pattern . '$/', 'Sales_Overview'));
	}//end testTheInstallRouteConstrainsTheSlug()

	/**
	 * REQ-STORE-008: the manifest declares the page and no engine store block.
	 *
	 * @return void
	 */
	public function testTheManifestDeclaresThePageButNoStoreBlock(): void {
		$manifest = $this->manifest();

		$this->assertArrayNotHasKey(
			'store',
			$manifest,
			'a store block configures the engine controller LaunchPad does not use'
		);

		$storePages = array_filter(
			$manifest['pages'],
			static fn (array $page): bool => (($page['type'] ?? '') === 'store')
		);
		$this->assertCount(1, $storePages);
	}//end testTheManifestDeclaresThePageButNoStoreBlock()

	/**
	 * The store page's copy follows the Conduction voice.
	 *
	 * No em-dash, sentence case, and short sentences. A gate rejects the
	 * em-dash in a manifest after the fact; this catches it in the suite.
	 *
	 * @return void
	 */
	public function testTheStorePageCopyHasNoEmDash(): void {
		$page = array_values(
			array_filter(
				$this->manifest()['pages'],
				static fn (array $candidate): bool => (($candidate['type'] ?? '') === 'store')
			)
		)[0];

		$description = (string)($page['config']['description'] ?? '');
		$this->assertNotSame('', $description);
		$this->assertStringNotContainsString('—', $description);
		$this->assertStringNotContainsString('–', $description);
	}//end testTheStorePageCopyHasNoEmDash()
}//end class
