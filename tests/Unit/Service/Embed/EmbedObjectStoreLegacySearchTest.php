<?php

/**
 * EmbedObjectStore against an OpenRegister release whose searchObjectsBySlug()
 * still takes the register and schema slugs as two named arguments.
 *
 * Runs in its own process without the OpenRegister stubs, so the
 * RegisterSchemaSlugs value does not exist and the store must fall back.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\EmbedObjectStore;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Unit\Service\Embed\Support\InMemoryObjectService;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class EmbedObjectStoreLegacySearchTest extends TestCase {
	/**
	 * Without RegisterSchemaSlugs the search names both slugs and still finds
	 * the launchpad register's objects.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function testAnOlderEngineIsSearchedWithNamedSlugs(): void {
		$this->assertFalse(condition: class_exists('OCA\\OpenRegister\\Contract\\RegisterSchemaSlugs'));

		$objects   = new InMemoryObjectService();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($objects);
		$store = new EmbedObjectStore($container);

		$store->save(schema: 'signing_key', object: ['status' => 'active']);

		$this->assertSame(expected: [['status' => 'active', 'id' => 'obj-1']], actual: $store->findBy(schema: 'signing_key'));
		$this->assertSame(expected: ['named slugs'], actual: $objects->searchShapes);
	}//end testAnOlderEngineIsSearchedWithNamedSlugs()
}//end class
