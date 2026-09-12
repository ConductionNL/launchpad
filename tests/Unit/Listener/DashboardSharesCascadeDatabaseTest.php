<?php

/**
 * DashboardSharesCascadeDatabaseTest
 *
 * Row-level proof that deleting a dashboard removes its user and group
 * shares (dashboard-cascade-events REQ-CSC-002, REQ-CSC-003), through the
 * real delete path: `DashboardService::deleteDashboard()`, the real
 * dispatcher and the real cascade listeners, against a real database.
 *
 * It needs a live Nextcloud database. phpunit.xml sets PHPUNIT_USE_NC_BOOTSTRAP=1,
 * so it runs wherever this tree sits inside an installed instance, which is
 * what CI's PHPUnit job is. This used to say it skipped in CI: it did, until
 * that line was added. A plain clone still skips it. The e2e twin is
 * "deleting a dashboard removes its user and group shares" in
 * tests/e2e/ci/dashboard-share-api.spec.ts.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class DashboardSharesCascadeDatabaseTest extends TestCase {
	/**
	 * Skip unless a live Nextcloud (and so a live database) is bootstrapped.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if (class_exists(class: '\OC', autoload: false) === false) {
			$this->markTestSkipped(
				message: 'Needs a live Nextcloud database. CI boots one (phpunit.xml sets '
				. 'PHPUNIT_USE_NC_BOOTSTRAP=1); a plain clone does not.'
			);
		}
	}//end setUp()

	/**
	 * Delete a dashboard that has a user share and a group share; both rows go.
	 *
	 * @return void
	 */
	public function testDeletingADashboardRemovesItsUserAndGroupShares(): void {
		$dashboards = Server::get(DashboardMapper::class);
		$shares = Server::get(DashboardShareMapper::class);
		$owner = 'cascade-owner-' . bin2hex(random_bytes(4));

		$dashboard = $dashboards->insert(
			Server::get(DashboardFactory::class)->create(userId: $owner, name: 'Cascade test')
		);
		$dashboardId = (int)$dashboard->getId();

		try {
			foreach ([['user', 'cascade-recipient'], ['group', 'cascade-group']] as [$type, $with]) {
				$share = new DashboardShare();
				$share->setDashboardId($dashboardId);
				$share->setShareType($type);
				$share->setShareWith($with);
				$share->setPermissionLevel('view_only');
				$share->setCreatedAt(gmdate(format: 'Y-m-d H:i:s'));
				$share->setUpdatedAt(gmdate(format: 'Y-m-d H:i:s'));
				$shares->insert($share);
			}

			$this->assertSame(2, $this->countShares(dashboardId: $dashboardId), 'CONTROL: both shares exist before the delete');

			Server::get(DashboardService::class)->deleteDashboard(dashboardId: $dashboardId, userId: $owner);

			$this->assertSame(
				0,
				$this->countShares(dashboardId: $dashboardId),
				'deleting the dashboard left its user and group shares behind'
			);
		} finally {
			// Leaves nothing behind whether or not the assertion held.
			$shares->deleteByDashboardId(dashboardId: $dashboardId);
		}//end try
	}//end testDeletingADashboardRemovesItsUserAndGroupShares()

	/**
	 * Count the share rows pointing at a dashboard id.
	 *
	 * @param int $dashboardId The dashboard id.
	 *
	 * @return int The row count.
	 */
	private function countShares(int $dashboardId): int {
		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'n'))
			->from('launchpad_dashboard_shares')
			->where($qb->expr()->eq('dashboard_id', $qb->createNamedParameter($dashboardId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}//end countShares()
}//end class
