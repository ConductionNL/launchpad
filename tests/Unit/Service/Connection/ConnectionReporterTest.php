<?php

/**
 * ConnectionReporter unit tests.
 *
 * The reporter tells integriq's connection registry what LaunchPad's calls
 * met, and asks integriq to resolve a connection again after a save. Every
 * test guards one way it could quietly stop telling the truth: reporting on
 * every page load, never reporting a change, reporting a key integriq refuses,
 * turning a listener's failure into a failed widget, or touching app config
 * when integriq is not installed.
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

use OCA\Integriq\Event\ConnectionRefreshRequestedEvent;
use OCA\Integriq\Event\ConnectionStatusReportedEvent;
use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Unit tests for ConnectionReporter.
 *
 * @covers \OCA\LaunchPad\Service\Connection\ConnectionReporter
 */
class ConnectionReporterTest extends TestCase {
	use ConnectionReporterFixture;

	/**
	 * Set up the fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->sentEvents  = [];
		$this->configStore = ['registry_url' => 'https://registry.gemeente.example/index.php'];
	}//end setUp()

	/**
	 * The reporter as it behaves on an instance without integriq.
	 *
	 * Only the class lookup is replaced. The stubs make both event classes
	 * resolvable in this process, so absence is simulated at the one seam
	 * that asks.
	 *
	 * @param IEventDispatcher $dispatcher A dispatcher that must stay silent.
	 * @param IAppConfig       $appConfig  An app config that must stay untouched.
	 * @param LoggerInterface  $logger     A logger that must stay silent.
	 *
	 * @return ConnectionReporter
	 */
	private function reporterWithoutIntegriq(IEventDispatcher $dispatcher, IAppConfig $appConfig, LoggerInterface $logger): ConnectionReporter {
		$time = $this->createMock(originalClassName: ITimeFactory::class);

		return new class($dispatcher, $appConfig, $time, $logger) extends ConnectionReporter {

			/**
			 * Integriq is not installed, so no class resolves.
			 *
			 * @param string $eventClass The class name asked for.
			 *
			 * @return string|null Always null.
			 */
			protected function resolveEventClass(string $eventClass): ?string {
				return null;
			}//end resolveEventClass()
		};
	}//end reporterWithoutIntegriq()

	/**
	 * A registry search reports with this app's id, the key, the status and the host.
	 *
	 * @return void
	 */
	public function testARegistrySearchReportsWithTheHost(): void {
		$this->assertTrue(condition: $this->recordingReporter()->reportRegistrySearch(outcome: 'store_unreachable', engineAvailable: true));

		$this->assertCount(expectedCount: 1, haystack: $this->sentEvents);
		$event = $this->sentEvents[0];
		$this->assertInstanceOf(expected: ConnectionStatusReportedEvent::class, actual: $event);
		$this->assertSame(expected: 'launchpad', actual: $event->app);
		$this->assertSame(expected: 'dashboard-registry', actual: $event->key);
		$this->assertSame(expected: 'error', actual: $event->status);
		$this->assertSame(expected: 'The last search could not reach the dashboard registry at registry.gemeente.example.', actual: $event->message);
		$this->assertSame(expected: 'error|' . $this->now, actual: $this->configStore['connection_report_dashboard-registry']);
	}//end testARegistrySearchReportsWithTheHost()

	/**
	 * Each widget family reports under its own declared key.
	 *
	 * @return void
	 */
	public function testEachWidgetFamilyReportsUnderItsDeclaredKey(): void {
		$reporter = $this->recordingReporter();

		foreach (array_keys(ConnectionReporter::CALL_NAMES) as $key) {
			$this->assertTrue(condition: $reporter->reportCall(key: $key, url: 'https://svc.example.nl/x', httpStatus: 200), message: $key);
		}

		$this->assertSame(
			expected: [
				['weather', 'configured', 'The weather provider at svc.example.nl answered the last call.'],
				['news-feeds', 'configured', 'The news feed at svc.example.nl answered the last call.'],
				['ics-calendars', 'configured', 'The calendar feed at svc.example.nl answered the last call.'],
				['live-tiles', 'configured', 'The live tile source at svc.example.nl answered the last call.'],
				['health-ping', 'configured', 'The health ping target at svc.example.nl answered the last call.'],
			],
			actual: array_map(static fn (array $row): array => array_slice($row, 1), $this->sentRows())
		);
	}//end testEachWidgetFamilyReportsUnderItsDeclaredKey()

