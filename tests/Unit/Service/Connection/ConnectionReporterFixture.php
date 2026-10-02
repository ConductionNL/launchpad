<?php

/**
 * A real ConnectionReporter wired to recording doubles.
 *
 * The caller tests use the REAL reporter rather than a mock of it, so a test
 * that sees an event also proves the mapping, the throttle and the host-only
 * message the reporter builds. Only the edges are doubled: the dispatcher
 * records, app config is an in-memory map, and the clock is a number the test
 * moves.
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

use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Builds a recording reporter inside a PHPUnit TestCase.
 */
trait ConnectionReporterFixture {

	/**
	 * Every event handed to the dispatcher, in order.
	 *
	 * @var array<int, Event>
	 */
	protected array $sentEvents = [];

	/**
	 * The app-config values, keyed by config key.
	 *
	 * @var array<string, string>
	 */
	protected array $configStore = [];

	/**
	 * The Unix time the clock answers.
	 *
	 * @var int
	 */
	protected int $now = 1_760_000_000;

	/**
	 * An IAppConfig double backed by {@see self::$configStore}.
	 *
	 * @return IAppConfig&MockObject
	 */
	protected function backedAppConfig(): IAppConfig {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->configStore[$key] ?? $default)
		);
		$appConfig->method('getValueInt')->willReturnCallback(
			static fn (string $app, string $key, int $default = 0): int => $default
		);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->configStore[$key] = $value;
				return true;
			}
		);
		$appConfig->method('deleteKey')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->configStore[$key]);
			}
		);

		return $appConfig;
	}//end backedAppConfig()

	/**
	 * The reporter as production builds it, recording what it sends.
	 *
	 * @param IAppConfig|null $appConfig The app config to share with the service under test, or null for a fresh backed one.
	 *
	 * @return ConnectionReporter
	 */
	protected function recordingReporter(?IAppConfig $appConfig = null): ConnectionReporter {
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(
			function (Event $event): void {
				$this->sentEvents[] = $event;
			}
		);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->now);

		return new ConnectionReporter(
			eventDispatcher: $dispatcher,
			appConfig: ($appConfig ?? $this->backedAppConfig()),
			timeFactory: $time,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);
	}//end recordingReporter()

	/**
	 * The recorded events as `[class short name, key, status, message]` rows.
	 *
	 * A refresh event has no status or message, so those read as ''.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
	 */
	protected function sentRows(): array {
		return array_map(
			static fn (Event $event): array => [
				substr(strrchr(get_class($event), '\\'), 1),
				(string) ($event->key ?? ''),
				(string) ($event->status ?? ''),
				(string) ($event->message ?? ''),
			],
			$this->sentEvents
		);
	}//end sentRows()
}//end trait
