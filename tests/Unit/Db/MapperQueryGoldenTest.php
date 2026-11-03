<?php

/**
 * Mapper query golden test
 *
 * Pins the query every public mapper method builds: each method is called
 * with fixed arguments on a recording connection, and the builder calls and
 * the outcome are compared with a recorded fixture.
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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ErrorException;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * The queries of every mapper method, case by case.
 *
 * @coversNothing
 */
class MapperQueryGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/mapper-queries.json';

	/**
	 * One case per mapper.
	 *
	 * @return array<string, array{0: class-string}>
	 */
	public static function mappers(): array {
		$cases = [];
		foreach (glob(__DIR__ . '/../../../lib/Db/*Mapper.php') as $file) {
			$class = 'OCA\\LaunchPad\\Db\\' . basename($file, '.php');
			$cases[basename($file, '.php')] = [$class];
		}

		return $cases;
	}

	/**
	 * Every public method of the mapper builds the recorded queries.
	 *
	 * @param class-string $class The mapper class.
	 *
	 * @return void
	 */
	#[DataProvider('mappers')]
	public function testQueriesMatchTheRecordedFixture(string $class): void {
		$actual = [];
		foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() === true || $method->isStatic() === true) {
				continue;
			}

			foreach (['all' => false, 'required' => true] as $variant => $requiredOnly) {
				$args = $this->argumentsFor(method: $method, requiredOnly: $requiredOnly);
				if ($args === null || ($requiredOnly === true && $method->getNumberOfParameters() === $method->getNumberOfRequiredParameters())) {
					continue;
				}

				$actual[$method->getName() . ' ' . $variant] = $this->record(class: $class, call: [$method->getName(), $args]);
				if ($requiredOnly === false) {
					$actual[$method->getName() . ' rows'] = $this->record(class: $class, call: [$method->getName(), $args], rows: [['id' => '5'], ['id' => '6']]);
					if (str_contains($method->getName(), 'Orphan') === true) {
						$actual[$method->getName() . ' one row'] = $this->record(class: $class, call: [$method->getName(), $args], rows: [['id' => '5']]);
					}
				}
			}
		}

		$short = substr(strrchr($class, '\\'), 1);
		$all = [];
		if (is_file(self::FIXTURE) === true) {
			$all = json_decode((string)file_get_contents(self::FIXTURE), true);
		}
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$all[$short] = $actual;
			ksort($all);
			file_put_contents(self::FIXTURE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		foreach ($actual as $case => $record) {
			$this->assertSame(expected: $all[$short][$case] ?? null, actual: $record, message: $short . '::' . $case);
		}

		$this->assertSame(expected: array_keys($all[$short] ?? []), actual: array_keys($actual), message: $short . ' cases');
	}

	/**
	 * Call one method on a fresh mapper and record the queries and outcome.
	 *
	 * @param class-string                    $class The mapper class.
	 * @param array{0: string, 1: array<int, mixed>} $call  The method and its arguments.
	 * @param array<int, array<string, mixed>>       $rows  The rows every query returns.
	 *
	 * @return array{calls: array<int, string>, outcome: string}
	 */
	private function record(string $class, array $call, array $rows = []): array {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = $rows;
		$mapper = new $class(...$this->constructorArguments(class: $class, db: $recorder->connection()));
		set_error_handler(static function (int $severity, string $message): bool {
			throw new ErrorException($message, 0, $severity);
		});
		try {
			$outcome = 'returned ' . $this->summarise(value: $mapper->{$call[0]}(...$call[1]));
		} catch (Throwable $e) {
			$outcome = 'threw ' . get_class($e) . ': ' . $e->getMessage();
		} finally {
			restore_error_handler();
		}

		$calls = array_map(fn (string $line): string => $this->normalise(text: $line), $recorder->calls);

		return ['calls' => $calls, 'outcome' => $this->normalise(text: $outcome)];
	}

	/**
	 * Constructor arguments: the recording connection, doubles for the rest.
	 *
	 * @param class-string  $class The mapper class.
	 * @param IDBConnection $db    The recording connection.
	 *
	 * @return array<int, mixed>
	 */
	private function constructorArguments(string $class, IDBConnection $db): array {
		$args = [];
		foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $param) {
			$name = (string)$param->getType();
			if ($name === IDBConnection::class) {
				$args[] = $db;
				continue;
			}

			$args[] = $this->getMockBuilder(className: $name)->disableOriginalConstructor()->getMock();
		}

		return $args;
	}

	/**
	 * Fixed arguments for a method, or null when one cannot be made.
	 *
	 * @param ReflectionMethod $method       The method.
	 * @param bool             $requiredOnly Pass only the required ones.
	 *
	 * @return array<int, mixed>|null
	 */
	private function argumentsFor(ReflectionMethod $method, bool $requiredOnly): ?array {
		$args = [];
		foreach ($method->getParameters() as $param) {
			if ($requiredOnly === true && $param->isOptional() === true) {
				break;
			}

			$value = $this->valueFor(param: $param);
			if ($value === false) {
				return null;
			}

			$args[] = $value;
		}

		return $args;
	}

	/**
	 * A fixed value for one parameter, or false when its type is not handled.
	 *
	 * @param ReflectionParameter $param The parameter.
	 *
	 * @return mixed
	 */
	private function valueFor(ReflectionParameter $param): mixed {
		$type = $param->getType();
		$name = 'mixed';
		if ($type instanceof ReflectionNamedType) {
			$name = $type->getName();
		}

		return match ($name) {
			'int' => 7,
			'float' => 1.5,
			'bool' => true,
			'string', 'mixed' => 's-' . $param->getName(),
			'array' => ['a-1', 'a-2'],
			DateTime::class => new DateTime('2001-02-03 04:05:06'),
			DateTimeImmutable::class, DateTimeInterface::class => new DateTimeImmutable('2001-02-03 04:05:06'),
			default => false,
		};
	}

	/**
	 * Summarise a return value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 */
	private function summarise(mixed $value): string {
		if ($value instanceof Entity) {
			return get_class($value) . ' ' . json_encode($value->jsonSerialize());
		}

		if (is_array($value) === true) {
			return 'array ' . json_encode(array_map(fn (mixed $v): string => $this->summarise(value: $v), $value));
		}

		if (is_object($value) === true) {
			return get_class($value);
		}

		return json_encode($value);
	}

	/**
	 * Replace clock- and random-derived values with fixed markers.
	 *
	 * @param string $text The recorded text.
	 *
	 * @return string
	 */
	private function normalise(string $text): string {
		$text = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<uuid>', $text);
		$text = preg_replace('/(?!2001-02-03)\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', '<date>', $text);

		return preg_replace('/\b1[6-9]\d{8}\b/', '<ts>', $text);
	}
}
