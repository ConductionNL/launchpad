<?php

/**
 * LaunchPad connection observations.
 *
 * Turns an outcome LaunchPad already has, such as a registry search outcome or
 * a feed host's HTTP status, into the status and message integriq's connection
 * registry shows (hydra change connection-registry, design D4 and D6). Pure: it
 * holds no state, reads nothing and sends nothing, so every mapping is testable
 * without a double.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\Connection
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Connection;

use Throwable;

/**
 * Maps call outcomes to connection statuses and messages.
 *
 * Every method answers `[status, message]`, or null when the outcome says
 * nothing about the connection (it is about one address, one widget or one
 * user) and so must not be reported.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
 */
class ConnectionObservations {

	/**
	 * HTTP statuses that say the other side refused the request.
	 *
	 * @var array<int, int>
	 */
	public const REFUSED_STATUSES = [401, 403];

	/**
	 * The HTTP status that says the other side limited the request.
	 *
	 * @var int
	 */
	public const RATE_LIMITED_STATUS = 429;

	/**
	 * HTTP statuses that say a gateway could not reach the other side.
	 *
	 * A 500 is left out, because it is often about one request.
	 *
	 * @var array<int, int>
	 */
	public const GATEWAY_ERROR_STATUSES = [502, 503, 504];

	/**
	 * What a dashboard registry search outcome says about the registry.
	 *
	 * The outcome values are OpenRegister `GenericStoreService::OUTCOME_*`.
	 *
	 * @param string $outcome         The search outcome.
	 * @param bool   $engineAvailable Whether OpenRegister's store engine answered the container.
	 * @param string $registryUrl     The saved `registry_url`, for the host in the message.
	 *
	 * @return array{0: string, 1: string}|null The status and message, or null for an unknown outcome.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function registrySearch(string $outcome, bool $engineAvailable, string $registryUrl): ?array {
		if ($engineAvailable === false) {
			return ['unavailable', 'OpenRegister is not enabled, so LaunchPad cannot reach a dashboard registry.'];
		}

		$registry = $this->withHost(name: 'dashboard registry', url: $registryUrl);

		return match ($outcome) {
			'ok' => ['configured', ucfirst($registry) . ' answered the last search.'],
			'not_configured' => [
				'unconfigured',
				'No registry address is set. Set the registry URL on the Sharing tab of the LaunchPad settings.',
			],
			'store_unreachable' => ['error', 'The last search could not reach ' . $registry . '.'],
			'store_invalid_response' => ['error', ucfirst($registry) . ' answered, but not with a list of templates.'],
			'rate_limited' => ['limited', ucfirst($registry) . ' limited the last search.'],
			default => null,
		};
	}//end registrySearch()

	/**
	 * What one outbound HTTP call says about the connection behind it.
	 *
	 * @param string   $name       How the message names the other side, such as `news feed`.
	 * @param string   $url        The address called. Only its host reaches the message.
	 * @param int|null $httpStatus The answer's HTTP status, or null when nothing answered.
	 *
	 * @return array{0: string, 1: string}|null The status and message, or null when the answer is about one address.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function httpCall(string $name, string $url, ?int $httpStatus): ?array {
		$target = $this->withHost(name: $name, url: $url);

		if ($httpStatus === null) {
			return ['error', 'The last call to ' . $target . ' got no answer.'];
		}

		if (in_array($httpStatus, self::REFUSED_STATUSES, true) === true) {
			return ['error', ucfirst($target) . ' refused the request (HTTP ' . $httpStatus . ').'];
		}

		if ($httpStatus === self::RATE_LIMITED_STATUS) {
			return ['limited', ucfirst($target) . ' limited the last call (HTTP ' . $httpStatus . ').'];
		}

		if (in_array($httpStatus, self::GATEWAY_ERROR_STATUSES, true) === true) {
			return ['error', ucfirst($target) . ' answered HTTP ' . $httpStatus . ' on the last call.'];
		}

		if ($httpStatus >= 200 && $httpStatus < 300) {
			return ['configured', ucfirst($target) . ' answered the last call.'];
		}

		return null;
	}//end httpCall()

	/**
	 * A weather widget with a location found no provider URL.
	 *
	 * @param string $configKey The app-config key that holds the provider URL.
	 *
	 * @return array{0: string, 1: string}
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function weatherNotConfigured(string $configKey): array {
		return [
			'unconfigured',
			'A weather widget with a location found no provider URL. Set ' . $configKey . ' with occ.',
		];
	}//end weatherNotConfigured()

	/**
	 * The weather provider URL does not start with http or https, so nothing was called.
	 *
	 * @param string $configKey The app-config key that holds the provider URL.
	 *
	 * @return array{0: string, 1: string}
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function weatherInvalidUrl(string $configKey): array {
		return [
			'error',
			'The weather provider URL does not start with http or https. Set ' . $configKey . ' with occ.',
		];
	}//end weatherInvalidUrl()

	/**
	 * The weather provider answered, but not with a reading LaunchPad can read.
	 *
	 * @param string $url The address called. Only its host reaches the message.
	 *
	 * @return array{0: string, 1: string}
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function weatherUnreadable(string $url): array {
		return [
			'error',
			ucfirst($this->withHost(name: 'weather provider', url: $url)) . ' answered, but not with a temperature LaunchPad can read.',
		];
	}//end weatherUnreadable()

	/**
	 * What a fail-closed allow-list refusal says about the connection.
	 *
	 * A list that holds at least one host refused one widget's address, which
	 * says nothing about the instance. A list that holds no host refuses
	 * every address, and that is what the admin needs to know.
	 *
	 * @param string $configKey The app-config key that holds the list.
	 * @param string $rawList   The value stored under that key.
	 *
	 * @return array{0: string, 1: string}|null The status and message, or null when the list holds a host.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function allowListRefused(string $configKey, string $rawList): ?array {
		$decoded = json_decode($rawList, true);
		if (is_array($decoded) === true && $decoded !== []) {
			return null;
		}

		return [
			'unconfigured',
			$configKey . ' holds no JSON list of hosts, so LaunchPad refused the last call. Set it with occ.',
		];
	}//end allowListRefused()

	/**
	 * The HTTP status a failed call still carries, or null when nothing answered.
	 *
	 * Nextcloud's HTTP client throws on a 4xx or 5xx answer unless the caller
	 * turns that off. Guzzle's request exceptions keep that answer, and a
	 * connection failure has none.
	 *
	 * @param Throwable $exception What the call threw.
	 *
	 * @return int|null The answer's HTTP status, or null.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function httpStatusOf(Throwable $exception): ?int {
		if (method_exists($exception, 'getResponse') === false) {
			return null;
		}

		$response = $exception->getResponse();
		if (is_object($response) === false || method_exists($response, 'getStatusCode') === false) {
			return null;
		}

		return (int) $response->getStatusCode();
	}//end httpStatusOf()

	/**
	 * Name a thing with the host of its URL, when the URL has one.
	 *
	 * Only the host leaves the URL: a path, a query or user info can carry a
	 * secret such as a feed token or a weather API key, and every admin reads
	 * the row.
	 *
	 * @param string $name The plain name, without an article.
	 * @param string $url  The URL to take the host from.
	 *
	 * @return string `the {name}`, followed by `at {host}` when there is a host.
	 */
	private function withHost(string $name, string $url): string {
		$host = parse_url(trim($url), PHP_URL_HOST);
		if (is_string($host) === false || $host === '') {
			return 'the ' . $name;
		}

		return 'the ' . $name . ' at ' . $host;
	}//end withHost()
}//end class
