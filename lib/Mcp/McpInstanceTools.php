<?php

/**
 * LaunchPad McpInstanceTools
 *
 * The instance-wide write tools: group dashboards and admin templates. Every
 * one is for administrators only, checked by the gate before the service the
 * matching `dashboardApi` or `admin` route calls (mcp-tool-surface REQ-MCP-003,
 * reach instance).
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

use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use Throwable;

/**
 * The group dashboard and admin template tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
 */
class McpInstanceTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate      The caller and the admin check.
	 * @param McpArgumentValidator $validator The argument checks.
	 * @param DashboardApiServices $services  The dashboard service the group routes call.
	 * @param AdminTemplateTools   $templates The template and resync services the admin routes call.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardApiServices $services,
		private readonly AdminTemplateTools $templates,
	) {
	}//end __construct()

	/**
	 * Create a dashboard shared with a group.
	 *
	 * @param array<string, mixed> $args The tool arguments: groupId, name, description.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function createGroupDashboard(array $args): array {
		$refusal = ($this->groupRefusal(args: $args) ?? $this->validator->string(args: $args, name: 'name', min: 1, max: 255));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => ['dashboard' => $this->services->dashboardService->createGroupShared(
				actorUserId: $this->gate->currentUserId(),
				groupId: (string)$args['groupId'],
				fields: ['name' => (string)$args['name'], 'description' => ($args['description'] ?? null)]
			)->jsonSerialize()]
		);
	}//end createGroupDashboard()

	/**
	 * Change a group dashboard's name, description, columns or placements.
	 *
	 * @param array<string, mixed> $args The tool arguments: groupId, dashboardUuid and the fields to change.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function updateGroupDashboard(array $args): array {
		$refusal = $this->groupDashboardRefusal(args: $args);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => ['dashboard' => $this->services->dashboardService->updateGroupShared(
				actorUserId: $this->gate->currentUserId(),
				groupId: (string)$args['groupId'],
				uuid: (string)$args['dashboardUuid'],
				patch: array_intersect_key($args, array_flip(['name', 'description', 'gridColumns', 'placements']))
			)->jsonSerialize()]
		);
	}//end updateGroupDashboard()

	/**
	 * Delete a group dashboard.
	 *
	 * @param array<string, mixed> $args The tool arguments: groupId, dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function deleteGroupDashboard(array $args): array {
		$refusal = $this->groupDashboardRefusal(args: $args);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->services->dashboardService->deleteGroupShared(
					actorUserId: $this->gate->currentUserId(),
					groupId: (string)$args['groupId'],
					uuid: (string)$args['dashboardUuid']
				);
				return ['deleted' => true];
			}
		);
	}//end deleteGroupDashboard()

	/**
	 * Make a group dashboard the group's default.
	 *
	 * @param array<string, mixed> $args The tool arguments: groupId, dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function setGroupDefault(array $args): array {
		$refusal = $this->groupDashboardRefusal(args: $args);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->services->dashboardService->setGroupDefault(
					actorUserId: $this->gate->currentUserId(),
					groupId: (string)$args['groupId'],
					uuid: (string)$args['dashboardUuid']
				);
				return ['groupId' => (string)$args['groupId'], 'default' => (string)$args['dashboardUuid']];
			}
		);
	}//end setGroupDefault()

	/**
	 * Create an admin template.
	 *
	 * @param array<string, mixed> $args The tool arguments: name, description, targetGroups, permissionLevel, isDefault.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function createTemplate(array $args): array {
		$refusal = ($this->gate->requireAdmin()
			?? $this->validator->string(args: $args, name: 'name', min: 1, max: 255)
			?? $this->templateFieldRefusal(args: $args));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->templates->templateService->createTemplate(
				name: (string)$args['name'],
				settings: array_intersect_key($args, array_flip(['description', 'targetGroups', 'permissionLevel', 'isDefault']))
			)->jsonSerialize()
		);
	}//end createTemplate()

	/**
	 * Change an admin template.
	 *
	 * @param array<string, mixed> $args The tool arguments: templateId and the fields to change.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function updateTemplate(array $args): array {
		$refusal = ($this->gate->requireAdmin()
			?? $this->validator->id(args: $args, name: 'templateId')
			?? $this->validator->string(args: $args, name: 'name', min: 1, max: 255, required: false)
			?? $this->templateFieldRefusal(args: $args));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->templates->templateService->updateTemplate(
				id: (int)$args['templateId'],
				data: array_intersect_key($args, array_flip(['name', 'description', 'targetGroups', 'permissionLevel', 'isDefault', 'gridColumns']))
			)->jsonSerialize()
		);
	}//end updateTemplate()

	/**
	 * Delete an admin template.
	 *
	 * @param array<string, mixed> $args The tool arguments: templateId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function deleteTemplate(array $args): array {
		$refusal = ($this->gate->requireAdmin() ?? $this->validator->id(args: $args, name: 'templateId'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->templates->templateService->deleteTemplate(id: (int)$args['templateId']);
				return ['deleted' => true];
			}
		);
	}//end deleteTemplate()

	/**
	 * Bring the dashboards made from a template in line with it, by overwrite
	 * or merge; a dry run (the default) only reports what would change.
	 *
	 * @param array<string, mixed> $args The tool arguments: templateId, strategy, dryRun.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-12
	 */
	public function resyncTemplate(array $args): array {
		$refusal = ($this->gate->requireAdmin()
			?? $this->validator->id(args: $args, name: 'templateId')
			?? $this->validator->oneOf(args: $args, name: 'strategy', allowed: ['overwrite', 'merge']));
		if ($refusal === null && array_key_exists('dryRun', $args) === true && is_bool($args['dryRun']) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument dryRun must be true or false.');
		}

		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->templates->resyncService->resync(
				templateId: (int)$args['templateId'],
				strategy: (string)$args['strategy'],
				dryRun: (($args['dryRun'] ?? true) !== false),
				actingAdminId: $this->gate->currentUserId()
			)
		);
	}//end resyncTemplate()

	/**
	 * A refusal for a caller who is not an administrator or a missing group.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function groupRefusal(array $args): ?array {
		return ($this->gate->requireAdmin() ?? $this->validator->string(args: $args, name: 'groupId', min: 1, max: 255));
	}//end groupRefusal()

	/**
	 * A refusal for a group dashboard target: the admin check, the group and the UUID.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function groupDashboardRefusal(array $args): ?array {
		return ($this->groupRefusal(args: $args) ?? $this->validator->uuid(args: $args, name: 'dashboardUuid'));
	}//end groupDashboardRefusal()

	/**
	 * A refusal for bad template fields.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function templateFieldRefusal(array $args): ?array {
		$refusal = $this->validator->oneOf(
			args: $args,
			name: 'permissionLevel',
			allowed: [Dashboard::PERMISSION_VIEW_ONLY, Dashboard::PERMISSION_ADD_ONLY, Dashboard::PERMISSION_FULL],
			required: false
		);
		if ($refusal === null && array_key_exists('targetGroups', $args) === true && is_array($args['targetGroups']) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument targetGroups must be a list of group ids.');
		}

		return $refusal;
	}//end templateFieldRefusal()

	/**
	 * Run a service call, mapping its failures to the tools' envelopes.
	 *
	 * @param \Closure(): array<string, mixed> $operation The service call.
	 *
	 * @return array<string, mixed>
	 */
	private function run(\Closure $operation): array {
		try {
			return $operation();
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end run()
}//end class
