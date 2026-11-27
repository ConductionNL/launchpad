<?php

/**
 * FoundServicesService
 *
 * The web services an administrator allowed for discovery (REQ-TILE-044):
 * the administrator names each service and gives its address, LaunchPad asks
 * each address whether it answers, and the Add tiles dialog lists the ones
 * that answered with their state, port and when they were first found.
 *
 * Only the addresses on this list are ever asked. LaunchPad does not scan
 * ports or hosts: discovery is the administrator's own list, checked with
 * one GET per address, no redirects and short timeouts, and the answers are
 * cached for five minutes.
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
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Throwable;

/**
 * Stores the administrator's list of services to discover and reports which answer.
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */
class FoundServicesService {

	/**
	 * App config key of the administrator's list, `[{name, url}]`.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'discoverable_services';

	/**
	 * App config key of when each address first answered, `{url: unix time}`.
	 *
	 * @var string
	 */
	public const FOUND_KEY = 'discoverable_services_found';

	/**
	 * The most services the list holds.
	 *
	 * @var integer
	 */
	public const MAX_SERVICES = 50;

	/**
	 * The longest service name.
	 *
	 * @var integer
	 */
	public const MAX_NAME_LENGTH = 80;

	/**
	 * How long one answer is kept, in seconds.
	 *
	 * @var integer
	 */
	public const CACHE_TTL_SECONDS = 300;

	/**
	 * Connect timeout per address, in seconds.
	 *
	 * @var integer
	 */
	private const CONNECT_TIMEOUT = 2;

	/**
	 * Request timeout per address, in seconds.
	 *
	 * @var integer
	 */
	private const REQUEST_TIMEOUT = 3;

	/**
	 * Lazily resolved answer cache.
	 *
	 * @var ICache|null
	 */
	private ?ICache $cache = null;

	/**
	 * Constructor.
	 *
	 * @param IAppConfig     $appConfig     Stores the list and the found times.
	 * @param IClientService $clientService Asks each address.
	 * @param ICacheFactory  $cacheFactory  Keeps the answers for five minutes.
	 * @param ITimeFactory   $timeFactory   The clock.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IClientService $clientService,
		private readonly ICacheFactory $cacheFactory,
		private readonly ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * The administrator's list.
	 *
	 * @return array<int, array{name: string, url: string}> The services, in the order stored.
	 *
	 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	public function getServices(): array {
		$services = [];
		foreach ($this->readJson(key: self::CONFIG_KEY) as $entry) {
			if (is_array($entry) === false) {
				continue;
			}

			$name = trim((string)($entry['name'] ?? ''));
			$url = self::normaliseUrl(raw: (string)($entry['url'] ?? ''));
			if ($name !== '' && $url !== null) {
				$services[] = ['name' => $name, 'url' => $url];
			}
		}

		return $services;
	}//end getServices()

	/**
	 * Replace the administrator's list.
	 *
	 * @param mixed $services The services, each `{name, url}`.
	 *
	 * @return array<int, array{name: string, url: string}> The list as stored.
	 *
	 * @throws InvalidArgumentException When the list or an entry is not valid; the message says why.
	 *
	 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	public function setServices(mixed $services): array {
		if (is_array($services) === false || array_is_list($services) === false) {
			throw new InvalidArgumentException('The services must be a list.');
		}

		if (count($services) > self::MAX_SERVICES) {
			throw new InvalidArgumentException('At most '.self::MAX_SERVICES.' services can be listed.');
		}

		$stored = [];
		$seen = [];
		foreach ($services as $entry) {
			$service = $this->readSubmitted(entry: $entry);
			if (isset($seen[$service['url']]) === true) {
				continue;
			}

			$seen[$service['url']] = true;
			$stored[] = $service;
		}

		$this->appConfig->setValueString(
			app: Application::APP_ID,
			key: self::CONFIG_KEY,
			value: json_encode($stored, JSON_THROW_ON_ERROR)
		);

		// A found time belongs to an address on the list; drop the others.
		$found = array_intersect_key($this->readJson(key: self::FOUND_KEY), $seen);
		$this->writeFound(found: $found);

		return $stored;
	}//end setServices()

	/**
	 * The listed services that answered, with their state, port and when they were first found.
	 *
	 * @return array<int, array{name: string, url: string, port: int, state: string, foundAt: int}> The found services.
	 *
	 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	public function found(): array {
		$services = $this->getServices();
		if ($services === []) {
			return [];
		}

		$found = $this->readJson(key: self::FOUND_KEY);
		$changed = false;
		$rows = [];
		foreach ($services as $service) {
			$state = $this->stateOf(url: $service['url']);
			if ($state === 'running' && isset($found[$service['url']]) === false) {
				$found[$service['url']] = $this->timeFactory->getTime();
				$changed = true;
			}

			if (isset($found[$service['url']]) === false) {
				continue;
			}

			$rows[] = [
				'name'    => $service['name'],
				'url'     => $service['url'],
				'port'    => self::portOf(url: $service['url']),
				'state'   => $state,
				'foundAt' => (int)$found[$service['url']],
			];
		}//end foreach

		if ($changed === true) {
			$this->writeFound(found: $found);
		}

		return $rows;
	}//end found()

	/**
	 * An address as stored: http or https with a host, no user info, no fragment, no trailing slash.
	 *
	 * @param string $raw The address as typed.
	 *
	 * @return string|null The address, or null when it is not one.
	 *
	 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	public static function normaliseUrl(string $raw): ?string {
		$parts = parse_url(trim($raw));
		if (is_array($parts) === false) {
			return null;
		}

		$scheme = strtolower((string)($parts['scheme'] ?? ''));
		$host = strtolower((string)($parts['host'] ?? ''));
		if (in_array(needle: $scheme, haystack: ['http', 'https'], strict: true) === false || $host === ''
			|| isset($parts['user']) === true || isset($parts['pass']) === true
		) {
			return null;
		}

		$url = $scheme.'://'.$host;
		if (isset($parts['port']) === true) {
			$url .= ':'.(int)$parts['port'];
		}

		$url .= rtrim((string)($parts['path'] ?? ''), '/');
		if (isset($parts['query']) === true) {
			$url .= '?'.$parts['query'];
		}

		return $url;
	}//end normaliseUrl()

	/**
	 * The port an address answers on: the one it names, else its scheme's.
	 *
	 * @param string $url A normalised address.
	 *
	 * @return integer The port.
	 *
	 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
	 */
	public static function portOf(string $url): int {
		$parts = parse_url($url);
		if (is_array($parts) === true && isset($parts['port']) === true) {
			return (int)$parts['port'];
		}

		if (is_array($parts) === true && strtolower((string)($parts['scheme'] ?? '')) === 'https') {
			return 443;
		}

		return 80;
	}//end portOf()

