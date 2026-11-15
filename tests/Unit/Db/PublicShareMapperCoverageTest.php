<?php

/**
 * Unit tests for PublicShareMapper: the queries it builds and the URL it
 * hydrates onto every share it returns.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Db
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Token lookup, per-dashboard listing, active filtering, view counting and
 * revocation, pinned against a recording connection.
 *
 * @covers \OCA\LaunchPad\Db\PublicShareMapper
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Db\PublicShare
 */
class PublicShareMapperCoverageTest extends TestCase {

	private QueryRecorder $recorder;

	/** @var ICache&MockObject */
	private ICache $cache;

	/**
	 * Route calls made on the URL generator.
	 *
	 * @var array<int, string>
	 */
	private array $routes = [];

	private PublicShareMapper $mapper;

	protected function setUp(): void {
		$this->recorder = new QueryRecorder(test: $this);
		$this->cache = $this->createMock(ICache::class);
		$factory = $this->createMock(ICacheFactory::class);
		$factory->expects($this->once())->method('createDistributed')->with('launchpad_vc_')->willReturn($this->cache);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(
			function (string $route, array $args): string {
				$this->routes[] = $route . ' ' . json_encode($args);
				return 'https://cloud.test/s/' . $args['token'];
			}
		);

		$this->mapper = new PublicShareMapper(
			db: $this->recorder->connection(),
			cacheFactory: $factory,
			urlGenerator: $urls,
		);
	}

	/**
	 * The recorded calls with the current timestamp replaced by `<date>`.
	 *
	 * @return array<int, string>
	 */
	private function calls(): array {
		return array_map(
			static fn (string $line): string => (string)preg_replace('/"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/', '"<date>"', $line),
			$this->recorder->calls
		);
	}

