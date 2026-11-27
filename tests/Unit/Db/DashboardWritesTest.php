<?php

/**
 * DashboardWritesTest
 *
 * The dashboard and share mappers count their writes.
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\DashboardWrites;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\DashboardWrites
 * @uses \OCA\LaunchPad\Db\DashboardMapper
 * @uses \OCA\LaunchPad\Db\DashboardShareMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 */
class DashboardWritesTest extends TestCase {

	/**
	 * Bulk statements on both tables count as writes.
	 *
	 * @return void
	 */
	public function testBulkStatementsCountAsWrites(): void {
		$recorder = new QueryRecorder(test: $this);
		$dashboards = new DashboardMapper($recorder->connection());
		$shares = new DashboardShareMapper($recorder->connection());

		$before = DashboardWrites::count();
		$dashboards->deactivateAllForUser('alice');
		$dashboards->clearDefaultTemplates();
		$shares->deleteByRecipientUser('alice');

		$this->assertSame($before + 3, DashboardWrites::count());
	}//end testBulkStatementsCountAsWrites()

	/**
	 * Reads do not count.
	 *
	 * @return void
	 */
	public function testReadsDoNotCount(): void {
		$recorder = new QueryRecorder(test: $this);
		$before = DashboardWrites::count();

		(new DashboardMapper($recorder->connection()))->findByUserId('alice');

		$this->assertSame($before, DashboardWrites::count());
	}//end testReadsDoNotCount()
}//end class
