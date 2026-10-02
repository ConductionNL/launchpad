<?php

/**
 * LaunchPad connection reporter.
 *
 * Tells integriq's connection registry what only LaunchPad can see about its
 * outside connections: what the last registry search, weather reading, feed
 * fetch, live tile fetch or health ping met, and when a registry save changed
 * the settings. Integriq owns the rows the Integrations page lists and works
 * out each status itself (hydra change connection-registry, design D4).
 * LaunchPad reports, and asks for a fresh resolve after a save.
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

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Service\WeatherService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends connection reports and refresh requests to integriq.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
 */
class ConnectionReporter {

	/**
	 * The app id integriq keys the rows by.
	 *
	 * @var string
	 */
	public const APP_ID = Application::APP_ID;

	/**
	 * Integriq's report event (ADR-041). Named by string so LaunchPad stays
	 * installable without integriq: the class only exists when integriq does.
	 *
	 * @var string
	 */
	public const STATUS_EVENT = 'OCA\Integriq\Event\ConnectionStatusReportedEvent';

	/**
	 * Integriq's refresh event. Named by string for the same reason.
	 *
	 * @var string
	 */
	public const REFRESH_EVENT = 'OCA\Integriq\Event\ConnectionRefreshRequestedEvent';

	/**
	 * The dashboard registry row.
	 *
	 * @var string
	 */
	public const KEY_REGISTRY = 'dashboard-registry';

	/**
	 * The weather provider row.
	 *
	 * @var string
	 */
	public const KEY_WEATHER = 'weather';

	/**
	 * The news feed family row.
	 *
	 * @var string
	 */
	public const KEY_NEWS_FEEDS = 'news-feeds';

	/**
	 * The calendar feed family row.
	 *
	 * @var string
	 */
	public const KEY_ICS_CALENDARS = 'ics-calendars';

	/**
	 * The live tile family row.
	 *
	 * @var string
	 */
	public const KEY_LIVE_TILES = 'live-tiles';

	/**
	 * The health ping family row.
	 *
	 * @var string
	 */
	public const KEY_HEALTH_PING = 'health-ping';

	/**
	 * The keys `lib/Settings/connections.json` declares, in declared order.
	 *
	 * A key outside this set is a caller's typo, not a new connection. A unit
	 * test keeps the two equal.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [
		self::KEY_REGISTRY,
		self::KEY_WEATHER,
		self::KEY_NEWS_FEEDS,
		self::KEY_ICS_CALENDARS,
		self::KEY_LIVE_TILES,
		self::KEY_HEALTH_PING,
	];

	/**
	 * The statuses integriq accepts in a report (design D6), `limited` included.
	 *
	 * @var array<int, string>
	 */
	public const STATUSES = ['configured', 'limited', 'unconfigured', 'simulated', 'unavailable', 'error'];

	/**
	 * How a message names the other side of each row whose calls are plain HTTP.
	 *
	 * @var array<string, string>
	 */
	public const CALL_NAMES = [
		self::KEY_WEATHER       => 'weather provider',
		self::KEY_NEWS_FEEDS    => 'news feed',
		self::KEY_ICS_CALENDARS => 'calendar feed',
		self::KEY_LIVE_TILES    => 'live tile source',
		self::KEY_HEALTH_PING   => 'health ping target',
	];

	/**
	 * The fail-closed allow-list behind each row that has one.
	 *
	 * @var array<string, string>
	 */
	public const ALLOW_LISTS = [
		self::KEY_LIVE_TILES  => LiveTileService::CONFIG_KEY_ALLOWED_HOSTS,
		self::KEY_HEALTH_PING => HealthPingService::CONFIG_KEY_ALLOWED_HOSTS,
	];

	/**
	 * App-config keys per connection whose save asks integriq to resolve again.
	 *
	 * The declared `requiredConfig`, plus the two keys that change what a
	 * registry search meets. A unit test keeps the declared keys inside this map.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const REFRESH_KEYS = [
		self::KEY_REGISTRY => [StoreService::CONFIG_URL, StoreService::CONFIG_REGISTER, StoreService::CONFIG_TOKEN],
	];

	/**
	 * Prefix of the app-config key that remembers the last report per connection.
	 *
	 * @var string
	 */
	public const MEMORY_KEY_PREFIX = 'connection_report_';

	/**
	 * Seconds after which the same status is reported again.
	 *
	 * @var int
	 */
	public const REPEAT_SECONDS = 3600;

	/**
	 * Seconds that must pass before a different status is reported.
	 *
	 * @var int
	 */
	public const CHANGE_SECONDS = 300;

	/**
	 * The pure outcome mapper.
	 *
	 * @var ConnectionObservations
	 */
	private readonly ConnectionObservations $observations;

