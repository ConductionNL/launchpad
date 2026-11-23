<?php

/**
 * CalculatedFieldCycleDetectorTest
 *
 * The server-side cycle detector refuses self-references, cycles inside a
 * widget and widgets that read each other, naming the cycle, and orders an
 * acyclic graph dependencies first, as the client-side detector does
 * (REQ-CALC-006).
 *
 * @category  Test
 * @package   Unit\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Cycle detection over calculated fields and widgets.
 */
#[CoversClass(CalculatedFieldCycleDetector::class)]
#[CoversClass(CalculatedFieldCycleError::class)]
class CalculatedFieldCycleDetectorTest extends TestCase {

	/**
	 * A field reference node.
	 *
	 * @param string $path   The path.
	 * @param string $source `self` or `widget:<id>`.
	 *
	 * @return array<string,mixed>
	 */
	private static function field(string $path, string $source='self'): array {
		return ['type' => 'fieldRef', 'source' => $source, 'path' => $path];
	}//end field()

	/**
	 * A calculated-field reference node.
	 *
	 * @param string $name The field name.
	 *
	 * @return array<string,mixed>
	 */
	private static function calc(string $name): array {
		return ['type' => 'calculatedRef', 'name' => $name];
	}//end calc()

	/**
	 * `left + 1`.
	 *
	 * @param array<string,mixed> $left The left operand.
	 *
	 * @return array<string,mixed>
	 */
	private static function plusOne(array $left): array {
		return ['type' => 'binary', 'op' => '+', 'left' => $left, 'right' => ['type' => 'literal', 'valueType' => 'number', 'value' => 1]];
	}//end plusOne()

	/**
	 * A widget with named fields.
	 *
	 * @param string                             $id     The widget id.
	 * @param array<string,array<string,mixed>> $fields Name => AST.
	 *
	 * @return array<string,mixed>
	 */
	private static function widget(string $id, array $fields): array {
		$list = [];
		foreach ($fields as $name => $ast) {
			$list[] = ['name' => $name, 'compiledExpression' => $ast];
		}

		return ['id' => $id, 'calculatedFields' => $list];
	}//end widget()

	/**
	 * The detail of the cycle error the widgets raise.
	 *
	 * @param array<int,array<string,mixed>> $widgets The widgets.
	 *
	 * @return array<string,mixed>
	 */
	private function cycleOf(array $widgets): array {
		try {
			(new CalculatedFieldCycleDetector())->assertAcyclic(widgets: $widgets);
		} catch (CalculatedFieldCycleError $e) {
			return $e->getDetail();
		}

		$this->fail(message: 'No cycle was detected.');
	}//end cycleOf()

	/**
	 * A field reading itself through `widget:<own id>` is refused.
	 *
	 * @return void
	 */
	public function testRejectsASelfReference(): void {
		$detail = $this->cycleOf(
			widgets: [self::widget(id: 'A', fields: ['x' => self::plusOne(left: self::field(path: 'x', source: 'widget:A'))])]);

		$this->assertSame(expected: 'cycleDetected', actual: $detail['error']);
		$this->assertSame(expected: ['A.x', 'A.x'], actual: $detail['cycle']);
		$this->assertSame(expected: ['A'], actual: $detail['widgets']);
		$this->assertSame(expected: 'Calculated fields depend on each other in a cycle: A.x -> A.x', actual: $detail['message']);
	}//end testRejectsASelfReference()

	/**
	 * Fields of one widget reading each other through calculated refs are refused.
	 *
	 * @return void
	 */
	public function testRejectsALongerCycleInsideAWidget(): void {
		$detail = $this->cycleOf(
			widgets: 			[
				self::widget(id: 'W', fields: ['a' => self::calc(name: 'b'), 'b' => self::calc(name: 'c'), 'c' => self::plusOne(left: self::calc(name: 'a'))]),
			]
		);

		$this->assertSame(expected: ['W.a', 'W.b', 'W.c', 'W.a'], actual: $detail['cycle']);
		$this->assertSame(expected: ['W'], actual: $detail['widgets']);
	}//end testRejectsALongerCycleInsideAWidget()

