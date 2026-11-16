<?php

/**
 * UrlSafetyValidator — shared SSRF guard for outbound HTTP fetches.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;

/**
 * Shared URL safety / SSRF guard for outbound HTTP fetches.
 *
 * Rules enforced (all must pass):
 *  1. Scheme MUST be https (no plain-text HTTP, no other schemes).
 *  2. Host MUST be present and non-empty.
 *  3. Every resolved IP MUST pass FILTER_FLAG_NO_PRIV_RANGE |
 *     FILTER_FLAG_NO_RES_RANGE.
 *
 * @spec openspec/specs/news-widget/spec.md
 * @spec openspec/specs/calendar-widget/spec.md
 */
class UrlSafetyValidator {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig App configuration (used by checkAllowList).
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Validate an outbound URL against SSRF rules.
	 *
	 * Accepts only HTTPS URLs whose hostname resolves exclusively to
	 * public IPs (no private/reserved/loopback ranges).
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return bool True when the URL passes all checks.
	 *
	 * @spec openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function isSafe(string $url): bool {
		$parts = parse_url(url: $url);
		if (is_array(value: $parts) === false) {
			return false;
		}

		if (($parts['scheme'] ?? '') !== 'https') {
			return false;
		}

		$host = (string)($parts['host'] ?? '');
		if ($host === '') {
			return false;
		}

		$ips = gethostbynamel(hostname: $host);
		if ($ips === false || $ips === []) {
			return false;
		}

		foreach ($ips as $ip) {
			$publicIp = filter_var(
				value: $ip,
				filter: FILTER_VALIDATE_IP,
				options: (FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
			);
			if ($publicIp === false) {
				return false;
			}
		}

		return true;
	}//end isSafe()

	/**
	 * Check whether the URL host is permitted by an admin allow-list
	 * stored in IAppConfig.
	 *
	 * An empty / missing list means ALL hosts are allowed (open policy).
	 * When the list is non-empty the host MUST appear in it (exact,
	 * case-insensitive; no wildcard subdomain expansion).
	 *
	 * @param string $url The URL to check.
	 * @param string $appId The app whose config key to read.
	 * @param string $configKey The IAppConfig key holding the JSON
	 *                          array of allowed hostnames.
	 *
	 * @return bool True when the host passes the allow-list check.
	 *
	 * @spec openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function checkAllowList(string $url, string $appId, string $configKey): bool {
		$allowed = $this->configuredHosts(appId: $appId, configKey: $configKey);
		if ($allowed === null) {
			return true;
		}

		return $this->hostIsListed(url: $url, allowed: $allowed);
	}//end checkAllowList()

	/**
	 * The configured allow-list, or null when none is configured (an empty
	 * or blank value, invalid JSON or an empty array all mean "no list").
	 *
	 * @param string $appId     The app whose config key to read.
	 * @param string $configKey The IAppConfig key holding the JSON array.
	 *
	 * @return array<mixed>|null The decoded allow-list entries, or null.
	 */
	private function configuredHosts(string $appId, string $configKey): ?array {
		$raw = $this->appConfig->getValueString(
			app: $appId,
			key: $configKey,
			default: ''
		);

		if (trim(string: $raw) === '') {
			return null;
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (is_array(value: $decoded) === false || $decoded === []) {
			return null;
		}

		return $decoded;
	}//end configuredHosts()

	/**
	 * Whether the URL's host is on the allow-list, case-insensitively.
	 *
	 * @param string       $url     The URL to check.
	 * @param array<mixed> $allowed The allow-list entries; non-strings never match.
	 *
	 * @return bool True when the host is listed; false for a URL without a host.
	 */
	private function hostIsListed(string $url, array $allowed): bool {
		$host = parse_url(url: $url, component: PHP_URL_HOST);
		if (is_string(value: $host) === false || $host === '') {
			return false;
		}

		$needle = strtolower(string: $host);
		foreach ($allowed as $entry) {
			if (is_string(value: $entry) === true
				&& strtolower(string: $entry) === $needle
			) {
				return true;
			}
		}

		return false;
	}//end hostIsListed()
}//end class
