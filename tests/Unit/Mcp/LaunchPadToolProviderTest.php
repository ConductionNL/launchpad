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

use OCA\LaunchPad\Exception\McpToolRefusedException;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\LaunchPadToolProvider;
use OCA\OpenRegister\Mcp\IMcpToolProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use OCA\LaunchPad\Mcp\McpEngagementTools;
use OCP\IUser;
use OCP\IUserSession;

/**
 * The catalogue's ids and annotations, and dispatch.
 *
 * @covers \OCA\LaunchPad\Mcp\LaunchPadToolProvider
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class LaunchPadToolProviderTest extends TestCase {

	/**
	 * OpenRegister finds the provider as `OCA\<namespace>\Mcp\<namespace>ToolProvider`,
	 * with the namespace from info.xml; the class must live at exactly that name.
	 *
	 * @return void
	 */
	public function testTheProviderLivesWhereOpenRegisterLooksForIt(): void {
		$info = (string)file_get_contents(filename: __DIR__ . '/../../../appinfo/info.xml');
		$this->assertSame(expected: 1, actual: preg_match(pattern: '#<namespace>([^<]+)</namespace>#', subject: $info, matches: $match));
		$namespace = $match[1];

		$expected   = 'OCA\\' . $namespace . '\\Mcp\\' . $namespace . 'ToolProvider';
		$reflection = new \ReflectionClass(objectOrClass: $expected);
		$this->assertSame(expected: $expected, actual: $reflection->getName());
		$this->assertTrue(condition: $reflection->implementsInterface(IMcpToolProvider::class));
	}//end testTheProviderLivesWhereOpenRegisterLooksForIt()

	/**
	 * Every tool id is `launchpad.{toolName}` and unique.
	 *
	 * @return void
	 */
	public function testToolIdsAreNamespacedAndUnique(): void {
		$ids = array_column(LaunchPadToolProvider::TOOL_DESCRIPTORS, 'id');

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
		foreach (LaunchPadToolProvider::TOOL_DESCRIPTORS as $tool) {
			$this->assertContains($tool['scope'], ['read', 'create', 'update', 'delete'], $tool['id']);
			$this->assertContains($tool['reach'], ['self', 'user', 'instance', 'external'], $tool['id']);
			$this->assertSame(expected: $tool['scope'] === 'read', actual: $tool['readOnlyHint'], message: $tool['id']);
			$this->assertSame(
				expected: ($tool['scope'] === 'delete' || $tool['reach'] === 'external'),
				actual: ($tool['destructiveHint'] ?? false),
				message: $tool['id']
			);
			$this->assertSame('object', $tool['inputSchema']['type'], $tool['id']);
		}
	}//end testEveryToolCarriesItsGrantAnnotations()

	/**
	 * A provider over a container that hands out the given handlers.
	 *
	 * @param array<class-string, object> $handlers The handlers by class.
	 *
	 * @return LaunchPadToolProvider
	 */
	private function provider(array $handlers = []): LaunchPadToolProvider {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$handlers += [IUserSession::class => $session];
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(callback: static fn (string $id): object => $handlers[$id]);

		return new LaunchPadToolProvider(container: $container);
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
		$this->assertSame(expected: LaunchPadToolProvider::TOOL_DESCRIPTORS, actual: $provider->getTools());
	}//end testTheProviderIsLaunchpads()

	/**
	 * Every declared tool has a handler, every handler a declared tool, and
	 * each handler method exists.
	 *
	 * @return void
	 */
	public function testEveryToolHasExactlyOneHandler(): void {
		$ids = array_column(LaunchPadToolProvider::TOOL_DESCRIPTORS, 'id');

		$this->assertSame(expected: $ids, actual: array_keys(LaunchPadToolProvider::TOOL_HANDLERS));
		foreach (LaunchPadToolProvider::TOOL_HANDLERS as $id => [$class, $method]) {
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
				array: LaunchPadToolProvider::TOOL_DESCRIPTORS,
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
		foreach (LaunchPadToolProvider::TOOL_HANDLERS as $id => [$class, $method]) {
			$handlers[$class] ??= $this->createMock(originalClassName: $class);
			$handlers[$class]->method($method)->willReturnCallback(
				callback: static fn (array $args): array => ['tool' => $id, 'args' => $args]
			);
		}

		$provider = $this->provider(handlers: $handlers);
		$scopes   = array_column(LaunchPadToolProvider::TOOL_DESCRIPTORS, 'scope', 'id');
		foreach (array_keys(LaunchPadToolProvider::TOOL_HANDLERS) as $id) {
			$result = $provider->invokeTool(toolId: $id, arguments: ['x' => 1]);
			unset($result['audit']);
			$this->assertSame(expected: ['tool' => $id, 'args' => ['x' => 1]], actual: $result);
			$this->assertSame(expected: $scopes[$id] !== 'read', actual: array_key_exists('audit', $provider->invokeTool(toolId: $id, arguments: [])));
		}
	}//end testEachToolDispatchesToItsHandler()

	/**
	 * An unknown tool is refused with a typed exception naming the available ids.
	 *
	 * @return void
	 */
	public function testAnUnknownToolIsRefused(): void {
		$refusal = $this->refusalOf(provider: $this->provider(), toolId: 'launchpad.dropTables', arguments: []);

		$this->assertSame(expected: 'unknown_tool', actual: $refusal->getErrorCode());
		$this->assertTrue(condition: $refusal->getResult()['isError']);
		$this->assertStringStartsWith(prefix: 'unknown_tool: ', string: $refusal->getMessage());
		$this->assertStringContainsString(needle: 'launchpad.listDashboards', haystack: $refusal->getMessage());
	}//end testAnUnknownToolIsRefused()

	/**
	 * Every kind of refusal a tool answers, read or write, leaves the provider
	 * as a typed exception, so OpenRegister answers it with `isError: true`.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function refusals(): array {
		return [
			'no permission, read tool' => ['launchpad.getDashboard', 'forbidden'],
			'not found, read tool' => ['launchpad.getDashboard', 'not_found'],
			'invalid input, read tool' => ['launchpad.getDashboard', 'invalid_arguments'],
			'no permission, write tool' => ['launchpad.addWidget', 'forbidden'],
			'not found, write tool' => ['launchpad.addWidget', 'not_found'],
			'invalid input, write tool' => ['launchpad.addWidget', 'invalid_arguments'],
		];
	}//end refusals()

	/**
	 * A tool's refusal is thrown with its code and message.
	 *
	 * @param string $toolId The tool.
	 * @param string $code   The refusal code the tool answers.
	 *
	 * @return void
	 */
	#[DataProvider('refusals')]
	public function testARefusalIsThrown(string $toolId, string $code): void {
		[$class, $method] = LaunchPadToolProvider::TOOL_HANDLERS[$toolId];
		$handler = $this->createMock(originalClassName: $class);
		$handler->method($method)->willReturn(McpArgumentValidator::error(code: $code, message: 'Refused.'));

		$refusal = $this->refusalOf(provider: $this->provider(handlers: [$class => $handler]), toolId: $toolId, arguments: ['dashboardId' => 3]);

		$this->assertSame(expected: $code, actual: $refusal->getErrorCode());
		$this->assertSame(expected: $code . ': Refused.', actual: $refusal->getMessage());
	}//end testARefusalIsThrown()

	/**
	 * Invoke a tool that must refuse, and return the refusal.
	 *
	 * @param LaunchPadToolProvider $provider  The provider.
	 * @param string                $toolId    The tool.
	 * @param array<string, mixed>  $arguments The arguments.
	 *
	 * @return McpToolRefusedException
	 */
	private function refusalOf(LaunchPadToolProvider $provider, string $toolId, array $arguments): McpToolRefusedException {
		try {
			$provider->invokeTool(toolId: $toolId, arguments: $arguments);
		} catch (McpToolRefusedException $refusal) {
			return $refusal;
		}

		$this->fail(message: $toolId . ' answered instead of refusing');
	}//end refusalOf()

	/**
	 * A write tool's answer carries the tool, target, caller and outcome for
	 * the audit trail, for a refusal as for a success.
	 *
	 * @return void
	 */
	public function testAWriteCarriesItsAuditRecord(): void {
		$engagement = $this->createMock(originalClassName: McpEngagementTools::class);
		$engagement->method('addReaction')->willReturn(['counts' => []]);
		$engagement->method('removeReaction')->willReturn(['isError' => true, 'error' => 'forbidden', 'message' => 'No.']);
		$provider = $this->provider(handlers: [McpEngagementTools::class => $engagement]);
		$args     = ['dashboardUuid' => 'u-1', 'emoji' => 'x'];

		$this->assertSame(
			expected: ['tool' => 'launchpad.addReaction', 'target' => 'u-1', 'caller' => 'alice', 'outcome' => 'done'],
			actual: $provider->invokeTool(toolId: 'launchpad.addReaction', arguments: $args)['audit']
		);
		$this->assertSame(
			expected: ['tool' => 'launchpad.removeReaction', 'target' => 'u-1', 'caller' => 'alice', 'outcome' => 'forbidden'],
			actual: $this->refusalOf(provider: $provider, toolId: 'launchpad.removeReaction', arguments: $args)->getResult()['audit']
		);
	}//end testAWriteCarriesItsAuditRecord()

	/**
	 * The write catalogue is exactly the 35 tools of REQ-MCP-003, each at its
	 * reach, and no tool exists for the deliberate exclusions of REQ-MCP-007.
	 *
	 * @return void
	 */
	public function testTheWriteCatalogueIsClosed(): void {
		$expected = [
			'self' => [
				'launchpad.createDashboard',
				'launchpad.updateDashboard',
				'launchpad.deleteDashboard',
				'launchpad.switchActiveDashboard',
				'launchpad.setDefaultDashboard',
				'launchpad.forkDashboard',
				'launchpad.addWidget',
				'launchpad.updateWidgetPlacement',
				'launchpad.removeWidget',
				'launchpad.addTileToDashboard',
				'launchpad.createTile',
				'launchpad.updateTile',
				'launchpad.deleteTile',
				'launchpad.createDashboardVersion',
				'launchpad.restoreDashboardVersion',
				'launchpad.setDashboardMetadata',
				'launchpad.addReaction',
				'launchpad.removeReaction',
			],
			'user' => [
				'launchpad.shareDashboard',
				'launchpad.replaceShares',
				'launchpad.unshareDashboard',
				'launchpad.setPublicationState',
			],
			'external' => [
				'launchpad.createPublicShareLink',
				'launchpad.revokePublicShareLink',
				'launchpad.createKioskPlaylist',
				'launchpad.updateKioskPlaylist',
				'launchpad.deleteKioskPlaylist',
			],
			'instance' => [
				'launchpad.createGroupDashboard',
				'launchpad.updateGroupDashboard',
				'launchpad.deleteGroupDashboard',
				'launchpad.setGroupDefault',
				'launchpad.createTemplate',
				'launchpad.updateTemplate',
				'launchpad.deleteTemplate',
				'launchpad.resyncTemplate',
			],
		];
		$actual = [];
		foreach (LaunchPadToolProvider::TOOL_DESCRIPTORS as $tool) {
			if ($tool['scope'] !== 'read') {
				$actual[$tool['reach']][] = $tool['id'];
			}
		}

		foreach ($expected as $reach => $ids) {
			sort($ids);
			sort($actual[$reach]);
			$this->assertSame(expected: $ids, actual: $actual[$reach], message: $reach);
		}

		$this->assertSame(expected: array_keys($expected), actual: array_keys($actual));
		$ids = implode(' ', array_column(LaunchPadToolProvider::TOOL_DESCRIPTORS, 'id'));
		$excludedSurfaces = ['viewEvent', 'Click', 'acknowledge(?!ments)', 'upload', 'Lock', 'Wizard', 'Confluence'];
		$excludedSurfaces = [...$excludedSurfaces, 'cleanup', 'Showcase', 'Bulk', 'export', 'import', 'Settings'];
		foreach ($excludedSurfaces as $excluded) {
			$this->assertDoesNotMatchRegularExpression(pattern: '/' . $excluded . '/i', string: $ids, message: $excluded);
		}
	}//end testTheWriteCatalogueIsClosed()
}//end class
