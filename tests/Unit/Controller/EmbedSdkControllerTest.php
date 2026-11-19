<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed SDK route serves only the built bundles, publicly and cacheably,
 * to any origin, with the content type of each.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-24
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\EmbedSdkController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use OCP\AppFramework\Http\Response;
use ReflectionMethod;
use ReflectionProperty;

class EmbedSdkControllerTest extends TestCase {

	private string $appDir;

	private EmbedSdkController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir().'/launchpad-embed-sdk-'.bin2hex(random_bytes(4));
		mkdir($this->appDir.'/js/embed-sdk', 0777, true);
		file_put_contents($this->appDir.'/js/embed-sdk/embed-sdk.umd.js', 'window.LaunchPadEmbed={}');
		file_put_contents($this->appDir.'/js/embed-sdk/embed-sdk.esm.js.map', '{"version":3}');
		file_put_contents($this->appDir.'/js/secret.txt', 'not served');

		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn($this->appDir);
		$apps->method('getAppVersion')->willReturn('1.4.0');
		$this->controller = new EmbedSdkController($this->createMock(IRequest::class), $apps);
	}

	protected function tearDown(): void {
		foreach (['js/embed-sdk/embed-sdk.umd.js', 'js/embed-sdk/embed-sdk.esm.js.map', 'js/secret.txt'] as $file) {
			@unlink($this->appDir.'/'.$file);
		}
		@rmdir($this->appDir.'/js/embed-sdk');
		@rmdir($this->appDir.'/js');
		@rmdir($this->appDir);
		parent::tearDown();
	}

	public function testServesABundleAsJavaScriptToAnyOriginAndCachesIt(): void {
		$response = $this->controller->bundle('embed-sdk.umd.js');
		$headers  = $this->headersOf($response);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('window.LaunchPadEmbed={}', $response->render());
		$this->assertSame('text/javascript; charset=utf-8', $headers['Content-Type']);
		$this->assertSame('*', $headers['Access-Control-Allow-Origin']);
		$this->assertSame('cross-origin', $headers['Cross-Origin-Resource-Policy']);
		$this->assertSame('nosniff', $headers['X-Content-Type-Options']);
		$this->assertSame('1.4.0', $headers['X-LaunchPad-Version']);
		$this->assertStringContainsString('public', $headers['Cache-Control']);
		$this->assertStringContainsString('max-age=86400', $headers['Cache-Control']);
		$this->assertSame(hash('sha256', 'window.LaunchPadEmbed={}'), $response->getETag());
	}

	public function testServesASourceMapAsJson(): void {
		$response = $this->controller->bundle('embed-sdk.esm.js.map');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('application/json; charset=utf-8', $this->headersOf($response)['Content-Type']);
	}

	public function testAnswers404ForANameThatIsNotABundleOrABundleNotBuilt(): void {
		foreach (['../secret.txt', 'secret.txt', '..%2Fsecret.txt', 'embed-sdk.esm.js', ''] as $file) {
			$response = $this->controller->bundle($file);
			$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus(), $file);
			$this->assertStringNotContainsString('not served', $response->render(), $file);
		}
	}

	/**
	 * The headers the controller set, read without the server container that
	 * Response::getHeaders() asks for its defaults.
	 */
	private function headersOf(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}

	public function testTheRouteIsPublicAndNeedsNoCsrfToken(): void {
		$method = new ReflectionMethod(EmbedSdkController::class, 'bundle');
		$this->assertCount(1, $method->getAttributes(PublicPage::class));
		$this->assertCount(1, $method->getAttributes(NoCSRFRequired::class));
	}
}
