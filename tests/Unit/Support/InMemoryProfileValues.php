<?php

/**
 * InMemoryProfileValues
 *
 * A ProfileValueMapper that keeps its rows in memory, so the real
 * ProfileFieldService and PeopleWidgetService run end to end in a unit test.
 * The search keeps the LIKE '%needle%' contract on the lower-cased value.
 * The database behaviour itself is covered by ProfileValuesDatabaseTest.
 *
 * @category  Test
 * @package   Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Db\ProfileValueMapper;

/**
 * In-memory profile values.
 */
class InMemoryProfileValues extends ProfileValueMapper {
	/**
	 * Stored rows.
	 *
	 * @var ProfileValue[]
	 */
	public array $rows = [];

	/**
	 * No database.
	 */
	public function __construct() {
	}//end __construct()

	/**
	 * Rows of the given users.
	 *
	 * @param string[] $userIds User ids.
	 *
	 * @return ProfileValue[]
	 */
	public function findByUsers(array $userIds): array {
		return array_values(
			array_filter(
				$this->rows,
				static fn (ProfileValue $row): bool => in_array($row->getUserId(), $userIds, true)
			)
		);
	}//end findByUsers()

	/**
	 * Rows whose lower-cased value contains the needle.
	 *
	 * @param string $needle Lower-cased search text.
	 *
	 * @return ProfileValue[]
	 */
	public function search(string $needle): array {
		return array_values(
			array_filter(
				$this->rows,
				static fn (ProfileValue $row): bool => str_contains($row->getValueSearch(), $needle)
			)
		);
	}//end search()

	/**
	 * Replace one person's values of one field.
	 *
	 * @param string $userId Owner.
	 * @param string $fieldKey Field key.
	 * @param string[] $values Values.
	 * @param string $source Source.
	 * @param string|null $scope Scope.
	 *
	 * @return void
	 */
	public function replaceValues(string $userId, string $fieldKey, array $values, string $source, ?string $scope = null): void {
		$this->deleteField($userId, $fieldKey);
		foreach ($values as $value) {
			$row = new ProfileValue();
			$row->setUserId($userId);
			$row->setFieldKey($fieldKey);
			$row->setValue($value);
			$row->setValueSearch(mb_strtolower($value));
			$row->setSource($source);
			$row->setScope($scope);
			$this->rows[] = $row;
		}
	}//end replaceValues()

	/**
	 * Delete one person's values of one field.
	 *
	 * @param string $userId Owner.
	 * @param string $fieldKey Field key.
	 *
	 * @return int
	 */
	public function deleteField(string $userId, string $fieldKey): int {
		$before = count($this->rows);
		$this->rows = array_values(
			array_filter(
				$this->rows,
				static fn (ProfileValue $row): bool => ($row->getUserId() === $userId && $row->getFieldKey() === $fieldKey) === false
			)
		);

		return $before - count($this->rows);
	}//end deleteField()

	/**
	 * Delete every value of a person.
	 *
	 * @param string $userId Owner.
	 *
	 * @return int
	 */
	public function deleteByUser(string $userId): int {
		$before = count($this->rows);
		$this->rows = array_values(
			array_filter($this->rows, static fn (ProfileValue $row): bool => $row->getUserId() !== $userId)
		);

		return $before - count($this->rows);
	}//end deleteByUser()
}//end class
