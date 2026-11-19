<?php

/**
 * CalculatedFieldServiceTest
 *
 * The save-time validation of a widget's calculated fields: fields come back
 * with their `returnType`, typed in dependency order; malformed, duplicate,
 * mistyped and cyclic fields are refused with the field named; evaluation
 * runs the shared evaluator on a row (widget-calculated-fields, tasks 23-25).
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
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-23
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValues;
use OCA\LaunchPad\Service\CalculatedFields\EvaluationError;
use OCA\LaunchPad\Service\CalculatedFieldService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Validation, compilation and evaluation of calculated fields.
 */
#[CoversClass(CalculatedFieldService::class)]
#[UsesClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldServerFields::class)]
#[UsesClass(CalculatedFieldAstChecker::class)]
#[UsesClass(CalculatedFieldTypeChecker::class)]
#[UsesClass(CalculatedFieldCycleDetector::class)]
#[UsesClass(CalculatedFieldCycleError::class)]
#[UsesClass(CalculatedFieldEvaluator::class)]
#[UsesClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources::class)]
#[UsesClass(CalculatedFieldFunctions::class)]
#[UsesClass(CalculatedFieldValues::class)]
#[UsesClass(CalculatedFieldValidationError::class)]
#[UsesClass(EvaluationError::class)]
class CalculatedFieldServiceTest extends TestCase {

	/**
	 * The service over its real, stateless collaborators.
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
	 * A literal node.
	 *
	 * @param mixed $value The value.
	 *
	 * @return array<string,mixed>
	 */
	private static function lit(mixed $value): array {
		$type = match (true) {
			is_string($value) => 'string',
			is_bool($value)   => 'boolean',
			$value === null   => 'null',
			default           => 'number',
		};

		return ['type' => 'literal', 'valueType' => $type, 'value' => $value];
	}//end lit()

	/**
	 * A binary node.
	 *
	 * @param string              $op    The operator.
	 * @param array<string,mixed> $left  The left operand.
	 * @param array<string,mixed> $right The right operand.
	 *
	 * @return array<string,mixed>
	 */
	private static function bin(string $op, array $left, array $right): array {
		return ['type' => 'binary', 'op' => $op, 'left' => $left, 'right' => $right];
	}//end bin()

	/**
	 * A calculated field.
	 *
	 * @param string              $name The name.
	 * @param array<string,mixed> $ast  The compiled expression.
	 *
	 * @return array<string,mixed>
	 */
	private static function calc(string $name, array $ast): array {
		return ['name' => $name, 'expression' => '…', 'compiledExpression' => $ast, 'evaluationMode' => 'client'];
	}//end calc()

	/**
	 * The refusal of a validation.
	 *
	 * @param array<string,mixed>            $widget The widget.
	 * @param array<int,array<string,mixed>> $others Its neighbours.
	 *
	 * @return array<string,mixed> The error envelope.
	 */
	private static function refusal(array $widget, array $others=[]): array {
		try {
			self::service()->validateAndCompile(widget: $widget, others: $others);
		} catch (CalculatedFieldValidationError | CalculatedFieldCycleError $error) {
			return $error->getDetail();
		}

		self::fail('The fields were accepted');
	}//end refusal()

	/**
	 * Fields come back as given with their return type, a field typed after the field it reads.
	 *
	 * @return void
	 */
	public function testSetsTheReturnTypeOfEveryField(): void {
		$label  = self::calc(
			'label',
			['type' => 'if', 'cond' => self::bin('>', ['type' => 'calculatedRef', 'name' => 'margin'], self::lit(30)), 'then' => self::lit('High'), 'else' => self::lit('Low')]
		);
		$margin = self::calc('margin', self::bin('*', self::bin('/', self::field('profit'), self::field('revenue')), self::lit(100)));

		$result = self::service()->validateAndCompile(widget: ['id' => 5, 'calculatedFields' => [$label, $margin]]);

		self::assertSame(['label', 'margin'], array_column($result['fields'], 'name'));
		self::assertSame(['string', 'number'], array_column($result['fields'], 'returnType'));
		self::assertSame('client', $result['fields'][0]['evaluationMode']);
		self::assertSame(['5.margin', '5.label'], $result['order']);
	}//end testSetsTheReturnTypeOfEveryField()

	/**
	 * Field types come from the schema when one is given.
	 *
	 * @return void
	 */
	public function testTypeChecksAgainstTheGivenSchema(): void {
		$widget = ['id' => 'w1', 'calculatedFields' => [self::calc('bad', self::bin('+', self::field('revenue'), self::field('customerName')))]];

		$detail = [];
		self::assertSame('number', self::service()->validateAndCompile(widget: $widget)['fields'][0]['returnType']);

		try {
			self::service()->validateAndCompile(widget: $widget, options: ['fieldSchema' => ['revenue' => 'number', 'customerName' => 'string']]);
			self::fail('A number plus a string was accepted');
		} catch (CalculatedFieldValidationError $error) {
			$detail = $error->getDetail();
		}

		self::assertSame('typeError', $detail['error']);
		self::assertSame(['revenue', 'customerName'], $detail['operands']);
		self::assertSame('bad', $detail['field']);
	}//end testTypeChecksAgainstTheGivenSchema()

