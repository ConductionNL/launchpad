<?php

/**
 * ActivityReportService Test
 *
 * REQ-DWMS-005 and REQ-DWMS-006: the gate, the self-read exception, the audit entry, the figures and the CSV.
 *
 * @category  Test
 * @package   Unit\Service\ActivityReport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\ActivityReport;

use OCA\LaunchPad\Service\ActivityReport\ActivityEventReader;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportingPolicy;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportRefusedException;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityReportService::class)]
#[UsesClass(ActivityReportRefusedException::class)]
class ActivityReportServiceTest extends TestCase {

	/** @var list<object> */
	private array $audited = [];

	/** @var list<array{0:string,1:int,2:int}> */
	private array $reads = [];

	private function service(bool $enabled, string $purpose = 'workload balancing', ?array $events = []): ActivityReportService {
		$policy = $this->createMock(ActivityReportingPolicy::class);
		$policy->method('isEnabled')->willReturn($enabled);
		$policy->method('purpose')->willReturn($purpose);
		$reader = $this->createMock(ActivityEventReader::class);
		$reader->method('events')->willReturnCallback(function (string $user, int $from, int $until) use ($events): ?array {
			$this->reads[] = [$user, $from, $until];
			return $events;
		});
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(function (object $event): void {
			$this->audited[] = $event;
		});

		return new ActivityReportService($policy, $reader, $dispatcher);
	}

	private static function event(string $type, string $at): array {
		return ['type' => $type, 'timestamp' => strtotime($at . ' UTC')];
	}

	public function testWhileOffAnotherPersonsActivityIsRefusedSayingHowToTurnItOn(): void {
		$service = $this->service(false, '');

		try {
			$service->report('admin', true, 'anna', '2026-09-01', '2026-09-30');
			$this->fail('the read was not refused');
		} catch (ActivityReportRefusedException $refused) {
			$this->assertSame(403, $refused->statusCode);
			$this->assertTrue($refused->disabled);
			$this->assertSame(ActivityReportService::DISABLED_MESSAGE, $refused->getMessage());
		}

		$this->assertSame([], $this->reads, 'nothing is read');
		$this->assertSame([], $this->audited);
	}

	public function testAnAdministratorReadsAColleaguesMonthWithThePurposeAndTheReadIsAudited(): void {
		$events = [self::event('file_changed', '2026-09-01 08:00'), self::event('file_changed', '2026-09-01 17:00'), self::event('dashboard', '2026-09-12 10:00')];
		$report = $this->service(true, 'workload balancing', $events)->report('admin', true, 'anna', '2026-09-01', '2026-09-30');

		$this->assertSame('anna', $report['subject']);
		$this->assertFalse($report['self']);
		$this->assertSame('workload balancing', $report['purpose']);
		$this->assertSame(['dashboard' => 1, 'file_changed' => 2], $report['counts']);
		$this->assertSame(['2026-09-01' => 2, '2026-09-12' => 1], $report['days']);
		$this->assertSame(3, $report['total']);
		$this->assertTrue($report['available']);
		$this->assertSame([['anna', strtotime('2026-09-01 00:00 UTC'), strtotime('2026-10-01 00:00 UTC')]], $this->reads);

		$this->assertCount(1, $this->audited);
		$this->assertInstanceOf(CriticalActionPerformedEvent::class, $this->audited[0]);
		$this->assertSame(
			['anna', '2026-09-01', '2026-09-30', 'admin', 'workload balancing'],
			$this->audited[0]->getParameters()
		);
		$this->assertStringContainsString('read by', $this->audited[0]->getLogMessage());
	}

	public function testANonAdministratorIsRefusedEvenWhenItIsOn(): void {
		$service = $this->service(true);

		$this->expectException(ActivityReportRefusedException::class);
		$this->expectExceptionMessage('Only an administrator can read another person\'s activity.');

		try {
			$service->report('bob', false, 'anna', '2026-09-01', '2026-09-30');
		} finally {
			$this->assertSame([], $this->audited);
			$this->assertSame([], $this->reads);
		}
	}

	public function testAPersonReadsTheirOwnWhileOffWithoutAnAuditEntry(): void {
		$report = $this->service(false, '', [self::event('calendar', '2026-09-03 09:00')])->report('anna', false, '', '2026-09-01', '2026-09-30');

		$this->assertTrue($report['self']);
		$this->assertSame('anna', $report['subject']);
		$this->assertNull($report['purpose']);
		$this->assertSame(1, $report['total']);
		$this->assertSame([], $this->audited);
	}

	public function testWhenItIsOnThePurposeShowsOnOnesOwnReportToo(): void {
		$this->assertSame('workload balancing', $this->service(true)->report('anna', false, 'anna', '2026-09-01', '2026-09-30')['purpose']);
		$this->assertSame([], $this->audited);
	}

	public function testWithoutTheActivityAppTheReportSaysSo(): void {
		$report = $this->service(false, '', null)->report('anna', false, 'anna', '2026-09-01', '2026-09-01');

		$this->assertFalse($report['available']);
		$this->assertSame(0, $report['total']);
		$this->assertSame([], $report['days']);
	}

	public function testAPeriodThatIsNotOneIsRefusedAfterTheAccessCheck(): void {
		foreach ([['2026-09-31', '2026-10-01'], ['2026-10-02', '2026-10-01'], ['2025-01-01', '2026-01-02'], ['', '2026-01-01']] as [$from, $to]) {
			try {
				$this->service(true)->report('anna', false, 'anna', $from, $to);
				$this->fail($from . '/' . $to . ' was accepted');
			} catch (ActivityReportRefusedException $refused) {
				$this->assertSame(400, $refused->statusCode);
			}
		}

		$this->expectExceptionMessage(ActivityReportService::DISABLED_MESSAGE);
		$this->service(false, '')->report('bob', true, 'anna', 'nonsense', '');
	}

	public function testTheCsvCarriesTheSameNumbersAsTheScreen(): void {
		$events = [self::event('file_changed', '2026-09-01 08:00'), self::event('comments', '2026-09-01 09:00'), self::event('file_changed', '2026-09-02 08:00')];
		$report = $this->service(true, 'workload, "balancing"', $events)->report('admin', true, 'anna', '2026-09-01', '2026-09-30');
		$rows = array_map('str_getcsv', array_filter(explode("\n", ActivityReportService::csv($report))));

		$this->assertSame(['kind', 'key', 'count'], $rows[0]);
		$this->assertSame(['purpose', 'workload, "balancing"', ''], $rows[1]);
		$this->assertSame(['subject', 'anna', ''], $rows[2]);
		$this->assertSame(['period', '2026-09-01/2026-09-30', ''], $rows[3]);
		$types = array_values(array_filter($rows, static fn (array $r): bool => $r[0] === 'type'));
		$days = array_values(array_filter($rows, static fn (array $r): bool => $r[0] === 'day'));
		$this->assertSame($report['counts'], array_map('intval', array_column($types, 2, 1)));
		$this->assertSame($report['days'], array_map('intval', array_column($days, 2, 1)));
		$this->assertSame(['total', '', '3'], end($rows));
		$this->assertSame($report['total'], array_sum(array_map('intval', array_column($types, 2))));
	}

	public function testFiguresLeaveADayWithoutActivityOut(): void {
		$days = [];
		foreach ([1, 3, 5, 8, 9, 10, 15, 16, 20, 22, 25, 30] as $day) {
			$days[] = self::event('file_changed', sprintf('2026-09-%02d 12:00', $day));
		}

		$figures = ActivityReportService::figures($days);

		$this->assertCount(12, $figures['days']);
		$this->assertArrayNotHasKey('2026-09-02', $figures['days']);
		$this->assertSame(12, $figures['total']);
	}
}
