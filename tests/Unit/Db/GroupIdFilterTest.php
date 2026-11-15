<?php

/**
 * GroupIdFilterTest
 *
 * Unit tests for GroupIdFilter: the group_id IN (...) restriction bound as a
 * string array.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\GroupIdFilter;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Pins the restriction GroupIdFilter adds.
 *
 * @covers \OCA\LaunchPad\Db\GroupIdFilter
 */
class GroupIdFilterTest extends TestCase {

	/**
	 * The groups are bound as one string-array parameter in a group_id IN condition, set as the WHERE.
	 *
	 * @return void
	 */
	public function testRestrictSetsAGroupIdInCondition(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())
			->method('in')
			->with('group_id', ':groups')
			->willReturn('group_id IN (:groups)');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->expects($this->once())
			->method('createNamedParameter')
			->with(['staff', 'admin'], IQueryBuilder::PARAM_STR_ARRAY)
			->willReturn(':groups');
		$qb->expects($this->once())
			->method('where')
			->with('group_id IN (:groups)')
			->willReturnSelf();
		$qb->expects($this->never())->method('andWhere');

		$this->assertSame($qb, (new GroupIdFilter())->restrict(qb: $qb, groupIds: ['staff', 'admin']));
	}//end testRestrictSetsAGroupIdInCondition()
}//end class
