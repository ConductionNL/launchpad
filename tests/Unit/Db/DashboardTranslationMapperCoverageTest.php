<?php

/**
 * DashboardTranslationMapperCoverageTest
 *
 * Unit tests for DashboardTranslationMapper: lookup by id, the per-dashboard
 * listing, the per-language and primary lookups, the locale-matching
 * fallback, the cascade delete and the primary-flag reset, asserted on the
 * entities returned and on the exact query each method builds.
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

use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\DashboardTranslationMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\DashboardTranslation
 */
class DashboardTranslationMapperCoverageTest extends TestCase {

	/**
	 * A connection whose successive query builders return successive row sets.
	 *
	 * @param QueryRecorder                                  $recorder The recorder.
	 * @param array<int, array<int, array<string, mixed>>> $rowSets  Rows per builder, in order.
	 *
	 * @return IDBConnection The connection double.
	 */
	private function sequencedConnection(QueryRecorder $recorder, array $rowSets): IDBConnection {
		$inner = $recorder->connection();
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(
			static function () use ($recorder, $inner, &$rowSets): IQueryBuilder {
				$recorder->rows = array_shift($rowSets) ?? [];
				return $inner->getQueryBuilder();
			}
		);

		return $db;
	}//end sequencedConnection()

	/**
	 * Find by id filters on the integer id and hydrates the row.
	 *
	 * @return void
	 */
	public function testFindSelectsByIntegerId(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 7, 'dashboard_uuid' => 'd-1', 'language_code' => 'nl', 'name' => 'Startpagina']];

		$row = (new DashboardTranslationMapper($recorder->connection()))->find(7);

		$this->assertSame(7, $row->getId());
		$this->assertSame('d-1', $row->getDashboardUuid());
		$this->assertSame('nl', $row->getLanguageCode());
		$this->assertSame('Startpagina', $row->getName());
		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_dash_translations", null)',
				'qb1.expr()',
				'qb1.createNamedParameter(7, 1, null)',
				'qb1.expr.eq("id", ":p1", null)',
				'qb1.where("eq(\"id\", \":p1\", null)")',
				'qb1.executeQuery(null)',
			],
			$recorder->calls
		);
	}//end testFindSelectsByIntegerId()

	/**
	 * Find by id throws when no row exists.
	 *
	 * @return void
	 */
	public function testFindThrowsWhenMissing(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->expectException(DoesNotExistException::class);
		(new DashboardTranslationMapper($recorder->connection()))->find(99);
	}//end testFindThrowsWhenMissing()

	/**
	 * The per-dashboard listing filters on the UUID and orders by language.
	 *
	 * @return void
	 */
	public function testFindByDashboardUuidIsOrderedByLanguage(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [
			['id' => 1, 'dashboard_uuid' => 'd-1', 'language_code' => 'de'],
			['id' => 2, 'dashboard_uuid' => 'd-1', 'language_code' => 'nl'],
		];

		$rows = (new DashboardTranslationMapper($recorder->connection()))->findByDashboardUuid('d-1');

		$this->assertSame(['de', 'nl'], array_map(static fn (DashboardTranslation $row): ?string => $row->getLanguageCode(), $rows));
		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_dash_translations", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.orderBy("language_code", "ASC")',
				'qb1.executeQuery(null)',
			],
			$recorder->calls
		);
	}//end testFindByDashboardUuidIsOrderedByLanguage()

	/**
	 * The language lookup normalises the code and limits to one row.
	 *
	 * @return void
	 */
	public function testFindByLanguageNormalisesTheCode(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'dashboard_uuid' => 'd-1', 'language_code' => 'nl']];

		$row = (new DashboardTranslationMapper($recorder->connection()))->findByDashboardUuidAndLanguage('d-1', ' NL-be ');

		$this->assertNotNull($row);
		$this->assertSame(4, $row->getId());
		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_dash_translations", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.expr()',
				'qb1.createNamedParameter("nl", 2, null)',
				'qb1.expr.eq("language_code", ":p2", null)',
				'qb1.andWhere("eq(\"language_code\", \":p2\", null)")',
				'qb1.setMaxResults(1)',
				'qb1.executeQuery(null)',
			],
			$recorder->calls
		);
	}//end testFindByLanguageNormalisesTheCode()

	/**
	 * An empty language code returns null without querying.
	 *
	 * @return void
	 */
	public function testFindByLanguageWithEmptyCodeDoesNotQuery(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'dashboard_uuid' => 'd-1', 'language_code' => 'nl']];

		$row = (new DashboardTranslationMapper($recorder->connection()))->findByDashboardUuidAndLanguage('d-1', '   ');

		$this->assertNull($row);
		$this->assertSame([], $recorder->calls);
	}//end testFindByLanguageWithEmptyCodeDoesNotQuery()

	/**
	 * A missing language variant returns null.
	 *
	 * @return void
	 */
	public function testFindByLanguageReturnsNullWhenMissing(): void {
		$recorder = new QueryRecorder(test: $this);

		$row = (new DashboardTranslationMapper($recorder->connection()))->findByDashboardUuidAndLanguage('d-1', 'fr_FR');

		$this->assertNull($row);
		$this->assertContains('qb1.createNamedParameter("fr", 2, null)', $recorder->calls);
		$this->assertContains('qb1.executeQuery(null)', $recorder->calls);
	}//end testFindByLanguageReturnsNullWhenMissing()

	/**
	 * The primary lookup filters on is_primary = 1 (integer) and limits to one row.
	 *
	 * @return void
	 */
	public function testFindPrimaryFiltersOnThePrimaryFlag(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 9, 'dashboard_uuid' => 'd-1', 'language_code' => 'en', 'is_primary' => 1]];

		$row = (new DashboardTranslationMapper($recorder->connection()))->findPrimaryByDashboardUuid('d-1');

		$this->assertNotNull($row);
		$this->assertSame(9, $row->getId());
		$this->assertSame(1, $row->getIsPrimary());
		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_dash_translations", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.expr()',
				'qb1.createNamedParameter(1, 1, null)',
				'qb1.expr.eq("is_primary", ":p2", null)',
				'qb1.andWhere("eq(\"is_primary\", \":p2\", null)")',
				'qb1.setMaxResults(1)',
				'qb1.executeQuery(null)',
			],
			$recorder->calls
		);
	}//end testFindPrimaryFiltersOnThePrimaryFlag()

	/**
	 * A dashboard with no primary variant returns null.
	 *
	 * @return void
	 */
	public function testFindPrimaryReturnsNullWhenMissing(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertNull((new DashboardTranslationMapper($recorder->connection()))->findPrimaryByDashboardUuid('d-1'));
		$this->assertContains('qb1.executeQuery(null)', $recorder->calls);
	}//end testFindPrimaryReturnsNullWhenMissing()

	/**
	 * Locale matching returns the exact variant without asking for the primary.
	 *
	 * @return void
	 */
	public function testLocaleMatchingPrefersTheExactVariant(): void {
		$recorder = new QueryRecorder(test: $this);
		$db = $this->sequencedConnection(
			$recorder,
			[
				[['id' => 3, 'dashboard_uuid' => 'd-1', 'language_code' => 'de']],
				[['id' => 9, 'dashboard_uuid' => 'd-1', 'language_code' => 'en']],
			]
		);

		$row = (new DashboardTranslationMapper($db))->findByDashboardUuidWithLocaleMatching('d-1', 'de-DE');

		$this->assertNotNull($row);
		$this->assertSame(3, $row->getId());
		$this->assertSame('de', $row->getLanguageCode());
		$this->assertContains('qb1.createNamedParameter("de", 2, null)', $recorder->calls);
		$this->assertNotContains('qb2 new', $recorder->calls);
	}//end testLocaleMatchingPrefersTheExactVariant()

	/**
	 * Locale matching falls back to the primary variant when no exact one exists.
	 *
	 * @return void
	 */
	public function testLocaleMatchingFallsBackToThePrimary(): void {
		$recorder = new QueryRecorder(test: $this);
		$db = $this->sequencedConnection(
			$recorder,
			[
				[],
				[['id' => 9, 'dashboard_uuid' => 'd-1', 'language_code' => 'en', 'is_primary' => 1]],
			]
		);

		$row = (new DashboardTranslationMapper($db))->findByDashboardUuidWithLocaleMatching('d-1', 'fr_FR');

		$this->assertNotNull($row);
		$this->assertSame(9, $row->getId());
		$this->assertSame('en', $row->getLanguageCode());
		$this->assertContains('qb1.createNamedParameter("fr", 2, null)', $recorder->calls);
		$this->assertContains('qb2.expr.eq("is_primary", ":p4", null)', $recorder->calls);
	}//end testLocaleMatchingFallsBackToThePrimary()

	/**
	 * An empty preferred language skips straight to the primary; no rows at all gives null.
	 *
	 * @return void
	 */
	public function testLocaleMatchingWithEmptyLanguageAndNoRowsReturnsNull(): void {
		$recorder = new QueryRecorder(test: $this);

		$row = (new DashboardTranslationMapper($recorder->connection()))->findByDashboardUuidWithLocaleMatching('d-1', '');

		$this->assertNull($row);
		$this->assertContains('qb1.expr.eq("is_primary", ":p2", null)', $recorder->calls);
		$this->assertNotContains('qb2 new', $recorder->calls);
	}//end testLocaleMatchingWithEmptyLanguageAndNoRowsReturnsNull()

	/**
	 * The cascade delete filters on the UUID and returns the affected count.
	 *
	 * @return void
	 */
	public function testDeleteByDashboardUuid(): void {
		$recorder = new QueryRecorder(test: $this);

		$count = (new DashboardTranslationMapper($recorder->connection()))->deleteByDashboardUuid('d-1');

		$this->assertSame(1, $count);
		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete("launchpad_dash_translations", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p1", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				'qb1.executeStatement(null)',
			],
			$recorder->calls
		);
	}//end testDeleteByDashboardUuid()

	/**
	 * Clearing the primary flag without an exception touches every variant.
	 *
	 * @return void
	 */
	public function testClearPrimaryResetsEveryVariant(): void {
		$recorder = new QueryRecorder(test: $this);

		$count = (new DashboardTranslationMapper($recorder->connection()))->clearPrimary('d-1');

		$this->assertSame(1, $count);
		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_dash_translations", null)',
				'qb1.createNamedParameter(0, 1, null)',
				'qb1.set("is_primary", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p2", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p2\", null)")',
				'qb1.executeStatement(null)',
			],
			$recorder->calls
		);
	}//end testClearPrimaryResetsEveryVariant()

	/**
	 * Clearing the primary flag with an exception leaves that row out.
	 *
	 * @return void
	 */
	public function testClearPrimaryExcludesTheGivenRow(): void {
		$recorder = new QueryRecorder(test: $this);

		$count = (new DashboardTranslationMapper($recorder->connection()))->clearPrimary('d-1', 4);

		$this->assertSame(1, $count);
		$this->assertSame(
			[
				'qb1 new',
				'qb1.update("launchpad_dash_translations", null)',
				'qb1.createNamedParameter(0, 1, null)',
				'qb1.set("is_primary", ":p1")',
				'qb1.expr()',
				'qb1.createNamedParameter("d-1", 2, null)',
				'qb1.expr.eq("dashboard_uuid", ":p2", null)',
				'qb1.where("eq(\"dashboard_uuid\", \":p2\", null)")',
				'qb1.expr()',
				'qb1.createNamedParameter(4, 1, null)',
				'qb1.expr.neq("id", ":p3", null)',
				'qb1.andWhere("neq(\"id\", \":p3\", null)")',
				'qb1.executeStatement(null)',
			],
			$recorder->calls
		);
	}//end testClearPrimaryExcludesTheGivenRow()

	/**
	 * The variant count reads the cnt column of the filtered count query.
	 *
	 * @return void
	 */
	public function testCountByDashboardUuid(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['cnt' => '3']];

		$count = (new DashboardTranslationMapper($recorder->connection()))->countByDashboardUuid('d-1');

		$this->assertSame(3, $count);
		$this->assertContains('qb1.from("launchpad_dash_translations", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);
	}//end testCountByDashboardUuid()
}//end class
