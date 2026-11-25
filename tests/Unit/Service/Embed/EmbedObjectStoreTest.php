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

require_once __DIR__ . '/../../../Stubs/OpenRegisterStubs.php';

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
	 * Delete removes the object as the system and says whether there was one;
	 * an unknown id deletes nothing.
	 *
	 * @return void
	 */
	public function testDeleteRemovesTheObjectAsTheSystem(): void {
		$store = $this->store();
		$store->save(schema: 'tenant_theme', object: ['name' => 'Zeist']);

		$this->assertFalse($store->delete(schema: 'tenant_theme', id: 'obj-9'));
		$this->assertTrue($store->delete(schema: 'tenant_theme', id: 'obj-1'));
		$this->assertSame([], $this->objects->objects['launchpad/tenant_theme']);
		$this->assertSame(
			['save launchpad/tenant_theme system', 'find launchpad/tenant_theme', 'find launchpad/tenant_theme', 'delete launchpad/tenant_theme system'],
			$this->objects->calls
		);
		$this->assertNull($store->find(schema: 'tenant_theme', id: 'obj-1'));
	}//end testDeleteRemovesTheObjectAsTheSystem()

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
	/**
	 * Against an OpenRegister whose slug search takes one RegisterSchemaSlugs
	 * target, findBy passes that target and still unwraps the page.
	 *
	 * @return void
	 */
	public function testFindBySpeaksTheTargetShapedSlugSearch(): void {
		$service = new class {
			/** @var list<array{string, string, array<string, mixed>}> */
			public array $calls = [];

			/**
			 * Pick the register.
			 *
			 * @param string $register The register.
			 *
			 * @return static
			 */
			public function setRegister(string $register): static {
				return $this;
			}

			/**
			 * Pick the schema.
			 *
			 * @param string $schema The schema.
			 *
			 * @return static
			 */
			public function setSchema(string $schema): static {
				return $this;
			}

			/**
			 * The target-shaped slug search.
			 *
			 * @param \OCA\OpenRegister\Contract\RegisterSchemaSlugs $target        The register and schema.
			 * @param array<string, mixed>                       $filters       Field => value.
			 * @param bool                                       $_rbac         Apply RBAC.
			 * @param bool                                       $_multitenancy Apply multitenancy.
			 *
			 * @return array<string, mixed>
			 */
			public function searchObjectsBySlug(
				\OCA\OpenRegister\Contract\RegisterSchemaSlugs $target,
				array $filters=[],
				bool $_rbac=true,
				bool $_multitenancy=true
			): array {
				$this->calls[] = [$target->register, $target->schema, $filters];
				return ['results' => [['@self' => ['id' => 'tok-1'], 'name' => 'Website']]];
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($service);

		$rows = (new EmbedObjectStore($container))->findBy(schema: 'embed_token', filters: ['name' => 'Website']);

		$this->assertSame(expected: [['launchpad', 'embed_token', ['name' => 'Website']]], actual: $service->calls);
		$this->assertCount(expectedCount: 1, haystack: $rows);
	}//end testFindBySpeaksTheTargetShapedSlugSearch()
}//end class