	/**
	 * Constructor.
	 *
	 * @param IEventDispatcher $eventDispatcher Sends the integriq events (ADR-041).
	 * @param IAppConfig       $appConfig       Reads the registry address and allow-lists, and keeps the report memory.
	 * @param ITimeFactory     $timeFactory     Tells the time for the report memory.
	 * @param LoggerInterface  $logger          Records what could not be sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
		$this->observations = new ConnectionObservations();
	}//end __construct()

	/**
	 * Report what a dashboard registry search met.
	 *
	 * @param string $outcome         The search outcome, `GenericStoreService::OUTCOME_*`.
	 * @param bool   $engineAvailable Whether OpenRegister's store engine is available.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportRegistrySearch(string $outcome, bool $engineAvailable): bool {
		return $this->reportObserved(
			key: self::KEY_REGISTRY,
			observe: fn (): ?array => $this->observations->registrySearch(
				outcome: $outcome,
				engineAvailable: $engineAvailable,
				registryUrl: $this->appConfig->getValueString(self::APP_ID, StoreService::CONFIG_URL, '')
			)
		);
	}//end reportRegistrySearch()

	/**
	 * Report what one outbound HTTP call of a widget or tile met.
	 *
	 * @param string   $key        One of the keys in {@see self::CALL_NAMES}.
	 * @param string   $url        The address called. Only its host reaches the message.
	 * @param int|null $httpStatus The answer's HTTP status, or null when nothing answered.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportCall(string $key, string $url, ?int $httpStatus): bool {
		if (array_key_exists($key, self::CALL_NAMES) === false) {
			return false;
		}

		return $this->reportObserved(
			key: $key,
			observe: fn (): ?array => $this->observations->httpCall(
				name: self::CALL_NAMES[$key],
				url: $url,
				httpStatus: $httpStatus
			)
		);
	}//end reportCall()

	/**
	 * Report that a weather widget with a location found no provider URL.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportWeatherNotConfigured(): bool {
		return $this->reportObserved(
			key: self::KEY_WEATHER,
			observe: fn (): array => $this->observations->weatherNotConfigured(configKey: WeatherService::CONFIG_KEY_PROVIDER_URL)
		);
	}//end reportWeatherNotConfigured()

	/**
	 * Report that the weather provider URL is not http or https.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportWeatherInvalidUrl(): bool {
		return $this->reportObserved(
			key: self::KEY_WEATHER,
			observe: fn (): array => $this->observations->weatherInvalidUrl(configKey: WeatherService::CONFIG_KEY_PROVIDER_URL)
		);
	}//end reportWeatherInvalidUrl()

	/**
	 * Report that the weather provider answered without a readable temperature.
	 *
	 * @param string $url The address called. Only its host reaches the message.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportWeatherUnreadable(string $url): bool {
		return $this->reportObserved(
			key: self::KEY_WEATHER,
			observe: fn (): array => $this->observations->weatherUnreadable(url: $url)
		);
	}//end reportWeatherUnreadable()

	/**
	 * Report a fail-closed allow-list refusal, when the list holds no host.
	 *
	 * The list is read only after the class check, so an instance without
	 * integriq reads nothing extra.
	 *
	 * @param string $key One of the keys in {@see self::ALLOW_LISTS}.
	 *
	 * @return bool True when a report was sent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function reportAllowListRefusal(string $key): bool {
		if (array_key_exists($key, self::ALLOW_LISTS) === false) {
			return false;
		}

		$configKey = self::ALLOW_LISTS[$key];

		return $this->reportObserved(
			key: $key,
			observe: fn (): ?array => $this->observations->allowListRefused(
				configKey: $configKey,
				rawList: $this->appConfig->getValueString(self::APP_ID, $configKey, '')
			)
		);
	}//end reportAllowListRefusal()

	/**
	 * The HTTP status a failed call still carries, for {@see reportCall()}.
	 *
	 * Pure: reads, stores and sends nothing.
	 *
	 * @param Throwable $exception What the call threw.
	 *
	 * @return int|null The answer's HTTP status, or null when nothing answered.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	public function httpStatusOf(Throwable $exception): ?int {
		return $this->observations->httpStatusOf(exception: $exception);
	}//end httpStatusOf()

	/**
	 * Ask integriq to resolve every connection whose config keys a save wrote.
	 *
	 * Clears that connection's report memory too, so the next call reports at
	 * once instead of waiting out the window. The refresh is sent here, before
	 * any report that follows the save. Integriq reads the saved values itself
	 * and decides the status (design D6). Never throws.
	 *
	 * @param array<int, string> $savedKeys The app-config keys the save wrote.
	 *
	 * @return array<int, string> The connection keys a refresh was sent for.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-002-a-registry-settings-save-asks-integriq-to-look-again
	 */
	public function refreshFromSave(array $savedKeys): array {
		$eventClass = $this->resolveEventClass(eventClass: self::REFRESH_EVENT);
		if ($eventClass === null) {
			return [];
		}

		$refreshed = [];
		foreach (self::REFRESH_KEYS as $key => $configKeys) {
			if (array_intersect($configKeys, $savedKeys) === []) {
				continue;
			}

			$this->forget(key: $key);
			$sent = $this->send(
				key: $key,
				build: static fn (): object => new $eventClass(
					app: self::APP_ID,
					key: $key,
				)
			);
			if ($sent === true) {
				$refreshed[] = $key;
			}
		}

		return $refreshed;
	}//end refreshFromSave()

