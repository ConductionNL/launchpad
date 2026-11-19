<?php

/**
 * LaunchPad McpCatalogueSources
 *
 * The services behind the catalogue read tools: the template gallery and the
 * caller's tiles, taken by McpCatalogueTools as one value.
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

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\TileService;

/**
 * The template and tile services.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpCatalogueSources {

	/**
	 * Constructor.
	 *
	 * @param AdminTemplateService $templates The gallery `GET /api/templates/gallery` lists.
	 * @param TileService          $tiles     The tiles `GET /api/tiles` lists.
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function __construct(
		public readonly AdminTemplateService $templates,
		public readonly TileService $tiles,
	) {
	}//end __construct()
}//end class
