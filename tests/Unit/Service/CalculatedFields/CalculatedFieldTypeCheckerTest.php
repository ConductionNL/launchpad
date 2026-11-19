<?php

/**
 * CalculatedFieldTypeCheckerTest
 *
 * The server-side type checker gives every AST of the client-generated
 * fixture the same type, or the same error envelope, as the client type
 * checker (REQ-CALC-002), and reads field types from a schema as it does.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Type inference over calculated-field ASTs.
 */
#[CoversClass(CalculatedFieldTypeChecker::class)]
#[CoversClass(CalculatedFieldValidationError::class)]
class CalculatedFieldTypeCheckerTest extends TestCase {

	/**
	 * The fixture generated from the client type checker.
	 *
	 * @return array<string,mixed>
	 */
	private static function fixture(): array {
		$json = file_get_contents(__DIR__.'/../../../fixtures/calculated-fields/type-check-cases.json');
		return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
	}//end fixture()

	/**
	 * One data set per fixture case.
	 *
	 * @return array<string,array{0:array<string,mixed>}>
	 */
	public static function clientCases(): array {
		$sets = [];
		foreach (self::fixture()['cases'] as $case) {
			$sets[$case['expression']] = [$case];
		}

		return $sets;
	}//end clientCases()

	/**
	 * The server answers each case as the client does.
	 *
	 * @param array<string,mixed> $case The case: `ast` and `type` or `error`.
	 *
	 * @return void
	 */
	#[DataProvider('clientCases')]
	public function testAgreesWithTheClientTypeChecker(array $case): void {
		$fixture = self::fixture();
		try {
			$type = (new CalculatedFieldTypeChecker())->inferType(
				ast: $case['ast'],
				fieldSchema: $fixture['schema'],
				calculatedTypes: $fixture['calculatedTypes'],
				widgetSchemas: $fixture['widgetSchemas']
			);
		} catch (CalculatedFieldValidationError $error) {
			self::assertArrayHasKey('error', $case, 'refused: '.$error->getMessage());
			self::assertSame($case['error'], $error->getDetail());
			return;
		}

		self::assertArrayHasKey('type', $case, 'accepted as '.$type);
		self::assertSame($case['type'], $type);
	}//end testAgreesWithTheClientTypeChecker()

	/**
	 * The fixture holds accepted and refused cases of every error kind.
	 *
	 * @return void
	 */
	public function testTheFixtureCoversAcceptedAndRefusedExpressions(): void {
		$errors = array_filter(array_map(static fn (array $case): ?string => $case['error']['error'] ?? null, self::fixture()['cases']));

		self::assertGreaterThanOrEqual(50, count(self::fixture()['cases']));
		self::assertContains('typeError', $errors);
		self::assertContains('functionSignature', $errors);
	}//end testTheFixtureCoversAcceptedAndRefusedExpressions()

	/**
	 * coalesce(null, revenue, 0) is a number (REQ-CALC-002 scenario).
	 *
	 * @return void
	 */
	public function testCoalesceTakesTheTypeOfItsKnownArguments(): void {
		$ast = [
			'type' => 'call',
			'name' => 'coalesce',
			'args' => [
				['type' => 'literal', 'valueType' => 'null', 'value' => null],
				['type' => 'fieldRef', 'source' => 'self', 'path' => 'revenue'],
				['type' => 'literal', 'valueType' => 'number', 'value' => 0],
			],
		];

		self::assertSame('number', (new CalculatedFieldTypeChecker())->inferType(ast: $ast, fieldSchema: ['revenue' => 'number']));
	}//end testCoalesceTakesTheTypeOfItsKnownArguments()

	/**
	 * Without a schema a field is `any` and fits everywhere.
	 *
	 * @return void
	 */
	public function testAFieldWithoutASchemaFitsEverywhere(): void {
		$ast = [
			'type'  => 'binary',
			'op'    => '+',
			'left'  => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'revenue'],
			'right' => ['type' => 'fieldRef', 'source' => 'widget:9', 'path' => 'customerName'],
		];

		self::assertSame('number', (new CalculatedFieldTypeChecker())->inferType(ast: $ast));
	}//end testAFieldWithoutASchemaFitsEverywhere()

	/**
	 * Schema property types map to expression types.
	 *
	 * @return void
	 */
	public function testReadsPropertyTypes(): void {
		self::assertSame('number', CalculatedFieldTypeChecker::typeOfProperty(property: 'integer'));
		self::assertSame('number', CalculatedFieldTypeChecker::typeOfProperty(property: ['type' => ['null', 'number']]));
		self::assertSame('date', CalculatedFieldTypeChecker::typeOfProperty(property: ['type' => 'string', 'format' => 'date-time']));
		self::assertSame('duration', CalculatedFieldTypeChecker::typeOfProperty(property: ['type' => 'string', 'format' => 'duration']));
		self::assertSame('string', CalculatedFieldTypeChecker::typeOfProperty(property: ['type' => 'string', 'format' => 'email']));
		self::assertSame('list', CalculatedFieldTypeChecker::typeOfProperty(property: 'array'));
		self::assertSame('any', CalculatedFieldTypeChecker::typeOfProperty(property: 'object'));
		self::assertSame('any', CalculatedFieldTypeChecker::typeOfProperty(property: 42));
	}//end testReadsPropertyTypes()

	/**
	 * Dotted paths walk nested properties and array items.
	 *
	 * @return void
	 */
	public function testReadsNestedPaths(): void {
		$schema = [
			'properties' => [
				'order' => ['type' => 'object', 'properties' => ['total' => ['type' => 'number']]],
				'lines' => ['type' => 'array', 'items' => ['properties' => ['due' => ['type' => 'string', 'format' => 'date']]]],
			],
		];

		self::assertSame('number', CalculatedFieldTypeChecker::typeOfPath(schema: $schema, path: 'order.total'));
		self::assertSame('date', CalculatedFieldTypeChecker::typeOfPath(schema: $schema, path: 'lines.due'));
		self::assertSame('any', CalculatedFieldTypeChecker::typeOfPath(schema: $schema, path: 'order.missing'));
		self::assertSame('any', CalculatedFieldTypeChecker::typeOfPath(schema: null, path: 'order'));
	}//end testReadsNestedPaths()

	/**
	 * Nodes are described in error messages as the client describes them.
	 *
	 * @return void
	 */
	public function testDescribesNodes(): void {
		self::assertSame('widget:3.total', CalculatedFieldTypeChecker::describe(node: ['type' => 'fieldRef', 'source' => 'widget:3', 'path' => 'total']));
		self::assertSame('"a/é"', CalculatedFieldTypeChecker::describe(node: ['type' => 'literal', 'valueType' => 'string', 'value' => 'a/é']));
		self::assertSame('false', CalculatedFieldTypeChecker::describe(node: ['type' => 'literal', 'valueType' => 'boolean', 'value' => false]));
		self::assertSame('null', CalculatedFieldTypeChecker::describe(node: ['type' => 'literal', 'valueType' => 'null', 'value' => null]));
		self::assertSame('1.5', CalculatedFieldTypeChecker::describe(node: ['type' => 'literal', 'valueType' => 'number', 'value' => 1.5]));
		self::assertSame('if(…)', CalculatedFieldTypeChecker::describe(node: ['type' => 'if']));
		self::assertSame('?', CalculatedFieldTypeChecker::describe(node: 'x'));
	}//end testDescribesNodes()
}//end class