	/**
	 * A malformed tree is refused with the field named.
	 *
	 * @return void
	 */
	public function testRefusesAMalformedTreeNamingTheField(): void {
		$detail = self::refusal(['id' => 1, 'calculatedFields' => [self::calc('x', ['type' => 'eval'])]]);

		self::assertSame(['invalidExpression', 'x'], [$detail['error'], $detail['field']]);
	}//end testRefusesAMalformedTreeNamingTheField()

	/**
	 * A function outside the instance allowlist is refused.
	 *
	 * @return void
	 */
	public function testHonoursTheAllowlist(): void {
		$widget = ['id' => 1, 'calculatedFields' => [self::calc('t', ['type' => 'call', 'name' => 'now', 'args' => []])]];

		try {
			self::service()->validateAndCompile(widget: $widget, options: ['allowedFunctions' => ['abs', 'round', 'sum']]);
			self::fail('now() was accepted');
		} catch (CalculatedFieldValidationError $error) {
			self::assertSame(['error' => 'functionNotAllowed', 'name' => 'now'], array_intersect_key($error->getDetail(), ['error' => 1, 'name' => 1]));
		}

		self::assertSame('date', self::service()->validateAndCompile(widget: $widget)['fields'][0]['returnType']);
	}//end testHonoursTheAllowlist()

	/**
	 * Malformed field entries.
	 *
	 * @return void
	 */
	public function testRefusesMalformedFieldEntries(): void {
		self::assertSame('invalidField', self::refusal(['id' => 1, 'calculatedFields' => ['a' => self::calc('a', self::lit(1))]])['error']);
		self::assertSame('invalidField', self::refusal(['id' => 1, 'calculatedFields' => 'x'])['error']);
		$detail = self::refusal(['id' => 1, 'calculatedFields' => [self::calc('a', self::lit(1)), ['name' => 'b', 'expression' => '1']]]);
		self::assertSame(['invalidField', 1], [$detail['error'], $detail['index']]);
		self::assertSame('invalidField', self::refusal(['id' => 1, 'calculatedFields' => [['name' => '', 'compiledExpression' => self::lit(1)]]])['error']);
	}//end testRefusesMalformedFieldEntries()

	/**
	 * A name declared twice is refused.
	 *
	 * @return void
	 */
	public function testRefusesADuplicateName(): void {
		$detail = self::refusal(['id' => 1, 'calculatedFields' => [self::calc('a', self::lit(1)), self::calc('a', self::lit(2))]]);

		self::assertSame(['duplicateField', 'a'], [$detail['error'], $detail['field']]);
	}//end testRefusesADuplicateName()

	/**
	 * Fields reading each other, and widgets reading each other, are refused as cycles.
	 *
	 * @return void
	 */
	public function testRefusesCycles(): void {
		$selfCycle = self::refusal(
			[
				'id'               => 'A',
				'calculatedFields' => [
					self::calc('x', ['type' => 'calculatedRef', 'name' => 'y']),
					self::calc('y', ['type' => 'calculatedRef', 'name' => 'x']),
				],
			]
		);
		self::assertSame('cycleDetected', $selfCycle['error']);
		self::assertSame(['A'], $selfCycle['widgets']);

		$across = self::refusal(
			['id' => 'A', 'calculatedFields' => [self::calc('calc1', self::field('x', 'widget:B'))]],
			[['id' => 'B', 'calculatedFields' => [self::calc('calc1', self::field('y', 'widget:A'))]]]
		);
		self::assertSame('cycleDetected', $across['error']);
		self::assertEqualsCanonicalizing(['A', 'B'], $across['widgets']);
	}//end testRefusesCycles()

	/**
	 * The widget's stored copy among its neighbours is replaced by the new fields, not checked twice.
	 *
	 * @return void
	 */
	public function testIgnoresTheWidgetsOwnStoredCopy(): void {
		$stale = ['id' => 'A', 'calculatedFields' => [self::calc('calc1', self::field('y', 'widget:B'))]];
		$other = ['id' => 'B', 'calculatedFields' => [self::calc('calc1', self::field('x', 'widget:A'))]];

		$result = self::service()->validateAndCompile(widget: ['id' => 'A', 'calculatedFields' => [self::calc('calc1', self::lit(1))]], others: [$stale, $other]);

		self::assertSame('number', $result['fields'][0]['returnType']);
		self::assertSame(['A.calc1', 'B.calc1'], $result['order']);
	}//end testIgnoresTheWidgetsOwnStoredCopy()

