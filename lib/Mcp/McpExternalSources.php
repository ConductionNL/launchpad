<?php

/**
 * LaunchPad McpExternalSources
 *
 * The services behind the external-reach tools: kiosk playlists and public
 * links, taken by McpSharingTools as one value.
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

use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\SharingLimits;

/**
 * The kiosk and public-link services.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
 */
class McpExternalSources {

	/**
	 * Constructor.
	 *
	 * @param KioskService       $kiosk The service the `kiosk` routes call.
	 * @param PublicShareService $links The service the `publicShare` routes call.
	 * @param SharingLimits|null $limits The public-link policy the `publicShare` create route applies (REQ-SHARE-015).
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function __construct(
		public readonly KioskService $kiosk,
		public readonly PublicShareService $links,
		public readonly ?SharingLimits $limits = null,
	) {
	}//end __construct()
}//end class
