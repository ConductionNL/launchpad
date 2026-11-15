<?php

/**
 * MetadataValueWriterTest
 *
 * Unit tests for MetadataValueWriter: an empty optional value clears the
 * row, every other value is upserted (REQ-MDFL-005, REQ-MDFL-006).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\MetadataField;
use OCA\LaunchPad\Db\MetadataValue;
use OCA\LaunchPad\Db\MetadataValueMapper;
use OCA\LaunchPad\Service\MetadataValueWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MetadataValueWriter.
 */
#[CoversClass(MetadataValueWriter::class)]
#[UsesClass(MetadataField::class)]
#[UsesClass(MetadataValue::class)]
class MetadataValueWriterTest extends TestCase {
	/**
	 * Value mapper double.
	 *
	 * @var MetadataValueMapper&MockObject
	 */
	private MetadataValueMapper&MockObject $mapper;

	/**
	 * Build the mapper double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper = $this->createMock(MetadataValueMapper::class);
	}//end setUp()

	/**
	 * A field with the given id and required flag.
	 *
	 * @param int $id       The field id.
	 * @param int $required 1 for required, 0 for optional.
	 *
	 * @return MetadataField
	 */
	private function field(int $id, int $required): MetadataField {
		$field = new MetadataField();
		$field->setId($id);
		$field->setRequired($required);

		return $field;
	}//end field()

	/**
	 * A non-empty value is upserted for the dashboard and field.
	 *
	 * @return void
	 */
	public function testValueIsUpserted(): void {
		$this->mapper->expects($this->once())->method('upsert')
			->with('dash-1', 7, 'blue')
			->willReturn(new MetadataValue());
		$this->mapper->expects($this->never())->method('findOne');
		$this->mapper->expects($this->never())->method('delete');

		(new MetadataValueWriter(valueMapper: $this->mapper, dashboardUuid: 'dash-1'))
			->write(field: $this->field(7, 0), encoded: 'blue');
	}//end testValueIsUpserted()

	/**
	 * An empty value of a required field is still upserted.
	 *
	 * @return void
	 */
	public function testEmptyRequiredValueIsUpserted(): void {
		$this->mapper->expects($this->once())->method('upsert')
			->with('dash-1', 3, '')
			->willReturn(new MetadataValue());
		$this->mapper->expects($this->never())->method('delete');

		(new MetadataValueWriter(valueMapper: $this->mapper, dashboardUuid: 'dash-1'))
			->write(field: $this->field(3, 1), encoded: '');
	}//end testEmptyRequiredValueIsUpserted()

	/**
	 * An empty optional value deletes the existing row.
	 *
	 * @return void
	 */
	public function testEmptyOptionalValueDeletesExistingRow(): void {
		$existing = new MetadataValue();
		$this->mapper->expects($this->once())->method('findOne')
			->with('dash-2', 5)
			->willReturn($existing);
		$this->mapper->expects($this->once())->method('delete')->with($this->identicalTo($existing))->willReturn($existing);
		$this->mapper->expects($this->never())->method('upsert');

		(new MetadataValueWriter(valueMapper: $this->mapper, dashboardUuid: 'dash-2'))
			->write(field: $this->field(5, 0), encoded: '');
	}//end testEmptyOptionalValueDeletesExistingRow()

	/**
	 * An empty optional value without a row writes nothing.
	 *
	 * @return void
	 */
	public function testEmptyOptionalValueWithoutRowDoesNothing(): void {
		$this->mapper->expects($this->once())->method('findOne')->with('dash-2', 5)->willReturn(null);
		$this->mapper->expects($this->never())->method('delete');
		$this->mapper->expects($this->never())->method('upsert');

		(new MetadataValueWriter(valueMapper: $this->mapper, dashboardUuid: 'dash-2'))
			->write(field: $this->field(5, 0), encoded: '');
	}//end testEmptyOptionalValueWithoutRowDoesNothing()
}//end class
