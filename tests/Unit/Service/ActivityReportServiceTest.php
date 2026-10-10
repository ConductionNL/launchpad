<?php

/**
 * ActivityReportServiceTest
 *
 * The purpose gate, the self-read exception and the audit entry of activity
 * reporting (REQ-DWMS-005, REQ-DWMS-006; task 4.7).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeZone;
use InvalidArgumentException;
use OCA\LaunchPad\Db\ActivityEventReader;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActivityReportService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ActivityReportService.
 */
class ActivityReportServiceTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Audit events dispatched.
	 *
	 * @var array<int, Event>
	 */
	private array $dispatched = [];

	/**
	 * Events in the stream, per user.
	 *
	 * @var array<string, array<int, array{type: string, timestamp: int}>>
	 */
	private array $stream = [];

	protected function setUp(): void {
		parent::setUp();
		$this->settings = [];
		$this->dispatched = [];
		// Twelve days of September with activity for anna: two events on
		// the 1st, one on each of eleven other days.
		$events = [];
		foreach ([1, 1, 2, 3, 5, 8, 9, 10, 15, 16, 22, 29, 30] as $day) {
			$events[] = ['type' => ($day === 1 ? 'file_changed' : 'launchpad_dashboard'), 'timestamp' => gmmktime(10, 0, 0, 9, $day, 2026)];
		}

		// Outside the period: the last minute of August, the first of October.
		$events[] = ['type' => 'file_changed', 'timestamp' => gmmktime(23, 59, 0, 8, 31, 2026)];
		$events[] = ['type' => 'file_changed', 'timestamp' => gmmktime(0, 0, 0, 10, 1, 2026)];
		$this->stream = ['anna' => $events];
	}//end setUp()

	public function testTheCapabilityIsOffByDefault(): void {
		$service = $this->service();

		$this->assertSame(['enabled' => false, 'purpose' => ''], $service->getPolicy());
		$this->assertSame(['error' => 'reporting_disabled'], $service->report('admin', 'anna', '2026-09-01', '2026-09-30'));
		$this->assertSame([], $this->dispatched);
	}//end testTheCapabilityIsOffByDefault()

	public function testItCannotBeTurnedOnWithoutAPurpose(): void {
		$service = $this->service();

		try {
			$service->setPolicy(enabled: true, purpose: '   ');
			$this->fail('turned on without a purpose');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('purpose_required', $e->getMessage());
		}

		$this->assertFalse($service->getPolicy()['enabled']);
		// A switch stored on without a purpose still reads as off.
		$this->settings['activity_reporting_enabled'] = true;
		$this->assertFalse($service->getPolicy()['enabled']);
	}//end testItCannotBeTurnedOnWithoutAPurpose()

	public function testReadingAColleaguesActivityShowsThePurposeAndIsAudited(): void {
		$service = $this->service();
		$service->setPolicy(enabled: true, purpose: 'workload balancing');

		$report = $service->report('admin', 'anna', '2026-09-01', '2026-09-30');

		$this->assertSame('workload balancing', $report['purpose']);
		$this->assertCount(1, $this->dispatched);
		$event = $this->dispatched[0];
		$this->assertInstanceOf(CriticalActionPerformedEvent::class, $event);
		$params = $event->getParameters();
		$this->assertSame(['read', 'admin', 'anna', '2026-09-01', '2026-09-30', 'workload balancing'], array_values($params));
		// One %s per parameter, or admin_audit's vsprintf drops the entry.
		$this->assertSame(count($params), substr_count($event->getLogMessage(), '%s'));
	}//end testReadingAColleaguesActivityShowsThePurposeAndIsAudited()

	public function testAPersonReadsTheirOwnWithoutTheSwitchAndWithoutALogEntry(): void {
		$service = $this->service();

		$report = $service->report('anna', 'anna', '2026-09-01', '2026-09-30');

		$this->assertSame(13, $report['total']);
		$this->assertNull($report['purpose']);
		$this->assertSame([], $this->dispatched);
	}//end testAPersonReadsTheirOwnWithoutTheSwitchAndWithoutALogEntry()

	public function testANonAdministratorCannotReadSomebodyElse(): void {
		$service = $this->service();
		$service->setPolicy(enabled: true, purpose: 'workload balancing');

		$this->assertSame(['error' => 'forbidden'], $service->report('bram', 'anna', '2026-09-01', '2026-09-30'));
		$this->assertSame(['error' => 'forbidden'], $service->export('bram', 'anna', '2026-09-01', '2026-09-30'));
		$this->assertSame([], $this->dispatched);
	}//end testANonAdministratorCannotReadSomebodyElse()

	public function testAMonthAsAHeatmapHasTwelveDaysWithAnIntensity(): void {
		$report = $this->service()->report('anna', 'anna', '2026-09-01', '2026-09-30');

		$this->assertCount(30, $report['days']);
		$active = array_filter($report['days'], static fn (array $day): bool => $day['count'] > 0);
		$this->assertCount(12, $active);
		$this->assertSame(['date' => '2026-09-01', 'count' => 2, 'level' => 4], $report['days'][0]);
		$this->assertSame(['date' => '2026-09-02', 'count' => 1, 'level' => 2], $report['days'][1]);
		$this->assertSame(['date' => '2026-09-04', 'count' => 0, 'level' => 0], $report['days'][3]);
		$this->assertSame(
			[['type' => 'launchpad_dashboard', 'count' => 11], ['type' => 'file_changed', 'count' => 2]],
			$report['byType']
		);
	}//end testAMonthAsAHeatmapHasTwelveDaysWithAnIntensity()

	public function testTheExportCarriesTheSameNumbers(): void {
		$service = $this->service();
		$service->setPolicy(enabled: true, purpose: 'workload balancing');
		$report = $service->report('admin', 'anna', '2026-09-01', '2026-09-30');

		$export = $service->export('admin', 'anna', '2026-09-01', '2026-09-30');

		$rows = array_map('str_getcsv', array_filter(explode("\n", $export['csv'])));
		$this->assertSame(['date', 'activity_type', 'events'], $rows[0]);
		$body = array_slice($rows, 1, -1);
		$sum = array_sum(array_map(static fn (array $row): int => (int)$row[2], $body));
		$this->assertSame($report['total'], $sum);
		$this->assertSame(['total', '', (string)$report['total']], end($rows));
		$perType = [];
		foreach ($body as $row) {
			$perType[$row[1]] = (($perType[$row[1]] ?? 0) + (int)$row[2]);
		}

		$this->assertSame(['file_changed' => 2, 'launchpad_dashboard' => 11], $perType);
		$this->assertSame('activity-anna-2026-09-01-2026-09-30.csv', $export['filename']);
		// The export is a read too, so it is audited as one.
		$this->assertSame('export', $this->dispatched[1]->getParameters()['action']);
	}//end testTheExportCarriesTheSameNumbers()

	public function testAPeriodLongerThanAYearOrBackwardsIsRefused(): void {
		$service = $this->service();

		foreach ([['2025-01-01', '2026-09-30', 'period_too_long'], ['2026-09-30', '2026-09-01', 'period_reversed'], ['2026-02-30', '2026-03-01', 'period_invalid']] as [$from, $until, $message]) {
			try {
				$service->report('anna', 'anna', $from, $until);
				$this->fail('accepted '.$from.' to '.$until);
			} catch (InvalidArgumentException $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}
	}//end testAPeriodLongerThanAYearOrBackwardsIsRefused()

	/**
	 * The service over a fake settings store and a fake stream.
	 *
	 * @return ActivityReportService
	 */
	private function service(): ActivityReportService {
		$settings = $this->getMockBuilder(AdminSettingMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['getValue', 'setSetting'])
			->getMock();
		$settings->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => (array_key_exists($key, $this->settings) === true ? $this->settings[$key] : $default)
		);
		$settings->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);

		$events = $this->getMockBuilder(ActivityEventReader::class)
			->disableOriginalConstructor()
			->onlyMethods(['eventsBy', 'isAvailable'])
			->getMock();
		$events->method('isAvailable')->willReturn(true);
		$events->method('eventsBy')->willReturnCallback(
			fn (string $userId, int $from, int $until): array => array_values(
				array_filter(
					($this->stream[$userId] ?? []),
					static fn (array $event): bool => ($event['timestamp'] >= $from && $event['timestamp'] < $until)
				)
			)
		);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => ($userId === 'admin'));

		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(
			function (Event $event): void {
				$this->dispatched[] = $event;
			}
		);

		$zone = $this->createMock(IDateTimeZone::class);
		$zone->method('getTimeZone')->willReturn(new DateTimeZone('UTC'));

		return new ActivityReportService($settings, $events, $groups, $dispatcher, $zone);
	}//end service()
}//end class
