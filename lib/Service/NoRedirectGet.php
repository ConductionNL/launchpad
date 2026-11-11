<?php

/**
 * NoRedirectGet
 *
 * The bounded, redirect-free GET the services that fetch admin-configured
 * URLs send.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;

/**
 * A GET with a connect and a total timeout that never follows a redirect
 * and never throws on an HTTP error status: a 3xx to an unexpected host
 * would bypass the allow-list / scheme check made before the request.
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */
final class NoRedirectGet {
	/**
	 * Constructor
	 *
	 * @param integer $connectTimeout Connect timeout in seconds.
	 * @param integer $timeout        Total timeout in seconds.
	 */
	public function __construct(
		private readonly int $connectTimeout,
		private readonly int $timeout,
	) {
	}//end __construct()

	/**
	 * Send the GET.
	 *
	 * @param IClient $client The HTTP client.
	 * @param string  $url    The URL, already checked by the caller.
	 *
	 * @return IResponse The response, whatever its status.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function send(IClient $client, string $url): IResponse {
		return $client->get(
			uri: $url,
			options: [
				'connect_timeout' => $this->connectTimeout,
				'timeout' => $this->timeout,
				'http_errors' => false,
				'allow_redirects' => false,
			]
		);
	}//end send()
}//end class
