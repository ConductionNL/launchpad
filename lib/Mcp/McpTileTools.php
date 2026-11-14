<?php

/**
 * LaunchPad McpTileTools
 *
 * The read tools over tiles. Like McpDashboardTools, each applies the ADR-023
 * action of the matching HTTP route through the gate and calls the same
 * service the route calls (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Service\TileService;

/**
 * Tile read tools for the MCP surface.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpTileTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate $gate  The caller and the ADR-023 action check.
	 * @param TileService      $tiles The tile service `GET /api/tiles` calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly TileService $tiles,
	) {
	}//end __construct()

	/**
	 * The caller's own tiles, as `GET /api/tiles` lists them.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listTiles(array $args): array {
		$refusal = $this->gate->requireAction(action: 'tile.index');
		if ($refusal !== null) {
			return $refusal;
		}

		$items = [];
		foreach ($this->tiles->getUserTiles(userId: $this->gate->currentUserId()) as $tile) {
			$items[] = $tile->jsonSerialize();
		}

		return ['items' => $items];
	}//end listTiles()
}//end class
