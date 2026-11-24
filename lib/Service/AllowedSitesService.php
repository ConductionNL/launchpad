<?php

/**
 * AllowedSitesService
 *
 * One admin list of the outside websites launchpad may embed or fetch from,
 * each host with its purpose (REQ-ASET-021).
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
use OCA\LaunchPad\Exception\InvalidAllowedSiteException;
use OCP\IAppConfig;

/**
 * Reads and stores `allowed_sites`: a JSON list of `{host, embed, fetch}`.
 *
 * Embed allows a host for the embedded page widget and for images from
 * outside; fetch allows it for live tiles, health pings and external calendar
 * feeds. Reading is FAIL-CLOSED: an unset, empty or unparseable list allows
 * nothing, and an entry for one purpose never allows the other.
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
class AllowedSitesService {

	/**
	 * IAppConfig key: the JSON list of allowed sites.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'allowed_sites';

	/**
	 * Purpose: embed a page or show an image from the host.
	 *
	 * @var string
	 */
	public const PURPOSE_EMBED = 'embed';

	/**
	 * Purpose: fetch data from the host (live tile, health ping, calendar feed).
	 *
	 * @var string
	 */
	public const PURPOSE_FETCH = 'fetch';

	/**
	 * A bare lower-case hostname: no scheme, path, port or wildcard.
	 *
	 * @var string
	 */
	private const HOST_PATTERN = '~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the list.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * The stored sites, each `{host, embed, fetch}`. Entries that are not a
	 * valid site are left out; empty when the list is unset or unparseable.
	 *
	 * @return list<array{host: string, embed: bool, fetch: bool}>
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function getSites(): array {
		$raw     = $this->appConfig->getValueString(app: Application::APP_ID, key: self::CONFIG_KEY, default: '');
		$decoded = json_decode(json: $raw, associative: true);
		if (is_array(value: $decoded) === false) {
			return [];
		}

		$sites = [];
		foreach ($decoded as $entry) {
			$site = $this->readStoredSite(entry: $entry);
			if ($site !== null) {
				$sites[$site['host']] = $this->mergeSite(existing: $sites[$site['host']] ?? null, site: $site);
			}
		}

		return array_values(array: $sites);
	}//end getSites()

	/**
	 * The hosts allowed for one purpose, lower case.
	 *
	 * @param string $purpose `embed` or `fetch`; anything else allows nothing.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function hostsFor(string $purpose): array {
		if (in_array(needle: $purpose, haystack: [self::PURPOSE_EMBED, self::PURPOSE_FETCH], strict: true) === false) {
			return [];
		}

		$hosts = [];
		foreach ($this->getSites() as $site) {
			if ($site[$purpose] === true) {
				$hosts[] = $site['host'];
			}
		}

		return $hosts;
	}//end hostsFor()

	/**
	 * Whether a URL's host is allowed for a purpose (case-insensitive, exact).
	 *
	 * @param string $url     The URL to check.
	 * @param string $purpose `embed` or `fetch`.
	 *
	 * @return bool True only when the host is on the list for that purpose.
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function permits(string $url, string $purpose): bool {
		$host = parse_url(url: $url, component: PHP_URL_HOST);
		if (is_string(value: $host) === false || $host === '') {
			return false;
		}

		return in_array(needle: strtolower(string: $host), haystack: $this->hostsFor(purpose: $purpose), strict: true);
	}//end permits()

	/**
	 * Replace the list. Every host is normalised (lower case, scheme, path and
	 * port dropped); a host listed twice keeps both purposes.
	 *
	 * @param mixed $sites The submitted list of `{host, embed, fetch}`.
	 *
	 * @return list<array{host: string, embed: bool, fetch: bool}> The stored list.
	 *
	 * @throws InvalidAllowedSiteException When an entry is not a site with a host and a purpose.
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function setSites(mixed $sites): array {
		if (is_array(value: $sites) === false) {
			throw new InvalidAllowedSiteException(message: 'The allowed websites must be a list.');
		}

		$clean = [];
		foreach ($sites as $entry) {
			$site = $this->readSubmittedSite(entry: $entry);
			if ($site !== null) {
				$clean[$site['host']] = $this->mergeSite(existing: $clean[$site['host']] ?? null, site: $site);
			}
		}

		$clean = array_values(array: $clean);
		$this->store(sites: $clean);

		return $clean;
	}//end setSites()

	/**
	 * Add hosts for one purpose to the stored list, keeping what is there.
	 * Entries that do not normalise to a host are skipped.
	 *
	 * @param string[] $hosts   The hosts to add.
	 * @param string   $purpose `embed` or `fetch`.
	 *
	 * @return int The number of hosts that gained the purpose.
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function addHosts(array $hosts, string $purpose): int {
		$sites = [];
		foreach ($this->getSites() as $site) {
			$sites[$site['host']] = $site;
		}

		$added = 0;
		foreach ($hosts as $raw) {
			if (is_string(value: $raw) === false) {
				continue;
			}

			$host = self::normaliseHost(raw: $raw);
			if ($host === null || ($sites[$host][$purpose] ?? false) === true) {
				continue;
			}

			$sites[$host] = $this->mergeSite(
				existing: $sites[$host] ?? null,
				site: [
					'host'  => $host,
					'embed' => $purpose === self::PURPOSE_EMBED,
					'fetch' => $purpose === self::PURPOSE_FETCH,
				]
			);
			$added++;
		}

		if ($added > 0) {
			$this->store(sites: array_values(array: $sites));
		}

		return $added;
	}//end addHosts()

	/**
	 * Normalise an entered host: trimmed, lower case, without scheme, path,
	 * query or port. Null when what is left is not a host name.
	 *
	 * @param string $raw The entered value, e.g. `https://App.PowerBI.com/x`.
	 *
	 * @return string|null The host, e.g. `app.powerbi.com`.
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public static function normaliseHost(string $raw): ?string {
		$value = strtolower(string: trim(string: $raw));
		$value = (string)preg_replace(pattern: '~^[a-z][a-z0-9+.-]*://~', replacement: '', subject: $value);
		$value = (string)preg_replace(pattern: '~[/?#].*$~', replacement: '', subject: $value);
		$value = (string)preg_replace(pattern: '~:[0-9]{1,5}$~', replacement: '', subject: $value);

		if ($value === '' || preg_match(pattern: self::HOST_PATTERN, subject: $value) !== 1) {
			return null;
		}

		return $value;
	}//end normaliseHost()

	/**
	 * A stored entry as a site, or null when it is not one.
	 *
	 * @param mixed $entry The decoded entry.
	 *
	 * @return array{host: string, embed: bool, fetch: bool}|null
	 */
	private function readStoredSite(mixed $entry): ?array {
		if (is_array(value: $entry) === false || is_string(value: $entry['host'] ?? null) === false) {
			return null;
		}

		$host  = self::normaliseHost(raw: $entry['host']);
		$embed = ($entry['embed'] ?? false) === true;
		$fetch = ($entry['fetch'] ?? false) === true;
		if ($host === null || ($embed === false && $fetch === false)) {
			return null;
		}

		return ['host' => $host, 'embed' => $embed, 'fetch' => $fetch];
	}//end readStoredSite()

	/**
	 * A submitted entry as a site; null for an entry with a blank host.
	 *
	 * @param mixed $entry The submitted entry.
	 *
	 * @return array{host: string, embed: bool, fetch: bool}|null
	 *
	 * @throws InvalidAllowedSiteException When the entry is not a site with a host and a purpose.
	 */
	private function readSubmittedSite(mixed $entry): ?array {
		if (is_array(value: $entry) === false || is_string(value: $entry['host'] ?? null) === false) {
			throw new InvalidAllowedSiteException(message: 'Every allowed website needs a host and a purpose.');
		}

		if (trim(string: $entry['host']) === '') {
			return null;
		}

		$host = self::normaliseHost(raw: $entry['host']);
		if ($host === null) {
			throw new InvalidAllowedSiteException(
				message: sprintf('"%s" is not a host name. Enter a host such as www.example.com.', trim(string: $entry['host']))
			);
		}

		$embed = ($entry['embed'] ?? false) === true;
		$fetch = ($entry['fetch'] ?? false) === true;
		if ($embed === false && $fetch === false) {
			throw new InvalidAllowedSiteException(
				message: sprintf('Choose whether %s may be embedded, fetched from, or both.', $host)
			);
		}

		return ['host' => $host, 'embed' => $embed, 'fetch' => $fetch];
	}//end readSubmittedSite()

	/**
	 * Merge two entries for the same host: either purpose is kept.
	 *
	 * @param array{host: string, embed: bool, fetch: bool}|null $existing The entry so far.
	 * @param array{host: string, embed: bool, fetch: bool}      $site     The entry to add.
	 *
	 * @return array{host: string, embed: bool, fetch: bool}
	 */
	private function mergeSite(?array $existing, array $site): array {
		if ($existing === null) {
			return $site;
		}

		return [
			'host'  => $site['host'],
			'embed' => $existing['embed'] || $site['embed'],
			'fetch' => $existing['fetch'] || $site['fetch'],
		];
	}//end mergeSite()

	/**
	 * Write the list.
	 *
	 * @param list<array{host: string, embed: bool, fetch: bool}> $sites The sites.
	 *
	 * @return void
	 */
	private function store(array $sites): void {
		$this->appConfig->setValueString(
			app: Application::APP_ID,
			key: self::CONFIG_KEY,
			value: json_encode(value: $sites, flags: JSON_THROW_ON_ERROR)
		);
	}//end store()
}//end class
