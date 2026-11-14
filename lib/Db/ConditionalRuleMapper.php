<?php

/**
 * ConditionalRuleMapper
 *
 * Database mapper for conditional rule entities.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * ConditionalRuleMapper
 *
 * Mapper for conditional rule entities.
 *
 * @extends QBMapper<ConditionalRule>
 */
class ConditionalRuleMapper extends QBMapper {
	use ColumnMatchQueries;
	use OrphanRowQueries;

	/**
	 * Parent link for the orphan queries: alias, parent table, parent alias, foreign key.
	 */
	private const ORPHAN_LINK = ['r', 'launchpad_widget_placements', 'p', 'widget_placement_id'];

	/**
	 * Constructor
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_conditional_rules',
			entityClass: ConditionalRule::class
		);
	}//end __construct()

	/**
	 * Find rule by ID.
	 *
	 * @param int $id The rule ID.
	 *
	 * @return ConditionalRule The found rule.
	 *
	 * @throws DoesNotExistException If not found.
	 */
	public function find(int $id): ConditionalRule {
		$qb = $this->selectWhere(match: ['id' => $id]);

		return $this->findEntity(query: $qb);
	}//end find()

	/**
	 * Find all rules for a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return ConditionalRule[] The list of rules.
	 */
	public function findByPlacementId(int $placementId): array {
		$qb = $this->selectWhere(match: ['widget_placement_id' => $placementId]);
		$qb->orderBy(sort: 'created_at', order: 'ASC');

		return $this->findEntities(query: $qb);
	}//end findByPlacementId()

	/**
	 * Find every conditional rule in the system, ordered by placement then
	 * creation time. Used by the admin Versioning & Audit overview
	 * (conditional-visibility spec) to enumerate rule-bearing placements.
	 *
	 * @return ConditionalRule[] All conditional rules.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->orderBy(sort: 'widget_placement_id', order: 'ASC')
			->addOrderBy(sort: 'created_at', order: 'ASC');

		return $this->findEntities(query: $qb);
	}//end findAll()

	/**
	 * Delete all rules for a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return void
	 */
	public function deleteByPlacementId(int $placementId): void {
		$qb = $this->deleteWhere(match: ['widget_placement_id' => $placementId]);

		$qb->executeStatement();
	}//end deleteByPlacementId()

	/**
	 * Count conditional-rule rows whose `widget_placement_id` no
	 * longer points at any row in `launchpad_widget_placements`.
	 *
	 * Used by the orphaned-data-cleanup scan path (REQ-CLN-001).
	 * Rules are normally cleared by the placement removal flow; a row
	 * left behind here typically indicates a manual SQL delete or an
	 * older code path that didn't cascade.
	 *
	 * @return int The number of orphaned rule rows.
	 */
	public function countOrphaned(): int {
		return $this->countOrphanRows(link: self::ORPHAN_LINK);
	}//end countOrphaned()

	/**
	 * Delete conditional-rule rows whose `widget_placement_id` no
	 * longer points at any row in `launchpad_widget_placements`.
	 *
	 * Companion to {@see self::countOrphaned()} on the purge path
	 * (REQ-CLN-002). Resolves the orphan IDs first via a SELECT and
	 * then deletes by primary key for portability across drivers.
	 *
	 * @return int The number of rows deleted.
	 */
	public function deleteOrphaned(): int {
		return $this->deleteOrphanRows(link: self::ORPHAN_LINK);
	}//end deleteOrphaned()
}//end class
