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
use OCA\LaunchPad\Mcp\McpDashboardTools;
use OCA\OpenRegister\Mcp\IMcpToolProvider;
use PHPUnit\Framework\TestCase;

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
	 * The provider answers for launchpad and implements OpenRegister's interface.
	 *
	 * @return void
	 */
	public function testTheProviderIsLaunchpads(): void {
		$provider = new LaunchpadToolProvider($this->createMock(McpDashboardTools::class));

		$this->assertInstanceOf(IMcpToolProvider::class, $provider);
		$this->assertSame('launchpad', $provider->getAppId());
		$this->assertSame(LaunchpadToolProvider::TOOL_DESCRIPTORS, $provider->getTools());
	}//end testTheProviderIsLaunchpads()

	/**
	 * Each tool id dispatches to its handler with the arguments.
	 *
	 * @return void
	 */
	public function testEachToolDispatchesToItsHandler(): void {
		$tools = $this->createMock(McpDashboardTools::class);
		$tools->expects($this->once())->method('getDashboard')->with(['dashboardUuid' => 'u'])->willReturn(['dashboard' => []]);
		$tools->expects($this->once())->method('listDashboards')->willReturn(['items' => []]);
		$tools->expects($this->once())->method('listGroupDashboards')->willReturn(['items' => ['g']]);
		$tools->expects($this->once())->method('listWidgetTypes')->willReturn(['items' => ['w']]);
		$provider = new LaunchpadToolProvider($tools);

		$this->assertSame(['dashboard' => []], $provider->invokeTool('launchpad.getDashboard', ['dashboardUuid' => 'u']));
		$this->assertSame(['items' => []], $provider->invokeTool('launchpad.listDashboards', []));
		$this->assertSame(['items' => ['g']], $provider->invokeTool('launchpad.listGroupDashboards', ['groupId' => 'x']));
		$this->assertSame(['items' => ['w']], $provider->invokeTool('launchpad.listWidgetTypes', []));
	}//end testEachToolDispatchesToItsHandler()

	/**
	 * An unknown tool is a structured error naming the available ids.
	 *
	 * @return void
	 */
	public function testAnUnknownToolIsAStructuredError(): void {
		$result = (new LaunchpadToolProvider($this->createMock(McpDashboardTools::class)))->invokeTool('launchpad.dropTables', []);

		$this->assertTrue($result['isError']);
		$this->assertSame('unknown_tool', $result['error']);
		$this->assertStringContainsString('launchpad.listDashboards', $result['message']);
	}//end testAnUnknownToolIsAStructuredError()
}//end class
