<?php

/**
 * DashboardMapperCoverageTest
 *
 * Pins the queries DashboardMapper builds for its finders, the group-bucket
 * filtering of findVisibleToUser, and the tree walks findAncestors and
 * findDescendants (including their MAX_DEPTH cycle guards).
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Query and tree-walk behaviour of DashboardMapper.
 *
 * @covers \OCA\LaunchPad\Db\DashboardMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\Dashboard
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class DashboardMapperCoverageTest extends TestCase {

	/**
	 * The SELECT prefix every selectWhere() query starts with.
	 *
	 * @var array<int, string>
	 */
	private const SELECT = [
		'qb1 new',
		'qb1.select("*")',
		'qb1.from("launchpad_dashboards", null)',
	];

	/**
	 * Run one mapper call on a recording connection.
	 *
	 * @param string                           $method The mapper method.
	 * @param array<int, mixed>                $args   Its arguments.
	 * @param array<int, array<string, mixed>> $rows   The rows every query returns.
	 *
	 * @return array{0: mixed, 1: array<int, string>} The result and the recorded calls.
	 */
	private function invokeMapper(string $method, array $args, array $rows = []): array {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = $rows;
		$result = (new DashboardMapper($recorder->connection()))->$method(...$args);

		return [$result, $recorder->calls];
	}//end invokeMapper()

	/**
	 * The recorded lines of one `column = :pN` condition.
	 *
	 * @param string $verb   where or andWhere.
	 * @param string $column The column.
	 * @param string $value  The JSON-encoded bound value.
	 * @param int    $type   The parameter type.
	 * @param int    $param  The placeholder number.
	 *
	 * @return array<int, string> The lines.
	 */
	private function eq(string $verb, string $column, string $value, int $type, int $param): array {
		return [
			'qb1.expr()',
			'qb1.createNamedParameter(' . $value . ', ' . $type . ', null)',
			'qb1.expr.eq("' . $column . '", ":p' . $param . '", null)',
			'qb1.' . $verb . '("eq(\"' . $column . '\", \":p' . $param . '\", null)")',
		];
	}//end eq()

	/**
	 * Build a dashboard.
	 *
	 * @param string      $uuid    The UUID.
	 * @param string|null $parent  The parent UUID.
	 * @param string|null $groupId The group id.
	 *
	 * @return Dashboard The entity.
	 */
	private function dashboard(string $uuid, ?string $parent = null, ?string $groupId = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setParentUuid($parent);
		$dashboard->setGroupId($groupId);

		return $dashboard;
	}//end dashboard()

	/**
	 * find() binds the id as an integer and maps the single row.
	 *
	 * @return void
	 */
	public function testFindBindsIdAsInteger(): void {
		[$dashboard, $calls] = $this->invokeMapper('find', [7], [['id' => 7, 'uuid' => 'u-7']]);

		$this->assertSame(7, $dashboard->getId());
		$this->assertSame('u-7', $dashboard->getUuid());
		$this->assertSame(
			array_merge(self::SELECT, $this->eq('where', 'id', '7', 1, 1), ['qb1.executeQuery(null)']),
			$calls
		);
	}//end testFindBindsIdAsInteger()

	/**
	 * find() throws DoesNotExistException on an empty result.
	 *
	 * @return void
	 */
	public function testFindThrowsWhenMissing(): void {
		$this->expectException(DoesNotExistException::class);

		$this->invokeMapper('find', [7]);
	}//end testFindThrowsWhenMissing()

	/**
	 * findByUuid() matches the uuid as a string.
	 *
	 * @return void
	 */
	public function testFindByUuid(): void {
		[$dashboard, $calls] = $this->invokeMapper('findByUuid', ['u-1'], [['id' => 3, 'uuid' => 'u-1']]);

		$this->assertSame(3, $dashboard->getId());
		$this->assertSame(
			array_merge(self::SELECT, $this->eq('where', 'uuid', '"u-1"', 2, 1), ['qb1.executeQuery(null)']),
			$calls
		);
	}//end testFindByUuid()

	/**
	 * findByUserId() reads personal dashboards oldest first.
	 *
	 * @return void
	 */
	public function testFindByUserId(): void {
		[$rows, $calls] = $this->invokeMapper('findByUserId', ['alice'], [['id' => 1], ['id' => 2]]);

		$this->assertSame([1, 2], array_map(static fn (Dashboard $d): int => $d->getId(), $rows));
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'user_id', '"alice"', 2, 1),
				$this->eq('andWhere', 'type', '"user"', 2, 2),
				['qb1.orderBy("created_at", "ASC")', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindByUserId()

	/**
	 * findActiveByUserId() also requires is_active = 1.
	 *
	 * @return void
	 */
	public function testFindActiveByUserId(): void {
		[$dashboard, $calls] = $this->invokeMapper('findActiveByUserId', ['alice'], [['id' => 4]]);

		$this->assertSame(4, $dashboard->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'user_id', '"alice"', 2, 1),
				$this->eq('andWhere', 'type', '"user"', 2, 2),
				$this->eq('andWhere', 'is_active', '1', 1, 3),
				['qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindActiveByUserId()

	/**
	 * findAdminTemplates() orders templates by name.
	 *
	 * @return void
	 */
	public function testFindAdminTemplates(): void {
		[$rows, $calls] = $this->invokeMapper('findAdminTemplates', [], [['id' => 9]]);

		$this->assertCount(1, $rows);
		$this->assertSame(9, $rows[0]->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'type', '"admin_template"', 2, 1),
				['qb1.orderBy("name", "ASC")', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindAdminTemplates()

	/**
	 * findByBasedOnTemplate() binds the template id as an integer, ordered by id.
	 *
	 * @return void
	 */
	public function testFindByBasedOnTemplate(): void {
		[$rows, $calls] = $this->invokeMapper('findByBasedOnTemplate', [3]);

		$this->assertSame([], $rows);
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'based_on_template', '3', 1, 1),
				['qb1.orderBy("id", "ASC")', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindByBasedOnTemplate()

	/**
	 * findDefaultTemplate() requires an admin template with is_default = 1.
	 *
	 * @return void
	 */
	public function testFindDefaultTemplate(): void {
		[$dashboard, $calls] = $this->invokeMapper('findDefaultTemplate', [], [['id' => 11]]);

		$this->assertSame(11, $dashboard->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'type', '"admin_template"', 2, 1),
				$this->eq('andWhere', 'is_default', '1', 1, 2),
				['qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindDefaultTemplate()

	/**
	 * With group memberships, group rows are queried with an IN list, and
	 * every bucket skips UUIDs already seen; the group bucket also skips
	 * default-group sentinels.
	 *
	 * @return void
	 */
	public function testFindVisibleToUserFiltersTheGroupBucket(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [
			['id' => 1, 'uuid' => 'p-1', 'group_id' => 'g1'],
			['id' => 2, 'uuid' => 'sentinel', 'group_id' => Dashboard::DEFAULT_GROUP_ID],
			['id' => 3, 'uuid' => 'g-1', 'group_id' => 'g1'],
		];
		$mapper = $this->getMockBuilder(DashboardMapper::class)
			->setConstructorArgs([$recorder->connection()])
			->onlyMethods(['findByUserId', 'findByGroup'])
			->getMock();
		$mapper->expects($this->once())->method('findByUserId')->with('alice')
			->willReturn([$this->dashboard('p-1'), $this->dashboard('p-1')]);
		$mapper->expects($this->once())->method('findByGroup')->with(Dashboard::DEFAULT_GROUP_ID)
			->willReturn([$this->dashboard('sentinel', null, Dashboard::DEFAULT_GROUP_ID), $this->dashboard('g-1')]);

		$result = $mapper->findVisibleToUser('alice', ['g1', 'g2']);

		$this->assertSame(
			[['p-1', Dashboard::SOURCE_USER], ['g-1', Dashboard::SOURCE_GROUP], ['sentinel', Dashboard::SOURCE_DEFAULT]],
			array_map(static fn (array $r): array => [$r['dashboard']->getUuid(), $r['source']], $result)
		);
		$this->assertSame(3, $result[1]['dashboard']->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'type', '"group_shared"', 2, 1),
				[
					'qb1.expr()',
					'qb1.createNamedParameter(["g1","g2"], 102, null)',
					'qb1.expr.in("group_id", ":p2", null)',
					'qb1.andWhere("in(\"group_id\", \":p2\", null)")',
					'qb1.orderBy("created_at", "ASC")',
					'qb1.executeQuery(null)',
				]
			),
			$recorder->calls
		);
	}//end testFindVisibleToUserFiltersTheGroupBucket()

	/**
	 * The private group lookup short-circuits on an empty list without a query.
	 *
	 * @return void
	 */
	public function testFindGroupSharedInGroupsEmptyListSkipsQuery(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');
		$method = new ReflectionMethod(DashboardMapper::class, 'findGroupSharedInGroups');

		$this->assertSame([], $method->invoke(new DashboardMapper($db), []));
	}//end testFindGroupSharedInGroupsEmptyListSkipsQuery()

	/**
	 * findByParent(null) selects roots with IS NULL, ordered by sort order then name.
	 *
	 * @return void
	 */
	public function testFindByParentNullSelectsRoots(): void {
		[$rows, $calls] = $this->invokeMapper('findByParent', [null], [['id' => 1], ['id' => 2]]);

		$this->assertCount(2, $rows);
		$this->assertSame(
			array_merge(
				self::SELECT,
				[
					'qb1.expr()',
					'qb1.expr.isNull("parent_uuid")',
					'qb1.where("isNull(\"parent_uuid\")")',
					'qb1.orderBy("sort_order", "ASC")',
					'qb1.addOrderBy("name", "ASC")',
					'qb1.executeQuery(null)',
				]
			),
			$calls
		);
	}//end testFindByParentNullSelectsRoots()

	/**
	 * findByParent(uuid) matches parent_uuid.
	 *
	 * @return void
	 */
	public function testFindByParentUuid(): void {
		[$rows, $calls] = $this->invokeMapper('findByParent', ['p-1']);

		$this->assertSame([], $rows);
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'parent_uuid', '"p-1"', 2, 1),
				['qb1.orderBy("sort_order", "ASC")', 'qb1.addOrderBy("name", "ASC")', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindByParentUuid()

	/**
	 * findChildBySlug() compares lower(slug) to the lower-cased slug, limit 1.
	 *
	 * @return void
	 */
	public function testFindChildBySlugMatchesCaseInsensitively(): void {
		[$dashboard, $calls] = $this->invokeMapper('findChildBySlug', ['p-1', 'MySlug'], [['id' => 8, 'slug' => 'myslug']]);

		$this->assertInstanceOf(Dashboard::class, $dashboard);
		$this->assertSame(8, $dashboard->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'parent_uuid', '"p-1"', 2, 1),
				[
					'qb1.expr()',
					'qb1.func()',
					'qb1.func.lower("slug")',
					'qb1.createNamedParameter("myslug", 2, null)',
					'qb1.expr.eq(lower("slug"), ":p2", null)',
					'qb1.andWhere("eq(lower(\"slug\"), \":p2\", null)")',
					'qb1.setMaxResults(1)',
					'qb1.executeQuery(null)',
				]
			),
			$calls
		);
	}//end testFindChildBySlugMatchesCaseInsensitively()

	/**
	 * findChildBySlug() under the root returns null when nothing matches.
	 *
	 * @return void
	 */
	public function testFindChildBySlugAtRootReturnsNullWhenMissing(): void {
		[$dashboard, $calls] = $this->invokeMapper('findChildBySlug', [null, 'x']);

		$this->assertNull($dashboard);
		$this->assertContains('qb1.where("isNull(\"parent_uuid\")")', $calls);
		$this->assertContains('qb1.createNamedParameter("x", 2, null)', $calls);
	}//end testFindChildBySlugAtRootReturnsNullWhenMissing()

	/**
	 * findDueScheduled() selects scheduled rows whose publish_at is now or earlier.
	 *
	 * @return void
	 */
	public function testFindDueScheduled(): void {
		$before = date('Y-m-d H:i:s');
		[$rows, $calls] = $this->invokeMapper('findDueScheduled', [], [['id' => 2]]);
		$after = date('Y-m-d H:i:s');

		$this->assertSame(2, $rows[0]->getId());
		$this->assertSame(array_merge(self::SELECT, $this->eq('where', 'publication_status', '"scheduled"', 2, 1)), array_slice($calls, 0, 7));
		$this->assertSame('qb1.expr()', $calls[7]);
		$this->assertMatchesRegularExpression('/^qb1\.createNamedParameter\("(.{19})", 2, null\)$/', $calls[8]);
		$stamp = substr($calls[8], strlen('qb1.createNamedParameter("'), 19);
		$this->assertGreaterThanOrEqual($before, $stamp);
		$this->assertLessThanOrEqual($after, $stamp);
		$this->assertSame(
			['qb1.expr.lte("publish_at", ":p2", null)', 'qb1.andWhere("lte(\"publish_at\", \":p2\", null)")', 'qb1.executeQuery(null)'],
			array_slice($calls, 9)
		);
	}//end testFindDueScheduled()

	/**
	 * The gallery filters by category and sorts NULL categories last, then by name.
	 *
	 * @return void
	 */
	public function testGalleryByCategorySortsNullCategoriesLast(): void {
		[$rows, $calls] = $this->invokeMapper('findAllTemplatesForGallery', ['ops', 'name'], [['id' => 1]]);

		$this->assertCount(1, $rows);
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'type', '"admin_template"', 2, 1),
				$this->eq('andWhere', 'template_category', '"ops"', 2, 2),
				[
					'qb1.createFunction("CASE WHEN template_category IS NULL THEN 1 ELSE 0 END")',
					'qb1.orderBy(CASE WHEN template_category IS NULL THEN 1 ELSE 0 END, "ASC")',
					'qb1.addOrderBy("template_category", "ASC")',
					'qb1.addOrderBy("name", "ASC")',
					'qb1.executeQuery(null)',
				]
			),
			$calls
		);
	}//end testGalleryByCategorySortsNullCategoriesLast()

	/**
	 * An empty category is not a filter, and updatedAt sorts newest first only.
	 *
	 * @return void
	 */
	public function testGalleryByUpdatedAtWithEmptyCategory(): void {
		[, $calls] = $this->invokeMapper('findAllTemplatesForGallery', ['', 'updatedAt']);

		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'type', '"admin_template"', 2, 1),
				['qb1.orderBy("updated_at", "DESC")', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testGalleryByUpdatedAtWithEmptyCategory()

	/**
	 * findOwnedByUserAndUuid() matches owner, uuid and personal type, limit 1.
	 *
	 * @return void
	 */
	public function testFindOwnedByUserAndUuid(): void {
		[$dashboard, $calls] = $this->invokeMapper('findOwnedByUserAndUuid', ['alice', 'u-1'], [['id' => 6, 'uuid' => 'u-1']]);

		$this->assertSame(6, $dashboard->getId());
		$this->assertSame(
			array_merge(
				self::SELECT,
				$this->eq('where', 'user_id', '"alice"', 2, 1),
				$this->eq('andWhere', 'uuid', '"u-1"', 2, 2),
				$this->eq('andWhere', 'type', '"user"', 2, 3),
				['qb1.setMaxResults(1)', 'qb1.executeQuery(null)']
			),
			$calls
		);
	}//end testFindOwnedByUserAndUuid()

	/**
	 * findOwnedByUserAndUuid() returns null when no row matches.
	 *
	 * @return void
	 */
	public function testFindOwnedByUserAndUuidReturnsNullWhenMissing(): void {
		[$dashboard] = $this->invokeMapper('findOwnedByUserAndUuid', ['alice', 'nope']);

		$this->assertNull($dashboard);
	}//end testFindOwnedByUserAndUuidReturnsNullWhenMissing()

	/**
	 * A mapper whose findByUuid() reads from a map, throwing when absent.
	 *
	 * @param array<string, Dashboard> $byUuid The dashboards by UUID.
	 *
	 * @return DashboardMapper The partial mock.
	 */
	private function uuidMapper(array $byUuid): DashboardMapper {
		$mapper = $this->getMockBuilder(DashboardMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByUuid'])
			->getMock();
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($byUuid): Dashboard {
				if (isset($byUuid[$uuid]) === false) {
					throw new DoesNotExistException('missing ' . $uuid);
				}

				return $byUuid[$uuid];
			}
		);

		return $mapper;
	}//end uuidMapper()

	/**
	 * findAncestors() walks up to the root and returns the chain root-first.
	 *
	 * @return void
	 */
	public function testFindAncestorsReturnsRootFirst(): void {
		$mapper = $this->uuidMapper([
			'c' => $this->dashboard('c', 'b'),
			'b' => $this->dashboard('b', 'a'),
			'a' => $this->dashboard('a', ''),
		]);

		$uuids = array_map(static fn (Dashboard $d): ?string => $d->getUuid(), $mapper->findAncestors('c'));

		$this->assertSame(['a', 'b'], $uuids);
	}//end testFindAncestorsReturnsRootFirst()

	/**
	 * findAncestors() stops at a missing start node or a dangling parent link.
	 *
	 * @return void
	 */
	public function testFindAncestorsStopsOnMissingNodes(): void {
		$mapper = $this->uuidMapper([
			'c' => $this->dashboard('c', 'b'),
			'b' => $this->dashboard('b', 'gone'),
			'x' => $this->dashboard('x', 'gone'),
		]);

		$this->assertSame([], $mapper->findAncestors('unknown'));
		$this->assertSame([], $mapper->findAncestors('x'));
		$this->assertSame(['b'], array_map(static fn (Dashboard $d): ?string => $d->getUuid(), $mapper->findAncestors('c')));
	}//end testFindAncestorsStopsOnMissingNodes()

	/**
	 * A parent cycle is cut off after MAX_DEPTH steps.
	 *
	 * @return void
	 */
	public function testFindAncestorsCapsACycleAtMaxDepth(): void {
		$mapper = $this->uuidMapper([
			'a' => $this->dashboard('a', 'b'),
			'b' => $this->dashboard('b', 'a'),
		]);

		$uuids = array_map(static fn (Dashboard $d): ?string => $d->getUuid(), $mapper->findAncestors('a'));

		$this->assertSame(Dashboard::MAX_DEPTH, count($uuids));
		$this->assertSame(['b', 'a', 'b', 'a', 'b'], $uuids);
	}//end testFindAncestorsCapsACycleAtMaxDepth()

	/**
	 * A mapper whose findByParent() reads from a map.
	 *
	 * @param array<string, array<int, Dashboard>> $children Children by parent UUID.
	 * @param array<int, string|null>              $asked    Receives every parent asked for.
	 *
	 * @return DashboardMapper The partial mock.
	 */
	private function parentMapper(array $children, array &$asked): DashboardMapper {
		$mapper = $this->getMockBuilder(DashboardMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByParent'])
			->getMock();
		$mapper->method('findByParent')->willReturnCallback(
			static function (?string $parentUuid) use ($children, &$asked): array {
				$asked[] = $parentUuid;
				return $children[(string)$parentUuid] ?? [];
			}
		);

		return $mapper;
	}//end parentMapper()

	/**
	 * findDescendants() walks breadth-first and does not descend from UUID-less rows.
	 *
	 * @return void
	 */
	public function testFindDescendantsBreadthFirst(): void {
		$asked = [];
		$mapper = $this->parentMapper(
			[
				'root' => [$this->dashboard('a', 'root'), $this->dashboard('', 'root')],
				'a'    => [$this->dashboard('a1', 'a')],
			],
			$asked
		);

		$uuids = array_map(static fn (Dashboard $d): ?string => $d->getUuid(), $mapper->findDescendants('root'));

		$this->assertSame(['a', '', 'a1'], $uuids);
		$this->assertSame(['root', 'a', 'a1'], $asked);
	}//end testFindDescendantsBreadthFirst()

	/**
	 * A child cycle is cut off after MAX_DEPTH levels.
	 *
	 * @return void
	 */
	public function testFindDescendantsCapsACycleAtMaxDepth(): void {
		$asked = [];
		$mapper = $this->parentMapper(
			[
				'a' => [$this->dashboard('b', 'a')],
				'b' => [$this->dashboard('a', 'b')],
			],
			$asked
		);

		$uuids = array_map(static fn (Dashboard $d): ?string => $d->getUuid(), $mapper->findDescendants('a'));

		$this->assertSame(['b', 'a', 'b', 'a', 'b'], $uuids);
		$this->assertCount(Dashboard::MAX_DEPTH, $asked);
	}//end testFindDescendantsCapsACycleAtMaxDepth()
}//end class
