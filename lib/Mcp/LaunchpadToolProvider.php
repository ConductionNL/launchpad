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

/**
 * The MCP tool provider for LaunchPad.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class LaunchpadToolProvider implements IMcpToolProvider {

	public const APP_ID = 'launchpad';

	private const NO_ARGUMENTS = ['type' => 'object', 'properties' => [], 'required' => []];

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
			'scope' => 'read',
			'reach' => 'self',
			'annotations' => ['readOnlyHint' => true],
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.getDashboard',
			'name' => 'Get dashboard',
			'description' => 'Fetch one dashboard you can see, with its widget placements and your permission level.',
			'scope' => 'read',
			'reach' => 'self',
			'annotations' => ['readOnlyHint' => true],
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['dashboardUuid' => ['type' => 'string', 'format' => 'uuid']],
				'required' => ['dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.listGroupDashboards',
			'name' => 'List group dashboards',
			'description' => 'List the shared dashboards of a group you are a member of.',
			'scope' => 'read',
			'reach' => 'self',
			'annotations' => ['readOnlyHint' => true],
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
			'scope' => 'read',
			'reach' => 'self',
			'annotations' => ['readOnlyHint' => true],
			'inputSchema' => self::NO_ARGUMENTS,
		],
	];

	/**
	 * Constructor.
	 *
	 * @param McpDashboardTools $dashboardTools The dashboard and widget-type handlers.
	 */
	public function __construct(
		private readonly McpDashboardTools $dashboardTools,
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
	 */
	public function invokeTool(string $toolId, array $arguments): array {
		return match ($toolId) {
			'launchpad.listDashboards' => $this->dashboardTools->listDashboards(args: $arguments),
			'launchpad.getDashboard' => $this->dashboardTools->getDashboard(args: $arguments),
			'launchpad.listGroupDashboards' => $this->dashboardTools->listGroupDashboards(args: $arguments),
			'launchpad.listWidgetTypes' => $this->dashboardTools->listWidgetTypes(args: $arguments),
			default => McpArgumentValidator::error(
				code: 'unknown_tool',
				message: "Unknown tool id '{$toolId}'. Available tools: "
					. implode(', ', array_column(self::TOOL_DESCRIPTORS, 'id')) . '.'
			),
		};
	}//end invokeTool()
}//end class
