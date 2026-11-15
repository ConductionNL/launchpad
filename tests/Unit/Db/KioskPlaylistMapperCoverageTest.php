<?php

/**
 * Unit tests for KioskPlaylistMapper: the active-only queries it builds and
 * the kiosk URL it hydrates onto every playlist it returns.
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

use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Lookup by token and id, per-creator and admin listing, and soft-revoke,
 * pinned against a recording connection.
 *
 * @covers \OCA\LaunchPad\Db\KioskPlaylistMapper
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Db\KioskPlaylist
 */
class KioskPlaylistMapperCoverageTest extends TestCase {

	private QueryRecorder $recorder;

	/**
	 * Route calls made on the URL generator.
	 *
	 * @var array<int, string>
	 */
	private array $routes = [];

	private KioskPlaylistMapper $mapper;

	protected function setUp(): void {
		$this->recorder = new QueryRecorder(test: $this);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(
			function (string $route, array $args): string {
				$this->routes[] = $route . ' ' . json_encode($args);
				return 'https://cloud.test/kiosk/' . $args['token'];
			}
		);

		$this->mapper = new KioskPlaylistMapper(
			db: $this->recorder->connection(),
			urlGenerator: $urls,
		);
	}

	/**
	 * The calls of an active-only select filtered on one column.
	 *
	 * @param string $column The filtered column.
	 * @param string $value  The recorded parameter (JSON-encoded value and type).
	 *
	 * @return array<int, string>
	 */
	private function activeSelect(string $column, string $value): array {
		return [
			'qb1 new',
			'qb1.select("*")',
			'qb1.from("launchpad_kiosk_playlists", null)',
			'qb1.expr()',
			'qb1.createNamedParameter(' . $value . ', null)',
			'qb1.expr.eq("' . $column . '", ":p1", null)',
			'qb1.where("eq(\"' . $column . '\", \":p1\", null)")',
			'qb1.expr()',
			'qb1.expr.isNull("revoked_at")',
			'qb1.andWhere("isNull(\"revoked_at\")")',
		];
	}

	public function testFindByTokenReturnsTheActivePlaylistWithItsUrl(): void {
		$this->recorder->rows = [['id' => '3', 'token' => 'k1', 'name' => 'Lobby', 'refresh_seconds' => '30']];

		$playlist = $this->mapper->findByToken(token: 'k1');

		$this->assertSame(
			[...$this->activeSelect(column: 'token', value: '"k1", 2'), 'qb1.executeQuery(null)'],
			$this->recorder->calls
		);
		$this->assertInstanceOf(KioskPlaylist::class, $playlist);
		$this->assertSame(3, $playlist->getId());
		$this->assertSame('Lobby', $playlist->getName());
		$this->assertSame(30, $playlist->getRefreshSeconds());
		$this->assertSame('https://cloud.test/kiosk/k1', $playlist->getUrl());
		$this->assertSame(['launchpad.kiosk.render {"token":"k1"}'], $this->routes);
	}

	public function testFindByIdBindsTheIdAsIntegerAndHydratesTheUrl(): void {
		$this->recorder->rows = [['id' => '8', 'token' => 'k8']];

		$playlist = $this->mapper->findById(id: 8);

		$this->assertSame(
			[...$this->activeSelect(column: 'id', value: '8, 1'), 'qb1.executeQuery(null)'],
			$this->recorder->calls
		);
		$this->assertSame(8, $playlist->getId());
		$this->assertSame('https://cloud.test/kiosk/k8', $playlist->getUrl());
	}

	public function testFindByIdLeavesUrlUnsetWithoutToken(): void {
		$this->recorder->rows = [['id' => '8']];

		$playlist = $this->mapper->findById(id: 8);

		$this->assertNull($playlist->getUrl());
		$this->assertSame([], $this->routes);
	}

	public function testFindByIdThrowsWhenNoActivePlaylistMatches(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Did expect one result but found none');

		$this->mapper->findById(id: 404);
	}

	public function testFindByCreatorListsNewestFirstAndHydratesEveryPlaylist(): void {
		$this->recorder->rows = [
			['id' => '2', 'token' => 'a', 'created_by' => 'alice'],
			['id' => '1', 'token' => 'b', 'created_by' => 'alice'],
		];

		$playlists = $this->mapper->findByCreator(createdBy: 'alice');

		$this->assertSame(
			[
				...$this->activeSelect(column: 'created_by', value: '"alice", 2'),
				'qb1.orderBy("created_at", "DESC")',
				'qb1.executeQuery(null)',
			],
			$this->recorder->calls
		);
		$this->assertSame([2, 1], [$playlists[0]->getId(), $playlists[1]->getId()]);
		$this->assertSame('alice', $playlists[0]->getCreatedBy());
		$this->assertSame(
			['https://cloud.test/kiosk/a', 'https://cloud.test/kiosk/b'],
			[$playlists[0]->getUrl(), $playlists[1]->getUrl()]
		);
	}

	public function testFindByCreatorReturnsEmptyListWithoutRows(): void {
		$this->assertSame([], $this->mapper->findByCreator(createdBy: 'nobody'));
		$this->assertSame([], $this->routes);
	}

	public function testFindAllActiveSelectsEveryUnrevokedPlaylistNewestFirst(): void {
		$this->recorder->rows = [['id' => '5', 'token' => 'x'], ['id' => '4']];

		$playlists = $this->mapper->findAllActive();

		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_kiosk_playlists", null)',
				'qb1.expr()',
				'qb1.expr.isNull("revoked_at")',
				'qb1.where("isNull(\"revoked_at\")")',
				'qb1.orderBy("created_at", "DESC")',
				'qb1.executeQuery(null)',
			],
			$this->recorder->calls
		);
		$this->assertCount(2, $playlists);
		$this->assertSame('https://cloud.test/kiosk/x', $playlists[0]->getUrl());
		$this->assertNull($playlists[1]->getUrl());
		$this->assertSame(['launchpad.kiosk.render {"token":"x"}'], $this->routes);
	}

	public function testSoftRevokeOnlyStampsAnUnrevokedPlaylist(): void {
		$this->mapper->softRevoke(id: 6);

		$calls = $this->recorder->calls;
		$this->assertMatchesRegularExpression('/^qb1\.createNamedParameter\("\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}", 2, null\)$/', $calls[2]);
		$calls[2] = 'date';
		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_kiosk_playlists", null)',
				'date',
				'qb1.set("revoked_at", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter(6, 1, null)',
				'qb1.expr.eq("id", ":p2", null)',
				'qb1.where("eq(\"id\", \":p2\", null)")',
				'qb1.expr()',
				'qb1.expr.isNull("revoked_at")',
				'qb1.andWhere("isNull(\"revoked_at\")")',
				'qb1.executeStatement(null)',
			],
			$calls
		);
	}
}//end class
