<?php

/**
 * EmbedObjectStore unit tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\EmbedObjectStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Unit\Service\Embed\Support\InMemoryObjectService;

#[CoversClass(EmbedObjectStore::class)]
class EmbedObjectStoreTest extends TestCase {

	private InMemoryObjectService $objects;

	/**
	 * A store over the in-memory object service.
	 *
	 * @return EmbedObjectStore
	 */
	private function store(): EmbedObjectStore {
		$this->objects = new InMemoryObjectService();
		$container     = $this->createMock(ContainerInterface::class);
		$container->method('get')->with('OCA\\OpenRegister\\Service\\ObjectService')->willReturn($this->objects);

		return new EmbedObjectStore($container);
	}//end store()

	/**
	 * A save without id creates, a save with the id updates; both as the system,
	 * in the launchpad register and the named schema.
	 *
	 * @return void
	 */
	public function testSaveCreatesThenUpdatesInTheLaunchpadRegister(): void {
		$store   = $this->store();
		$created = $store->save(schema: 'embed_token', object: ['name' => 'Zeist']);
		$updated = $store->save(schema: 'embed_token', object: ['id' => $created['id'], 'name' => 'Zeist WOO']);

		$this->assertSame(['name' => 'Zeist', 'id' => 'obj-1'], $created);
		$this->assertSame(['name' => 'Zeist WOO', 'id' => 'obj-1'], $updated);
		$this->assertSame(['launchpad/embed_token' => ['obj-1' => ['name' => 'Zeist WOO']]], $this->objects->objects);
		$this->assertSame(['save launchpad/embed_token system', 'save launchpad/embed_token system'], $this->objects->calls);
	}//end testSaveCreatesThenUpdatesInTheLaunchpadRegister()

	/**
	 * Find returns the object with its id; an unknown or empty id is null.
	 *
	 * @return void
	 */
	public function testFindReturnsTheObjectOrNull(): void {
		$store = $this->store();
		$store->save(schema: 'tenant_theme', object: ['name' => 'Zeist']);

		$this->assertSame(['name' => 'Zeist', 'id' => 'obj-1'], $store->find(schema: 'tenant_theme', id: 'obj-1'));
		$this->assertNull($store->find(schema: 'tenant_theme', id: 'obj-9'));
		$this->assertNull($store->find(schema: 'tenant_theme', id: ''));
		$this->assertNull($store->find(schema: 'embed_token', id: 'obj-1'));
	}//end testFindReturnsTheObjectOrNull()

	/**
	 * findBy unwraps the result page and keeps only the matching objects.
	 *
	 * @return void
	 */
	public function testFindByFiltersAndUnwrapsResults(): void {
		$store = $this->store();
		$store->save(schema: 'signing_key', object: ['status' => 'active']);
		$store->save(schema: 'signing_key', object: ['status' => 'retiring']);

		$this->assertSame([['status' => 'retiring', 'id' => 'obj-2']], $store->findBy(schema: 'signing_key', filters: ['status' => 'retiring']));
		$this->assertCount(2, $store->findBy(schema: 'signing_key'));
	}//end testFindByFiltersAndUnwrapsResults()

	/**
	 * A failing save is reported with the schema; without OpenRegister every
	 * call says so.
	 *
	 * @return void
	 */
	public function testFailuresAreNamed(): void {
		$store = $this->store();
		$this->objects->failSaves = true;
		try {
			$store->save(schema: 'embed_usage_event', object: []);
			$this->fail('expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame('The embed_usage_event object could not be saved.', $e->getMessage());
		}

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('no such service'));
		$this->expectExceptionMessage('Embeds need OpenRegister, which is not available.');
		(new EmbedObjectStore($container))->findBy(schema: 'embed_token');
	}//end testFailuresAreNamed()
}//end class
