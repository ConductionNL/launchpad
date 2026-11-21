<?php

/**
 * WidgetPlacementCalculatedFieldsTest
 *
 * A widget stores its calculated fields as a JSON list, reads a missing or
 * broken value as the empty list, and carries the list on the wire
 * (widget-calculated-fields, task 21).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-21
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\PlacementUpdater;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the calculated-fields column of WidgetPlacement and its update path.
 *
 * @covers \OCA\LaunchPad\Db\WidgetPlacement
 * @covers \OCA\LaunchPad\Service\PlacementUpdater
 */
class WidgetPlacementCalculatedFieldsTest extends TestCase {

	/**
	 * One calculated field as the editor saves it.
	 *
	 * @return array<string, mixed>
	 */
	private static function field(): array {
		return [
			'id' => 'calc-001',
			'name' => 'profit_margin',
			'label' => 'Profit Margin (%)',
			'expression' => '(revenue - cost) / revenue * 100',
			'returnType' => 'number',
			'evaluationMode' => 'client',
		];
	}//end field()

	/**
	 * A widget saved before the column existed has no calculated fields, and says so on the wire.
	 *
	 * @return void
	 */
	public function testANullColumnReadsAsTheEmptyList(): void {
		$placement = new WidgetPlacement();

		$this->assertNull($placement->getCalculatedFields());
		$this->assertSame([], $placement->getCalculatedFieldsArray());
		$this->assertSame([], $placement->jsonSerialize()['calculatedFields']);
	}//end testANullColumnReadsAsTheEmptyList()

	/**
	 * The fields are stored as a JSON list and read back unchanged, also on the wire.
	 *
	 * @return void
	 */
	public function testTheFieldsRoundTripAsAJsonList(): void {
		$placement = new WidgetPlacement();
		$placement->setCalculatedFieldsArray(fields: [self::field()]);

		$this->assertSame(json_encode([self::field()]), $placement->getCalculatedFields());
		$this->assertSame([self::field()], $placement->getCalculatedFieldsArray());
		$this->assertSame([self::field()], $placement->jsonSerialize()['calculatedFields']);
	}//end testTheFieldsRoundTripAsAJsonList()

	/**
	 * Keys are dropped and entries that are not field objects are skipped on write.
	 *
	 * @return void
	 */
	public function testWritingKeepsOnlyFieldObjectsInOrder(): void {
		$second = ['id' => 'calc-002', 'name' => 'net'];
		$placement = new WidgetPlacement();
		$placement->setCalculatedFieldsArray(fields: ['a' => self::field(), 'b' => 'junk', 'c' => 7, 'd' => $second]);

		$this->assertSame('[', substr((string)$placement->getCalculatedFields(), 0, 1));
		$this->assertSame([self::field(), $second], $placement->getCalculatedFieldsArray());
	}//end testWritingKeepsOnlyFieldObjectsInOrder()

	/**
	 * Stored values that are not a list of objects read as the empty list or lose their stray entries.
	 *
	 * @return void
	 */
	public function testBrokenStoredValuesAreReadSafely(): void {
		$placement = new WidgetPlacement();

		$placement->setCalculatedFields('{not json');
		$this->assertSame([], $placement->getCalculatedFieldsArray());

		$placement->setCalculatedFields('{"a":{"id":"x"}}');
		$this->assertSame([], $placement->getCalculatedFieldsArray());

		$placement->setCalculatedFields('"text"');
		$this->assertSame([], $placement->getCalculatedFieldsArray());

		$placement->setCalculatedFields('[{"id":"x"},3,"y"]');
		$this->assertSame([['id' => 'x']], $placement->getCalculatedFieldsArray());
	}//end testBrokenStoredValuesAreReadSafely()

	/**
	 * An update with a list replaces the stored fields; an update without one, or with a non-list, leaves them.
	 *
	 * @return void
	 */
	public function testADisplayUpdateReplacesTheFieldsOnlyWhenGivenAList(): void {
		$placement = new WidgetPlacement();
		$placement->setCalculatedFieldsArray(fields: [self::field()]);
		$updater = new PlacementUpdater();

		$updater->applyDisplayUpdates(placement: $placement, data: ['customTitle' => 'Mine']);
		$this->assertSame([self::field()], $placement->getCalculatedFieldsArray());

		$updater->applyDisplayUpdates(placement: $placement, data: ['calculatedFields' => 'not a list']);
		$this->assertSame([self::field()], $placement->getCalculatedFieldsArray());

		$updater->applyDisplayUpdates(placement: $placement, data: ['calculatedFields' => []]);
		$this->assertSame('[]', $placement->getCalculatedFields());
		$this->assertSame([], $placement->getCalculatedFieldsArray());
	}//end testADisplayUpdateReplacesTheFieldsOnlyWhenGivenAList()
}//end class