	/**
	 * The event class to instantiate, or null when integriq does not ship it.
	 *
	 * @param string $eventClass The fully qualified class name, without a leading backslash.
	 *
	 * @return string|null The class name to instantiate, or null when absent.
	 *
	 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-003-launchpad-reports-what-its-outbound-calls-met
	 */
	protected function resolveEventClass(string $eventClass): ?string {
		$qualified = '\\' . $eventClass;
		if (class_exists($qualified) === false) {
			return null;
		}

		return $qualified;
	}//end resolveEventClass()

	/**
	 * Observe, throttle and send one status report. Never throws.
	 *
	 * Without integriq the class check fails first, so nothing is read,
	 * stored, sent or logged.
	 *
	 * @param string                                         $key     One of {@see self::KEYS}.
	 * @param callable(): (array{0: string, 1: string}|null) $observe Works out the status and message, or null to report nothing.
	 *
	 * @return bool True when a report was sent.
	 */
	private function reportObserved(string $key, callable $observe): bool {
		$eventClass = $this->resolveEventClass(eventClass: self::STATUS_EVENT);
		if ($eventClass === null) {
			return false;
		}

		try {
			$observed = $observe();
			if ($observed === null) {
				return false;
			}

			[$status, $message] = $observed;

			$now = $this->timeFactory->getTime();
			if ($this->isDue(key: $key, status: $status, now: $now) === false) {
				return false;
			}

			$sent = $this->send(
				key: $key,
				build: static fn (): object => new $eventClass(
					app: self::APP_ID,
					key: $key,
					status: $status,
					message: $message,
				)
			);
			if ($sent === true) {
				$this->appConfig->setValueString(self::APP_ID, self::MEMORY_KEY_PREFIX . $key, $status . '|' . $now);
			}

			return $sent;
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: could not report a connection to integriq',
				['key' => $key, 'exception' => $e->getMessage()]
			);
			return false;
		}//end try
	}//end reportObserved()

	/**
	 * Whether the report memory allows a report with this status now.
	 *
	 * A different status waits five minutes after the last report, so two
	 * widgets that disagree cannot report on every page load. The same status
	 * reports again after an hour.
	 *
	 * @param string $key    The connection key.
	 * @param string $status The status the call observed.
	 * @param int    $now    The current Unix time.
	 *
	 * @return bool
	 */
	private function isDue(string $key, string $status, int $now): bool {
		$memory = $this->appConfig->getValueString(self::APP_ID, self::MEMORY_KEY_PREFIX . $key, '');
		$parts  = explode('|', $memory, 2);
		if (count($parts) !== 2 || ctype_digit($parts[1]) === false) {
			return true;
		}

		$elapsed = ($now - (int) $parts[1]);
		if ($parts[0] === $status) {
			return $elapsed >= self::REPEAT_SECONDS;
		}

		return $elapsed >= self::CHANGE_SECONDS;
	}//end isDue()

	/**
	 * Clear the report memory of one connection.
	 *
	 * @param string $key The connection key.
	 *
	 * @return void
	 */
	private function forget(string $key): void {
		try {
			$this->appConfig->deleteKey(self::APP_ID, self::MEMORY_KEY_PREFIX . $key);
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: could not clear a connection report memory',
				['key' => $key, 'exception' => $e->getMessage()]
			);
		}
	}//end forget()

	/**
	 * Build and dispatch one event, swallowing anything a listener throws.
	 *
	 * @param string             $key   The connection the event is about, for the log.
	 * @param callable(): object $build Builds the event.
	 *
	 * @return bool True when the event was dispatched without an exception.
	 */
	private function send(string $key, callable $build): bool {
		try {
			$event = $build();
			if (($event instanceof Event) === false) {
				return false;
			}

			$this->eventDispatcher->dispatchTyped($event);
			return true;
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: could not send a connection event to integriq',
				['key' => $key, 'exception' => $e->getMessage()]
			);
			return false;
		}
	}//end send()
}//end class
