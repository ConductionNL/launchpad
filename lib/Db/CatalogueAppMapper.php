<?php

/**
 * CatalogueAppMapper
 *
 * Reads and writes `launchpad_catalogue_apps` (launcher-app-catalogue).
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
 * Mapper for the approved applications.
 *
 * @extends QBMapper<CatalogueApp>
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */
class CatalogueAppMapper extends QBMapper {
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
			tableName: 'launchpad_catalogue_apps',
			entityClass: CatalogueApp::class
		);
	}//end __construct()

	/**
	 * Every entry, in catalogue order then by name.
	 *
	 * @return array<int, CatalogueApp>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function findAllOrdered(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->orderBy(sort: 'sort_order')
			->addOrderBy(sort: 'name')
			->addOrderBy(sort: 'id');

		return $this->findEntities(query: $qb);
	}//end findAllOrdered()

	/**
	 * One entry by its uuid.
	 *
	 * @param string $uuid The uuid.
	 *
	 * @return CatalogueApp
	 *
	 * @throws DoesNotExistException When there is no such entry.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function findByUuid(string $uuid): CatalogueApp {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'uuid', y: $qb->createNamedParameter(value: $uuid)));

		return $this->findEntity(query: $qb);
	}//end findByUuid()
}//end class
