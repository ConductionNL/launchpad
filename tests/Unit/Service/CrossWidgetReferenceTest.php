<?php

/**
 * CrossWidgetReferenceTest
 *
 * Cross-widget references (`widget:<id>.<field>`, REQ-CALC-006) through the
 * server-side service: a stored AST that reads another widget passes the
 * save-time checks, is ordered after the field it reads, evaluates against
 * that widget's data and calculated values, and a reference cycle across
 * widgets is refused with the `cycleDetected` envelope naming the widgets
 * (widget-calculated-fields, task 40). The server checks stored ASTs and
 * does not parse expression text (design D3), so parsing is the client's.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-40
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues;
use OCA\LaunchPad\Service\CalculatedFieldService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Cross-widget references, checked and evaluated on the server.
 */
#[CoversClass(CalculatedFieldService::class)]
#[UsesClass(CalculatedFieldAstChecker::class)]
#[UsesClass(CalculatedFieldTypeChecker::class)]
#[UsesClass(CalculatedFieldCycleDetector::class)]
#[UsesClass(CalculatedFieldCycleError::class)]
#[UsesClass(CalculatedFieldEvaluator::class)]
#[UsesClass(CalculatedFieldFunctions::class)]
#[UsesClass(CalculatedFieldValues::class)]
class CrossWidgetReferenceTest extends TestCase {

	/**
	 * The service over its real collaborators.
	 *
	 * @return CalculatedFieldService
	 */
	private static function service(): CalculatedFieldService {
		return new CalculatedFieldService(
			astChecker: new CalculatedFieldAstChecker(),
			typeChecker: new CalculatedFieldTypeChecker(),
			cycleDetector: new CalculatedFieldCycleDetector(),
			evaluator: new CalculatedFieldEvaluator()
		);
	}//end service()

	/**
	 * A reference to another widget's field.
	 *
	 * @param string $widget The widget id.
	 * @param string $path   The field path.
	 *
	 * @return array<string,string>
	 */
	private static function ref(string $widget, string $path): array {
		return ['type' => 'fieldRef', 'source' => 'widget:'.$widget, 'path' => $path];
	}//end ref()

	/**
	 * A field.
	 *
	 * @param string              $name The name.
	 * @param array<string,mixed> $ast  The AST.
	 *
	 * @return array<string,mixed>
	 */
	private static function field(string $name, array $ast): array {
		return ['name' => $name, 'compiledExpression' => $ast];
	}//end field()

	/**
	 * A field reading a neighbour's calculated field is accepted and ordered after it.
	 *
	 * @return void
	 */
	public function testAReferenceToANeighbourIsOrderedAfterIt(): void {
		$result = self::service()->validateAndCompile(
			widget: ['id' => 'A', 'calculatedFields' => [self::field('share', ['type' => 'binary', 'op' => '/', 'left' => self::ref('B', 'total'), 'right' => ['type' => 'literal', 'valueType' => 'number', 'value' => 4]])]],
			others: [['id' => 'B', 'calculatedFields' => [self::field('total', ['type' => 'fieldRef', 'source' => 'self', 'path' => 'amount'])]]]
		);

		$this->assertSame(['B.total', 'A.share'], $result['order']);
		$this->assertSame('number', $result['fields'][0]['returnType']);
	}//end testAReferenceToANeighbourIsOrderedAfterIt()

	/**
	 * Two widgets reading each other are refused, the cycle and widgets named.
	 *
	 * @return void
	 */
	public function testACrossWidgetCycleIsRefused(): void {
		try {
			self::service()->validateAndCompile(
				widget: ['id' => 'A', 'calculatedFields' => [self::field('x', self::ref('B', 'y'))]],
				others: [['id' => 'B', 'calculatedFields' => [self::field('y', self::ref('A', 'x'))]]]
			);
			$this->fail('A cycle across widgets must be refused.');
		} catch (CalculatedFieldCycleError $error) {
			$detail = $error->getDetail();
			$this->assertSame('cycleDetected', $detail['error']);
			$this->assertSame(['A.x', 'B.y', 'A.x'], $detail['cycle']);
			$this->assertSame(['A', 'B'], $detail['widgets']);
		}
	}//end testACrossWidgetCycleIsRefused()

	/**
	 * A neighbour's calculated value wins over a data field of the same name.
	 *
	 * @return void
	 */
	public function testANeighboursCalculatedValueIsRead(): void {
		$value = self::service()->evaluateExpression(
			ast: ['type' => 'binary', 'op' => '*', 'left' => self::ref('B', 'total'), 'right' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'rate']],
			row: ['rate' => 2],
			context: ['widgets' => ['B' => ['total' => 1]], 'widgetCalculated' => ['B' => ['total' => 21]]]
		);

		$this->assertSame(42, $value);
	}//end testANeighboursCalculatedValueIsRead()

	/**
	 * A neighbour's data is read along a dotted path.
	 *
	 * @return void
	 */
	public function testANeighboursDataIsReadByPath(): void {
		$value = self::service()->evaluateExpression(
			ast: self::ref('B', 'summary.count'),
			row: [],
			context: ['widgets' => ['B' => ['summary' => ['count' => 7]]]]
		);

		$this->assertSame(7, $value);
	}//end testANeighboursDataIsReadByPath()

	/**
	 * A widget the dashboard does not hold reads as null, and null propagates.
	 *
	 * @return void
	 */
	public function testAnAbsentNeighbourReadsAsNull(): void {
		$service = self::service();
		$this->assertNull($service->evaluateExpression(ast: self::ref('Z', 'total'), row: [], context: []));
		$this->assertNull(
			$service->evaluateExpression(
				ast: ['type' => 'binary', 'op' => '+', 'left' => self::ref('Z', 'total'), 'right' => ['type' => 'literal', 'valueType' => 'number', 'value' => 1]],
				row: [],
				context: ['widgets' => ['B' => ['total' => 1]]]
			)
		);
	}//end testAnAbsentNeighbourReadsAsNull()
}//end class
