<?php

/**
 * ReadReceiptPeople
 *
 * The names and departments of the people on a read-receipt report
 * (REQ-ACK-010).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\ProfileValueMapper;
use OCP\IUserManager;

/**
 * Adds `displayName` and `department` to every row of a report, and the
 * department field's label to the report. The department is the value of
 * the profile field the administrator marked as the department, shown only
 * where the viewer may see that field; '' otherwise.
 *
 * @spec openspec/changes/read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */
class ReadReceiptPeople {
	/**
	 * Constructor.
	 *
	 * @param IUserManager        $userManager   Display names.
	 * @param ProfileFieldService $profileFields The field definitions and who may see them.
	 * @param ProfileValueMapper  $values        The profile values.
	 */
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly ProfileFieldService $profileFields,
		private readonly ProfileValueMapper $values,
	) {
	}//end __construct()

	/**
	 * The report with each row's display name and department, and
	 * `departmentLabel` ('' when no field is the department).
	 *
	 * @param array<string, mixed> $report   The report of AcknowledgementService::report().
	 * @param string|null          $viewerId Who reads the report.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
	 */
	public function withPeople(array $report, ?string $viewerId): array {
		$rows = [];
		if (is_array(value: $report['rows'] ?? null) === true) {
			$rows = $report['rows'];
		}

		$userIds = array_values(array: array_map(array: $rows, callback: static fn (array $row): string => (string)($row['userId'] ?? '')));
		$field   = $this->departmentField();
		$departments = [];
		if ($field !== null) {
			$departments = $this->departmentsOf(field: $field, userIds: $userIds, viewerId: $viewerId);
		}

		$report['rows'] = array_map(
			array: $rows,
			callback: fn (array $row): array => $row + [
				'displayName' => $this->displayName(userId: (string)($row['userId'] ?? '')),
				'department'  => $departments[(string)($row['userId'] ?? '')] ?? '',
			]
		);
		$report['departmentLabel'] = (string)($field['label'] ?? '');

		return $report;
	}//end withPeople()

	/**
	 * The field marked as the department, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	private function departmentField(): ?array {
		foreach ($this->profileFields->getDefinitions() as $definition) {
			if (($definition['department'] ?? false) === true) {
				return $definition;
			}
		}

		return null;
	}//end departmentField()

	/**
	 * Each person's department the viewer may see: the field's values joined.
	 *
	 * @param array<string, mixed> $field    The department field.
	 * @param string[]             $userIds  The people.
	 * @param string|null          $viewerId Who reads the report.
	 *
	 * @return array<string, string> userId => department.
	 */
	private function departmentsOf(array $field, array $userIds, ?string $viewerId): array {
		$values = [];
		foreach ($this->values->findByUsers(userIds: $userIds) as $value) {
			if ($value->getFieldKey() === $field['key']) {
				$values[$value->getUserId()][] = $value->getValue();
			}
		}

		$departments = [];
		foreach ($values as $userId => $list) {
			if ($this->profileFields->canSee(viewerId: $viewerId, ownerId: (string)$userId, definition: $field) === true) {
				$departments[(string)$userId] = implode(separator: ', ', array: $list);
			}
		}

		return $departments;
	}//end departmentsOf()

	/**
	 * The person's display name, else the user id.
	 *
	 * @param string $userId The person.
	 *
	 * @return string
	 */
	private function displayName(string $userId): string {
		$name = (string)$this->userManager->getDisplayName(uid: $userId);
		if ($name === '') {
			return $userId;
		}

		return $name;
	}//end displayName()
}//end class
