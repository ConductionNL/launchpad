<?php

/**
 * LaunchPad LaunchpadToolProvider
 *
 * LaunchPad's MCP tool provider: a dispatcher over a constant tool catalogue,
 * routing each call to a domain handler that validates, authorises through
 * McpDashboardGate and calls the service layer (mcp-tool-surface D1, D4).
 * Registered under the string alias
 * `OCA\OpenRegister\Mcp\IMcpToolProvider::launchpad`, which OpenRegister
 * enumerates; without OpenRegister the alias resolves nothing and the app boots
 * as before.
 *
 * Every descriptor carries hermiq's grant annotations: `scope` (read, create,
 * update, delete), `reach` (self, user, instance, external) and `readOnlyHint`.
 *
 * Deliberately not tools (REQ-MCP-007): view and tile-click recording (agents
 * must not fabricate analytics), acknowledgement writes (evidence of a human
 * act), binary uploads, editing locks, the setup wizard, imports, cleanup, demo
 * showcases, bulk operations, export and import, and settings surfaces.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Mcp
 * @package  OCA\LaunchPad\Mcp
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Mcp;

use OCA\OpenRegister\Mcp\IMcpToolProvider;
use Psr\Container\ContainerInterface;

/**
 * The MCP tool provider for LaunchPad.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class LaunchpadToolProvider implements IMcpToolProvider {

	public const APP_ID = 'launchpad';

	private const NO_ARGUMENTS = ['type' => 'object', 'properties' => [], 'required' => []];

	private const REACTION_ARGUMENTS = [
		'type' => 'object',
		'properties' => [
			'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
			'emoji' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32],
		],
		'required' => ['dashboardUuid', 'emoji'],
	];

	private const DASHBOARD_UUID_ARGUMENT = [
		'type' => 'object',
		'properties' => ['dashboardUuid' => ['type' => 'string', 'format' => 'uuid']],
		'required' => ['dashboardUuid'],
	];

	/**
	 * The tool catalogue. A plain constant so tests assert against it directly.
	 *
	 * @var list<array<string, mixed>>
	 */
	public const TOOL_DESCRIPTORS = [
		[
			'id' => 'launchpad.listDashboards',
			'name' => 'List dashboards',
			'description' => 'List the dashboards you can see: your own, your groups\' and the ones shared with you.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.getDashboard',
			'name' => 'Get dashboard',
			'description' => 'Fetch one dashboard you can see, with its widget placements and your permission level.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.getActiveDashboard',
			'name' => 'Get active dashboard',
			'description' => 'Fetch the dashboard the app opens on for you, with its widget placements and your permission level.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listGroupDashboards',
			'name' => 'List group dashboards',
			'description' => 'List the shared dashboards of a group you are a member of.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['groupId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255]],
				'required' => ['groupId'],
			],
		],
		[
			'id' => 'launchpad.listWidgetTypes',
			'name' => 'List widget types',
			'description' => 'List the widget types your role may place on a dashboard.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listTiles',
			'name' => 'List tiles',
			'description' => 'List your own tiles: the links and apps you can place on a dashboard.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.getDashboardTree',
			'name' => 'Get dashboard tree',
			'description' => 'The parent and child structure of the dashboards you can see.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listShares',
			'name' => 'List shares',
			'description' => 'List the user and group shares of a dashboard you own.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['dashboardId' => ['type' => 'integer', 'minimum' => 1]],
				'required' => ['dashboardId'],
			],
		],
		[
			'id' => 'launchpad.listVersions',
			'name' => 'List versions',
			'description' => 'List the saved versions of a dashboard you own or administer.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.listTemplates',
			'name' => 'List templates',
			'description' => 'List the dashboard templates you can start a dashboard from, optionally of one category.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'category' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'sort' => ['type' => 'string', 'enum' => ['name', 'updatedAt']],
				],
				'required' => [],
			],
		],
		[
			'id' => 'launchpad.getDashboardMetadata',
			'name' => 'Get dashboard metadata',
			'description' => 'Fetch the metadata values of a dashboard you can see.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.getReactions',
			'name' => 'Get reactions',
			'description' => 'Fetch the reaction counts of a dashboard and the reactions you gave.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.listPendingAcknowledgements',
			'name' => 'List pending acknowledgements',
			'description' => 'List the dashboards that ask you to acknowledge them and that you have not acknowledged yet.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listKioskPlaylists',
			'name' => 'List kiosk playlists',
			'description' => 'List the kiosk playlists you may see: your own, or every active one for an administrator.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.createDashboardVersion',
			'name' => 'Create dashboard version',
			'description' => 'Save the current state of a dashboard you own as a version, with an optional note.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'note' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
				],
				'required' => ['dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.restoreDashboardVersion',
			'name' => 'Restore dashboard version',
			'description' => 'Restore a dashboard you own to one of its saved versions.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'versionNumber' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['dashboardUuid', 'versionNumber'],
			],
		],
		[
			'id' => 'launchpad.setDashboardMetadata',
			'name' => 'Set dashboard metadata',
			'description' => 'Set metadata values on a dashboard whose metadata you may edit.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'metadata' => ['type' => 'object'],
				],
				'required' => ['dashboardUuid', 'metadata'],
			],
		],
		[
			'id' => 'launchpad.addReaction',
			'name' => 'Add reaction',
			'description' => 'Add your emoji reaction to a dashboard you can see.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => self::REACTION_ARGUMENTS,
		],
		[
			'id' => 'launchpad.removeReaction',
			'name' => 'Remove reaction',
			'description' => 'Remove your emoji reaction from a dashboard.',
			'scope' => 'delete', 'reach' => 'self', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => self::REACTION_ARGUMENTS,
		],
	];

	/**
	 * The handler class and method of each tool. The handler is resolved from
	 * the container only when its tool runs.
	 *
	 * @var array<string, array{0: class-string, 1: string}>
	 */
	public const TOOL_HANDLERS = [
		'launchpad.listDashboards' => [McpDashboardTools::class, 'listDashboards'],
		'launchpad.getDashboard' => [McpDashboardTools::class, 'getDashboard'],
		'launchpad.getActiveDashboard' => [McpDashboardTools::class, 'getActiveDashboard'],
		'launchpad.listGroupDashboards' => [McpDashboardTools::class, 'listGroupDashboards'],
		'launchpad.listWidgetTypes' => [McpDashboardTools::class, 'listWidgetTypes'],
		'launchpad.listTiles' => [McpTileTools::class, 'listTiles'],
		'launchpad.getDashboardTree' => [McpCatalogueTools::class, 'getDashboardTree'],
		'launchpad.listShares' => [McpSharingTools::class, 'listShares'],
		'launchpad.listVersions' => [McpHistoryTools::class, 'listVersions'],
		'launchpad.listTemplates' => [McpCatalogueTools::class, 'listTemplates'],
		'launchpad.getDashboardMetadata' => [McpMetadataTools::class, 'getDashboardMetadata'],
		'launchpad.getReactions' => [McpEngagementTools::class, 'getReactions'],
		'launchpad.listPendingAcknowledgements' => [McpEngagementTools::class, 'listPendingAcknowledgements'],
		'launchpad.listKioskPlaylists' => [McpSharingTools::class, 'listKioskPlaylists'],
		'launchpad.createDashboardVersion' => [McpHistoryTools::class, 'createDashboardVersion'],
		'launchpad.restoreDashboardVersion' => [McpHistoryTools::class, 'restoreDashboardVersion'],
		'launchpad.setDashboardMetadata' => [McpMetadataTools::class, 'setDashboardMetadata'],
		'launchpad.addReaction' => [McpEngagementTools::class, 'addReaction'],
		'launchpad.removeReaction' => [McpEngagementTools::class, 'removeReaction'],
	];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves the handler of the tool that runs.
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * The app id OpenRegister lists the tools under.
	 *
	 * @return string The id.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getAppId(): string {
		return self::APP_ID;
	}//end getAppId()

	/**
	 * The tool catalogue.
	 *
	 * @return list<array<string, mixed>> The descriptors.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getTools(): array {
		return self::TOOL_DESCRIPTORS;
	}//end getTools()

	/**
	 * Run one tool.
	 *
	 * @param string               $toolId    The tool id.
	 * @param array<string, mixed> $arguments The arguments.
	 *
	 * @return array<string, mixed> The result, or a structured error.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function invokeTool(string $toolId, array $arguments): array {
		$handler = (self::TOOL_HANDLERS[$toolId] ?? null);
		if ($handler === null) {
			return (new McpArgumentValidator())->refuse(
				code: 'unknown_tool',
				message: "Unknown tool id '{$toolId}'. Available tools: "
					. implode(', ', array_column(self::TOOL_DESCRIPTORS, 'id')) . '.'
			);
		}

		[$class, $method] = $handler;
		$result = $this->container->get($class)->$method($arguments);
		if ((array_column(self::TOOL_DESCRIPTORS, 'scope', 'id')[$toolId] ?? 'read') === 'read') {
			return $result;
		}

		return $result + ['audit' => $this->auditRecord(toolId: $toolId, arguments: $arguments, result: $result)];
	}//end invokeTool()

	/**
	 * What hermiq's audit trail records for a write tool call: the tool, its
	 * target, the caller and whether it went through (REQ-MCP-006).
	 *
	 * @param string               $toolId    The tool id.
	 * @param array<string, mixed> $arguments The arguments.
	 * @param array<string, mixed> $result    The handler's answer.
	 *
	 * @return array{tool: string, target: mixed, caller: string, outcome: string}
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-14
	 */
	private function auditRecord(string $toolId, array $arguments, array $result): array {
		$outcome = 'done';
		if (($result['isError'] ?? false) === true) {
			$outcome = (string)($result['error'] ?? 'failed');
		}

		return [
			'tool' => $toolId,
			'target' => ($arguments['dashboardUuid'] ?? $arguments['dashboardId'] ?? null),
			'caller' => $this->container->get(McpDashboardGate::class)->currentUserId(),
			'outcome' => $outcome,
		];
	}//end auditRecord()
}//end class
