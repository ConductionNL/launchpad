<?php

/**
 * ColleagueActivityReader Test
 *
 * REQ-DWMS-007: the rows the activity app addressed to the reader, minus the reader's own actions.
 *
 * @category  Test
 * @package   Unit\Service\ColleagueActivity
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

namespace Unit\Service\ColleagueActivity;

use OCA\LaunchPad\Service\ColleagueActivity\ColleagueActivityReader;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ColleagueActivityReader::class)]
class ColleagueActivityReaderTest extends TestCase {

	public function testItReadsTheRowsAddressedToTheReaderAndCausedByOthersNewestFirst(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())->method('eq')->with('affecteduser', ':reader')->willReturn('eq');
		$expr->expects($this->exactly(2))->method('neq')->willReturnCallback(static fn (string $column, string $value): string => 'neq ' . $column . ' ' . $value);
		$expr->expects($this->once())->method('gte')->with('timestamp', ':since')->willReturn('gte');
		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn([['activity_id' => 9, 'user' => 'bob']]);
		$query = $this->createMock(IQueryBuilder::class);
		$query->method('expr')->willReturn($expr);
		$query->method('createNamedParameter')->willReturnCallback(static fn (mixed $value): string => match ($value) {
			'anna' => ':reader',
			''     => ':empty',
			1000   => ':since',
		});
		$query->expects($this->once())->method('from')->with('activity')->willReturnSelf();
		$query->method('select')->willReturnSelf();
		$query->expects($this->once())->method('where')->with('eq')->willReturnSelf();
		$wheres = [];
		$query->method('andWhere')->willReturnCallback(static function (string $where) use (&$wheres, $query): IQueryBuilder {
			$wheres[] = $where;
			return $query;
		});
		$query->expects($this->once())->method('orderBy')->with('timestamp', 'DESC')->willReturnSelf();
		$query->expects($this->once())->method('addOrderBy')->with('activity_id', 'DESC')->willReturnSelf();
		$query->expects($this->once())->method('setMaxResults')->with(500)->willReturnSelf();
		$query->method('executeQuery')->willReturn($result);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($query);

		$this->assertSame([['activity_id' => 9, 'user' => 'bob']], (new ColleagueActivityReader($db))->rows('anna', 1000, 9999));
		$this->assertSame(['neq user :reader', 'neq user :empty', 'gte'], $wheres);
	}

	public function testWithoutTheActivityTableThereIsNothingToRead(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new RuntimeException('no such table'));

		$this->assertNull((new ColleagueActivityReader($db))->rows('anna', 0));
	}
}
