<?php

/**
 * LaunchPad McpReadHandlers
 *
 * The read-tool handlers beyond dashboards and tiles, handed to the
 * provider together so its constructor stays small.
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

/**
 * The tree, share and personal read handlers.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
final class McpReadHandlers {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardTreeTools $tree     The dashboard tree tool.
	 * @param McpShareTools         $shares   The share tool.
	 * @param McpPersonalTools      $personal The template, acknowledgement and kiosk tools.
	 */
	public function __construct(
		public readonly McpDashboardTreeTools $tree,
		public readonly McpShareTools $shares,
		public readonly McpPersonalTools $personal,
	) {
	}//end __construct()
}//end class
