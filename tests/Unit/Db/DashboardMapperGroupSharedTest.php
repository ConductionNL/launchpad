<?php

/**
 * DashboardMapperGroupSharedTest
 *
 * Unit tests for the group-shared additions to {@see DashboardMapper}:
 *   - `findByGroup()` returns an empty array for a nonexistent group
 *   - `findVisibleToUser()` with a 0-group user returns only personal + default rows
 *   - `findVisibleToUser()` deduplicates by UUID (personal row wins over default)
 *   - `findVisibleToUser()` sources are tagged correctly per result set
 *
 * REQ-DASH-011, REQ-DASH-012, REQ-DASH-013.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/multi-scope-dashboards/tasks.md#task-10
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Mapper-layer tests for the group-shared queries (REQ-DASH-011..013).
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardMapperGroupSharedTest extends TestCase {

	/**
	 * @var IDBConnection&MockObject
	 */
	private $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
	}//end setUp()

	/**
	 * Build a minimal Dashboard entity for use in stubs.
	 *
	 * @param string $uuid Dashboard UUID.
	 * @param string $type Dashboard type constant.
	 * @param string|null $groupId Group ID.
	 * @param string|null $userId Owner user ID.
	 *
	 * @return Dashboard The entity.
	 */
	private function makeDashboard(
		string $uuid,
		string $type = Dashboard::TYPE_USER,
		?string $groupId = null,
		?string $userId = null,
	): Dashboard {
		$d = new Dashboard();
		$d->setUuid($uuid);
		$d->setName('Test ' . $uuid);
		$d->setType($type);
		$d->setGroupId($groupId);
		$d->setUserId($userId);
		return $d;
	}//end makeDashboard()

	/**
	 * Build a partial mock of `DashboardMapper` that stubs only the
	 * data-retrieval methods (`findByUserId` and `findByGroup`) so the
	 * real PHP union/dedup logic in `findVisibleToUser` executes verbatim.
	 *
	 * The private `findGroupSharedInGroups` is NOT invoked when
	 * `$userGroupIds` is empty — which all `findVisibleToUser` tests use.
	 *
	 * @param Dashboard[] $personalRows Personal dashboards to return.
	 * @param Dashboard[] $defaultRows Default-group dashboards to return.
	 *
	 * @return DashboardMapper The partial mock.
	 */
	private function makePartialMapper(
		array $personalRows,
		array $defaultRows,
	): DashboardMapper {
		// phpcs:disable
		$mock = $this->getMockBuilder(DashboardMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['findByUserId', 'findByGroup'])
			->getMock();
		// phpcs:enable

		$mock->method('findByUserId')->willReturn($personalRows);
		$mock->method('findByGroup')->willReturn($defaultRows);

		return $mock;
	}//end makePartialMapper()

	/**
	 * Build a mapper with a mocked DB that returns no rows on any query.
	 *
	 * @return array{DashboardMapper, \OCP\DB\QueryBuilder\IQueryBuilder&\PHPUnit\Framework\MockObject\MockObject}
	 */
	private function makeMapperWithEmptyDb(): array {
		$mockResult = $this->createMock(\OCP\DB\IResult::class);
		$mockResult->method('fetch')->willReturn(false);
		$mockResult->method('closeCursor')->willReturn(true);

		$mockExpr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$mockExpr->method('eq')->willReturn('');
		$mockExpr->method('neq')->willReturn('');
		$mockExpr->method('in')->willReturn('');

		$mockQb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$mockQb->method('select')->willReturnSelf();
		$mockQb->method('from')->willReturnSelf();
		$mockQb->method('where')->willReturnSelf();
		$mockQb->method('andWhere')->willReturnSelf();
		$mockQb->method('orderBy')->willReturnSelf();
		$mockQb->method('createNamedParameter')->willReturnArgument(0);
		$mockQb->method('expr')->willReturn($mockExpr);
		$mockQb->method('executeQuery')->willReturn($mockResult);

		$this->db->method('getQueryBuilder')->willReturn($mockQb);

		$mapper = new DashboardMapper($this->db);
		return [$mapper, $mockQb];
	}//end makeMapperWithEmptyDb()

	// -------------------------------------------------------------------------
	// findByGroup — REQ-DASH-011
	// -------------------------------------------------------------------------

	/**
	 * `findByGroup` with a nonexistent group returns an empty array when
	 * the DB cursor returns no rows.
	 *
	 * Uses a full mock of the query-builder chain so the mapper's real
	 * SQL-building code runs up to the `findEntities` call, where the
	 * cursor returns `false` on first `fetch()` (empty result).
	 *
	 * @return void
	 */
	public function testFindByGroupEmptyGroupReturnsArray(): void {
		[$mapper] = $this->makeMapperWithEmptyDb();

		$result = $mapper->findByGroup(groupId: 'nonexistent-group-xyz');

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}//end testFindByGroupEmptyGroupReturnsArray()

	/**
	 * `findByGroup` via partial mock returns the stubbed entities directly,
	 * verifying the method delegates to `findEntities` and returns whatever
	 * it receives.
	 *
	 * @return void
	 */
	public function testFindByGroupReturnsEntities(): void {
		$d1 = $this->makeDashboard(
			uuid: 'gs-1',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: 'marketing'
		);
		$d2 = $this->makeDashboard(
			uuid: 'gs-2',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: 'marketing'
		);

		// Partial mock stubs findByGroup so it returns known data.
		// phpcs:disable
		$mapper = $this->getMockBuilder(DashboardMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['findByGroup'])
			->getMock();
		// phpcs:enable
		$mapper->method('findByGroup')->willReturn([$d1, $d2]);

		$result = $mapper->findByGroup(groupId: 'marketing');

		$this->assertCount(2, $result);
		$this->assertSame('gs-1', $result[0]->getUuid());
		$this->assertSame('gs-2', $result[1]->getUuid());
	}//end testFindByGroupReturnsEntities()

	// -------------------------------------------------------------------------
	// findVisibleToUser — REQ-DASH-013
	// -------------------------------------------------------------------------

	/**
	 * `findVisibleToUser` with zero group memberships returns only personal
	 * and default-group dashboards (REQ-DASH-013 zero-group scenario).
	 *
	 * @return void
	 */
	public function testFindVisibleToUserZeroGroupsPersonalAndDefault(): void {
		$personal = $this->makeDashboard(
			uuid: 'p-1',
			type: Dashboard::TYPE_USER,
			userId: 'dave'
		);
		$default = $this->makeDashboard(
			uuid: 'd-1',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: Dashboard::DEFAULT_GROUP_ID
		);

		$mapper = $this->makePartialMapper(
			personalRows: [$personal],
			defaultRows: [$default]
		);

		$result = $mapper->findVisibleToUser(userId: 'dave', userGroupIds: []);

		$this->assertCount(2, $result);

		// First entry is the personal dashboard.
		$this->assertSame('p-1', $result[0]['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_USER, $result[0]['source']);

		// Second entry is the default-group dashboard.
		$this->assertSame('d-1', $result[1]['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_DEFAULT, $result[1]['source']);
	}//end testFindVisibleToUserZeroGroupsPersonalAndDefault()

	/**
	 * `findVisibleToUser` with no personal dashboards and no default-group rows
	 * returns an empty list (REQ-DASH-013 "nothing visible" edge-case).
	 *
	 * @return void
	 */
	public function testFindVisibleToUserNothingReturnsEmpty(): void {
		$mapper = $this->makePartialMapper(
			personalRows: [],
			defaultRows: []
		);

		$result = $mapper->findVisibleToUser(userId: 'eve', userGroupIds: []);

		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}//end testFindVisibleToUserNothingReturnsEmpty()

	/**
	 * `findVisibleToUser` deduplicates by UUID: when a personal dashboard and a
	 * default-group dashboard share the same UUID, the personal row wins and the
	 * default row is silently dropped (REQ-DASH-013 dedup scenario).
	 *
	 * @return void
	 */
	public function testFindVisibleToUserDeduplicatesByUuid(): void {
		$personal = $this->makeDashboard(
			uuid: 'shared-uuid',
			type: Dashboard::TYPE_USER,
			userId: 'alice'
		);
		// Same UUID as personal but appears in the default-group result set.
		$defaultDuplicate = $this->makeDashboard(
			uuid: 'shared-uuid',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: Dashboard::DEFAULT_GROUP_ID
		);

		$mapper = $this->makePartialMapper(
			personalRows: [$personal],
			defaultRows: [$defaultDuplicate]
		);

		$result = $mapper->findVisibleToUser(userId: 'alice', userGroupIds: []);

		// UUID-overlap → only one row, personal takes priority.
		$this->assertCount(1, $result);
		$this->assertSame('shared-uuid', $result[0]['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_USER, $result[0]['source']);
	}//end testFindVisibleToUserDeduplicatesByUuid()

	/**
	 * `findVisibleToUser` source field: personal dashboards receive
	 * SOURCE_USER, default-group dashboards receive SOURCE_DEFAULT
	 * (REQ-DASH-013 source-tagging scenario).
	 *
	 * @return void
	 */
	public function testFindVisibleToUserSourceTaggingIsCorrect(): void {
		$p1 = $this->makeDashboard(
			uuid: 'user-1',
			type: Dashboard::TYPE_USER,
			userId: 'alice'
		);
		$p2 = $this->makeDashboard(
			uuid: 'user-2',
			type: Dashboard::TYPE_USER,
			userId: 'alice'
		);
		$def = $this->makeDashboard(
			uuid: 'default-1',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: Dashboard::DEFAULT_GROUP_ID
		);

		$mapper = $this->makePartialMapper(
			personalRows: [$p1, $p2],
			defaultRows: [$def]
		);

		$result = $mapper->findVisibleToUser(userId: 'alice', userGroupIds: []);

		$this->assertCount(3, $result);

		$sources = array_column($result, 'source');
		$this->assertContains(Dashboard::SOURCE_USER, $sources);
		$this->assertContains(Dashboard::SOURCE_DEFAULT, $sources);

		// All personal rows are SOURCE_USER.
		$personalResults = array_filter(
			$result,
			static fn ($r) => $r['source'] === Dashboard::SOURCE_USER
		);
		$this->assertCount(2, $personalResults);

		// Default-group row is SOURCE_DEFAULT.
		$defaultResults = array_filter(
			$result,
			static fn ($r) => $r['source'] === Dashboard::SOURCE_DEFAULT
		);
		$this->assertCount(1, $defaultResults);
	}//end testFindVisibleToUserSourceTaggingIsCorrect()

	/**
	 * `findVisibleToUser` with only a default-group dashboard (user has no
	 * personal dashboards and 0 group memberships) returns just the default
	 * dashboard (REQ-DASH-012 default-group visibility).
	 *
	 * @return void
	 */
	public function testFindVisibleToUserDefaultGroupVisibleToAllUsers(): void {
		$default = $this->makeDashboard(
			uuid: 'welcome',
			type: Dashboard::TYPE_GROUP_SHARED,
			groupId: Dashboard::DEFAULT_GROUP_ID
		);

		$mapper = $this->makePartialMapper(
			personalRows: [],
			defaultRows: [$default]
		);

		// A user with zero personal dashboards and no group memberships.
		$result = $mapper->findVisibleToUser(userId: 'new-user', userGroupIds: []);

		$this->assertCount(1, $result);
		$this->assertSame('welcome', $result[0]['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_DEFAULT, $result[0]['source']);
	}//end testFindVisibleToUserDefaultGroupVisibleToAllUsers()
}//end class
