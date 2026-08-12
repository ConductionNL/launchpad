<?php

/**
 * AcknowledgementMapper
 *
 * Database mapper for Acknowledgement entities. Covers the local
 * `oc_launchpad_acknowledgements` receipt table — idempotent
 * mandatory-read acknowledgements. REQ-ACK-003..006.
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

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Mapper for Acknowledgement receipts.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 *
 * @extends QBMapper<Acknowledgement>
 */
class AcknowledgementMapper extends QBMapper {
	/**
	 * Constructor
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_acknowledgements',
			entityClass: Acknowledgement::class
		);
	}//end __construct()

	/**
	 * Determine whether a receipt already exists for the exact
	 * `(announcementKey, userId, contentVersion)` tuple. Backs the
	 * idempotency guarantee (REQ-ACK-003) and the outstanding-item
	 * calculation (REQ-ACK-002).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The user ID.
	 * @param int $contentVersion The content version.
	 *
	 * @return bool True when a matching receipt exists.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function existsFor(
		string $announcementKey,
		string $userId,
		int $contentVersion,
	): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->eq(
					x: 'announcement_key',
					y: $qb->createNamedParameter(value: $announcementKey)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'user_id',
					y: $qb->createNamedParameter(value: $userId)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'content_version',
					y: $qb->createNamedParameter(
						value: $contentVersion,
						type: \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT
					)
				)
			);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (is_array($row) === false) {
			return false;
		}

		return ((int)($row['cnt'] ?? 0)) > 0;
	}//end existsFor()

	/**
	 * Return the single receipt for an exact tuple, or null when none
	 * exists. Used to surface the original `acknowledgedAt` on an
	 * idempotent repeat (REQ-ACK-003).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The user ID.
	 * @param int $contentVersion The content version.
	 *
	 * @return Acknowledgement|null The receipt or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function findOneFor(
		string $announcementKey,
		string $userId,
		int $contentVersion,
	): ?Acknowledgement {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->eq(
					x: 'announcement_key',
					y: $qb->createNamedParameter(value: $announcementKey)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'user_id',
					y: $qb->createNamedParameter(value: $userId)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'content_version',
					y: $qb->createNamedParameter(
						value: $contentVersion,
						type: \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT
					)
				)
			)
			->setMaxResults(maxResults: 1);

		$entities = $this->findEntities(query: $qb);
		if (empty($entities) === true) {
			return null;
		}

		return $entities[0];
	}//end findOneFor()

	/**
	 * Find every receipt for an announcement at a given content version.
	 * Backs the read-receipt report (REQ-ACK-004).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param int $contentVersion The content version.
	 *
	 * @return Acknowledgement[] The receipts (may be empty).
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function findByAnnouncement(
		string $announcementKey,
		int $contentVersion,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->eq(
					x: 'announcement_key',
					y: $qb->createNamedParameter(value: $announcementKey)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'content_version',
					y: $qb->createNamedParameter(
						value: $contentVersion,
						type: \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT
					)
				)
			)
			->orderBy(sort: 'acknowledged_at', order: 'ASC')
			->addOrderBy(sort: 'id', order: 'ASC');

		return $this->findEntities(query: $qb);
	}//end findByAnnouncement()

	/**
	 * Find all receipts the given user holds for an announcement across
	 * every content version. Used to answer "which version has this user
	 * already acknowledged" (REQ-ACK-005 `reacknowledgeOnChange = 0`).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The user ID.
	 *
	 * @return Acknowledgement[] The receipts (may be empty).
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function findByUserForAnnouncement(
		string $announcementKey,
		string $userId,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->eq(
					x: 'announcement_key',
					y: $qb->createNamedParameter(value: $announcementKey)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					x: 'user_id',
					y: $qb->createNamedParameter(value: $userId)
				)
			)
			->orderBy(sort: 'content_version', order: 'DESC');

		return $this->findEntities(query: $qb);
	}//end findByUserForAnnouncement()

	/**
	 * Insert a receipt row. Throws on duplicate — the caller is expected
	 * to swallow the unique-constraint violation for idempotent semantics
	 * (REQ-ACK-003).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The user ID.
	 * @param int $contentVersion The content version.
	 *
	 * @return Acknowledgement The inserted receipt.
	 *
	 * @throws \OCP\DB\Exception When the unique constraint is violated.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function record(
		string $announcementKey,
		string $userId,
		int $contentVersion,
	): Acknowledgement {
		$entity = new Acknowledgement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		// Entity setters resolve via __call which forwards $args[0]; named
		// parameters MUST NOT be used here.
		$entity->setAnnouncementKey($announcementKey);
		$entity->setUserId($userId);
		$entity->setContentVersion($contentVersion);
		$entity->setAcknowledgedAt(new DateTime());
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		return $this->insert(entity: $entity);
	}//end record()
}//end class
