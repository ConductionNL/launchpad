<?php

/**
 * ScheduledExportStoreTest
 *
 * The OpenRegister adapter of scheduled exports: each read and write goes to
 * the `launchpad` register through ObjectService with openregister main's
 * option objects, RBAC passed through, and every answer normalised to the
 * object's data with its id and owner.
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
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\ScheduledExportStore;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectDeletion;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/../../Stubs/OpenRegisterStubs.php';

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExportStore
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */
class ScheduledExportStoreTest extends TestCase {

	/**
	 * A stand-in ObjectService with openregister main's parameter names, recording each call.
	 *
	 * @var object
	 */
	private object $objects;

	/**
	 * The store under test.
	 *
	 * @var ScheduledExportStore
	 */
	private ScheduledExportStore $store;

	/**
	 * Wire the store to the stand-in ObjectService.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->objects = new class {
			/** @var list<array<string, mixed>> */
			public array $calls = [];

			/** @var mixed */
			public mixed $answer = null;

			public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], bool $_rbac=true, bool $_multitenancy=true): array|int {
				$this->calls[] = ['searchObjectsBySlug', $registerSlug, $schemaSlug, $filters, $_rbac];
				return $this->answer;
			}

			public function find(int|string $id, ?ObjectFindOptions $options=null, bool $_rbac=true, bool $_multitenancy=true): mixed {
				$this->calls[] = ['find', $id, $options, $_rbac];
				return $this->answer;
			}

			public function saveObject(array $object, ?ObjectSaveOptions $options=null, bool $_rbac=true, bool $_multitenancy=true): mixed {
				$this->calls[] = ['saveObject', $object, $options, $_rbac];
				return $this->answer;
			}

			public function deleteObject(ObjectDeletion $deletion, bool $_rbac=true, bool $_multitenancy=true, bool $_retentionSweep=false): bool {
				$this->calls[] = ['deleteObject', $deletion, $_rbac];
				return true;
			}
		};

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with('OCA\OpenRegister\Service\ObjectService')->willReturn($this->objects);
		$this->store = new ScheduledExportStore($container);
	}//end setUp()

	/**
	 * A list searches the launchpad register by schema slug with the filters and RBAC, and keeps only rows.
	 *
	 * @return void
	 */
	public function testListSearchesTheRegisterBySlugAndNormalisesRows(): void {
		$this->objects->answer = [
			['name' => 'Weekly', '@self' => ['id' => 'u-1', 'owner' => 'anna']],
			'not a row',
		];

		$rows = $this->store->list(schema: 'scheduled_export', filters: ['enabled' => true], rbac: false);

		$this->assertSame([['searchObjectsBySlug', 'launchpad', 'scheduled_export', ['enabled' => true], false]], $this->objects->calls);
		$this->assertSame([['name' => 'Weekly', '@self' => ['id' => 'u-1', 'owner' => 'anna'], 'id' => 'u-1']], $rows);
	}//end testListSearchesTheRegisterBySlugAndNormalisesRows()

	/**
	 * A count instead of rows reads as no rows.
	 *
	 * @return void
	 */
	public function testListOfACountIsEmpty(): void {
		$this->objects->answer = 3;
		$this->assertSame([], $this->store->list(schema: 'scheduled_export'));
	}//end testListOfACountIsEmpty()

	/**
	 * A find passes main's find options and turns an entity into its data with id and owner.
	 *
	 * @return void
	 */
	public function testFindReadsAnEntityAsItsDataWithIdAndOwner(): void {
		$this->objects->answer = new class {
			public function getObject(): array {
				return ['name' => 'Weekly'];
			}

			public function getUuid(): string {
				return 'u-2';
			}

			public function getOwner(): string {
				return 'bert';
			}
		};

		$row = $this->store->find(schema: 'scheduled_export_run', uuid: 'u-2');

		[$method, $id, $options, $rbac] = $this->objects->calls[0];
		$this->assertSame(['find', 'u-2', true], [$method, $id, $rbac]);
		$this->assertInstanceOf(ObjectFindOptions::class, $options);
		$this->assertSame(['launchpad', 'scheduled_export_run'], [$options->register, $options->schema]);
		$this->assertSame(['name' => 'Weekly', '@self' => ['id' => 'u-2', 'owner' => 'bert'], 'id' => 'u-2'], $row);
	}//end testFindReadsAnEntityAsItsDataWithIdAndOwner()

	/**
	 * Nothing found is null.
	 *
	 * @return void
	 */
	public function testFindOfNothingIsNull(): void {
		$this->assertNull($this->store->find(schema: 'scheduled_export', uuid: 'gone'));
	}//end testFindOfNothingIsNull()

	/**
	 * A save sends the data without id or @self, targeted at the register and schema, with the uuid to update.
	 *
	 * @return void
	 */
	public function testSaveTargetsTheSchemaAndDropsTheIdentityKeys(): void {
		$this->objects->answer = ['name' => 'Weekly', 'id' => 'u-3'];

		$saved = $this->store->save(schema: 'scheduled_export', data: ['id' => 'x', '@self' => [], 'name' => 'Weekly'], uuid: 'u-3', rbac: false);

		[$method, $object, $options, $rbac] = $this->objects->calls[0];
		$this->assertSame(['saveObject', ['name' => 'Weekly'], false], [$method, $object, $rbac]);
		$this->assertInstanceOf(ObjectSaveOptions::class, $options);
		$this->assertSame('u-3', $options->uuid);
		$this->assertSame(['launchpad', 'scheduled_export'], [$options->target->register, $options->target->schema]);
		$this->assertSame(['name' => 'Weekly', 'id' => 'u-3'], $saved);
	}//end testSaveTargetsTheSchemaAndDropsTheIdentityKeys()

	/**
	 * A delete is a soft delete of that uuid in the register and schema.
	 *
	 * @return void
	 */
	public function testDeleteIsASoftDeleteOfTheUuid(): void {
		$this->assertTrue($this->store->delete(schema: 'scheduled_export', uuid: 'u-4'));

		[$method, $deletion, $rbac] = $this->objects->calls[0];
		$this->assertSame(['deleteObject', true], [$method, $rbac]);
		$this->assertSame('u-4', $deletion->uuid);
		$this->assertFalse($deletion->permanent);
		$this->assertSame(['launchpad', 'scheduled_export'], [$deletion->target->register, $deletion->target->schema]);
	}//end testDeleteIsASoftDeleteOfTheUuid()
}//end class
