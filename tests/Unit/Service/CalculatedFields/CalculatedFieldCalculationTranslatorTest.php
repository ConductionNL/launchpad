<?php

/**
 * CalculatedFieldCalculationTranslatorTest
 *
 * A calculated field's AST becomes OpenRegister's JSON-AST calculation
 * expression where both engines evaluate it alike, and nothing otherwise
 * (widget-calculated-fields, task 26).
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
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCalculationTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AST to OpenRegister calculation expression.
 */
#[CoversClass(CalculatedFieldCalculationTranslator::class)]
class CalculatedFieldCalculationTranslatorTest extends TestCase {

	/**
	 * A field reference.
	 *
	 * @param string $path   The path.
	 * @param string $source The source.
	 *
	 * @return array<string,string>
	 */
	private static function field(string $path, string $source='self'): array {
		return ['type' => 'fieldRef', 'source' => $source, 'path' => $path];
	}//end field()

	/**
	 * A number literal.
	 *
	 * @param int|float $value The value.
	 *
	 * @return array<string,mixed>
	 */
	private static function num(int|float $value): array {
		return ['type' => 'literal', 'valueType' => 'number', 'value' => $value];
	}//end num()

	/**
	 * Translatable ASTs and what they become.
	 *
	 * @return array<string,array{0: array<string,mixed>, 1: mixed}>
	 */
	public static function translatable(): array {
		return [
			'subtraction'        => [
				['type' => 'binary', 'op' => '-', 'left' => self::field('revenue'), 'right' => self::field('cost')],
				['-' => [['prop' => 'revenue'], ['prop' => 'cost']]],
			],
			'comparison'         => [
				['type' => 'binary', 'op' => '>=', 'left' => self::field('a.b'), 'right' => self::num(3)],
				['gte' => [['prop' => 'a.b'], ['lit' => 3]]],
			],
			'equality'           => [
				['type' => 'binary', 'op' => '!=', 'left' => self::field('x'), 'right' => ['type' => 'literal', 'valueType' => 'null', 'value' => null]],
				['ne' => [['prop' => 'x'], ['lit' => null]]],
			],
			'own widget by id'   => [self::field('total', 'widget:7'), ['prop' => 'total']],
			'negation'           => [['type' => 'unary', 'op' => '-', 'operand' => self::num(2)], ['-' => [['lit' => 2]]]],
			'not'                => [
				['type' => 'unary', 'op' => 'not', 'operand' => ['type' => 'literal', 'valueType' => 'boolean', 'value' => true]],
				['not' => [['lit' => true]]],
			],
			'conditional'        => [
				['type' => 'if', 'cond' => self::field('ok'), 'then' => self::num(1), 'else' => self::num(0)],
				['if' => [['prop' => 'ok'], ['lit' => 1], ['lit' => 0]]],
			],
			'coalesce'           => [
				['type' => 'call', 'name' => 'coalesce', 'args' => [self::field('a'), self::num(0)]],
				['coalesce' => [['prop' => 'a'], ['lit' => 0]]],
			],
			'materialised field' => [['type' => 'calculatedRef', 'name' => 'margin'], ['prop' => 'launchpad_7__margin']],
		];
	}//end translatable()

	/**
	 * Each translatable AST becomes its OpenRegister expression.
	 *
	 * @param array<string,mixed> $ast      The AST.
	 * @param mixed               $expected The expression.
	 *
	 * @return void
	 */
	#[DataProvider('translatable')]
	public function testTranslates(array $ast, mixed $expected): void {
		$translator = new CalculatedFieldCalculationTranslator();
		$this->assertSame($expected, $translator->translate(node: $ast, widgetId: '7', keys: ['margin' => 'launchpad_7__margin']));
	}//end testTranslates()

	/**
	 * ASTs OpenRegister does not evaluate alike.
	 *
	 * @return array<string,array{0: mixed}>
	 */
	public static function untranslatable(): array {
		return [
			'another widget'          => [self::field('total', 'widget:8')],
			'a metadata path'         => [self::field('@self.owner')],
			'a placeholder string'    => [['type' => 'literal', 'valueType' => 'string', 'value' => '$now']],
			'an unmaterialised field' => [['type' => 'calculatedRef', 'name' => 'other']],
			'a function it lacks'     => [['type' => 'call', 'name' => 'upper', 'args' => [self::field('name')]]],
			'round'                   => [['type' => 'call', 'name' => 'round', 'args' => [self::field('x')]]],
			'a nested gap'            => [
				['type' => 'binary', 'op' => '+', 'left' => self::num(1), 'right' => ['type' => 'call', 'name' => 'now', 'args' => []]],
			],
			'an unknown operator'     => [['type' => 'binary', 'op' => '^', 'left' => self::num(1), 'right' => self::num(2)]],
			'an unknown node'         => [['type' => 'lambda']],
			'not a node'              => ['revenue'],
		];
	}//end untranslatable()

	/**
	 * Each untranslatable AST answers null.
	 *
	 * @param mixed $ast The AST.
	 *
	 * @return void
	 */
	#[DataProvider('untranslatable')]
	public function testRefuses(mixed $ast): void {
		$translator = new CalculatedFieldCalculationTranslator();
		$this->assertNull($translator->translate(node: $ast, widgetId: '7', keys: ['margin' => 'launchpad_7__margin']));
	}//end testRefuses()

	/**
	 * Only the types OpenRegister declares are mapped.
	 *
	 * @return void
	 */
	public function testCalculationType(): void {
		$translator = new CalculatedFieldCalculationTranslator();
		$this->assertSame('number', $translator->calculationType(returnType: 'number'));
		$this->assertSame('date', $translator->calculationType(returnType: 'date'));
		$this->assertNull($translator->calculationType(returnType: 'duration'));
		$this->assertNull($translator->calculationType(returnType: null));
	}//end testCalculationType()

	/**
	 * The calculated fields an AST reads, each once, from every child.
	 *
	 * @return void
	 */
	public function testCalculatedNames(): void {
		$ref = static fn (string $name): array => ['type' => 'calculatedRef', 'name' => $name];
		$ast = [
			'type' => 'if',
			'cond' => ['type' => 'unary', 'op' => 'not', 'operand' => $ref('a')],
			'then' => ['type' => 'binary', 'op' => '+', 'left' => $ref('b'), 'right' => $ref('a')],
			'else' => ['type' => 'call', 'name' => 'max', 'args' => [$ref('c'), self::num(1)]],
		];
		$this->assertSame(['a', 'b', 'c'], (new CalculatedFieldCalculationTranslator())->calculatedNames(node: $ast));
		$this->assertSame([], (new CalculatedFieldCalculationTranslator())->calculatedNames(node: 'x'));
	}//end testCalculatedNames()
}//end class