	/**
	 * Read one submitted entry.
	 *
	 * @param mixed $entry The entry.
	 *
	 * @return array{name: string, url: string} The entry as stored.
	 *
	 * @throws InvalidArgumentException When it has no name or no web address.
	 */
	private function readSubmitted(mixed $entry): array {
		if (is_array($entry) === false) {
			throw new InvalidArgumentException('Every service needs a name and a web address.');
		}

		$name = trim((string)($entry['name'] ?? ''));
		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new InvalidArgumentException('Every service needs a name of at most '.self::MAX_NAME_LENGTH.' characters.');
		}

		$url = self::normaliseUrl(raw: (string)($entry['url'] ?? ''));
		if ($url === null) {
			throw new InvalidArgumentException('"'.(string)($entry['url'] ?? '').'" is not a web address starting with http:// or https://.');
		}

		return ['name' => $name, 'url' => $url];
	}//end readSubmitted()

	/**
	 * Whether an address answers now: `running` for any HTTP answer below 500, else `not_responding`.
	 *
	 * @param string $url A listed address.
	 *
	 * @return string `running` or `not_responding`.
	 */
	private function stateOf(string $url): string {
		$cache = $this->getCache();
		$key = 'state_'.sha1($url);
		$cached = $cache?->get($key);
		if (is_string($cached) === true) {
			return $cached;
		}

		$state = 'not_responding';
		try {
			$response = $this->clientService->newClient()->get(
				uri: $url,
				options: [
					'connect_timeout' => self::CONNECT_TIMEOUT,
					'timeout'         => self::REQUEST_TIMEOUT,
					'http_errors'     => false,
					'allow_redirects' => false,
					// The administrator listed this address on purpose; services on
					// the server itself (localhost:3000) must be reachable.
					'nextcloud'       => ['allow_local_address' => true],
				]
			);
			if ((int)$response->getStatusCode() < 500) {
				$state = 'running';
			}
		} catch (Throwable) {
			$state = 'not_responding';
		}

		$cache?->set($key, $state, self::CACHE_TTL_SECONDS);
		return $state;
	}//end stateOf()

	/**
	 * Read a JSON app config value as an array.
	 *
	 * @param string $key The key.
	 *
	 * @return array<mixed> The value, or an empty array.
	 */
	private function readJson(string $key): array {
		$raw = $this->appConfig->getValueString(app: Application::APP_ID, key: $key, default: '[]');
		$decoded = json_decode($raw, true);
		if (is_array($decoded) === true) {
			return $decoded;
		}

		return [];
	}//end readJson()

	/**
	 * Store when each address first answered.
	 *
	 * @param array<mixed> $found `{url: unix time}`.
	 *
	 * @return void
	 */
	private function writeFound(array $found): void {
		$this->appConfig->setValueString(
			app: Application::APP_ID,
			key: self::FOUND_KEY,
			value: json_encode((object)$found, JSON_THROW_ON_ERROR)
		);
	}//end writeFound()

	/**
	 * Lazily resolve the distributed cache, or null when there is none.
	 *
	 * @return ICache|null The cache.
	 */
	private function getCache(): ?ICache {
		if ($this->cache !== null) {
			return $this->cache;
		}

		try {
			$this->cache = $this->cacheFactory->createDistributed(prefix: 'launchpad_found_services_');
		} catch (Throwable) {
			$this->cache = null;
		}

		return $this->cache;
	}//end getCache()
}//end class
