<?php

/**
 * LaunchPad McpWidgetTools
 *
 * The write tools over widget placements and tiles: add, change and remove a
 * widget, and create, change and delete a tile, which is a widget of type
 * `tile`. Each applies the ADR-023 action and the per-dashboard or
 * per-placement check of its `widgetApi` route and calls the service that
 * route calls (mcp-tool-surface REQ-MCP-003).
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

use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use Throwable;

/**
 * The widget and tile write tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
 */
class McpWidgetTools {

	/**
	 * The placement fields a tool may change. Calculated fields and the
	 * acknowledgement settings stay with the app, whose routes run the checks
	 * and the OpenRegister materialisation those need.
	 *
	 * @var list<string>
	 */
	private const PLACEMENT_FIELDS = [
		'version', 'gridX', 'gridY', 'gridWidth', 'gridHeight', 'isVisible',
		'showTitle', 'customTitle', 'customIcon', 'styleConfig', 'content',
	];

	/**
	 * The content fields of a tile.
	 *
	 * @var list<string>
	 */
	private const TILE_FIELDS = ['title', 'icon', 'iconType', 'backgroundColor', 'textColor', 'linkType', 'linkValue'];

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate             $gate        The caller, the ADR-023 action and the per-object checks.
	 * @param McpArgumentValidator         $validator   The argument checks.
	 * @param WidgetApiSources             $sources     The widget, placement and calculated-field services the routes call.
	 * @param RoleFeaturePermissionService $roleWidgets Whether the caller's role may place a widget type.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly WidgetApiSources $sources,
		private readonly RoleFeaturePermissionService $roleWidgets,
	) {
	}//end __construct()

	/**
	 * Place a widget on a dashboard the caller may add widgets to.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, widgetId, grid position, content.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function addWidget(array $args): array {
		$refusal = ($this->validator->string(args: $args, name: 'widgetId', min: 1, max: 255) ?? $this->addRefusal(args: $args));
		if ($refusal !== null) {
			return $refusal;
		}

		if ($this->roleWidgets->isWidgetAllowed(userId: $this->gate->currentUserId(), widgetId: (string)$args['widgetId']) === false) {
			return $this->validator->refuse(code: 'forbidden', message: 'Your role may not place this widget type.');
		}

		return $this->place(dashboardId: (int)$args['dashboardId'], widgetId: (string)$args['widgetId'], args: $args);
	}//end addWidget()

	/**
	 * Create a tile: a widget of type `tile` with a title and a link.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, title, linkType, linkValue, icon, colours, grid position.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function createTile(array $args): array {
		$refusal = ($this->tileRefusal(args: $args, required: true) ?? $this->addRefusal(args: $args));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->place(
			dashboardId: (int)$args['dashboardId'],
			widgetId: 'tile',
			args: ['content' => $this->tileContent(args: $args, current: [])] + $args
		);
	}//end createTile()

	/**
	 * Add a tile through the route that places one from its fields.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, title, linkType, linkValue, icon, colours, grid position.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function addTileToDashboard(array $args): array {
		$refusal = ($this->tileRefusal(args: $args, required: true)
			?? $this->validator->id(args: $args, name: 'dashboardId')
			?? $this->validator->grid(args: $args)
			?? $this->gate->requireAction(action: 'widget.add-tile')
			?? $this->gate->requireWidgetPermission(operation: 'add', id: (int)$args['dashboardId']));
		if ($refusal !== null) {
			return $refusal;
		}

		$content  = $this->tileContent(args: $args, current: []);
		$tileData = [
			'title' => $content['title'], 'icon' => ($content['icon'] ?? 'icon-link'), 'iconType' => ($content['iconType'] ?? 'class'),
			'bgColor' => ($content['backgroundColor'] ?? '#0082c9'), 'txtColor' => ($content['textColor'] ?? '#ffffff'),
			'linkType' => $content['linkType'], 'linkVal' => $content['linkValue'], 'linkTarget' => null,
			'gridX' => (int)($args['gridX'] ?? 0), 'gridY' => (int)($args['gridY'] ?? 0),
		];

		return $this->run(
			operation: fn (): array => $this->sources->widgetService->addTileFromArray(
				dashboardId: (int)$args['dashboardId'],
				tileData: $tileData
			)->jsonSerialize()
		);
	}//end addTileToDashboard()

	/**
	 * Change a widget placement the caller may change: position, size,
	 * visibility, title, style or content.
	 *
	 * @param array<string, mixed> $args The tool arguments: placementId and the fields to change.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function updateWidgetPlacement(array $args): array {
		$refusal = $this->changeRefusal(args: $args);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->change(placementId: (int)$args['placementId'], data: array_intersect_key($args, array_flip(self::PLACEMENT_FIELDS)));
	}//end updateWidgetPlacement()

	/**
	 * Change a tile's title, link, icon or colours, keeping its other content.
	 *
	 * @param array<string, mixed> $args The tool arguments: placementId and the tile fields to change.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function updateTile(array $args): array {
		$refusal = ($this->tileRefusal(args: $args, required: false) ?? $this->changeRefusal(args: $args));
		if ($refusal !== null) {
			return $refusal;
		}

		$placement = $this->tilePlacement(placementId: (int)$args['placementId']);
		if (is_array($placement) === true) {
			return $placement;
		}

		return $this->change(
			placementId: (int)$args['placementId'],
			data: ['content' => $this->tileContent(args: $args, current: $placement->getContentArray())]
				+ array_intersect_key($args, array_flip(['version', 'gridX', 'gridY', 'gridWidth', 'gridHeight']))
		);
	}//end updateTile()

	/**
	 * Remove a widget placement the caller may remove, with the OpenRegister
	 * fields its calculated fields wrote.
	 *
	 * @param array<string, mixed> $args The tool arguments: placementId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function removeWidget(array $args): array {
		$refusal = $this->removeRefusal(args: $args);
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->remove(placementId: (int)$args['placementId']);
	}//end removeWidget()

	/**
	 * Delete a tile placement.
	 *
	 * @param array<string, mixed> $args The tool arguments: placementId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function deleteTile(array $args): array {
		$refusal = ($this->removeRefusal(args: $args) ?? $this->notATile(placementId: (int)$args['placementId']));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->remove(placementId: (int)$args['placementId']);
	}//end deleteTile()

	/**
	 * A refusal for adding to the dashboard: its id, the grid, the
	 * `widget.add-widget` action and the add check.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function addRefusal(array $args): ?array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId') ?? $this->validator->grid(args: $args));
		if ($refusal === null && array_key_exists('content', $args) === true && is_array($args['content']) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument content must be an object.');
		}

		return ($refusal
			?? $this->gate->requireAction(action: 'widget.add-widget')
			?? $this->gate->requireWidgetPermission(operation: 'add', id: (int)$args['dashboardId'])
			?? $this->depthRefusal(content: ($args['content'] ?? null)));
	}//end addRefusal()

	/**
	 * A refusal for changing the placement: its id, the grid, the fields the
	 * tools leave to the app, the `widget.update-placement` action and the
	 * style check.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function changeRefusal(array $args): ?array {
		$refusal = ($this->validator->id(args: $args, name: 'placementId') ?? $this->validator->grid(args: $args));
		$leftToApp = array_diff(array_keys($args), self::PLACEMENT_FIELDS, self::TILE_FIELDS, ['placementId']);
		if ($refusal === null && $leftToApp !== []) {
			$refusal = $this->validator->refuse(
				code: 'invalid_arguments',
				message: 'These fields cannot be changed through this tool: ' . implode(', ', $leftToApp) . '.'
			);
		}

		return ($refusal
			?? $this->gate->requireAction(action: 'widget.update-placement')
			?? $this->gate->requireWidgetPermission(operation: 'style', id: (int)$args['placementId'])
			?? $this->depthRefusal(content: ($args['content'] ?? null)));
	}//end changeRefusal()

	/**
	 * A refusal for removing the placement: its id, the
	 * `widget.remove-placement` action and the remove check.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function removeRefusal(array $args): ?array {
		return ($this->validator->id(args: $args, name: 'placementId')
			?? $this->gate->requireAction(action: 'widget.remove-placement')
			?? $this->gate->requireWidgetPermission(operation: 'remove', id: (int)$args['placementId']));
	}//end removeRefusal()

	/**
	 * A refusal for bad tile fields; with `required`, the title, link type
	 * and link value must be there.
	 *
	 * @param array<string, mixed> $args     The tool arguments.
	 * @param bool                 $required Whether this creates the tile.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) Creating and changing a tile differ only in which fields are required.
	 */
	private function tileRefusal(array $args, bool $required): ?array {
		return ($this->validator->string(args: $args, name: 'title', min: 1, max: 255, required: $required)
			?? $this->validator->oneOf(args: $args, name: 'linkType', allowed: ['app', 'url'], required: $required)
			?? $this->validator->string(args: $args, name: 'linkValue', min: 1, max: 2048, required: $required));
	}//end tileRefusal()

	/**
	 * Refuse content nested deeper than containers may go.
	 *
	 * @param mixed $content The content argument.
	 *
	 * @return array<string, mixed>|null
	 */
	private function depthRefusal(mixed $content): ?array {
		if (is_array($content) === false) {
			return null;
		}

		try {
			$this->sources->placementService->validateContainerDepth(content: $content);
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		return null;
	}//end depthRefusal()

	/**
	 * The tile content, from the arguments over the current content.
	 *
	 * @param array<string, mixed> $args    The tool arguments.
	 * @param array<string, mixed> $current The tile's current content.
	 *
	 * @return array<string, mixed>
	 */
	private function tileContent(array $args, array $current): array {
		return array_intersect_key($args, array_flip(self::TILE_FIELDS)) + $current;
	}//end tileContent()

	/**
	 * The placement when it is a tile, or a refusal.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return object|array<string, mixed>
	 */
	private function tilePlacement(int $placementId): object|array {
		try {
			$placement = $this->sources->widgetService->getPlacement(placementId: $placementId);
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		if ($placement->getWidgetId() !== 'tile') {
			return $this->validator->refuse(code: 'invalid_arguments', message: 'This placement is not a tile.');
		}

		return $placement;
	}//end tilePlacement()

	/**
	 * A refusal when the placement is not a tile, or NULL to proceed.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return array<string, mixed>|null
	 */
	private function notATile(int $placementId): ?array {
		$placement = $this->tilePlacement(placementId: $placementId);
		if (is_array($placement) === true) {
			return $placement;
		}

		return null;
	}//end notATile()

	/**
	 * Add the placement through WidgetService::addWidget(), as the route does.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param string               $widgetId    The widget type.
	 * @param array<string, mixed> $args        The tool arguments: grid position, content.
	 *
	 * @return array<string, mixed>
	 */
	private function place(int $dashboardId, string $widgetId, array $args): array {
		$layout = [
			'gridX' => (int)($args['gridX'] ?? 0),
			'gridY' => (int)($args['gridY'] ?? 0),
			'gridWidth' => (int)($args['gridWidth'] ?? 4),
			'gridHeight' => (int)($args['gridHeight'] ?? 4),
			'content' => ($args['content'] ?? null),
		];

		return $this->run(
			operation: fn (): array => $this->sources->widgetService->addWidget(
				dashboardId: $dashboardId,
				widgetId: $widgetId,
				layout: $layout
			)->jsonSerialize()
		);
	}//end place()

	/**
	 * Change the placement through WidgetService::updatePlacement(). Content on
	 * a widget whose calculated fields OpenRegister stores is left to the app,
	 * which re-materialises them.
	 *
	 * @param int                  $placementId The placement.
	 * @param array<string, mixed> $data        The fields to change.
	 *
	 * @return array<string, mixed>
	 */
	private function change(int $placementId, array $data): array {
		return $this->run(
			operation: function () use ($placementId, $data): array {
				if (isset($data['content']) === true) {
					$fields = $this->sources->widgetService->getPlacement(placementId: $placementId)->getCalculatedFieldsArray();
					if ($this->sources->calculatedFields->hasServerFields(fields: $fields) === true) {
						return $this->validator->refuse(
							code: 'invalid_arguments',
							message: 'This widget has calculated fields stored in OpenRegister; change its content in LaunchPad.'
						);
					}
				}

				return $this->sources->widgetService->updatePlacement(placementId: $placementId, data: $data)->jsonSerialize();
			}
		);
	}//end change()

	/**
	 * Remove the placement, and first the OpenRegister fields its calculated
	 * fields wrote, as the route does.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return array<string, mixed>
	 */
	private function remove(int $placementId): array {
		return $this->run(
			operation: function () use ($placementId): array {
				$placement = $this->sources->widgetService->getPlacement(placementId: $placementId);
				$warnings  = [];
				if ($this->sources->calculatedFields->hasServerFields(fields: $placement->getCalculatedFieldsArray()) === true) {
					$warnings = $this->sources->calculatedFields->cleanupServerFields(
						widget: [
							'id' => (string)$placementId,
							'source' => (new CalculatedFieldSources())->sourceKey(content: $placement->getContentArray()),
						]
					);
				}

				$this->sources->widgetService->removePlacement(placementId: $placementId);
				return ['removed' => true, 'warnings' => $warnings];
			}
		);
	}//end remove()

	/**
	 * Run a service call, mapping its failures to the tools' envelopes: a
	 * stale version is `stale`, the app's resource exceptions (quota, not
	 * found, ...) keep their code, anything else stays generic.
	 *
	 * @param \Closure(): array<string, mixed> $operation The service call.
	 *
	 * @return array<string, mixed>
	 */
	private function run(\Closure $operation): array {
		try {
			return $operation();
		} catch (StalePlacementException $e) {
			return $this->validator->refuse(code: 'stale', message: $e->getMessage());
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end run()
}//end class
