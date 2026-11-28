<?php

/**
 * ExportCalculatedValues Test
 *
 * An export evaluates its widgets' calculated fields on the server in
 * dependency order, across widgets, and a failure or a cycle is an empty cell.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\ExportCalculatedValues;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ExportCalculatedValues}.
 */
class ExportCalculatedValuesTest extends TestCase {

	/**
	 * A reference to a field of the widget's own data.
	 *
	 * @param string $path   The path.
	 * @param string $source `self`, or `widget:<id>`.
	 *
	 * @return array<string, string>
	 */
	private static function field(string $path, string $source='self'): array {
		return ['type' => 'fieldRef', 'source' => $source, 'path' => $path];
	}//end field()

	/**
	 * A number literal.
	 *
	 * @param int|float $value The number.
	 *
	 * @return array<string, mixed>
	 */
	private static function number(int|float $value): array {
		return ['type' => 'literal', 'valueType' => 'number', 'value' => $value];
	}//end number()

	/**
	 * A binary operation.
	 *
	 * @param string $op    The operator.
	 * @param array  $left  The left operand.
	 * @param array  $right The right operand.
	 *
	 * @return array<string, mixed>
	 */
	private static function binary(string $op, array $left, array $right): array {
		return ['type' => 'binary', 'op' => $op, 'left' => $left, 'right' => $right];
	}//end binary()

	/**
	 * Fields read their own data, earlier fields and other widgets, dependencies first.
	 *
	 * @return void
	 */
	public function testEvaluatesInDependencyOrderAcrossWidgets(): void {
		$share   = self::binary(op: '/', left: self::field(path: 'total'), right: self::field(path: 'total', source: 'widget:a'));
		$double  = self::binary(op: '*', left: ['type' => 'calculatedRef', 'name' => 'half'], right: self::number(value: 4));
		$half    = self::binary(op: '/', left: self::field(path: 'total'), right: self::number(value: 2));
		$widgets = [
			['id' => 'b', 'calculatedFields' => [['name' => 'share', 'compiledExpression' => $share]]],
			['id' => 'a', 'calculatedFields' => [['name' => 'double', 'compiledExpression' => $double], ['name' => 'half', 'compiledExpression' => $half]]],
			['id' => 'c'],
		];

		$values = (new ExportCalculatedValues())->values(widgets: $widgets, dataById: ['a' => ['total' => 8], 'b' => ['total' => 2]]);

		$this->assertSame(expected: ['b' => ['share' => 0.25], 'a' => ['half' => 4, 'double' => 16]], actual: $values);
	}//end testEvaluatesInDependencyOrderAcrossWidgets()

	/**
	 * A failing field is an empty cell, and the others still evaluate.
	 *
	 * @return void
	 */
	public function testAFailingFieldIsNullAndTheOthersStillEvaluate(): void {
		$widgets = [['id' => 'a', 'calculatedFields' => [
			['name' => 'broken', 'compiledExpression' => self::binary(op: '*', left: self::field(path: 'name'), right: self::number(value: 2))],
			['name' => 'count', 'expression' => 'total', 'compiledExpression' => self::field(path: 'total')],
		]]];

		$values = (new ExportCalculatedValues())->values(widgets: $widgets, dataById: ['a' => ['name' => 'x', 'total' => 3]]);

		$this->assertSame(expected: ['a' => ['broken' => null, 'count' => 3]], actual: $values);
	}//end testAFailingFieldIsNullAndTheOthersStillEvaluate()

	/**
	 * Fields that read each other in a circle are all empty.
	 *
	 * @return void
	 */
	public function testACycleLeavesEveryFieldNull(): void {
		$widgets = [['id' => 'a', 'calculatedFields' => [
			['name' => 'x', 'compiledExpression' => ['type' => 'calculatedRef', 'name' => 'y']],
			['name' => 'y', 'compiledExpression' => ['type' => 'calculatedRef', 'name' => 'x']],
		]]];

		$this->assertSame(expected: ['a' => ['x' => null, 'y' => null]], actual: (new ExportCalculatedValues())->values(widgets: $widgets, dataById: []));
	}//end testACycleLeavesEveryFieldNull()
}//end class