	public function testFindByTokenSelectsOnTokenAndHydratesTheUrl(): void {
		$this->recorder->rows = [['id' => '5', 'token' => 'abc', 'dashboard_uuid' => 'd-1', 'view_count' => '3']];

		$share = $this->mapper->findByToken(token: 'abc');

		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_public_shares", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("abc", 2, null)',
				'qb1.expr.eq("token", ":p1", null)',
				'qb1.where("eq(\"token\", \":p1\", null)")',
				'qb1.executeQuery(null)',
			],
			$this->calls()
		);
		$this->assertInstanceOf(PublicShare::class, $share);
		$this->assertSame(5, $share->getId());
		$this->assertSame('d-1', $share->getDashboardUuid());
		$this->assertSame(3, $share->getViewCount());
		$this->assertSame('https://cloud.test/s/abc', $share->getUrl());
		$this->assertSame(['launchpad.publicShare.show {"token":"abc"}'], $this->routes);
	}

	public function testFindByTokenLeavesUrlUnsetWhenTheRowHasNoToken(): void {
		$this->recorder->rows = [['id' => '9']];

		$share = $this->mapper->findByToken(token: 'abc');

		$this->assertSame(9, $share->getId());
		$this->assertNull($share->getUrl());
		$this->assertSame([], $this->routes);
	}

	public function testFindByTokenThrowsWhenNoShareMatches(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Did expect one result but found none');

		$this->mapper->findByToken(token: 'missing');
	}

	public function testFindByDashboardUuidOrdersByCreationAndHydratesEveryShare(): void {
		$this->recorder->rows = [
			['id' => '1', 'token' => 't1', 'dashboard_uuid' => 'd-1'],
			['id' => '2', 'dashboard_uuid' => 'd-1', 'revoked_at' => '2026-01-01 00:00:00'],
		];

		$shares = $this->mapper->findByDashboardUuid(dashboardUuid: 'd-1');

		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_public_shares", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.orderBy("created_at", "ASC")',
				'qb1.executeQuery(null)',
			],
			$this->calls()
		);
		$this->assertCount(2, $shares);
		$this->assertSame([1, 2], [$shares[0]->getId(), $shares[1]->getId()]);
		$this->assertSame('https://cloud.test/s/t1', $shares[0]->getUrl());
		$this->assertNull($shares[1]->getUrl());
		$this->assertSame('2026-01-01 00:00:00', $shares[1]->getRevokedAt());
		$this->assertSame(['launchpad.publicShare.show {"token":"t1"}'], $this->routes);
	}

	public function testFindByDashboardUuidReturnsEmptyListWithoutRows(): void {
		$this->assertSame([], $this->mapper->findByDashboardUuid(dashboardUuid: 'd-2'));
		$this->assertSame([], $this->routes);
	}

	public function testFindActiveByDashboardUuidFiltersRevokedAndExpiredShares(): void {
		$this->recorder->rows = [['id' => '4', 'token' => 'live', 'dashboard_uuid' => 'd-3']];

		$shares = $this->mapper->findActiveByDashboardUuid(dashboardUuid: 'd-3');

		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_public_shares", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-3", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.expr()',
				'qb1.expr.isNull("revoked_at")',
				'qb1.andWhere("isNull(\"revoked_at\")")',
				'qb1.expr()',
				'qb1.expr()',
				'qb1.expr.isNull("expires_at")',
				'qb1.expr()',
				'qb1.createNamedParameter("<date>", 2, null)',
				'qb1.expr.gt("expires_at", ":p2", null)',
				'qb1.expr.orX("isNull(\"expires_at\")", "gt(\"expires_at\", \":p2\", null)")',
				'qb1.andWhere(orX("isNull(\"expires_at\")", "gt(\"expires_at\", \":p2\", null)"))',
				'qb1.orderBy("created_at", "ASC")',
				'qb1.executeQuery(null)',
			],
			$this->calls()
		);
		$this->assertCount(1, $shares);
		$this->assertSame(4, $shares[0]->getId());
		$this->assertSame('https://cloud.test/s/live', $shares[0]->getUrl());
	}

	public function testIncrementViewCountCountsOnceAndArmsTheDebounceKey(): void {
		$key = 'tok_' . md5('10.0.0.1');
		$this->cache->expects($this->once())->method('hasKey')->with($key)->willReturn(false);
		$this->cache->expects($this->once())->method('set')->with($key, 1, 60);

		$this->mapper->incrementViewCount(id: 7, token: 'tok', ipAddress: '10.0.0.1');

		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_public_shares", null)',
				'qb1.createFunction("view_count + 1")',
				'qb1.set("view_count", view_count + 1)',
				'qb1.createNamedParameter("<date>", 2, null)',
				'qb1.set("last_viewed_at", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter(7, 1, null)',
				'qb1.expr.eq("id", ":p2", null)',
				'qb1.where("eq(\"id\", \":p2\", null)")',
				'qb1.executeStatement(null)',
			],
			$this->calls()
		);
	}

	public function testIncrementViewCountOnlyStampsLastViewedInsideTheWindow(): void {
		$this->cache->expects($this->once())->method('hasKey')->with('tok_' . md5('10.0.0.1'))->willReturn(true);
		$this->cache->expects($this->never())->method('set');

		$this->mapper->incrementViewCount(id: 7, token: 'tok', ipAddress: '10.0.0.1');

		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_public_shares", null)',
				'qb1.createNamedParameter("<date>", 2, null)',
				'qb1.set("last_viewed_at", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter(7, 1, null)',
				'qb1.expr.eq("id", ":p2", null)',
				'qb1.where("eq(\"id\", \":p2\", null)")',
				'qb1.executeStatement(null)',
			],
			$this->calls()
		);
	}

	public function testRevokeByDashboardUuidOnlyTouchesUnrevokedSharesAndReturnsTheCount(): void {
		$this->assertSame(1, $this->mapper->revokeByDashboardUuid(dashboardUuid: 'd-4'));

		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_public_shares", null)',
				'qb1.createNamedParameter("<date>", 2, null)',
				'qb1.set("revoked_at", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter("d-4", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p2", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p2\", null)")',
				'qb1.expr()',
				'qb1.expr.isNull("revoked_at")',
				'qb1.andWhere("isNull(\"revoked_at\")")',
				'qb1.executeStatement(null)',
			],
			$this->calls()
		);
	}

	public function testSoftRevokeOnlyStampsAnUnrevokedShare(): void {
		$this->mapper->softRevoke(id: 12);

		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_public_shares", null)',
				'qb1.createNamedParameter("<date>", 2, null)',
				'qb1.set("revoked_at", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter(12, 1, null)',
				'qb1.expr.eq("id", ":p2", null)',
				'qb1.where("eq(\\"id\\", \\":p2\\", null)")',
				'qb1.expr()',
				'qb1.expr.isNull("revoked_at")',
				'qb1.andWhere("isNull(\\"revoked_at\\")")',
				'qb1.executeStatement(null)',
			],
			$this->calls()
		);
	}
}//end class