	/**
	 * A key with no call name is a caller's typo and sends nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownCallKeySendsNothing(): void {
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		// A typo is refused up front, not by a TypeError the catch-all logs.
		$logger->expects($this->never())->method('warning');
		$reporter = new ConnectionReporter(
			eventDispatcher: $dispatcher,
			appConfig: $this->backedAppConfig(),
			timeFactory: $this->createMock(originalClassName: ITimeFactory::class),
			logger: $logger,
		);

		$this->assertFalse(condition: $reporter->reportCall(key: 'dashboard-registry', url: 'https://x.example', httpStatus: 200));
		$this->assertFalse(condition: $reporter->reportCall(key: 'iframes', url: 'https://x.example', httpStatus: 200));
		$this->assertFalse(condition: $reporter->reportAllowListRefusal(key: 'news-feeds'));
		$this->assertSame(expected: [], actual: $this->sentEvents);
	}//end testAnUnknownCallKeySendsNothing()

	/**
	 * An outcome about one address sends nothing and writes no memory.
	 *
	 * @return void
	 */
	public function testAnOutcomeAboutOneAddressSendsNothing(): void {
		$reporter = $this->recordingReporter();

		$this->assertFalse(
			condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://feeds.example.nl/x', httpStatus: 404)
		);
		$this->assertFalse(
			condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://feeds.example.nl/x', httpStatus: 301)
		);
		$this->assertFalse(condition: $reporter->reportRegistrySearch(outcome: 'something_new', engineAvailable: true));

		$this->assertSame(expected: [], actual: $this->sentEvents);
		$this->assertArrayNotHasKey(key: 'connection_report_news-feeds', array: $this->configStore);
		$this->assertArrayNotHasKey(key: 'connection_report_dashboard-registry', array: $this->configStore);
	}//end testAnOutcomeAboutOneAddressSendsNothing()

	/**
	 * A burst of widget fetches sends at most one report per window.
	 *
	 * Dashboard widgets fetch on page load. Fifty fetches that meet the same
	 * answer within the hour send one report; the next one after the hour
	 * sends the second.
	 *
	 * @return void
	 */
	public function testABurstOfWidgetFetchesSendsOneReportPerWindow(): void {
		$reporter = $this->recordingReporter();

		$sent = 0;
		for ($fetch = 0; $fetch < 50; $fetch++) {
			if ($reporter->reportCall(key: ConnectionReporter::KEY_ICS_CALENDARS, url: 'https://cal.example.nl/a.ics', httpStatus: 200) === true) {
				$sent++;
			}

			$this->now += 60;
		}

		$this->assertSame(expected: 1, actual: $sent);
		$this->assertCount(expectedCount: 1, haystack: $this->sentEvents);

		$this->now = (1_760_000_000 + ConnectionReporter::REPEAT_SECONDS);
		$this->assertTrue(
			condition: $reporter->reportCall(key: ConnectionReporter::KEY_ICS_CALENDARS, url: 'https://cal.example.nl/a.ics', httpStatus: 200)
		);
		$this->assertCount(expectedCount: 2, haystack: $this->sentEvents);
	}//end testABurstOfWidgetFetchesSendsOneReportPerWindow()

