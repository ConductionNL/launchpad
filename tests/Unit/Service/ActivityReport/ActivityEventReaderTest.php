<?php

/**
 * ActivityEventReader Test
 *
 * REQ-DWMS-006: the person's events from the activity table, one per action.
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
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ActivityEventReader::class)]
class ActivityEventReaderTest extends TestCase {

	public function testTheRowsOfOneActionFoldIntoOneEvent(): void {
		$rows = [
			['type' => 'file_changed', 'timestamp' => 100, 'app' => 'files', 'subject' => 'changed_self', 'object_type' => 'files', 'object_id' => 7],
			['type' => 'file_changed', 'timestamp' => 100, 'app' => 'files', 'subject' => 'changed_self', 'object_type' => 'files', 'object_id' => 7],
			['type' => 'file_changed', 'timestamp' => 100, 'app' => 'files', 'subject' => 'changed_self', 'object_type' => 'files', 'object_id' => 8],
			['type' => '', 'timestamp' => '200'],
		];

		$this->assertSame(
			[['type' => 'file_changed', 'timestamp' => 100], ['type' => 'file_changed', 'timestamp' => 100], ['type' => 'other', 'timestamp' => 200]],
			ActivityEventReader::fold($rows)
		);
	}

	public function testItReadsThePersonsRowsInThePeriod(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())->method('eq')->with('user', ':user')->willReturn('eq');
		$expr->expects($this->once())->method('gte')->with('timestamp', ':from')->willReturn('gte');
		$expr->expects($this->once())->method('lt')->with('timestamp', ':until')->willReturn('lt');
		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn([['type' => 'calendar', 'timestamp' => 150]]);
		$query = $this->createMock(IQueryBuilder::class);
		$query->method('expr')->willReturn($expr);
		$query->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): string => match ($value) {
			'anna' => ':user',
			100 => ':from',
			200 => ':until',
		});
		$query->expects($this->once())->method('select')->with('type', 'timestamp', 'app', 'subject', 'object_type', 'object_id')->willReturnSelf();
		$query->expects($this->once())->method('from')->with('activity')->willReturnSelf();
		$query->method('where')->with('eq')->willReturnSelf();
		$query->method('andWhere')->willReturnSelf();
		$query->method('orderBy')->with('timestamp', 'ASC')->willReturnSelf();
		$query->method('executeQuery')->willReturn($result);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($query);

		$this->assertSame([['type' => 'calendar', 'timestamp' => 150]], (new ActivityEventReader($db))->events('anna', 100, 200));
	}

	public function testWithoutTheActivityTableThereAreNoEventsToReport(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new RuntimeException('no such table'));

		$this->assertNull((new ActivityEventReader($db))->events('anna', 100, 200));
	}
}
