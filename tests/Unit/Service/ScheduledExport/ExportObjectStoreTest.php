<?php

/**
 * ExportObjectStoreTest
 *
 * The OpenRegister adapter of scheduled exports (tasks.md task 3): the
 * launchpad register and schema on every call, RBAC off on every call, `id`/`owner` lifted from `@self`, and an absent OpenRegister read
 * as no objects.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
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

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

require_once __DIR__ . '/../../../Stubs/OpenRegisterValueStubs.php';

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */
class ExportObjectStoreTest extends TestCase {

	/**
	 * A fake ObjectService recording every call.
	 *
	 * @return object
	 */
	private function objectService(): object {
		return new class {
			/** @var list<array<string, mixed>> */
			public array $calls = [];

			/** @var mixed */
			public mixed $found = null;

			public function findAll(array $config, bool $_rbac = true): array {
				$this->calls[] = ['findAll', $config, $_rbac];
				return [['name' => 'A', '@self' => ['id' => 'u1', 'owner' => 'alice']], 'junk'];
			}

			public function find(string $id, object $options, bool $_rbac = true): mixed {
				$this->calls[] = ['find', $id, $options, $_rbac];
				if ($this->found instanceof \Throwable) {
					throw $this->found;
				}

				return $this->found;
			}

			public function saveObject(array $object, object $options, bool $_rbac = true): object {
				$this->calls[] = ['save', $object, $options, $_rbac];
				return new class($object, $options->uuid ?? 'new') implements \JsonSerializable {
					public function __construct(private array $o, private string $id) {
					}

					public function jsonSerialize(): array {
						return $this->o + ['@self' => ['id' => $this->id, 'owner' => 'alice']];
					}
				};
			}

			public function deleteObject(object $deletion, bool $_rbac = true): bool {
				$this->calls[] = ['delete', $deletion->uuid, $_rbac];
				return true;
			}
		};
	}//end objectService()

	/**
	 * A store over the given ObjectService.
	 *
	 * @param object|null $service The service, or null for "OpenRegister is not enabled".
	 *
	 * @return ExportObjectStore
	 */
	private function store(?object $service): ExportObjectStore {
		$container = $this->createMock(ContainerInterface::class);
		if ($service === null) {
			$container->method('get')->willThrowException(new RuntimeException('no'));
		} else {
			$container->method('get')->with('OCA\OpenRegister\Service\ObjectService')->willReturn($service);
		}

		return new ExportObjectStore($container);
	}//end store()

	/**
	 * findAll scopes to the register and schema, reads with RBAC off and normalises the rows.
	 *
	 * @return void
	 */
	public function testFindAllScopesAndNormalises(): void {
		$service = $this->objectService();
		$rows    = $this->store($service)->findAll(schema: 'scheduled_export', filters: ['enabled' => true]);

		$this->assertSame([['name' => 'A', 'id' => 'u1', 'owner' => 'alice']], $rows);
		$this->assertSame(['enabled' => true, 'register' => 'launchpad', 'schema' => 'scheduled_export'], $service->calls[0][1]['filters']);
		$this->assertFalse($service->calls[0][2]);
	}//end testFindAllScopesAndNormalises()

	/**
	 * find returns the object, null for an empty id, and null when OpenRegister throws.
	 *
	 * @return void
	 */
	public function testFindReturnsObjectOrNull(): void {
		$service        = $this->objectService();
		$service->found = ['name' => 'B', '@self' => ['uuid' => 'u2']];
		$store          = $this->store($service);

		$this->assertSame(['name' => 'B', 'id' => 'u2', 'owner' => null], $store->find(schema: 'scheduled_export', uuid: 'u2'));
		$this->assertSame('launchpad', $service->calls[0][2]->register);
		$this->assertSame('scheduled_export', $service->calls[0][2]->schema);
		$this->assertFalse($service->calls[0][3]);

		$this->assertNull($store->find(schema: 'scheduled_export', uuid: ''));
		$service->found = new RuntimeException('Object not found');
		$this->assertNull($store->find(schema: 'scheduled_export', uuid: 'gone'));
	}//end testFindReturnsObjectOrNull()

	/**
	 * save passes the id as the uuid to update and strips the metadata from the data.
	 *
	 * @return void
	 */
	public function testSaveUpdatesByUuidWithoutMetadata(): void {
		$service = $this->objectService();
		$saved   = $this->store($service)->save(schema: 'scheduled_export_run', data: ['id' => 'u3', 'owner' => 'x', '@self' => [], 'status' => 'running']);

		$this->assertSame(['status' => 'running'], $service->calls[0][1]);
		$this->assertSame('u3', $service->calls[0][2]->uuid);
		$this->assertSame('scheduled_export_run', $service->calls[0][2]->target->schema);
		$this->assertFalse($service->calls[0][3]);
		$this->assertSame(['status' => 'running', 'id' => 'u3', 'owner' => 'alice'], $saved);

		$this->store($service)->save(schema: 'scheduled_export', data: ['name' => 'n']);
		$this->assertNull($service->calls[1][2]->uuid);
		$this->assertFalse($service->calls[1][3]);
	}//end testSaveUpdatesByUuidWithoutMetadata()

	/**
	 * delete soft-deletes the uuid through OpenRegister.
	 *
	 * @return void
	 */
	public function testDelete(): void {
		$service = $this->objectService();
		$this->assertTrue($this->store($service)->delete(uuid: 'u4'));
		$this->assertSame(['delete', 'u4', false], $service->calls[0]);
	}//end testDelete()

	/**
	 * Without OpenRegister reads are empty, a delete is false and a save is refused.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegister(): void {
		$store = $this->store(null);
		$this->assertSame([], $store->findAll(schema: 'scheduled_export'));
		$this->assertNull($store->find(schema: 'scheduled_export', uuid: 'u1'));
		$this->assertFalse($store->delete(uuid: 'u1'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('OpenRegister is not available');
		$store->save(schema: 'scheduled_export', data: []);
	}//end testWithoutOpenRegister()
}//end class
