<?php

/**
 * EmbedSdkController Test
 *
 * The embed SDK route serves only the built bundles, publicly and cacheably,
 * to any origin, with the content type of each.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-24
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\EmbedSdkController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for {@see EmbedSdkController}.
 */
class EmbedSdkControllerTest extends TestCase {

	/**
	 * The app directory the test builds the bundles in.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The controller under test.
	 *
	 * @var EmbedSdkController
	 */
	private EmbedSdkController $controller;

	/**
	 * Build two bundles and a file the route must never serve.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir().'/launchpad-embed-sdk-'.bin2hex(random_bytes(length: 4));
		mkdir(directory: $this->appDir.'/js/embed-sdk', permissions: 0777, recursive: true);
		file_put_contents(filename: $this->appDir.'/js/embed-sdk/embed-sdk.umd.js', data: 'window.LaunchPadEmbed={}');
		file_put_contents(filename: $this->appDir.'/js/embed-sdk/embed-sdk.esm.js.map', data: '{"version":3}');
		file_put_contents(filename: $this->appDir.'/js/secret.txt', data: 'not served');

		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppPath')->willReturn($this->appDir);
		$apps->method('getAppVersion')->willReturn('1.4.0');
		$this->controller = new EmbedSdkController(
			appName: 'launchpad',
			request: $this->createMock(originalClassName: IRequest::class),
			appManager: $apps
		);
	}//end setUp()

	/**
	 * Remove the built files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (['js/embed-sdk/embed-sdk.umd.js', 'js/embed-sdk/embed-sdk.esm.js.map', 'js/secret.txt'] as $file) {
			@unlink(filename: $this->appDir.'/'.$file);
		}

		@rmdir(directory: $this->appDir.'/js/embed-sdk');
		@rmdir(directory: $this->appDir.'/js');
		@rmdir(directory: $this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * A bundle is JavaScript, readable from any origin, cached for a day.
	 *
	 * @return void
	 */
	public function testServesABundleAsJavaScriptToAnyOriginAndCachesIt(): void {
		$response = $this->controller->bundle(file: 'embed-sdk.umd.js');
		$headers  = $this->headersOf(response: $response);

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'window.LaunchPadEmbed={}', actual: $response->render());
		$this->assertSame(expected: 'text/javascript; charset=utf-8', actual: $headers['Content-Type']);
		$this->assertSame(expected: '*', actual: $headers['Access-Control-Allow-Origin']);
		$this->assertSame(expected: 'cross-origin', actual: $headers['Cross-Origin-Resource-Policy']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertSame(expected: '1.4.0', actual: $headers['X-LaunchPad-Version']);
		$this->assertSame(expected: 'public, max-age=86400', actual: $headers['Cache-Control']);
		$this->assertSame(expected: hash(algo: 'sha256', data: 'window.LaunchPadEmbed={}'), actual: $response->getETag());
	}//end testServesABundleAsJavaScriptToAnyOriginAndCachesIt()

	/**
	 * A source map is JSON.
	 *
	 * @return void
	 */
	public function testServesASourceMapAsJson(): void {
		$response = $this->controller->bundle(file: 'embed-sdk.esm.js.map');

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'application/json; charset=utf-8', actual: $this->headersOf(response: $response)['Content-Type']);
	}//end testServesASourceMapAsJson()

	/**
	 * Any other name, and a bundle that was not built, is a 404.
	 *
	 * @return void
	 */
	public function testAnswers404ForANameThatIsNotABundleOrABundleNotBuilt(): void {
		foreach (['../secret.txt', 'secret.txt', '..%2Fsecret.txt', 'embed-sdk.esm.js', ''] as $file) {
			$response = $this->controller->bundle(file: $file);
			$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus(), message: $file);
			$this->assertStringNotContainsString(needle: 'not served', haystack: $response->render(), message: $file);
		}
	}//end testAnswers404ForANameThatIsNotABundleOrABundleNotBuilt()

	/**
	 * The route is public, needs no CSRF token and has a volume ceiling.
	 *
	 * @return void
	 */
	public function testTheRouteIsPublicNeedsNoCsrfTokenAndIsThrottled(): void {
		$method = new ReflectionMethod(EmbedSdkController::class, 'bundle');
		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(name: PublicPage::class));
		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(name: NoCSRFRequired::class));
		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(name: AnonRateLimit::class));
	}//end testTheRouteIsPublicNeedsNoCsrfTokenAndIsThrottled()

	/**
	 * The headers the controller set, read without the server container that
	 * Response::getHeaders() asks for its defaults.
	 *
	 * @param Response $response The response.
	 *
	 * @return array<string, string>
	 */
	private function headersOf(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}//end headersOf()
}//end class
