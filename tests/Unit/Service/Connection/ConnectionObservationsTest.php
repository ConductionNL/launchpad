<?php

/**
 * ConnectionObservations unit tests.
 *
 * The mapper decides what the Integrations page says about each connection.
 * A wrong mapping makes a working connection read Error, or leaks part of a
 * URL an admin set into a row every admin reads.
 *
 * @category Tests
 * @package  Unit\Service\Connection
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

namespace Unit\Service\Connection;

use OCA\LaunchPad\Service\Connection\ConnectionObservations;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for ConnectionObservations.
 *
 * @covers \OCA\LaunchPad\Service\Connection\ConnectionObservations
 */
class ConnectionObservationsTest extends TestCase {

	/**
	 * The mapper under test.
	 *
	 * @var ConnectionObservations
	 */
	private ConnectionObservations $observations;

	/**
	 * Set up the mapper.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->observations = new ConnectionObservations();
	}//end setUp()

	/**
	 * Every registry outcome maps to its status, and an absent engine reads unavailable whatever the outcome.
	 *
	 * @return void
	 */
	public function testRegistryOutcomes(): void {
		$url = 'https://registry.example.nl/index.php';

		$this->assertSame(expected: ['configured', 'The dashboard registry at registry.example.nl answered the last search.'], actual: $this->observations->registrySearch(outcome: 'ok', engineAvailable: true, registryUrl: $url));
		$this->assertSame(expected: 'unconfigured', actual: $this->observations->registrySearch(outcome: 'not_configured', engineAvailable: true, registryUrl: '')[0]);
		$this->assertSame(expected: ['error', 'The last search could not reach the dashboard registry at registry.example.nl.'], actual: $this->observations->registrySearch(outcome: 'store_unreachable', engineAvailable: true, registryUrl: $url));
		$this->assertSame(expected: 'error', actual: $this->observations->registrySearch(outcome: 'store_invalid_response', engineAvailable: true, registryUrl: $url)[0]);
		$this->assertSame(expected: 'limited', actual: $this->observations->registrySearch(outcome: 'rate_limited', engineAvailable: true, registryUrl: $url)[0]);
		$this->assertNull(actual: $this->observations->registrySearch(outcome: 'unknown', engineAvailable: true, registryUrl: $url));
		$this->assertSame(
			expected: ['unavailable', 'OpenRegister is not enabled, so LaunchPad cannot reach a dashboard registry.'],
			actual: $this->observations->registrySearch(outcome: 'not_configured', engineAvailable: false, registryUrl: $url)
		);
	}//end testRegistryOutcomes()

