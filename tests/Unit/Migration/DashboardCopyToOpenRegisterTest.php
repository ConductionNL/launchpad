<?php

/**
 * DashboardCopyToOpenRegisterTest
 *
 * Pins how Version002000Date20260519000000 hands a legacy dashboard row to
 * OpenRegister's ObjectService::saveObject(). The object service is a recording
 * double with OpenRegister's saveObject() parameter list, which takes register
 * and schema inside ObjectSaveOptions, so a call that passes them any other way
 * fails and the row is counted as failed.
 *
 * @category  Test
 * @package   Unit\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCA\LaunchPad\Migration\Version002000Date20260519000000;
use OCA\OpenRegister\Service\Object\ObjectSaveOptions;
use OCP\DB\IResult;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../Stubs/OpenRegisterStubs.php';

/**
 * The dashboard copy saves each legacy row as the object, under the launchpad register and dashboard schema.
 */
class DashboardCopyToOpenRegisterTest extends TestCase {
	/**
	 * A legacy dashboard row reaches saveObject() as the object, with register and schema by name.
	 *
	 * @return void
	 */
	public function testLegacyDashboardIsSavedAsTheObjectInTheLaunchpadRegister(): void {
		$objectService = new class {
			/**
			 * The arguments of each saveObject() call.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public array $calls = [];

			/**
			 * Record a save with OpenRegister's saveObject() parameter list.
			 *
			 * @param array|object      $object  The object data.
			 * @param ObjectSaveOptions $options The register, schema and save options.
			 *
			 * @return object The saved object.
			 */
			public function saveObject(
				array|object $object,
				ObjectSaveOptions $options = new ObjectSaveOptions(),
			): object {
				$this->calls[] = [
					'object' => $object,
					'extend' => $options->extend,
					'register' => $options->target->register,
					'schema' => $options->target->schema,
				];
				return new \stdClass();
			}
		};

		$db = $this->databaseWithOneDashboard();

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => $id === IDBConnection::class ? $db : $objectService
		);

		$output = $this->createMock(IOutput::class);
		$messages = [];
		$output->method('info')->willReturnCallback(
			static function (string $message) use (&$messages): void {
				$messages[] = $message;
			}
		);

		$migration = new Version002000Date20260519000000(
			container: $container,
			logger: $this->createMock(LoggerInterface::class),
		);
		$migration->postSchemaChange(
			$output,
			static fn (): ISchemaWrapper => throw new \LogicException('not used'),
			[]
		);

		self::assertCount(1, $objectService->calls);
		$call = $objectService->calls[0];
		self::assertSame('launchpad', $call['register']);
		self::assertSame('dashboard', $call['schema']);
		self::assertSame([], $call['extend']);
		self::assertIsArray($call['object']);
		self::assertSame('home', $call['object']['slug']);
		self::assertSame('Home', $call['object']['title']);
		self::assertContains('LaunchPad migration complete: 1 migrated, 0 failed.', $messages);
	}

	/**
	 * A connection whose legacy table holds one dashboard without placements.
	 *
	 * @return IDBConnection The connection double.
	 */
	private function databaseWithOneDashboard(): IDBConnection {
		$schema = new class extends Schema {
			/**
			 * Every table exists.
			 *
			 * @param mixed $name The table name.
			 *
			 * @return bool Always true.
			 */
			public function hasTable($name): bool {
				return $name !== '';
			}
		};

		$dashboards = $this->createMock(IResult::class);
		$dashboards->method('fetch')->willReturnOnConsecutiveCalls(
			['id' => 7, 'uuid' => 'uuid-7', 'slug' => 'home', 'name' => 'Home', 'description' => '', 'is_default' => 1],
			false
		);
		$placements = $this->createMock(IResult::class);
		$placements->method('fetch')->willReturn(false);

		$query = $this->createMock(IQueryBuilder::class);
		$query->method('select')->willReturnSelf();
		$query->method('from')->willReturnSelf();
		$query->method('where')->willReturnSelf();
		$query->method('setMaxResults')->willReturnSelf();
		$query->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$query->method('createNamedParameter')->willReturn(':param');
		$query->method('executeQuery')->willReturnOnConsecutiveCalls($dashboards, $placements);

		$db = $this->createMock(IDBConnection::class);
		$db->method('createSchema')->willReturn($schema);
		$db->method('getQueryBuilder')->willReturn($query);

		return $db;
	}
}
