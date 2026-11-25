<?php

/**
 * RetryAfterMiddleware
 *
 * Every 429 a LaunchPad route answers tells the client when to try again
 * (dashboard-public-share REQ-PSHR-009: "HTTP 429 on the 11th attempt with
 * `Retry-After`"). Nextcloud's own rate-limit and brute-force middleware answer
 * the throttled unlock before the controller runs, with an empty 429 and only
 * `X-Nextcloud-Bruteforce-Throttled`; Nextcloud still runs every middleware's
 * afterController on that response, so this one adds `Retry-After` there:
 * the throttle delay in whole seconds when Nextcloud names one, else the
 * 60-second window of the unlock limit.
 *
 * @category  Middleware
 * @package   OCA\LaunchPad\Middleware
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

/**
 * Adds Retry-After to a 429 that has none.
 *
 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
 */
class RetryAfterMiddleware extends Middleware {

	/**
	 * The window of the unlock limit (10 attempts per 60 seconds), used when
	 * Nextcloud names no delay.
	 */
	public const DEFAULT_SECONDS = 60;

	/**
	 * The header Nextcloud's brute-force throttle sets, e.g. "25000ms".
	 */
	private const THROTTLED_HEADER = 'X-Nextcloud-Bruteforce-Throttled';

	/**
	 * Add Retry-After to a 429 without one.
	 *
	 * @param object   $controller The controller.
	 * @param string   $methodName The method.
	 * @param Response $response   The response so far.
	 *
	 * @return Response The response, with Retry-After on a 429.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The signature is Nextcloud's.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public function afterController($controller, $methodName, Response $response): Response {
		if ($response->getStatus() !== Http::STATUS_TOO_MANY_REQUESTS) {
			return $response;
		}

		$headers = array_change_key_case(array: $response->getHeaders(), case: CASE_LOWER);
		if (array_key_exists(key: 'retry-after', array: $headers) === true) {
			return $response;
		}

		$response->addHeader(
			name: 'Retry-After',
			value: (string)self::secondsFrom(throttled: (string)($headers[strtolower(string: self::THROTTLED_HEADER)] ?? ''))
		);
		return $response;
	}//end afterController()

	/**
	 * Whole seconds from Nextcloud's throttle header ("25000ms"), at least 1;
	 * the default window when the header is missing or unreadable.
	 *
	 * @param string $throttled The header value.
	 *
	 * @return integer The seconds.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-req-pshr-009-brute-force-protection
	 */
	public static function secondsFrom(string $throttled): int {
		if (preg_match(pattern: '/^\s*(\d+)\s*ms\s*$/i', subject: $throttled, matches: $match) !== 1) {
			return self::DEFAULT_SECONDS;
		}

		return max(1, (int)ceil(num: ((int)$match[1]) / 1000));
	}//end secondsFrom()
}//end class