	/**
	 * HTTP answers map to statuses; answers about one address map to nothing.
	 *
	 * @return void
	 */
	public function testHttpAnswers(): void {
		$url = 'https://feeds.example.nl/rss';

		$this->assertSame(expected: ['error', 'The last call to the news feed at feeds.example.nl got no answer.'], actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: null));
		$this->assertSame(expected: ['error', 'The news feed at feeds.example.nl refused the request (HTTP 401).'], actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: 401));
		$this->assertSame(expected: 'error', actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: 403)[0]);
		$this->assertSame(expected: ['limited', 'The news feed at feeds.example.nl limited the last call (HTTP 429).'], actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: 429));
		$this->assertSame(expected: ['error', 'The news feed at feeds.example.nl answered HTTP 503 on the last call.'], actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: 503));
		$this->assertSame(expected: ['configured', 'The news feed at feeds.example.nl answered the last call.'], actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: 204));

		foreach ([301, 302, 400, 404, 410, 500] as $aboutOneAddress) {
			$this->assertNull(actual: $this->observations->httpCall(name: 'news feed', url: $url, httpStatus: $aboutOneAddress), message: 'HTTP ' . $aboutOneAddress);
		}
	}//end testHttpAnswers()

	/**
	 * Only the host of an address reaches a message: no user info, path, query or key.
	 *
	 * @return void
	 */
	public function testOnlyTheHostReachesAMessage(): void {
		$secretUrl = 'https://user:secret@feeds.example.nl/private/path?token=abc&appid=KEY123#frag';

		$messages = [
			$this->observations->httpCall(name: 'news feed', url: $secretUrl, httpStatus: 503)[1],
			$this->observations->httpCall(name: 'weather provider', url: $secretUrl, httpStatus: null)[1],
			$this->observations->weatherUnreadable(url: $secretUrl)[1],
			$this->observations->registrySearch(outcome: 'store_unreachable', engineAvailable: true, registryUrl: $secretUrl)[1],
		];

		foreach ($messages as $message) {
			$this->assertStringContainsString(needle: 'feeds.example.nl', haystack: $message);
			foreach (['user', 'secret', 'private', 'path', 'token', 'abc', 'KEY123', 'frag'] as $leak) {
				$this->assertStringNotContainsString(needle: $leak, haystack: $message, message: $message);
			}
		}

		$this->assertSame(expected: ['error', 'The last call to the live tile source got no answer.'], actual: $this->observations->httpCall(name: 'live tile source', url: 'not a url', httpStatus: null));
	}//end testOnlyTheHostReachesAMessage()

	/**
	 * The weather messages name the key an admin sets.
	 *
	 * @return void
	 */
	public function testWeatherMessagesNameTheKey(): void {
		$this->assertSame(
			expected: ['unconfigured', 'A weather widget with a location found no provider URL. Set weather_provider_url with occ.'],
			actual: $this->observations->weatherNotConfigured(configKey: 'weather_provider_url')
		);
		$this->assertSame(expected: 'error', actual: $this->observations->weatherInvalidUrl(configKey: 'weather_provider_url')[0]);
		$this->assertStringContainsString(needle: 'weather_provider_url', haystack: $this->observations->weatherInvalidUrl(configKey: 'weather_provider_url')[1]);
	}//end testWeatherMessagesNameTheKey()

	/**
	 * A refusal says something only when the list holds no host.
	 *
	 * @return void
	 */
	public function testAllowListRefusals(): void {
		$this->assertNull(actual: $this->observations->allowListRefused(configKey: 'livetile_allowed_hosts', rawList: '["a.example.nl"]'));
		foreach (['', '[]', '{}', 'null', 'a.example.nl,b.example.nl', 'not json'] as $empty) {
			$this->assertSame(expected: 'unconfigured', actual: $this->observations->allowListRefused(configKey: 'livetile_allowed_hosts', rawList: $empty)[0] ?? null, message: '"' . $empty . '"');
		}
	}//end testAllowListRefusals()

	/**
	 * A failed call's HTTP status is read from the exception's response, when it has one.
	 *
	 * @return void
	 */
	public function testTheStatusOfAFailedCall(): void {
		$withResponse = new class('HTTP 503') extends RuntimeException {

			/**
			 * A Guzzle-like response carrier.
			 *
			 * @return object
			 */
			public function getResponse(): object {
				return new class {

					/**
					 * The status.
					 *
					 * @return int
					 */
					public function getStatusCode(): int {
						return 503;
					}//end getStatusCode()
				};
			}//end getResponse()
		};

		$this->assertSame(expected: 503, actual: $this->observations->httpStatusOf(exception: $withResponse));
		$this->assertNull(actual: $this->observations->httpStatusOf(exception: new RuntimeException('connection refused')));
	}//end testTheStatusOfAFailedCall()

	/**
	 * Every status the mapper can produce is one integriq accepts.
	 *
	 * @return void
	 */
	public function testEveryProducedStatusIsAContractStatus(): void {
		$produced = [
			$this->observations->registrySearch(outcome: 'ok', engineAvailable: false, registryUrl: '')[0],
			$this->observations->allowListRefused(configKey: 'k', rawList: '')[0],
			$this->observations->weatherNotConfigured(configKey: 'k')[0],
		];
		foreach ([null, 401, 429, 503, 200] as $status) {
			$produced[] = $this->observations->httpCall(name: 'x', url: 'https://x.example', httpStatus: $status)[0];
		}

		$this->assertSame(expected: [], actual: array_diff($produced, ConnectionReporter::STATUSES));
	}//end testEveryProducedStatusIsAContractStatus()
}//end class
