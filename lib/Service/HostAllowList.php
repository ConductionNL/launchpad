<?php

/**
 * HostAllowList
 *
 * The fail-closed outbound host allow-list and the http(s) scheme check
 * shared by the services that fetch admin-configured URLs.
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

use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;

/**
 * One admin-configured list of hosts an outbound request may reach: a JSON
 * list stored under an app config key. FAIL-CLOSED: an empty, missing, or
 * unparseable list permits NO host — the admin must explicitly opt hosts in.
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */
final class HostAllowList {
	/**
	 * Constructor
	 *
	 * @param IAppConfig $appConfig The app config holding the list.
	 * @param string     $configKey The config key of the list.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly string $configKey,
	) {
	}//end __construct()

	/**
	 * Whether a URL's scheme is `http` or `https`.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return boolean
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public static function hasHttpScheme(string $url): bool {
		$scheme = strtolower(string: (string)parse_url(url: $url, component: PHP_URL_SCHEME));
		return in_array(needle: $scheme, haystack: ['http', 'https'], strict: true);
	}//end hasHttpScheme()

	/**
	 * Whether a URL's host is on the list (case-insensitive). The list is
	 * only read once the URL has a host.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return boolean True only when the host is explicitly allow-listed.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function permits(string $url): bool {
		$host = parse_url(url: $url, component: PHP_URL_HOST);
		if (is_string(value: $host) === false || $host === '') {
			return false;
		}

		$decoded = json_decode(json: $this->rawList(), associative: true);
		if (is_array(value: $decoded) === false || $decoded === []) {
			// FAIL-CLOSED — no configured list means no host is allowed.
			return false;
		}

		return $this->listsHost(allowed: $decoded, host: $host);
	}//end permits()

	/**
	 * The stored JSON list, '' when unset.
	 *
	 * @return string The raw list.
	 */
	private function rawList(): string {
		return $this->appConfig->getValueString(
			app: Application::APP_ID,
			key: $this->configKey,
			default: ''
		);
	}//end rawList()

	/**
	 * Whether a decoded list carries the host (case-insensitive); non-string
	 * entries never match.
	 *
	 * @param array<mixed> $allowed The decoded list.
	 * @param string       $host    The URL's host.
	 *
	 * @return boolean
	 */
	private function listsHost(array $allowed, string $host): bool {
		$needle = strtolower(string: $host);
		foreach ($allowed as $entry) {
			if (is_string(value: $entry) === true && strtolower(string: $entry) === $needle) {
				return true;
			}
		}

		return false;
	}//end listsHost()
}//end class
