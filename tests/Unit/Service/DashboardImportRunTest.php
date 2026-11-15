<?php

/**
 * DashboardImportRunTest
 *
 * Unit tests for DashboardImportRun: the imported and skipped counters, the
 * created-dashboard records and the summary an import returns.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardImportRun;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardImportRun.
 */
#[CoversClass(DashboardImportRun::class)]
#[UsesClass(Dashboard::class)]
class DashboardImportRunTest extends TestCase {
	/**
	 * A persisted dashboard with an id and uuid.
	 *
	 * @param int    $id   The id.
	 * @param string $uuid The uuid.
	 *
	 * @return Dashboard
	 */
	private static function dashboard(int $id, string $uuid): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);

		return $dashboard;
	}//end dashboard()

	/**
	 * A fresh run reports nothing and keeps its options.
	 *
	 * @return void
	 */
	public function testFreshRunSummaryIsEmpty(): void {
		$run = new DashboardImportRun(currentUserId: 'alice', preserveUuids: true);

		$this->assertSame('alice', $run->currentUserId);
		$this->assertTrue($run->preserveUuids);
		$this->assertSame(
			['importedDashboardCount' => 0, 'skippedDashboardCount' => 0, 'errors' => [], 'dashboards' => []],
			$run->summary()
		);
	}//end testFreshRunSummaryIsEmpty()

	/**
	 * Imported dashboards are counted and listed with source and new uuid,
	 * skipped ones counted with their error, each in order.
	 *
	 * @return void
	 */
	public function testRecordsImportedAndSkippedInOrder(): void {
		$run = new DashboardImportRun(currentUserId: 'alice', preserveUuids: false);

		$run->recordImported(sourceUuid: 'src-1', persisted: self::dashboard(11, 'new-1'));
		$run->recordSkipped(error: ['uuid' => 'src-2', 'reason' => 'invalid']);
		$run->recordImported(sourceUuid: 'src-3', persisted: self::dashboard(13, 'new-3'));
		$run->recordSkipped(error: ['uuid' => 'src-4', 'reason' => 'duplicate']);

		$this->assertSame(
			[
				'importedDashboardCount' => 2,
				'skippedDashboardCount' => 2,
				'errors' => [
					['uuid' => 'src-2', 'reason' => 'invalid'],
					['uuid' => 'src-4', 'reason' => 'duplicate'],
				],
				'dashboards' => [
					['sourceUuid' => 'src-1', 'uuid' => 'new-1', 'id' => 11],
					['sourceUuid' => 'src-3', 'uuid' => 'new-3', 'id' => 13],
				],
			],
			$run->summary()
		);
	}//end testRecordsImportedAndSkippedInOrder()

	/**
	 * A dashboard without uuid or id is recorded as '' and 0.
	 *
	 * @return void
	 */
	public function testUnsavedDashboardIsRecordedWithEmptyIdentity(): void {
		$run = new DashboardImportRun(currentUserId: 'alice', preserveUuids: false);

		$run->recordImported(sourceUuid: 'src-1', persisted: new Dashboard());

		$this->assertSame([['sourceUuid' => 'src-1', 'uuid' => '', 'id' => 0]], $run->created);
	}//end testUnsavedDashboardIsRecordedWithEmptyIdentity()
}//end class
