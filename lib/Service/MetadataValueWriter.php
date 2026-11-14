<?php

/**
 * MetadataValueWriter
 *
 * Writes encoded metadata values for one dashboard: an empty value of an
 * optional field clears its row, every other value is upserted
 * (REQ-MDFL-005, REQ-MDFL-006).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\MetadataField;
use OCA\LaunchPad\Db\MetadataValueMapper;

/**
 * Writes encoded metadata values for one dashboard.
 *
 * Built by {@see MetadataService::setMetadataForDashboard()} for one
 * write; never shared.
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */
final class MetadataValueWriter {
	/**
	 * Constructor.
	 *
	 * @param MetadataValueMapper $valueMapper   The value mapper.
	 * @param string              $dashboardUuid The dashboard the values belong to.
	 */
	public function __construct(
		private readonly MetadataValueMapper $valueMapper,
		private readonly string $dashboardUuid,
	) {
	}//end __construct()

	/**
	 * Write one field's encoded value.
	 *
	 * @param MetadataField $field   The field definition.
	 * @param string        $encoded The validated, encoded value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	public function write(MetadataField $field, string $encoded): void {
		if ($encoded === '' && $field->getRequired() === 0) {
			// Empty optional value: remove the row so the read
			// payload omits the key (matches scenario "omitted
			// keys are not deleted" by allowing explicit empty
			// to clear the value — keeps client UX coherent).
			$this->clear(fieldId: (int)$field->getId());
			return;
		}

		$this->valueMapper->upsert(
			dashboardUuid: $this->dashboardUuid,
			fieldId: (int)$field->getId(),
			value: $encoded
		);
	}//end write()

	/**
	 * Remove the dashboard's row for a field, if it has one.
	 *
	 * @param integer $fieldId The field id.
	 *
	 * @return void
	 */
	private function clear(int $fieldId): void {
		$existing = $this->valueMapper->findOne(
			dashboardUuid: $this->dashboardUuid,
			fieldId: $fieldId
		);
		if ($existing !== null) {
			$this->valueMapper->delete(entity: $existing);
		}
	}//end clear()
}//end class
