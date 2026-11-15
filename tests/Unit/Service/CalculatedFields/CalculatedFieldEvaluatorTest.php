<?php

/**
 * CalculatedFieldEvaluatorTest
 *
 * The server-side evaluator answers every case of the shared evaluator suite
 * the client-side evaluator answers, numbers within 1e-9, and fails the same
 * cases with the same message (REQ-CALC-005).
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shared suite, case by case, plus the evaluator's own edges.
 */
#[CoversClass(CalculatedFieldEvaluator::class)]
#[CoversClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions::class)]
#[CoversClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues::class)]
class CalculatedFieldEvaluatorTest extends TestCase {

	/**
	 * Every case of the shared suite.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function sharedCases(): array {
		$path = dirname(__DIR__, 3) . '/fixtures/calculated-fields/shared-evaluator-test-suite.json';
		$suite = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$cases = [];
		foreach ($suite['cases'] as $case) {
			$cases[$case['name']] = [$case];
		}

		return $cases;
	}//end sharedCases()

	/**
	 * The suite is the one the client runs, with errors in it.
	 *
	 * @return void
	 */
	public function testTheSharedSuiteHoldsFiftyCasesWithErrors(): void {
		$cases = array_column(self::sharedCases(), 0);
		$this->assertGreaterThanOrEqual(50, count($cases));
		$this->assertNotSame([], array_filter($cases, static fn (array $case): bool => array_key_exists('error', $case)));
	}//end testTheSharedSuiteHoldsFiftyCasesWithErrors()

	/**
	 * One shared case: the same value (numbers within 1e-9) or the same failure.
	 *
	 * @param array<string, mixed> $case The case.
	 *
	 * @return void
	 */
	#[DataProvider('sharedCases')]
	public function testTheSharedCase(array $case): void {
		$evaluator = new CalculatedFieldEvaluator();
		if (array_key_exists('error', $case) === true) {
			$this->expectException(EvaluationError::class);
			$this->expectExceptionMessage($case['error']);
			$evaluator->evaluate(ast: $case['ast'], context: $case['context'], nowMs: $case['now']);
			return;
		}

		$actual = $evaluator->evaluate(ast: $case['ast'], context: $case['context'], nowMs: $case['now']);
		$expected = $case['expected'];
		if (is_int($expected) === true || is_float($expected) === true) {
			$this->assertTrue(is_int($actual) || is_float($actual), 'a number, got ' . var_export($actual, true));
			$this->assertEqualsWithDelta((float)$expected, (float)$actual, 1e-9);
			return;
		}

		$this->assertSame($expected, $actual);
	}//end testTheSharedCase()

	/**
	 * A failure caught for the widget names the expression; a success has no error.
	 *
	 * @return void
	 */
	public function testEvaluateFieldCatchesTheFailure(): void {
		$evaluator = new CalculatedFieldEvaluator();
		$ast = ['type' => 'binary', 'op' => '/', 'left' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'a'], 'right' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'b']];

		$this->assertSame(
			['value' => null, 'error' => 'division by zero', 'expression' => 'a / b'],
			$evaluator->evaluateField(ast: $ast, expression: 'a / b', context: ['row' => ['a' => 7, 'b' => 0]])
		);
		$this->assertSame(
			['value' => 3.5, 'error' => null, 'expression' => 'a / b'],
			$evaluator->evaluateField(ast: $ast, expression: 'a / b', context: ['row' => ['a' => 7, 'b' => 2]])
		);
	}//end testEvaluateFieldCatchesTheFailure()

	/**
	 * Another widget's calculated value wins over its data; a missing widget is null.
	 *
	 * @return void
	 */
	public function testACrossWidgetReferenceReadsTheCalculatedValueFirst(): void {
		$evaluator = new CalculatedFieldEvaluator();
		$ast = ['type' => 'fieldRef', 'source' => 'widget:w2', 'path' => 'total'];

		$this->assertSame(2, $evaluator->evaluate(ast: $ast, context: ['widgets' => ['w2' => ['total' => 1]], 'widgetCalculated' => ['w2' => ['total' => 2]]]));
		$this->assertSame(1, $evaluator->evaluate(ast: $ast, context: ['widgets' => ['w2' => ['total' => 1]]]));
		$this->assertNull($evaluator->evaluate(ast: $ast, context: ['widgets' => []]));
	}//end testACrossWidgetReferenceReadsTheCalculatedValueFirst()

	/**
	 * An unknown node, an unknown function and a value of the wrong kind fail by name.
	 *
	 * @return void
	 */
	public function testUnknownNodesAndWrongKindsFail(): void {
		$evaluator = new CalculatedFieldEvaluator();
		$cases = [
			[['type' => 'lambda'], "unknown node 'lambda'"],
			[['type' => 'call', 'name' => 'exec', 'args' => []], "unknown function 'exec'"],
			[['type' => 'binary', 'op' => '+', 'left' => ['type' => 'literal', 'value' => 'x'], 'right' => ['type' => 'literal', 'value' => 1]], '+ needs a number'],
			[['type' => 'binary', 'op' => '<', 'left' => ['type' => 'literal', 'value' => true], 'right' => ['type' => 'literal', 'value' => 1]], "'<' needs two numbers, two strings or two dates"],
		];
		foreach ($cases as [$ast, $message]) {
			try {
				$evaluator->evaluate(ast: $ast, context: ['row' => []], nowMs: 0);
				$this->fail('expected: ' . $message);
			} catch (EvaluationError $error) {
				$this->assertSame($message, $error->getMessage());
			}
		}
	}//end testUnknownNodesAndWrongKindsFail()
}//end class