	/**
	 * No calculated fields is valid.
	 *
	 * @return void
	 */
	public function testAcceptsNoFields(): void {
		self::assertSame(['fields' => [], 'order' => [], 'warnings' => []], self::service()->validateAndCompile(widget: ['id' => 3, 'calculatedFields' => []]));
		self::assertSame(['fields' => [], 'order' => [], 'warnings' => []], self::service()->validateAndCompile(widget: ['id' => 3]));
	}//end testAcceptsNoFields()

	/**
	 * A server-mode field reading a widget of another register is downgraded with a warning;
	 * one reading the same register and schema, and a client-mode field, are kept.
	 *
	 * @return void
	 */
	public function testDowngradesServerFieldsThatReadAnotherSource(): void {
		$cross     = self::calc('comparison', self::bin('/', self::field('target', 'widget:B'), self::field('revenue')));
		$cross['evaluationMode'] = 'server';
		$same      = self::calc('net', self::bin('-', self::field('revenue'), self::field('refunds', 'widget:C')));
		$same['evaluationMode'] = 'both';
		$client    = self::calc('local', self::field('x', 'widget:B'));
		$widget    = ['id' => 'A', 'calculatedFields' => [$cross, $same, $client]];
		$sources   = ['A' => 'sales/deals', 'B' => 'crm/targets', 'C' => 'sales/deals'];

		$result = self::service()->validateAndCompile(widget: $widget, options: ['sources' => $sources]);

		self::assertSame(['client', 'both', 'client'], array_column($result['fields'], 'evaluationMode'));
		self::assertSame(
			[
				[
					'warning' => 'downgraded_evaluation_mode',
					'field'   => 'comparison',
					'reason'  => "Cross-register reference; evaluationMode downgraded from 'server' to 'client'",
				],
			],
			$result['warnings']
		);
	}//end testDowngradesServerFieldsThatReadAnotherSource()

	/**
	 * A server-mode field on a widget without an OpenRegister source cannot be materialised.
	 *
	 * @return void
	 */
	public function testDowngradesServerFieldsOfAWidgetWithoutASource(): void {
		$field = self::calc('total', self::field('revenue'));
		$field['evaluationMode'] = 'server';

		$result = self::service()->downgradeCrossSource(id: 'A', fields: [$field], sources: ['A' => '']);

		self::assertSame('client', $result['fields'][0]['evaluationMode']);
		self::assertSame("The widget has no OpenRegister source; evaluationMode downgraded from 'server' to 'client'", $result['warnings'][0]['reason']);
	}//end testDowngradesServerFieldsOfAWidgetWithoutASource()

	/**
	 * The source key reads an object-list's top-level and a stat's nested source.
	 *
	 * @return void
	 */
	public function testSourceKey(): void {
		self::assertSame('5/12', CalculatedFieldService::sourceKey(content: ['register' => 5, 'schema' => 12]));
		self::assertSame('sales/deals', CalculatedFieldService::sourceKey(content: ['source' => ['register' => 'sales', 'schema' => 'deals']]));
		self::assertSame('', CalculatedFieldService::sourceKey(content: ['register' => 'sales']));
		self::assertSame('', CalculatedFieldService::sourceKey(content: null));
	}//end testSourceKey()

	/**
	 * detectCycles answers the dependency order of several widgets.
	 *
	 * @return void
	 */
	public function testDetectCyclesOrdersDependenciesFirst(): void {
		$order = self::service()->detectCycles(
			widgets: [
				['id' => 'A', 'calculatedFields' => [self::calc('sum', self::field('total', 'widget:B'))]],
				['id' => 'B', 'calculatedFields' => [self::calc('total', self::lit(2))]],
			]
		);

		self::assertLessThan(array_search('A.sum', $order, true), array_search('B.total', $order, true));
	}//end testDetectCyclesOrdersDependenciesFirst()

	/**
	 * Evaluation runs on the row, failures raise the evaluator's error.
	 *
	 * @return void
	 */
	public function testEvaluatesOnARow(): void {
		$margin = self::bin('*', self::bin('/', self::bin('-', self::field('revenue'), self::field('cost')), self::field('revenue')), self::lit(100));

		self::assertEqualsWithDelta(40.0, self::service()->evaluateExpression(ast: $margin, row: ['revenue' => 150000, 'cost' => 90000]), 1e-9);
		self::assertNull(self::service()->evaluateExpression(ast: $margin, row: ['revenue' => null, 'cost' => 1]));

		$this->expectException(EvaluationError::class);
		self::service()->evaluateExpression(ast: $margin, row: ['revenue' => 0, 'cost' => 0]);
	}//end testEvaluatesOnARow()
}//end class
