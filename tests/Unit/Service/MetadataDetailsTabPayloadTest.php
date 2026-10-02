<?php

/**
 * The exact values the Details tab sends (src/components/Workspace/config/
 * DetailsTab.vue save()) pass the real MetadataValidationService, one per
 * field type (dashboard-language-and-details-tabs REQ-MDUI-002).
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\MetadataField;
use OCA\LaunchPad\Service\MetadataValidationService;
use PHPUnit\Framework\TestCase;

class MetadataDetailsTabPayloadTest extends TestCase {
	private function field(string $type, ?array $options = null): MetadataField {
		$field = new MetadataField();
		$field->setFieldKey($type);
		$field->setLabel(ucfirst($type));
		$field->setType($type);
		$field->setRequired(0);
		$field->setOptionsArray($options);
		return $field;
	}

	public function testEveryDetailsTabValueIsAccepted(): void {
		$validator = new MetadataValidationService();

		$this->assertSame('Sanne', $validator->validateValue(value: 'Sanne', field: $this->field('text')));
		$this->assertSame('1200', $validator->validateValue(value: 1200, field: $this->field('number')));
		$this->assertSame('2026-10-01', $validator->validateValue(value: '2026-10-01', field: $this->field('date')));
		$this->assertSame('Finance', $validator->validateValue(value: 'Finance', field: $this->field('select', ['HR', 'Finance'])));
		$this->assertSame('["Staff"]', $validator->validateValue(value: ['Staff'], field: $this->field('multi-select', ['Staff', 'Guests'])));
		$this->assertSame('1', $validator->validateValue(value: true, field: $this->field('boolean')));
		$this->assertSame('', $validator->validateValue(value: '', field: $this->field('select', ['HR'])));
	}
}
