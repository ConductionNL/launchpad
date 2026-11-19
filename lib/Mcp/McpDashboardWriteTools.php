<?php

/**
 * LaunchPad McpDashboardWriteTools
 *
 * The write tools over the caller's own dashboards: create, update, delete,
 * fork, and set the active and the default one. Each applies the ADR-023 action
 * and the permission check of its `dashboardApi` route and calls the service
 * that route calls (mcp-tool-surface REQ-MCP-003).
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

use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use Throwable;

/**
 * The dashboard write tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
 */
class McpDashboardWriteTools {

	/**
	 * The dashboard fields updateDashboard may change.
	 *
	 * @var list<string>
	 */
	private const UPDATE_FIELDS = ['name', 'description', 'icon', 'parentUuid', 'slug', 'sortOrder'];

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate      The caller, the ADR-023 action and the permission checks.
	 * @param McpArgumentValidator $validator The argument checks.
	 * @param DashboardApiServices $services  The dashboard service the routes call.
	 * @param DashboardApiHistory  $history   The version service that snapshots an update, and the logger.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardApiServices $services,
		private readonly DashboardApiHistory $history,
	) {
	}//end __construct()

	/**
	 * Create a personal dashboard, seeded with the default widgets.
	 *
	 * @param array<string, mixed> $args The tool arguments: name, description, icon, parentUuid, slug, sortOrder.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function createDashboard(array $args): array {
		$refusal = ($this->fieldRefusal(args: $args, nameRequired: true) ?? $this->gate->requireAction(action: 'dashboard.create'));
		if ($refusal !== null) {
			return $refusal;
		}

		$userId = $this->gate->currentUserId();
		return $this->run(
			operation: function () use ($args, $userId): array {
				$this->services->dashboardService->assertPersonalDashboardsAllowed();
				$hasDashboards = ($this->services->dashboardService->getUserDashboards(userId: $userId) !== []);
				$refusal       = $this->gate->requireCreateDashboard(hasDashboards: $hasDashboards);
				if ($refusal !== null) {
					return $refusal;
				}

				$dashboard = $this->services->dashboardService->createDashboard(
					userId: $userId,
					name: (string)$args['name'],
					options: ['seedDefaults' => true] + array_intersect_key($args, array_flip(['description', 'icon', 'parentUuid', 'slug', 'sortOrder']))
				);
				return $this->withPlacements(dashboard: $dashboard);
			}
		);
	}//end createDashboard()

	/**
	 * Rename, describe, move or re-slug a dashboard whose details the caller may edit.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId and the fields to change.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function updateDashboard(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId')
			?? $this->fieldRefusal(args: $args, nameRequired: false)
			?? $this->gate->requireAction(action: 'dashboard.update'));
		if ($refusal === null) {
			$refusal = $this->gate->requireMetadataEdit(dashboardId: (int)$args['dashboardId']);
		}

		if ($refusal !== null) {
			return $refusal;
		}

		$data = array_intersect_key($args, array_flip(self::UPDATE_FIELDS));
		if (($data['parentUuid'] ?? null) === '') {
			$data['parentUuid'] = null;
		}

		return $this->run(
			operation: function () use ($args, $data): array {
				$dashboard = $this->services->dashboardService->updateDashboard(
					dashboardId: (int)$args['dashboardId'],
					userId: $this->gate->currentUserId(),
					data: $data
				);
				$this->snapshot(dashboard: $dashboard);
				return ['dashboard' => $dashboard->jsonSerialize()];
			}
		);
	}//end updateDashboard()

	/**
	 * Delete a dashboard; with children only when `cascade` is set.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, cascade.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function deleteDashboard(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId') ?? $this->gate->requireAction(action: 'dashboard.delete'));
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->services->dashboardService->deleteDashboard(
				dashboardId: (int)$args['dashboardId'],
				userId: $this->gate->currentUserId(),
				cascade: (($args['cascade'] ?? false) === true)
			);
		} catch (DashboardHasChildrenException $e) {
			return $this->validator->refuse(code: DashboardHasChildrenException::ERROR_CODE, message: $e->getMessage())
				+ ['childCount' => $e->getChildCount()];
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		return ['deleted' => true];
	}//end deleteDashboard()

	/**
	 * Make a dashboard the one the app opens on for the caller.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function switchActiveDashboard(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid') ?? $this->gate->requireAction(action: 'dashboard.set-active-dashboard'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->services->dashboardService->setActivePreference(userId: $this->gate->currentUserId(), uuid: (string)$args['dashboardUuid']);
				return ['active' => (string)$args['dashboardUuid']];
			}
		);
	}//end switchActiveDashboard()

	/**
	 * Make a dashboard the caller's default.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function setDefaultDashboard(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid') ?? $this->gate->requireAction(action: 'dashboard.set-default-dashboard'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->services->dashboardService->setDefaultPreference(userId: $this->gate->currentUserId(), uuid: (string)$args['dashboardUuid']);
				return ['default' => (string)$args['dashboardUuid']];
			}
		);
	}//end setDefaultDashboard()

	/**
	 * Copy a dashboard the caller can see, or a gallery template, as a
	 * personal dashboard.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, name.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function forkDashboard(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->validator->string(args: $args, name: 'name', min: 1, max: 255, required: false)
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => ['dashboard' => $this->services->dashboardService->forkAsPersonal(
				userId: $this->gate->currentUserId(),
				sourceUuid: (string)$args['dashboardUuid'],
				name: ($args['name'] ?? null)
			)->jsonSerialize()]
		);
	}//end forkDashboard()

	/**
	 * A refusal for bad dashboard fields.
	 *
	 * @param array<string, mixed> $args         The tool arguments.
	 * @param bool                 $nameRequired Whether this creates the dashboard.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) Creating and updating a dashboard differ only in whether the name is required.
	 */
	private function fieldRefusal(array $args, bool $nameRequired): ?array {
		$refusal = ($this->validator->string(args: $args, name: 'name', min: 1, max: 255, required: $nameRequired)
			?? $this->validator->string(args: $args, name: 'description', min: 0, max: 2000, required: false)
			?? $this->validator->string(args: $args, name: 'slug', min: 1, max: 255, required: false));
		if ($refusal === null && array_key_exists('sortOrder', $args) === true && is_int($args['sortOrder']) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument sortOrder must be an integer.');
		}

		return $refusal;
	}//end fieldRefusal()

	/**
	 * The created dashboard with its placements, as the create route answers.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return array<string, mixed>
	 */
	private function withPlacements(Dashboard $dashboard): array {
		$placements = [];
		foreach ($this->services->dashboardService->findPlacements(dashboardId: (int)$dashboard->getId()) as $placement) {
			$placements[] = $placement->jsonSerialize();
		}

		return ['dashboard' => $dashboard->jsonSerialize(), 'placements' => $placements];
	}//end withPlacements()

	/**
	 * Save an automatic version after an update, as the route does; a failed
	 * snapshot is logged and does not fail the update.
	 *
	 * @param Dashboard $dashboard The updated dashboard.
	 *
	 * @return void
	 */
	private function snapshot(Dashboard $dashboard): void {
		try {
			$this->history->versionService->captureSnapshot(dashboard: $dashboard, createdBy: $this->gate->currentUserId());
		} catch (Throwable $t) {
			$this->history->logger->warning(message: 'launchpad: automatic version snapshot failed', context: ['exception' => $t]);
		}
	}//end snapshot()

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