	/**
	 * A different outcome reports after five minutes, and not before.
	 *
	 * Two widgets on one dashboard that disagree cannot write on every load.
	 *
	 * @return void
	 */
	public function testAChangedStatusWaitsFiveMinutes(): void {
		$reporter = $this->recordingReporter();

		$this->assertTrue(condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://a.example.nl', httpStatus: 200));
		for ($load = 0; $load < 20; $load++) {
			$this->assertFalse(
				condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://b.example.nl', httpStatus: null)
			);
			$this->assertFalse(
				condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://a.example.nl', httpStatus: 200)
			);
		}

		$this->now += (ConnectionReporter::CHANGE_SECONDS - 1);
		$this->assertFalse(condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://b.example.nl', httpStatus: null));
		$this->now += 1;
		$this->assertTrue(condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://b.example.nl', httpStatus: null));

		$this->assertSame(expected: ['configured', 'error'], actual: array_column($this->sentRows(), 2));
	}//end testAChangedStatusWaitsFiveMinutes()

	/**
	 * Each connection keeps its own memory.
	 *
	 * @return void
	 */
	public function testEachConnectionKeepsItsOwnMemory(): void {
		$reporter = $this->recordingReporter();

		$this->assertTrue(condition: $reporter->reportRegistrySearch(outcome: 'ok', engineAvailable: true));
		$this->assertTrue(condition: $reporter->reportCall(key: ConnectionReporter::KEY_HEALTH_PING, url: 'https://svc.example.nl', httpStatus: 200));

		$this->assertCount(expectedCount: 2, haystack: $this->sentEvents);
	}//end testEachConnectionKeepsItsOwnMemory()

	/**
	 * An empty fail-closed allow-list reports unconfigured, naming the key; a list with a host sends nothing.
	 *
	 * @return void
	 */
	public function testOnlyAnEmptyAllowListReportsARefusal(): void {
		$reporter = $this->recordingReporter();

		$this->configStore['healthping_allowed_hosts'] = '["intranet.example.nl"]';
		$this->assertFalse(condition: $reporter->reportAllowListRefusal(key: ConnectionReporter::KEY_HEALTH_PING));

		// The comma-separated form docs/features/service-health-ping.md shows
		// decodes to no list, so the service refuses every host.
		$this->configStore['healthping_allowed_hosts'] = 'intranet.example.nl,api.example.nl';
		$this->assertTrue(condition: $reporter->reportAllowListRefusal(key: ConnectionReporter::KEY_HEALTH_PING));

		$this->configStore['livetile_allowed_hosts'] = '[]';
		$this->assertTrue(condition: $reporter->reportAllowListRefusal(key: ConnectionReporter::KEY_LIVE_TILES));

		$this->assertSame(
			expected: [
				[
					'health-ping',
					'unconfigured',
					'healthping_allowed_hosts holds no JSON list of hosts, so LaunchPad refused the last call. Set it with occ.',
				],
				[
					'live-tiles',
					'unconfigured',
					'livetile_allowed_hosts holds no JSON list of hosts, so LaunchPad refused the last call. Set it with occ.',
				],
			],
			actual: array_map(static fn (array $row): array => array_slice($row, 1), $this->sentRows())
		);
	}//end testOnlyAnEmptyAllowListReportsARefusal()

	/**
	 * A registry save refreshes the registry and clears its memory, so the next search reports at once.
	 *
	 * The refresh is sent first and the report after it: the order the
	 * contract asks for, and the order that lets the report land inside the
	 * five minutes a changed status would otherwise wait.
	 *
	 * @return void
	 */
	public function testARegistrySaveRefreshesFirstAndTheNextReportFollows(): void {
		$reporter = $this->recordingReporter();
		$reporter->reportRegistrySearch(outcome: 'store_unreachable', engineAvailable: true);
		$reporter->reportCall(key: ConnectionReporter::KEY_HEALTH_PING, url: 'https://svc.example.nl', httpStatus: 200);
		$this->sentEvents = [];
		$this->now       += 120;

		$refreshed = $reporter->refreshFromSave(savedKeys: ['registry_token']);

		$this->assertSame(expected: ['dashboard-registry'], actual: $refreshed);
		$this->assertArrayNotHasKey(key: 'connection_report_dashboard-registry', array: $this->configStore);
		$this->assertArrayHasKey(key: 'connection_report_health-ping', array: $this->configStore);

		$this->assertTrue(condition: $reporter->reportRegistrySearch(outcome: 'ok', engineAvailable: true));

		$this->assertInstanceOf(expected: ConnectionRefreshRequestedEvent::class, actual: $this->sentEvents[0]);
		$this->assertSame(expected: 'launchpad', actual: $this->sentEvents[0]->app);
		$this->assertInstanceOf(expected: ConnectionStatusReportedEvent::class, actual: $this->sentEvents[1]);
		$this->assertSame(
			expected: [
				['ConnectionRefreshRequestedEvent', 'dashboard-registry', '', ''],
				[
					'ConnectionStatusReportedEvent',
					'dashboard-registry',
					'configured',
					'The dashboard registry at registry.gemeente.example answered the last search.',
				],
			],
			actual: $this->sentRows()
		);
	}//end testARegistrySaveRefreshesFirstAndTheNextReportFollows()

	/**
	 * A save that writes no registry key sends nothing.
	 *
	 * @return void
	 */
	public function testAnUnrelatedSaveSendsNoRefresh(): void {
		$this->assertSame(expected: [], actual: $this->recordingReporter()->refreshFromSave(savedKeys: ['weather_provider_url', 'register']));
		$this->assertSame(expected: [], actual: $this->recordingReporter()->refreshFromSave(savedKeys: []));
		$this->assertSame(expected: [], actual: $this->sentEvents);
	}//end testAnUnrelatedSaveSendsNoRefresh()

	/**
	 * Without integriq nothing is read, sent, stored, cleared or logged.
	 *
	 * @return void
	 */
	public function testWithoutIntegriqNothingIsReadSentStoredOrLogged(): void {
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$appConfig  = $this->createMock(originalClassName: IAppConfig::class);
		$logger     = $this->createMock(originalClassName: LoggerInterface::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');
		$appConfig->expects($this->never())->method('getValueString');
		$appConfig->expects($this->never())->method('setValueString');
		$appConfig->expects($this->never())->method('deleteKey');
		$logger->expects($this->never())->method('warning');

		$reporter = $this->reporterWithoutIntegriq(dispatcher: $dispatcher, appConfig: $appConfig, logger: $logger);

		$this->assertFalse(condition: $reporter->reportRegistrySearch(outcome: 'store_unreachable', engineAvailable: true));
		$this->assertFalse(
			condition: $reporter->reportCall(key: ConnectionReporter::KEY_NEWS_FEEDS, url: 'https://feeds.example.nl', httpStatus: null)
		);
		$this->assertFalse(condition: $reporter->reportAllowListRefusal(key: ConnectionReporter::KEY_LIVE_TILES));
		$this->assertFalse(condition: $reporter->reportWeatherNotConfigured());
		$this->assertSame(expected: [], actual: $reporter->refreshFromSave(savedKeys: ['registry_url']));
	}//end testWithoutIntegriqNothingIsReadSentStoredOrLogged()

	/**
	 * The class lookup answers null for a class nobody ships, and the class for a stub.
	 *
	 * This is the real guard, not the test double above.
	 *
	 * @return void
	 */
	public function testTheLookupAnswersNullForAnAbsentClass(): void {
		$method   = new ReflectionMethod(ConnectionReporter::class, 'resolveEventClass');
		$reporter = $this->recordingReporter();

		$this->assertNull(actual: $method->invoke($reporter, 'OCA\\Nobody\\Event\\ShipsThisEvent'));
		$this->assertSame(
			expected: '\\' . ConnectionReporter::STATUS_EVENT,
			actual: $method->invoke($reporter, ConnectionReporter::STATUS_EVENT)
		);
	}//end testTheLookupAnswersNullForAnAbsentClass()

	/**
	 * The event names are the ones the contract fixes.
	 *
	 * A string class name is exactly the reference that rots into a silent
	 * no-op after a rename, so it is compared to the stubs' real names.
	 *
	 * @return void
	 */
	public function testTheEventNamesAreTheContractNames(): void {
		$this->assertSame(expected: ConnectionStatusReportedEvent::class, actual: ConnectionReporter::STATUS_EVENT);
		$this->assertSame(expected: ConnectionRefreshRequestedEvent::class, actual: ConnectionReporter::REFRESH_EVENT);
	}//end testTheEventNamesAreTheContractNames()

	/**
	 * A listener that throws never escapes, and leaves the memory unwritten.
	 *
	 * An unwritten memory means the next call tries again instead of keeping
	 * quiet for an hour about a report that never landed.
	 *
	 * @return void
	 */
	public function testAThrowingListenerNeverEscapes(): void {
		$dispatcher = $this->createMock(originalClassName: IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willThrowException(new RuntimeException('registry down'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->exactly(count: 2))->method('warning')
			->with($this->stringContains(string: 'could not send'), $this->arrayHasKey(key: 'key'));

		$reporter = new ConnectionReporter(
			eventDispatcher: $dispatcher,
			appConfig: $this->backedAppConfig(),
			timeFactory: $this->createMock(originalClassName: ITimeFactory::class),
			logger: $logger,
		);

		$this->assertFalse(condition: $reporter->reportRegistrySearch(outcome: 'ok', engineAvailable: true));
		// Asserted before the refresh, because the refresh clears this key itself.
		$this->assertArrayNotHasKey(key: 'connection_report_dashboard-registry', array: $this->configStore);
		$this->assertSame(expected: [], actual: $reporter->refreshFromSave(savedKeys: ['registry_url']));
	}//end testAThrowingListenerNeverEscapes()

	/**
	 * A failing app config never escapes into the call.
	 *
	 * @return void
	 */
	public function testAFailingAppConfigNeverEscapes(): void {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willThrowException(new RuntimeException('type conflict'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')
			->with($this->stringContains(string: 'could not report'), $this->arrayHasKey(key: 'exception'));

		$reporter = new ConnectionReporter(
			eventDispatcher: $this->createMock(originalClassName: IEventDispatcher::class),
			appConfig: $appConfig,
			timeFactory: $this->createMock(originalClassName: ITimeFactory::class),
			logger: $logger,
		);

		$this->assertFalse(condition: $reporter->reportRegistrySearch(outcome: 'ok', engineAvailable: true));
	}//end testAFailingAppConfigNeverEscapes()
}//end class