	/**
	 * Two widgets whose fields read each other are refused, naming both.
	 *
	 * @return void
	 */
	public function testRejectsTwoWidgetsReadingEachOtherFields(): void {
		$detail = $this->cycleOf(
			widgets: 			[
				self::widget(id: 'A', fields: ['x' => self::field(path: 'y', source: 'widget:B')]),
				self::widget(id: 'B', fields: ['y' => self::field(path: 'x', source: 'widget:A')]),
			]
		);

		$this->assertSame(expected: ['A.x', 'B.y', 'A.x'], actual: $detail['cycle']);
		$this->assertSame(expected: ['A', 'B'], actual: $detail['widgets']);
	}//end testRejectsTwoWidgetsReadingEachOtherFields()

	/**
	 * Widgets reading each other's plain data (not calculated fields) still
	 * form a widget cycle.
	 *
	 * @return void
	 */
	public function testRejectsWidgetsReadingEachOtherData(): void {
		$detail = $this->cycleOf(
			widgets: 			[
				self::widget(id: 'A', fields: ['x' => self::field(path: 'total', source: 'widget:B')]),
				self::widget(id: 'B', fields: ['y' => self::field(path: 'count', source: 'widget:A')]),
			]
		);

		$this->assertSame(expected: ['A', 'B', 'A'], actual: $detail['cycle']);
		$this->assertSame(expected: ['A', 'B'], actual: $detail['widgets']);
	}//end testRejectsWidgetsReadingEachOtherData()

	/**
	 * An acyclic graph comes back dependencies first.
	 *
	 * @return void
	 */
	public function testOrdersAnAcyclicGraphDependenciesFirst(): void {
		$order = (new CalculatedFieldCycleDetector())->assertAcyclic(
			widgets: [
				self::widget(
					id: 'A',
					fields: [
						'doubled' => self::field(path: 'net', source: 'widget:B'),
						'total'   => self::plusOne(left: self::calc(name: 'doubled')),
					]
				),
				self::widget(id: 'B', fields: ['net' => self::field(path: 'revenue')]),
			]
		);

		$this->assertSame(expected: ['B.net', 'A.doubled', 'A.total'], actual: $order);
	}//end testOrdersAnAcyclicGraphDependenciesFirst()

	/**
	 * References inside function arguments and conditionals count.
	 *
	 * @return void
	 */
	public function testFindsReferencesInsideCallsAndConditionals(): void {
		$detail = $this->cycleOf(
			widgets: 			[
				self::widget(
					id: 'W',
					fields: 					[
						'a' => ['type' => 'call', 'name' => 'coalesce', 'args' => [self::field(path: 'n'), self::calc(name: 'b')]],
						'b' => ['type' => 'if', 'cond' => self::field(path: 'flag'), 'then' => self::calc(name: 'a'), 'else' => self::field(path: 'n')],
					]
				),
			]
		);

		$this->assertSame(expected: ['W.a', 'W.b', 'W.a'], actual: $detail['cycle']);
	}//end testFindsReferencesInsideCallsAndConditionals()

	/**
	 * Fields without an AST, and widgets without fields, are left out.
	 *
	 * @return void
	 */
	public function testSkipsFieldsWithoutAnAst(): void {
		$order = (new CalculatedFieldCycleDetector())->assertAcyclic(
			widgets: [
				['id' => 'W', 'calculatedFields' => [['name' => 'draft'], ['name' => 'ok', 'compiledExpression' => self::field(path: 'n')]]],
				['id' => 'V'],
				['id' => 'U', 'calculatedFields' => 'not a list'],
			]
		);

		$this->assertSame(expected: ['W.ok'], actual: $order);
	}//end testSkipsFieldsWithoutAnAst()
}//end class
