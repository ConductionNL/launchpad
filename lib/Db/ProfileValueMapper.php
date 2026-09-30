<?php

/**
 * ProfileValueMapper
 *
 * Reads and writes `launchpad_profile_values` (REQ-PEX-001..004).
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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for profile values.
 *
 * @extends QBMapper<ProfileValue>
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileValueMapper extends QBMapper {
	/**
	 * Most rows one search returns before the caller filters them.
	 *
	 * @var int
	 */
	public const SEARCH_ROW_CAP = 1000;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db Database connection.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_profile_values',
			entityClass: ProfileValue::class
		);
	}//end __construct()

	/**
	 * Every value of the given people.
	 *
	 * @param string[] $userIds User ids.
	 *
	 * @return ProfileValue[]
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function findByUsers(array $userIds): array {
		if ($userIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->in(
					x: 'user_id',
					y: $qb->createNamedParameter(
						value: array_values(array: $userIds),
						type: IQueryBuilder::PARAM_STR_ARRAY
					)
				)
			)
			->orderBy(sort: 'id');

		return $this->findEntities(query: $qb);
	}//end findByUsers()

	/**
	 * Values whose lower-cased text contains the needle.
	 *
	 * @param string $needle Lower-cased search text.
	 *
	 * @return ProfileValue[] At most SEARCH_ROW_CAP rows.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function search(string $needle): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->like(
					x: 'value_search',
					y: $qb->createNamedParameter(
						value: '%' . $this->db->escapeLikeParameter(param: $needle) . '%'
					)
				)
			)
			->setMaxResults(maxResults: self::SEARCH_ROW_CAP);

		return $this->findEntities(query: $qb);
	}//end search()

	/**
	 * Replace one person's values of one field with a new list.
	 *
	 * @param string $userId Owner.
	 * @param string $fieldKey Field key.
	 * @param string[] $values New values (already cleaned).
	 * @param string $source self, ldap or nextcloud.
	 * @param string|null $scope Visibility scope of a mirrored standard field.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function replaceValues(
		string $userId,
		string $fieldKey,
		array $values,
		string $source,
		?string $scope = null,
	): void {
		$this->deleteField(userId: $userId, fieldKey: $fieldKey);

		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		foreach ($values as $value) {
			$row = new ProfileValue();
			// Entity setters take their argument positionally.
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$row->setUserId($userId);
			$row->setFieldKey($fieldKey);
			$row->setValue($value);
			$row->setValueSearch(mb_strtolower(string: $value));
			$row->setSource($source);
			$row->setScope($scope);
			$row->setUpdatedAt($now);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->insert(entity: $row);
		}
	}//end replaceValues()

	/**
	 * Delete one person's values of one field.
	 *
	 * @param string $userId Owner.
	 * @param string $fieldKey Field key.
	 *
	 * @return int Rows deleted.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function deleteField(string $userId, string $fieldKey): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName())
			->where(
				$qb->expr()->eq(x: 'user_id', y: $qb->createNamedParameter(value: $userId)),
				$qb->expr()->eq(x: 'field_key', y: $qb->createNamedParameter(value: $fieldKey))
			);

		return $qb->executeStatement();
	}//end deleteField()

	/**
	 * Delete every value of a person (user deletion).
	 *
	 * @param string $userId Owner.
	 *
	 * @return int Rows deleted.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function deleteByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName())
			->where($qb->expr()->eq(x: 'user_id', y: $qb->createNamedParameter(value: $userId)));

		return $qb->executeStatement();
	}//end deleteByUser()
}//end class
