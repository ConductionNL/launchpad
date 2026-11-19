<?php

/**
 * CalculatedFieldAstCheckerTest
 *
 * The save-time AST check accepts the trees the client parser produces and
 * refuses malformed nodes, unknown and disallowed functions, wrong argument
 * counts and references to undeclared calculated fields (REQ-CALC-001,
 * REQ-CALC-009).
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-009-allowlisted-function-set-with-named-signatures
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Structural, allowlist and arity checks of a calculated-field AST.
 */
#[CoversClass(CalculatedFieldAstChecker::class)]
#[CoversClass(CalculatedFieldValidationError::class)]
#[UsesClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions::class)]
class CalculatedFieldAstCheckerTest extends TestCase {

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
	 * A field reference on the widget's own row.
	 *
	 * @param string $path The path.
	 *
	 * @return array<string,mixed>
	 */
	private static function field(string $path): array {
		return ['type' => 'fieldRef', 'source' => 'self', 'path' => $path];
	}//end field()

	/**
	 * A call node.
	 *
	 * @param string                    $name The function.
	 * @param list<array<string,mixed>> $args The arguments.
	 *
	 * @return array<string,mixed>
	 */
	private static function call(string $name, array $args): array {
		return ['type' => 'call', 'name' => $name, 'args' => $args];
	}//end call()

	/**
	 * The error a check raises.
	 *
	 * @param mixed             $ast     The AST.
	 * @param list<string>      $names   The calculated field names.
	 * @param list<string>|null $allowed The allowlist.
	 *
	 * @return array<string,mixed> The error envelope.
	 */
	private static function errorOf(mixed $ast, array $names=[], ?array $allowed=null): array {
		try {
			(new CalculatedFieldAstChecker())->check(ast: $ast, calculatedNames: $names, allowed: $allowed);
		} catch (CalculatedFieldValidationError $error) {
			return $error->getDetail();
		}

		self::fail('The AST was accepted');
	}//end errorOf()

	/**
	 * A tree using every node type passes.
	 *
	 * @return void
	 */
	public function testAcceptsEveryNodeType(): void {
		$ast = [
			'type' => 'if',
			'cond' => ['type' => 'binary', 'op' => '>', 'left' => self::field('revenue'), 'right' => self::num(0)],
			'then' => self::call('round', [['type' => 'unary', 'op' => '-', 'operand' => ['type' => 'calculatedRef', 'name' => 'margin']], self::num(2)]),
			'else' => self::call(
				'coalesce',
				[
					['type' => 'literal', 'valueType' => 'null', 'value' => null],
					['type' => 'fieldRef', 'source' => 'widget:7', 'path' => 'target'],
					['type' => 'literal', 'valueType' => 'string', 'value' => 'x'],
					['type' => 'literal', 'valueType' => 'boolean', 'value' => true],
				]
			),
		];

		$checker = new CalculatedFieldAstChecker();
		$checker->check(ast: $ast, calculatedNames: ['margin']);
		self::assertTrue($checker->isNode(node: $ast));
	}//end testAcceptsEveryNodeType()

	/**
	 * Malformed nodes.
	 *
	 * @return array<string,array{0:mixed}>
	 */
	public static function malformed(): array {
		return [
			'not an array'          => ['revenue'],
			'a list'                => [[self::num(1)]],
			'unknown type'          => [['type' => 'eval', 'code' => 'x']],
			'extra key'             => [['type' => 'literal', 'valueType' => 'number', 'value' => 1, 'raw' => '1']],
			'missing key'           => [['type' => 'binary', 'op' => '+', 'left' => self::num(1)]],
			'number as string'      => [['type' => 'literal', 'valueType' => 'number', 'value' => '1']],
			'null with a value'     => [['type' => 'literal', 'valueType' => 'null', 'value' => 0]],
			'unknown value type'    => [['type' => 'literal', 'valueType' => 'object', 'value' => 1]],
			'bad field source'      => [['type' => 'fieldRef', 'source' => 'register:1', 'path' => 'x']],
			'empty field path'      => [['type' => 'fieldRef', 'source' => 'self', 'path' => '']],
			'empty calculated name' => [['type' => 'calculatedRef', 'name' => '']],
			'unknown operator'      => [['type' => 'binary', 'op' => '**', 'left' => self::num(1), 'right' => self::num(2)]],
			'unknown unary'         => [['type' => 'unary', 'op' => '!', 'operand' => self::num(1)]],
			'bad if branch'         => [['type' => 'if', 'cond' => self::num(1), 'then' => self::num(1), 'else' => 'x']],
			'args not a list'       => [['type' => 'call', 'name' => 'abs', 'args' => ['a' => self::num(1)]]],
			'bad argument'          => [self::call('abs', [['type' => 'nope']])],
			'name not a string'     => [['type' => 'call', 'name' => 5, 'args' => []]],
		];
	}//end malformed()

