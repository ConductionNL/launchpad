<?php

/**
 * MetadataService branch characterisation test
 *
 * Pins the field-definition patch (every key, its validation messages
 * and the order they are checked in), the value upsert / clear loop and
 * the per-type filter matching (number and date ranges, UUID lookup on
 * entities and rows), so these methods can be split into named steps
 * without changing their answers or messages.
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
use OCA\LaunchPad\Db\MetadataFieldMapper;
use OCA\LaunchPad\Db\MetadataValue;
use OCA\LaunchPad\Db\MetadataValueMapper;
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\MetadataValidationService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MetadataServiceBranchesTest extends TestCase {
	private MetadataFieldMapper $fieldMapper;
	private MetadataValueMapper $valueMapper;
	private MetadataService $service;

	protected function setUp(): void {
		$this->fieldMapper = $this->createMock(MetadataFieldMapper::class);
		$this->valueMapper = $this->createMock(MetadataValueMapper::class);
		$this->service = new MetadataService(
			$this->fieldMapper,
			$this->valueMapper,
			new MetadataValidationService(),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function field(int $id, string $key, string $type, ?array $options = null, int $required = 0): MetadataField {
		$field = new MetadataField();
		$field->setId($id);
		$field->setFieldKey($key);
		$field->setLabel('Label');
		$field->setType($type);
		$field->setRequired($required);
		$field->setSortOrder(3);
		if ($options !== null) {
			$field->setOptionsArray($options);
		}

		return $field;
	}

	private function value(int $fieldId, string $value, string $uuid): MetadataValue {
		$row = new MetadataValue();
		$row->setDashboardUuid($uuid);
		$row->setFieldId($fieldId);
		$row->setValue($value);

		return $row;
	}

	/**
	 * Run an update patch against a stored field and return what was saved.
	 */
	private function patched(MetadataField $field, array $patch): MetadataField {
		$this->fieldMapper->method('findById')->willReturn($field);
		$this->fieldMapper->method('update')->willReturnArgument(0);

		return $this->service->updateFieldDefinition(id: 1, patch: $patch);
	}

	public function testRenameIsRefusedBeforeTheFieldIsRead(): void {
		$this->fieldMapper->expects($this->never())->method('findById');
		$this->expectException(InvalidMetadataFieldException::class);
		$this->expectExceptionMessage('Field key cannot be renamed');

		$this->service->updateFieldDefinition(id: 1, patch: ['key' => null, 'label' => '']);
	}

	public function testEveryPatchKeyIsApplied(): void {
		$field = $this->patched(
			$this->field(1, 'tags', MetadataField::TYPE_SELECT, ['a']),
			['label' => 'New', 'sortOrder' => '7', 'required' => '1', 'options' => ['x', 'y'], 'ignored' => 1]
		);

		$this->assertSame('New', $field->getLabel());
		$this->assertSame(7, $field->getSortOrder());
		$this->assertSame(1, $field->getRequired());
		$this->assertSame(['x', 'y'], $field->getOptionsArray());
		$this->assertNotNull($field->getUpdatedAt());
	}

	/**
	 * Required values and what they store.
	 *
	 * @return array<string,array{0:mixed,1:int}>
	 */
	public static function requiredValues(): array {
		return ['one' => [1, 1], 'true' => [true, 1], 'two' => [2, 0], 'zero' => ['0', 0], 'text' => ['yes', 0]];
	}

	/**
	 * @dataProvider requiredValues
	 */
	public function testRequiredIsOneOnlyForOne(mixed $required, int $stored): void {
		$field = $this->patched($this->field(1, 'k', MetadataField::TYPE_TEXT, null, 1), ['required' => $required]);

		$this->assertSame($stored, $field->getRequired());
	}

	public function testEmptyPatchOnlyStampsTheUpdate(): void {
		$field = $this->patched($this->field(1, 'k', MetadataField::TYPE_TEXT), []);

		$this->assertSame('Label', $field->getLabel());
		$this->assertSame(3, $field->getSortOrder());
		$this->assertNotNull($field->getUpdatedAt());
	}

	/**
	 * Patches that fail validation and their message.
	 *
	 * @return array<string,array{0:string,1:array<int,string>|null,2:array<string,mixed>,3:string}>
	 */
	public static function invalidPatches(): array {
		return [
			'empty label'           => [MetadataField::TYPE_TEXT, null, ['label' => ''], 'Field label must be 1..'],
			'label before options'  => [MetadataField::TYPE_TEXT, null, ['options' => 'x', 'label' => ''], 'Field label must be 1..'],
			'scalar options'        => [MetadataField::TYPE_SELECT, ['a'], ['options' => 'x'], 'Options must be an array of strings or null'],
			'null select options'   => [MetadataField::TYPE_SELECT, ['a'], ['options' => null], 'Select type requires non-empty options array'],
			'empty select options'  => [MetadataField::TYPE_MULTI_SELECT, ['a'], ['options' => []], 'Select type requires non-empty options array'],
			'non-string option'     => [MetadataField::TYPE_SELECT, ['a'], ['options' => ['a', 3]], 'Select options must be non-empty strings'],
			'empty option'          => [MetadataField::TYPE_SELECT, ['a'], ['options' => ['']], 'Select options must be non-empty strings'],
			'text with options'     => [MetadataField::TYPE_TEXT, null, ['options' => ['a']], "Type 'text' does not support options"],
		];
	}

	/**
	 * @dataProvider invalidPatches
	 */
	public function testInvalidPatchIsRefused(string $type, ?array $options, array $patch, string $message): void {
		$this->fieldMapper->method('findById')->willReturn($this->field(1, 'k', $type, $options));
		$this->fieldMapper->expects($this->never())->method('update');
		$this->expectException(InvalidMetadataFieldException::class);
		$this->expectExceptionMessage($message);

		$this->service->updateFieldDefinition(id: 1, patch: $patch);
	}

	public function testTextFieldAcceptsNullAndEmptyOptions(): void {
		$field = $this->patched($this->field(1, 'k', MetadataField::TYPE_TEXT), ['options' => []]);

		$this->assertSame([], $field->getOptionsArray() ?? []);
	}

	public function testEmptyMetadataKeyIsRefused(): void {
		$this->fieldMapper->expects($this->never())->method('findByKey');
		$this->expectException(InvalidMetadataFieldException::class);
		$this->expectExceptionMessage('Metadata keys must be non-empty strings');

		$this->service->setMetadataForDashboard(dashboardUuid: 'd', keyValues: ['' => 'x']);
	}

	public function testUnknownMetadataKeyNamesTheKey(): void {
		$this->fieldMapper->method('findByKey')->willThrowException(new DoesNotExistException(''));
		$this->expectException(InvalidMetadataFieldException::class);
		$this->expectExceptionMessage("Unknown metadata field 'ghost'");

		$this->service->setMetadataForDashboard(dashboardUuid: 'd', keyValues: ['ghost' => 'x']);
	}

	public function testEmptyOptionalValueClearsTheRowAndRequiredOneIsUpserted(): void {
		$optional = $this->field(1, 'note', MetadataField::TYPE_TEXT);
		$required = $this->field(2, 'owner', MetadataField::TYPE_TEXT, null, 1);
		$absent = $this->field(3, 'other', MetadataField::TYPE_TEXT);
		$this->fieldMapper->method('findByKey')->willReturnCallback(
			static fn (string $key): MetadataField => ['note' => $optional, 'owner' => $required, 'other' => $absent, '7' => $required][$key]
		);
		$existing = $this->value(1, 'old', 'd');
		$this->valueMapper->method('findOne')->willReturnCallback(
			static fn (string $dashboardUuid, int $fieldId): ?MetadataValue => $fieldId === 1 ? $existing : null
		);
		$calls = [];
		$this->valueMapper->method('delete')->willReturnCallback(
			static function ($entity) use (&$calls) {
				$calls[] = ['delete', $entity->getFieldId()];
				return $entity;
			}
		);
		$this->valueMapper->method('upsert')->willReturnCallback(
			static function (string $dashboardUuid, int $fieldId, string $value) use (&$calls) {
				$calls[] = ['upsert', $dashboardUuid, $fieldId, $value];
				return new MetadataValue();
			}
		);
		$this->valueMapper->method('findByDashboard')->willReturn([]);

		$result = $this->service->setMetadataForDashboard(
			dashboardUuid: 'd',
			keyValues: ['note' => '', 'other' => '', 'owner' => 'ann', 7 => 'x'],
		);

		$this->assertSame([], $result);
		$this->assertSame([['delete', 1], ['upsert', 'd', 2, 'ann'], ['upsert', 'd', 2, 'x']], $calls);
	}

	/**
	 * Run a one-field filter over three dashboards.
	 *
	 * @return list<string> The UUIDs kept.
	 */
	private function filterBy(string $type, array $stored, mixed $criterion): array {
		$field = $this->field(1, 'f', $type);
		$this->fieldMapper->method('findByKey')->willReturn($field);
		$rows = [];
		foreach ($stored as $uuid => $value) {
			$rows[] = $this->value(1, $value, $uuid);
		}

		$this->valueMapper->method('findByField')->willReturn($rows);
		$dashboards = array_map(static fn (string $uuid): array => ['uuid' => $uuid], array_keys($stored));

		return array_map(
			static fn (array $d): string => $d['uuid'],
			$this->service->filterDashboards($dashboards, ['f' => $criterion])
		);
	}

	/**
	 * Number criteria and the UUIDs they keep.
	 *
	 * @return array<string,array{0:mixed,1:list<string>}>
	 */
	public static function numberCriteria(): array {
		return [
			'min only'        => [['min' => '5'], ['b', 'c']],
			'max only'        => [['max' => 5], ['a', 'b']],
			'both inclusive'  => [['min' => 5, 'max' => 5], ['b']],
			'empty range'     => [[], ['a', 'b', 'c']],
			'exact'           => ['5.0', ['b']],
			'exact non-num'   => ['five', []],
		];
	}

	/**
	 * @dataProvider numberCriteria
	 */
	public function testNumberCriteria(mixed $criterion, array $kept): void {
		$this->assertSame($kept, $this->filterBy(MetadataField::TYPE_NUMBER, ['a' => '1', 'b' => '5', 'c' => '9', 'x' => 'n/a'], $criterion));
	}

	/**
	 * Date criteria and the UUIDs they keep.
	 *
	 * @return array<string,array{0:mixed,1:list<string>}>
	 */
	public static function dateCriteria(): array {
		return [
			'after'         => [['after' => '2026-02-01'], ['b', 'c']],
			'before'        => [['before' => '2026-02-01'], ['a', 'b']],
			'between'       => [['after' => '2026-02-01', 'before' => '2026-02-01'], ['b']],
			'empty range'   => [['x' => 1], ['a', 'b', 'c']],
			'exact'         => ['2026-03-01', ['c']],
		];
	}

	/**
	 * @dataProvider dateCriteria
	 */
	public function testDateCriteria(mixed $criterion, array $kept): void {
		$this->assertSame($kept, $this->filterBy(MetadataField::TYPE_DATE, ['a' => '2026-01-01', 'b' => '2026-02-01', 'c' => '2026-03-01'], $criterion));
	}

	public function testDashboardUuidsComeFromEntitiesAndRows(): void {
		$field = $this->field(1, 'f', MetadataField::TYPE_TEXT);
		$this->fieldMapper->method('findByKey')->willReturn($field);
		$this->valueMapper->method('findByField')->willReturn([
			$this->value(1, 'x', 'e1'),
			$this->value(1, 'x', 'r1'),
			$this->value(1, 'x', ''),
		]);
		$entity = new class() {
			public function getUuid(): string {
				return 'e1';
			}
		};
		$nullUuid = new class() {
			public function getUuid(): ?string {
				return null;
			}
		};
		$noMethod = new \stdClass();
		$dashboards = [$entity, $nullUuid, $noMethod, ['uuid' => 'r1'], ['id' => 3], ['uuid' => null], 'text'];

		$result = $this->service->filterDashboards($dashboards, ['f' => 'x']);

		$this->assertSame([$entity, ['uuid' => 'r1'], ['uuid' => null]], $result);
	}
}//end class
