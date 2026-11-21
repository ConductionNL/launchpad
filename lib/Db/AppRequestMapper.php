<?php

/**
 * AppRequestMapper
 *
 * Reads and writes `launchpad_app_requests` (launcher-app-catalogue).
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
use OCP\IDBConnection;

/**
 * Mapper for the requests for a missing application.
 *
 * @extends QBMapper<AppRequest>
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */
class AppRequestMapper extends QBMapper {
	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db Database connection.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_app_requests',
			entityClass: AppRequest::class
		);
	}//end __construct()

	/**
	 * The open requests, oldest first.
	 *
	 * @return array<int, AppRequest>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function findOpen(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'status', y: $qb->createNamedParameter(value: 'open')))
			->orderBy(sort: 'created_at')
			->addOrderBy(sort: 'id');

		return $this->findEntities(query: $qb);
	}//end findOpen()

	/**
	 * How many open requests a user has.
	 *
	 * @param string $userId The user.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function countOpenBy(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count(count: '*', alias: 'open_count'))
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->eq(x: 'requested_by', y: $qb->createNamedParameter(value: $userId)),
				$qb->expr()->eq(x: 'status', y: $qb->createNamedParameter(value: 'open'))
			);

		$result = $qb->executeQuery();
		$count  = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}//end countOpenBy()

	/**
	 * One request by its uuid.
	 *
	 * @param string $uuid The uuid.
	 *
	 * @return AppRequest
	 *
	 * @throws DoesNotExistException When there is no such request.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function findByUuid(string $uuid): AppRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'uuid', y: $qb->createNamedParameter(value: $uuid)));

		return $this->findEntity(query: $qb);
	}//end findByUuid()
}//end class
