<?php

/**
 * ConnectionReporterWeatherTest
 *
 * Pins the connection reports ConnectionReporterTest leaves open: the two
 * weather failures, a report memory that cannot be cleared on a registry
 * save, a resolved event class that is not a Nextcloud event, and the HTTP
 * status read from an exception without a usable response.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Connection
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Connection;

use OCA\Integriq\Event\ConnectionRefreshRequestedEvent;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Weather reports, memory clearing and event guards of the reporter.
 *
 * @covers \OCA\LaunchPad\Service\Connection\ConnectionReporter
 * @covers \OCA\LaunchPad\Service\Connection\ConnectionObservations
 */
class ConnectionReporterWeatherTest extends TestCase {
	use ConnectionReporterFixture;

	/**
	 * Fresh recorded state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->sentEvents = [];
		$this->configStore = [];
	}//end setUp()

	/**
	 * A provider URL without http(s) is an error that names the config key.
	 *
	 * @return void
	 */
	public function testAnInvalidWeatherUrlIsReported(): void {
		$this->assertTrue($this->recordingReporter()->reportWeatherInvalidUrl());

		$this->assertSame(
			[[
				'ConnectionStatusReportedEvent',
				'weather',
				'error',
				'The weather provider URL does not start with http or https. Set weather_provider_url with occ.',
			]],
			$this->sentRows()
		);
		$this->assertSame('error|' . $this->now, $this->configStore['connection_report_weather']);
	}//end testAnInvalidWeatherUrlIsReported()

	/**
	 * An unreadable answer is an error naming only the provider's host; a
	 * second one inside the window is not sent again.
	 *
	 * @return void
	 */
	public function testAnUnreadableWeatherAnswerIsReportedOncePerWindow(): void {
		$reporter = $this->recordingReporter();

		$this->assertTrue($reporter->reportWeatherUnreadable(url: 'https://api.weather.example/v1?key=secret&lat=52'));
		$this->assertFalse($reporter->reportWeatherUnreadable(url: 'https://api.weather.example/v1?key=secret&lat=53'));

		$this->assertSame(
			[[
				'ConnectionStatusReportedEvent',
				'weather',
				'error',
				'The weather provider at api.weather.example answered, but not with a temperature LaunchPad can read.',
			]],
			$this->sentRows()
		);
	}//end testAnUnreadableWeatherAnswerIsReportedOncePerWindow()

	/**
	 * A memory that cannot be cleared is logged, and the refresh is still sent.
	 *
	 * @return void
	 */
	public function testAnUnclearableMemoryStillSendsTheRefresh(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('deleteKey')->willThrowException(new RuntimeException('config locked'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				'LaunchPad: could not clear a connection report memory',
				['key' => 'dashboard-registry', 'exception' => 'config locked']
			);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ConnectionRefreshRequestedEvent::class));

		$reporter = new ConnectionReporter(
			eventDispatcher: $dispatcher,
			appConfig: $appConfig,
			timeFactory: $this->createMock(ITimeFactory::class),
			logger: $logger,
		);

		$saved = [ConnectionReporter::REFRESH_KEYS[ConnectionReporter::KEY_REGISTRY][0]];
		$this->assertSame(['dashboard-registry'], $reporter->refreshFromSave(savedKeys: $saved));
	}//end testAnUnclearableMemoryStillSendsTheRefresh()

	/**
	 * A resolved class that is not a Nextcloud event is quietly not
	 * dispatched: no attempt, no warning, no report memory.
	 *
	 * @return void
	 */
	public function testANonEventClassIsNotDispatched(): void {
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');
		$appConfig = $this->backedAppConfig();
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn($this->now);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('warning');

		$reporter = new class ($dispatcher, $appConfig, $time, $logger) extends ConnectionReporter {
			/**
			 * Resolve every event to a plain value object.
			 *
			 * @param string $eventClass The class asked for.
			 *
			 * @return string|null
			 */
			protected function resolveEventClass(string $eventClass): ?string {
				return NotAnEvent::class;
			}
		};

		$this->assertFalse($reporter->reportWeatherInvalidUrl());
		$this->assertSame([], $reporter->refreshFromSave(savedKeys: [ConnectionReporter::REFRESH_KEYS[ConnectionReporter::KEY_REGISTRY][0]]));
		$this->assertSame([], $this->configStore);
	}//end testANonEventClassIsNotDispatched()

	/**
	 * Only a response object with a status code yields a status.
	 *
	 * @return void
	 */
	public function testHttpStatusNeedsAResponseWithAStatusCode(): void {
		$withResponse = static fn (mixed $response): RuntimeException => new class ('x', $response) extends RuntimeException {
			/**
			 * Constructor.
			 *
			 * @param string $message  The message.
			 * @param mixed  $response The response.
			 */
			public function __construct(string $message, private mixed $response) {
				parent::__construct($message);
			}

			/**
			 * The response.
			 *
			 * @return mixed
			 */
			public function getResponse(): mixed {
				return $this->response;
			}
		};
		$status = new class {
			/**
			 * The status code.
			 *
			 * @return string
			 */
			public function getStatusCode(): string {
				return '503';
			}
		};
		$reporter = $this->recordingReporter();

		$this->assertSame(
			[null, null, null, 503],
			[
				$reporter->httpStatusOf(new RuntimeException('plain')),
				$reporter->httpStatusOf($withResponse(null)),
				$reporter->httpStatusOf($withResponse(new \stdClass())),
				$reporter->httpStatusOf($withResponse($status)),
			]
		);
	}//end testHttpStatusNeedsAResponseWithAStatusCode()
}//end class

/**
 * A value object built like an integriq event but not a Nextcloud event.
 */
class NotAnEvent {
	/**
	 * Constructor.
	 *
	 * @param string      $app     The app id.
	 * @param string      $key     The connection key.
	 * @param string|null $status  The status.
	 * @param string|null $message The message.
	 */
	public function __construct(
		public string $app,
		public string $key,
		public ?string $status = null,
		public ?string $message = null,
	) {
	}
}//end class
