<?php

/**
 * Unit tests for the LaunchPad MCP tool catalogue and dispatcher.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Mcp\LaunchpadToolProvider;
use OCA\OpenRegister\Mcp\IMcpToolProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The catalogue's ids and annotations, and dispatch.
 *
 * @covers \OCA\LaunchPad\Mcp\LaunchpadToolProvider
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class LaunchpadToolProviderTest extends TestCase {

	/**
	 * Every tool id is `launchpad.{toolName}` and unique.
	 *
	 * @return void
	 */
	public function testToolIdsAreNamespacedAndUnique(): void {
		$ids = array_column(LaunchpadToolProvider::TOOL_DESCRIPTORS, 'id');

		$this->assertNotEmpty($ids);
		foreach ($ids as $id) {
			$this->assertMatchesRegularExpression('/^launchpad\.[a-z][A-Za-z]+$/', $id);
		}

		$this->assertSame($ids, array_values(array_unique($ids)));
	}//end testToolIdsAreNamespacedAndUnique()

	/**
	 * Every tool carries one scope and one reach from hermiq's vocabulary, and a read is read-only.
	 *
	 * @return void
	 */
	public function testEveryToolCarriesItsGrantAnnotations(): void {
		foreach (LaunchpadToolProvider::TOOL_DESCRIPTORS as $tool) {
			$this->assertContains($tool['scope'], ['read', 'create', 'update', 'delete'], $tool['id']);
			$this->assertContains($tool['reach'], ['self', 'user', 'instance', 'external'], $tool['id']);
			$this->assertSame($tool['scope'] === 'read', ($tool['annotations']['readOnlyHint'] ?? false), $tool['id']);
			$this->assertSame('object', $tool['inputSchema']['type'], $tool['id']);
		}
	}//end testEveryToolCarriesItsGrantAnnotations()

	/**
	 * A provider over a container that hands out the given handlers.
	 *
	 * @param array<class-string, object> $handlers The handlers by class.
	 *
	 * @return LaunchpadToolProvider
	 */
	private function provider(array $handlers = []): LaunchpadToolProvider {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(callback: static fn (string $id): object => $handlers[$id]);

		return new LaunchpadToolProvider(container: $container);
	}//end provider()

	/**
	 * The provider answers for launchpad and implements OpenRegister's interface.
	 *
	 * @return void
	 */
	public function testTheProviderIsLaunchpads(): void {
		$provider = $this->provider();

		$this->assertInstanceOf(expected: IMcpToolProvider::class, actual: $provider);
		$this->assertSame(expected: 'launchpad', actual: $provider->getAppId());
		$this->assertSame(expected: LaunchpadToolProvider::TOOL_DESCRIPTORS, actual: $provider->getTools());
	}//end testTheProviderIsLaunchpads()

	/**
	 * Every declared tool has a handler, every handler a declared tool, and
	 * each handler method exists.
	 *
	 * @return void
	 */
	public function testEveryToolHasExactlyOneHandler(): void {
		$ids = array_column(LaunchpadToolProvider::TOOL_DESCRIPTORS, 'id');

		$this->assertSame(expected: $ids, actual: array_keys(LaunchpadToolProvider::TOOL_HANDLERS));
		foreach (LaunchpadToolProvider::TOOL_HANDLERS as $id => [$class, $method]) {
			$this->assertTrue(condition: method_exists($class, $method), message: $id);
		}
	}//end testEveryToolHasExactlyOneHandler()

	/**
	 * The read catalogue is the fourteen tools of REQ-MCP-002.
	 *
	 * @return void
	 */
	public function testTheReadCatalogueIsTheFourteenReadTools(): void {
		$reads = array_column(
			array: array_filter(
				array: LaunchpadToolProvider::TOOL_DESCRIPTORS,
				callback: static fn (array $tool): bool => $tool['scope'] === 'read'
			),
			column_key: 'id'
		);
		sort($reads);

		$this->assertSame(
			expected: [
				'launchpad.getActiveDashboard',
				'launchpad.getDashboard',
				'launchpad.getDashboardMetadata',
				'launchpad.getDashboardTree',
				'launchpad.getReactions',
				'launchpad.listDashboards',
				'launchpad.listGroupDashboards',
				'launchpad.listKioskPlaylists',
				'launchpad.listPendingAcknowledgements',
				'launchpad.listShares',
				'launchpad.listTemplates',
				'launchpad.listTiles',
				'launchpad.listVersions',
				'launchpad.listWidgetTypes',
			],
			actual: $reads
		);
	}//end testTheReadCatalogueIsTheFourteenReadTools()

	/**
	 * Each tool id runs its handler method with the arguments, and answers what it answers.
	 *
	 * @return void
	 */
	public function testEachToolDispatchesToItsHandler(): void {
		$handlers = [];
		foreach (LaunchpadToolProvider::TOOL_HANDLERS as $id => [$class, $method]) {
			$handlers[$class] ??= $this->createMock(originalClassName: $class);
			$handlers[$class]->method($method)->willReturnCallback(
				callback: static fn (array $args): array => ['tool' => $id, 'args' => $args]
			);
		}

		$provider = $this->provider(handlers: $handlers);
		foreach (array_keys(LaunchpadToolProvider::TOOL_HANDLERS) as $id) {
			$this->assertSame(
				expected: ['tool' => $id, 'args' => ['x' => 1]],
				actual: $provider->invokeTool(toolId: $id, arguments: ['x' => 1])
			);
		}
	}//end testEachToolDispatchesToItsHandler()

	/**
	 * An unknown tool is a structured error naming the available ids.
	 *
	 * @return void
	 */
	public function testAnUnknownToolIsAStructuredError(): void {
		$result = $this->provider()->invokeTool(toolId: 'launchpad.dropTables', arguments: []);

		$this->assertTrue(condition: $result['isError']);
		$this->assertSame(expected: 'unknown_tool', actual: $result['error']);
		$this->assertStringContainsString(needle: 'launchpad.listDashboards', haystack: $result['message']);
	}//end testAnUnknownToolIsAStructuredError()
}//end class
