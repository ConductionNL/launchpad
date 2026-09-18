<?php

/**
 * PersonalLayerMapper
 *
 * Reads and writes one person's arrangement of a dashboard somebody else owns
 * (REQ-DWMS-001, REQ-DWMS-002). Every query is narrowed by user id, so a layer
 * is unreachable from any other account by construction rather than by a check
 * somebody has to remember.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for personal dashboard layers.
 *
 * @extends QBMapper<PersonalLayer>
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class PersonalLayerMapper extends QBMapper {
	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_personal_layers',
			entityClass: PersonalLayer::class
		);
	}//end __construct()

	/**
	 * One person's layer on one dashboard, or null when they have none.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 *
	 * @return PersonalLayer|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function findForUser(string $userId, int $dashboardId): ?PersonalLayer {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('dashboard_id', $qb->createNamedParameter($dashboardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		try {
			return $this->findEntity(query: $qb);
		} catch (DoesNotExistException) {
			// No layer is the ordinary case: it means this person sees what
			// the owner composed.
			return null;
		}
	}//end findForUser()

	/**
	 * Remove one person's layer on one dashboard.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 *
	 * @return int How many rows went.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function deleteForUser(string $userId, int $dashboardId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('dashboard_id', $qb->createNamedParameter($dashboardId, IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement();
	}//end deleteForUser()
}//end class
