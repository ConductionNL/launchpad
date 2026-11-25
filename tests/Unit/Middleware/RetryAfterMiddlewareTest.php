<?php

/**
 * RetryAfterMiddlewareTest
 *
 * REQ-PSHR-009: a 429 tells the client when to retry. Nextcloud's throttle
 * answers the eleventh failed unlock with an empty 429 that names its delay
 * only in X-Nextcloud-Bruteforce-Throttled; the middleware turns that into
 * Retry-After, and leaves every other response alone.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Middleware
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Middleware;

use OCA\LaunchPad\Middleware\RetryAfterMiddleware;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Retry-After on a 429.
 */
class RetryAfterMiddlewareTest extends TestCase {

	/**
	 * A response with a status and headers, without a booted Nextcloud
	 * (Response::getHeaders() reads the server's security headers).
	 *
	 * @param int                  $status  The status.
	 * @param array<string,string> $headers The headers.
	 *
	 * @return Response The response; added headers land in its `added` property.
	 */
	private function response(int $status, array $headers = []): Response {
		return new class ($status, $headers) extends Response {
			/**
			 * The headers the middleware added.
			 *
			 * @var array<string,string>
			 */
			public array $added = [];

			/**
			 * Constructor.
			 *
			 * @param int                  $code The status.
			 * @param array<string,string> $own  The headers.
			 */
			public function __construct(private int $code, private array $own) {
			}//end __construct()

			/**
			 * The status.
			 *
			 * @return int
			 */
			public function getStatus(): int {
				return $this->code;
			}//end getStatus()

			/**
			 * The headers, with those added.
			 *
			 * @return array<string,string>
			 */
			public function getHeaders(): array {
				return array_merge($this->own, $this->added);
			}//end getHeaders()

			/**
			 * Record an added header.
			 *
			 * @param string $name  The name.
			 * @param mixed  $value The value.
			 *
			 * @return $this
			 */
			public function addHeader($name, $value) {
				$this->added[$name] = $value;
				return $this;
			}//end addHeader()
		};
	}//end response()

	/**
	 * Run the middleware's afterController on a response.
	 *
	 * @param Response $response The response.
	 *
	 * @return Response The result.
	 */
	private function after(Response $response): Response {
		return (new RetryAfterMiddleware())->afterController(controller: new \stdClass(), methodName: 'unlock', response: $response);
	}//end after()

	/**
	 * Nextcloud's throttled 429 gets the delay it names, in whole seconds.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public function testAThrottled429GetsTheNamedDelay(): void {
		$response = $this->response(status: Http::STATUS_TOO_MANY_REQUESTS, headers: ['X-Nextcloud-Bruteforce-Throttled' => '25000ms']);
		$this->assertSame(expected: '25', actual: $this->after(response: $response)->getHeaders()['Retry-After']);
	}//end testAThrottled429GetsTheNamedDelay()

	/**
	 * A 429 that names no delay gets the 60-second window.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public function testA429WithoutADelayGetsTheWindow(): void {
		$response = $this->response(status: Http::STATUS_TOO_MANY_REQUESTS);
		$this->assertSame(expected: '60', actual: $this->after(response: $response)->getHeaders()['Retry-After']);
	}//end testA429WithoutADelayGetsTheWindow()

	/**
	 * A Retry-After already set is kept, and other statuses are left alone.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public function testOtherResponsesAreLeftAlone(): void {
		$own = $this->response(status: Http::STATUS_TOO_MANY_REQUESTS, headers: ['Retry-After' => '7']);
		$this->assertSame(expected: '7', actual: $this->after(response: $own)->getHeaders()['Retry-After']);

		$denied = $this->response(status: Http::STATUS_UNAUTHORIZED);
		$this->assertArrayNotHasKey(key: 'Retry-After', array: $this->after(response: $denied)->getHeaders());
	}//end testOtherResponsesAreLeftAlone()

	/**
	 * Delays round up to whole seconds and never fall below one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public function testSecondsFromRoundsUp(): void {
		$this->assertSame(expected: 2, actual: RetryAfterMiddleware::secondsFrom(throttled: '1200ms'));
		$this->assertSame(expected: 1, actual: RetryAfterMiddleware::secondsFrom(throttled: '0ms'));
		$this->assertSame(expected: 60, actual: RetryAfterMiddleware::secondsFrom(throttled: 'soon'));
	}//end testSecondsFromRoundsUp()
}//end class