	/**
	 * A malformed tree is refused as an invalid expression.
	 *
	 * @param mixed $ast The AST.
	 *
	 * @return void
	 */
	#[DataProvider('malformed')]
	public function testRefusesAMalformedTree(mixed $ast): void {
		self::assertSame('invalidExpression', self::errorOf($ast)['error']);
	}//end testRefusesAMalformedTree()

	/**
	 * An unknown function is refused by name.
	 *
	 * @return void
	 */
	public function testRefusesAnUnknownFunction(): void {
		$detail = self::errorOf(self::call('eval', [['type' => 'literal', 'valueType' => 'string', 'value' => 'revenue * 2']]));

		self::assertSame(['error' => 'unknownFunction', 'name' => 'eval', 'message' => "Unknown function 'eval'"], $detail);
	}//end testRefusesAnUnknownFunction()

	/**
	 * A function outside the instance's allowlist is refused, nested ones too.
	 *
	 * @return void
	 */
	public function testRefusesAFunctionOutsideTheAllowlist(): void {
		$ast = self::call('round', [self::call('now', []), self::num(0)]);

		$detail = self::errorOf($ast, [], ['abs', 'round', 'sum']);

		self::assertSame('functionNotAllowed', $detail['error']);
		self::assertSame('now', $detail['name']);
	}//end testRefusesAFunctionOutsideTheAllowlist()

	/**
	 * Calls with too few or too many arguments are refused with the accepted count.
	 *
	 * @return void
	 */
	public function testRefusesAWrongArgumentCount(): void {
		self::assertSame(
			"'pow' takes 2 argument(s), got 1",
			self::errorOf(self::call('pow', [self::num(2)]))['message']
		);
		self::assertSame(
			"'round' takes 1 to 2 argument(s), got 3",
			self::errorOf(self::call('round', [self::num(1), self::num(2), self::num(3)]))['message']
		);
		self::assertSame(
			"'sum' takes at least 1 argument(s), got 0",
			self::errorOf(self::call('sum', []))['message']
		);
		self::assertSame('arityMismatch', self::errorOf(self::call('now', [self::num(1)]))['error']);
	}//end testRefusesAWrongArgumentCount()

	/**
	 * A variadic function takes any number of arguments above its minimum.
	 *
	 * @return void
	 */
	public function testAcceptsAVariadicCall(): void {
		$ast = self::call('sum', [self::field('revenue'), self::field('cost'), self::field('profit')]);

		(new CalculatedFieldAstChecker())->check(ast: $ast, allowed: ['abs', 'round', 'sum']);
		self::assertTrue((new CalculatedFieldAstChecker())->isNode(node: $ast));
	}//end testAcceptsAVariadicCall()

	/**
	 * A reference to a calculated field the widget does not declare is refused.
	 *
	 * @return void
	 */
	public function testRefusesAnUndeclaredCalculatedField(): void {
		$detail = self::errorOf(['type' => 'calculatedRef', 'name' => 'ghost'], ['margin']);

		self::assertSame('unknownCalculatedField', $detail['error']);
		self::assertSame('ghost', $detail['name']);
	}//end testRefusesAnUndeclaredCalculatedField()

	/**
	 * Every function the evaluator implements has an argument count.
	 *
	 * @return void
	 */
	public function testEveryBuiltInHasAnArity(): void {
		foreach (array_keys(CalculatedFieldAstChecker::ARITY) as $name) {
			self::assertTrue(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldFunctions::has(name: $name), $name);
		}

		self::assertCount(39, CalculatedFieldAstChecker::ARITY);
	}//end testEveryBuiltInHasAnArity()
}//end class
