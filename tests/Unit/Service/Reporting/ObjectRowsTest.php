<?php

/**
 * ObjectRows Test
 *
 * The report reader: what it asks OpenRegister, and that every way the read
 * can be impossible answers null instead of an error.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Reporting
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Reporting;

use JsonSerializable;
use OCA\LaunchPad\Service\Reporting\ObjectRows;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ObjectRowsTest extends TestCase {

	/**
	 * The queries the fake object service received.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $queries = [];

	public function testItAsksForTheRegisterAndSchemaAndReturnsArrays(): void {
		$entity = new class implements JsonSerializable {
			public function jsonSerialize(): array {
				return ['id' => 'b', 'status' => 'open'];
			}
		};
		$rows = $this->reader(answer: [['id' => 'a'], $entity, 'skipped'])->rows(register: 'cases', schema: 'case', filters: ['status' => 'open'], limit: 99999);

		$this->assertSame([['id' => 'a'], ['id' => 'b', 'status' => 'open']], $rows);
		$this->assertSame(
			[['status' => 'open', '@self' => ['register' => 'cases', 'schema' => 'case'], '_limit' => ObjectRows::MAX_ROWS]],
			$this->queries
		);
	}//end testItAsksForTheRegisterAndSchemaAndReturnsArrays()

	public function testWithoutOpenRegisterEveryReadIsNull(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('has')->with(ObjectRows::OBJECT_SERVICE)->willReturn(false);
		$container->expects($this->never())->method('get');
		$reader = new ObjectRows($container, $this->createMock(LoggerInterface::class));

		$this->assertNull($reader->rows(register: 'cases', schema: 'case'));
		$this->assertFalse($reader->isInstalled());
	}//end testWithoutOpenRegisterEveryReadIsNull()

	public function testAMissingSchemaIsNullAndLogged(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info')
			->with('launchpad.report.read_failed', $this->callback(static fn (array $c): bool => $c['schema'] === 'gone' && $c['exception'] === 'Schema not found'));

		$reader = $this->reader(answer: new RuntimeException('Schema not found'), logger: $logger);

		$this->assertNull($reader->rows(register: 'cases', schema: 'gone'));
		$this->assertTrue($reader->isInstalled());
	}//end testAMissingSchemaIsNullAndLogged()

	public function testAnEmptySlugOrACountAnswerIsNull(): void {
		$this->assertNull($this->reader(answer: [])->rows(register: '', schema: 'case'));
		$this->assertSame([], $this->queries);
		$this->assertNull($this->reader(answer: 3)->rows(register: 'cases', schema: 'case', limit: 0));
		$this->assertSame(1, $this->queries[0]['_limit']);
	}//end testAnEmptySlugOrACountAnswerIsNull()

	/**
	 * A reader over a fake object service.
	 *
	 * @param mixed                $answer What searchObjects returns, or a Throwable it throws.
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return ObjectRows
	 */
	private function reader(mixed $answer, ?LoggerInterface $logger=null): ObjectRows {
		$test = $this;
		$service = new class($answer, $test) {
			public function __construct(private mixed $answer, private ObjectRowsTest $test) {
			}

			public function searchObjects(array $query=[]): array|int {
				$this->test->record(query: $query);
				if ($this->answer instanceof \Throwable) {
					throw $this->answer;
				}

				return $this->answer;
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('has')->willReturn(true);
		$container->method('get')->with(ObjectRows::OBJECT_SERVICE)->willReturn($service);

		return new ObjectRows($container, $logger ?? $this->createMock(LoggerInterface::class));
	}//end reader()

	/**
	 * Record one query.
	 *
	 * @param array<string, mixed> $query The query.
	 *
	 * @return void
	 */
	public function record(array $query): void {
		$this->queries[] = $query;
	}//end record()
}//end class
