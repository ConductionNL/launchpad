<?php

/**
 * StatusReportService Test
 *
 * REQ-DWMS-004: the prefill, and the computed-versus-written record of a kept report.
 *
 * @category  Test
 * @package   Unit\Service\StatusReport
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

namespace Unit\Service\StatusReport;
use DateTimeImmutable;
use DateTimeZone;
use OCA\LaunchPad\Service\Reporting\ObjectRows;
use OCA\LaunchPad\Service\StatusReport\StatusReportRefusedException;
use OCA\LaunchPad\Service\StatusReport\StatusReportService;
use OCA\LaunchPad\Service\StatusReport\StatusReportStore;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusReportService::class)]
#[UsesClass(StatusReportRefusedException::class)]
class StatusReportServiceTest extends TestCase {

	// 2026-11-15T12:00:00Z
	private const NOW = 1_794_744_000;

	private const SUBJECT = ['register' => 'projects', 'schema' => 'task', 'title' => 'Programme', 'deadline' => '2026-12-15'];

	private ObjectRows&MockObject $rows;
	private StatusReportStore&MockObject $store;

	protected function setUp(): void {
		$this->rows  = $this->createMock(ObjectRows::class);
		$this->store = $this->createMock(StatusReportStore::class);
	}

	private function service(): StatusReportService {
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(self::NOW);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters=[]): string => vsprintf($text, $parameters));

		return new StatusReportService($this->rows, $this->store, $time, $l10n);
	}

	/** Ten items: six closed, four open of which one overdue. */
	private function items(): array {
		$items = [];
		for ($i = 0; $i < 6; $i++) {
			$items[] = ['status' => ($i % 2 === 0 ? 'Done' : 'closed')];
		}

		$items[] = ['status' => 'open', 'deadline' => '2026-11-14'];
		$items[] = ['status' => 'open', 'deadline' => '2026-11-15'];
		$items[] = ['object' => ['status' => 'in progress', 'deadline' => '2026-12-01T10:00:00+00:00']];
		$items[] = ['status' => 'open'];
		return $items;
	}

	public function testTheRatingAndTheFiguresArePrefilledFromTheBodyOfWork(): void {
		$this->rows->expects($this->once())->method('rows')->with('projects', 'task', ['priority' => 'high'])->willReturn($this->items());

		$draft = $this->service()->draft(self::SUBJECT + ['filters' => ['priority' => 'high']]);

		$this->assertSame('2026-11-15', $draft['date']);
		$this->assertSame(
			['total' => 10, 'open' => 4, 'closed' => 6, 'overdue' => 1, 'percentClosed' => 60, 'daysToDeadline' => 30],
			$draft['figures']
		);
		$this->assertSame('at-risk', $draft['health']);
		$this->assertSame('6 of 10 items closed (60%). 4 open, of which 1 overdue. Deadline 2026-12-15, 30 days from today.', $draft['summary']);
		$this->assertSame('computed', $draft['provenance']['health']);
		$this->assertSame('computed', $draft['provenance']['summary']);
		$this->assertSame(array_fill_keys(array_keys($draft['figures']), 'computed'), $draft['provenance']['figures']);
		$this->assertSame('status', $draft['subject']['statusProperty']);
		$this->assertSame('deadline', $draft['subject']['dueProperty']);
	}

	public function testTheHealthRules(): void {
		$figures = static fn (int $open, int $overdue, int $percent, ?int $days): array => ['total' => 10, 'open' => $open, 'closed' => (10 - $open), 'overdue' => $overdue, 'percentClosed' => $percent, 'daysToDeadline' => $days];

		$this->assertSame('on-track', StatusReportService::health($figures(2, 0, 80, 5)));
		$this->assertSame('on-track', StatusReportService::health($figures(5, 0, 50, 30)));
		$this->assertSame('on-track', StatusReportService::health($figures(5, 0, 50, null)));
		$this->assertSame('on-track', StatusReportService::health($figures(0, 0, 100, -3)));
		$this->assertSame('at-risk', StatusReportService::health($figures(5, 0, 50, 14)));
		$this->assertSame('at-risk', StatusReportService::health($figures(4, 1, 60, 30)));
		$this->assertSame('off-track', StatusReportService::health($figures(3, 1, 70, 30)));
		$this->assertSame('off-track', StatusReportService::health($figures(1, 0, 90, -1)));
	}

	public function testAnEmptyBodyOfWorkWithoutADeadlineIsOnTrackAtNothingClosed(): void {
		$this->rows->method('rows')->willReturn([]);

		$draft = $this->service()->draft(['register' => 'projects', 'schema' => 'task']);

		$this->assertSame(['total' => 0, 'open' => 0, 'closed' => 0, 'overdue' => 0, 'percentClosed' => 0, 'daysToDeadline' => null], $draft['figures']);
		$this->assertSame('on-track', $draft['health']);
		$this->assertSame('0 of 0 items closed (0%). 0 open, of which 0 overdue.', $draft['summary']);
	}

	public function testItsOwnStatusAndDuePropertiesAndClosedValuesAreUsed(): void {
		$this->rows->method('rows')->willReturn([
			['fase' => 'Afgerond', 'einddatum' => '2026-01-01'],
			['fase' => 'loopt', 'einddatum' => '2026-01-01'],
			['fase' => 'loopt', 'einddatum' => 'not a date'],
		]);

		$figures = $this->service()->draft(['register' => 'r', 'schema' => 's', 'statusProperty' => 'fase', 'dueProperty' => 'einddatum', 'closedValues' => ['afgerond']])['figures'];

		$this->assertSame(1, $figures['closed']);
		$this->assertSame(1, $figures['overdue']);
	}

	public function testAKeptReportRecordsTheSummaryAsWrittenByAPerson(): void {
		$this->rows->method('rows')->willReturn($this->items());
		$kept = null;
		$this->store->expects($this->once())->method('save')->willReturnCallback(static function (array $report) use (&$kept): array {
			$kept = $report;
			return $report + ['id' => 'uuid-1'];
		});

		$stored = $this->service()->keep('anna', self::SUBJECT, null, "  The programme is behind on one item.\n");

		$this->assertSame('uuid-1', $stored['id']);
		$this->assertSame('The programme is behind on one item.', $kept['summary']);
		$this->assertSame('written', $kept['provenance']['summary']);
		$this->assertSame('at-risk', $kept['health']);
		$this->assertSame('computed', $kept['provenance']['health']);
		$this->assertSame('computed', $kept['provenance']['figures']['open']);
		$this->assertSame('anna', $kept['author']);
		$this->assertSame('2026-11-15T12:00:00+00:00', $kept['keptAt']);
	}

	public function testAValueEqualToTheComputedOneIsRecordedAsComputed(): void {
		$this->rows->method('rows')->willReturn($this->items());
		$this->store->method('save')->willReturnArgument(0);
		$draft = $this->service()->draft(self::SUBJECT);

		$kept = $this->service()->keep('anna', self::SUBJECT, 'at-risk', $draft['summary']);

		$this->assertSame(['date' => 'computed', 'health' => 'computed', 'summary' => 'computed'], array_slice($kept['provenance'], 0, 3));
	}

	public function testAChosenRatingIsRecordedAsWrittenAndTheFiguresStayComputed(): void {
		$this->rows->method('rows')->willReturn($this->items());
		$this->store->method('save')->willReturnArgument(0);

		$kept = $this->service()->keep('anna', self::SUBJECT, 'off-track');

		$this->assertSame('off-track', $kept['health']);
		$this->assertSame('written', $kept['provenance']['health']);
		$this->assertSame('computed', $kept['provenance']['summary']);
		$this->assertSame(1, $kept['figures']['overdue']);
	}

	public function testUnusableInputIsRefusedBeforeAnythingIsReadOrKept(): void {
		$this->rows->expects($this->never())->method('rows');
		$this->store->expects($this->never())->method('save');
		$service = $this->service();

		foreach ([
			fn () => $service->draft(['register' => '', 'schema' => 'task']),
			fn () => $service->draft(['register' => 'projects', 'schema' => 'task', 'deadline' => '15-12-2026']),
			fn () => $service->draft(['register' => 'projects', 'schema' => 'task', 'deadline' => '2026-02-30']),
			fn () => $service->keep('anna', self::SUBJECT, 'fine'),
			fn () => $service->keep('anna', self::SUBJECT, null, '   '),
			fn () => $service->keep('anna', self::SUBJECT, null, str_repeat('x', 5001)),
		] as $call) {
			try {
				$call();
				$this->fail('Accepted unusable input');
			} catch (StatusReportRefusedException $refused) {
				$this->assertSame(400, $refused->statusCode);
			}
		}
	}

	public function testAnUnreadableBodyOfWorkIsA409(): void {
		$this->rows->method('rows')->willReturn(null);

		$this->expectExceptionObject(new StatusReportRefusedException('The objects of projects/task cannot be read.', 409));
		$this->service()->draft(self::SUBJECT);
	}

	public function testWithoutAStoreTheReportIsNotKeptAndSaysWhy(): void {
		$this->rows->method('rows')->willReturn([]);
		$this->store->method('save')->willReturn(null);

		try {
			$this->service()->keep('anna', self::SUBJECT);
			$this->fail('Kept without a store');
		} catch (StatusReportRefusedException $refused) {
			$this->assertSame(503, $refused->statusCode);
		}
	}

	public function testTheFiguresCountAnItemDueTodayAsNotOverdue(): void {
		$today   = new DateTimeImmutable('2026-11-15', new DateTimeZone('UTC'));
		$subject = StatusReportService::subject(['register' => 'r', 'schema' => 's']);

		$figures = StatusReportService::figures([['status' => 'open', 'deadline' => '2026-11-15'], ['status' => 'open', 'deadline' => '2026-11-14']], $subject, $today);

		$this->assertSame(1, $figures['overdue']);
	}
}
