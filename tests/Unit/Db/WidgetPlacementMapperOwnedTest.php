<?php

/**
 * WidgetPlacementMapper owned-placements query test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the one query that reads a user's placements of one widget type.
 *
 * @covers \OCA\LaunchPad\Db\WidgetPlacementMapper
 * @uses \OCA\LaunchPad\Db\WidgetPlacement
 */
class WidgetPlacementMapperOwnedTest extends TestCase {

	public function testOwnedPlacementsAreOneJoinOnTheUsersPersonalDashboards(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'dashboard_id' => 9, 'widget_id' => 'news']];

		$found = (new WidgetPlacementMapper($recorder->connection()))->findOwnedByUserAndWidgetId('alice', 'news');

		$this->assertCount(1, $found);
		$this->assertSame(4, $found[0]->getId());
		$this->assertSame([], preg_grep('/^qb2 /', $recorder->calls), 'one query builder, one query');
		$this->assertContains('qb1.from("launchpad_widget_placements", "p")', $recorder->calls);
		$this->assertContains('qb1.innerJoin("p", "launchpad_dashboards", "d", "d.id = p.dashboard_id")', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("alice", 2, null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("user", 2, null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("news", 2, null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"d.user_id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"d.type\", \":p2\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"p.widget_id\", \":p3\", null)")', $recorder->calls);
		$this->assertContains('qb1.orderBy("d.is_active", "DESC")', $recorder->calls);
	}//end testOwnedPlacementsAreOneJoinOnTheUsersPersonalDashboards()
}//end class
