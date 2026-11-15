<?php

/**
 * QueryRecorder
 *
 * A database connection double whose query builders record every call made
 * on them (and on their expression and function builders), so a test can pin
 * the exact query a mapper method builds without a database.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCP\DB\IResult;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Records the calls mappers make on query builders.
 */
class QueryRecorder {

	/**
	 * The recorded calls, in order.
	 *
	 * @var array<int, string>
	 */
	public array $calls = [];

	/**
	 * The rows every executed query returns.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = [];

	/**
	 * Number of builders handed out.
	 *
	 * @var int
	 */
	private int $builders = 0;

	/**
	 * Number of placeholders handed out.
	 *
	 * @var int
	 */
	private int $placeholders = 0;

	/**
	 * Build the recorder for a test.
	 *
	 * @param TestCase $test The test that owns the doubles.
	 */
	public function __construct(
		private readonly TestCase $test,
	) {
	}

	/**
	 * A connection whose query builders record into this recorder.
	 *
	 * @return IDBConnection&MockObject The connection double.
	 */
	public function connection(): IDBConnection {
		$db = $this->test->getMockBuilder(className: IDBConnection::class)->getMock();
		$db->method('getQueryBuilder')->willReturnCallback(fn (): IQueryBuilder => $this->builder());

		return $db;
	}

	/**
	 * A fresh recording query builder.
	 *
	 * @return IQueryBuilder&MockObject The builder double.
	 */
	private function builder(): IQueryBuilder {
		$tag = 'qb' . (++$this->builders);
		$this->calls[] = $tag . ' new';
		$qb = $this->test->getMockBuilder(className: IQueryBuilder::class)->getMock();
		$expr = $this->stringDouble(interface: IExpressionBuilder::class, tag: $tag . '.expr');
		$func = $this->stringDouble(interface: IFunctionBuilder::class, tag: $tag . '.func');
		$result = $this->result();

		foreach ((new ReflectionClass(IQueryBuilder::class))->getMethods() as $method) {
			$name = $method->getName();
			$qb->method($name)->willReturnCallback(
				function (mixed ...$args) use ($name, $tag, $qb, $expr, $func, $result): mixed {
					$this->calls[] = $tag . '.' . $name . '(' . $this->describe(args: $args) . ')';
					return match ($name) {
						'expr' => $expr,
						'func' => $func,
						'createNamedParameter', 'createPositionalParameter', 'createParameter' => ':p' . (++$this->placeholders),
						'createFunction' => $this->queryFunction(text: (string)($args[0] ?? '')),
						'executeQuery' => $result,
						'executeStatement', 'getLastInsertId' => 1,
						'getSQL', 'getTableName', 'prefixTableName', 'getColumnName' => (string)($args[0] ?? 'sql'),
						'getParameters', 'getParameterTypes', 'getQueryParts', 'getOutputColumns' => [],
						default => $qb,
					};
				}
			);
		}

		return $qb;
	}

	/**
	 * A result that yields the canned rows once.
	 *
	 * @return IResult&MockObject The result double.
	 */
	private function result(): IResult {
		$rows = $this->rows;
		$result = $this->test->getMockBuilder(className: IResult::class)->getMock();
		$next = static function () use (&$rows): array|false {
			return array_shift($rows) ?? false;
		};
		$result->method('fetch')->willReturnCallback($next);
		$result->method('fetchAssociative')->willReturnCallback($next);
		$result->method('fetchAll')->willReturn($this->rows);
		$first = false;
		if ($this->rows !== []) {
			$first = reset($this->rows[0]);
		}

		$result->method('fetchOne')->willReturn($first);
		$result->method('closeCursor')->willReturn(true);

		return $result;
	}

	/**
	 * A double whose methods record their call and return its text, or a
	 * typed stand-in for methods that must return an object.
	 *
	 * @param string $interface The interface to double.
	 * @param string $tag       The recording prefix.
	 *
	 * @return MockObject The double.
	 */
	private function stringDouble(string $interface, string $tag): MockObject {
		$double = $this->test->getMockBuilder(className: $interface)->getMock();
		foreach ((new ReflectionClass($interface))->getMethods() as $method) {
			$name = $method->getName();
			$type = $method->getReturnType();
			$returns = 'string';
			if ($type instanceof ReflectionNamedType) {
				$returns = $type->getName();
			}

			$double->method($name)->willReturnCallback(
				function (mixed ...$args) use ($name, $tag, $returns): mixed {
					$text = $name . '(' . $this->describe(args: $args) . ')';
					$this->calls[] = $tag . '.' . $text;
					return match ($returns) {
						ICompositeExpression::class => $this->composite(text: $text),
						ILiteral::class => $this->literal(text: $text),
						IQueryFunction::class => $this->queryFunction(text: $text),
						default => $text,
					};
				}
			);
		}

		return $double;
	}

	/**
	 * A query-function stand-in that prints as the given text.
	 *
	 * @param string $text The text.
	 *
	 * @return IQueryFunction The stand-in.
	 */
	private function queryFunction(string $text): IQueryFunction {
		$double = $this->test->getMockBuilder(className: IQueryFunction::class)->getMock();
		$double->method('__toString')->willReturn($text);

		return $double;
	}

	/**
	 * A literal stand-in that prints as the given text.
	 *
	 * @param string $text The text.
	 *
	 * @return ILiteral The stand-in.
	 */
	private function literal(string $text): ILiteral {
		$double = $this->test->getMockBuilder(className: ILiteral::class)->getMock();
		$double->method('__toString')->willReturn($text);

		return $double;
	}

	/**
	 * A composite-expression stand-in that prints as the given text.
	 *
	 * @param string $text The text.
	 *
	 * @return ICompositeExpression The stand-in.
	 */
	private function composite(string $text): ICompositeExpression {
		return new class($text) implements ICompositeExpression {
			/**
			 * Hold the printed text.
			 *
			 * @param string $text The text.
			 */
			public function __construct(
				private string $text,
			) {
			}

			/**
			 * Append parts to the printed text.
			 *
			 * @param array<int, mixed> $parts The parts.
			 *
			 * @return ICompositeExpression
			 */
			public function addMultiple(array $parts = []): ICompositeExpression {
				foreach ($parts as $part) {
					$this->add(part: $part);
				}

				return $this;
			}

			/**
			 * Append one part to the printed text.
			 *
			 * @param mixed $part The part.
			 *
			 * @return ICompositeExpression
			 */
			public function add($part): ICompositeExpression {
				$this->text .= ' + ' . (string)$part;

				return $this;
			}

			/**
			 * The stand-in does not count parts.
			 *
			 * @return int
			 */
			public function count(): int {
				return 0;
			}

			/**
			 * The stand-in has no type.
			 *
			 * @return string
			 */
			public function getType(): string {
				return '';
			}

			/**
			 * The printed text.
			 *
			 * @return string
			 */
			public function __toString(): string {
				return $this->text;
			}
		};
	}

	/**
	 * Print call arguments with their names and types.
	 *
	 * @param array<int|string, mixed> $args The arguments.
	 *
	 * @return string The printed arguments.
	 */
	private function describe(array $args): string {
		$out = [];
		foreach ($args as $key => $value) {
			$text = match (true) {
				is_object($value) && method_exists($value, '__toString') => (string)$value,
				is_object($value) => get_class($value),
				default => json_encode($value),
			};
			if (is_string($key) === true) {
				$text = $key . ': ' . $text;
			}

			$out[] = $text;
		}

		return implode(', ', $out);
	}
}
