<?php

/**
 * LaunchPad McpPersonalTools
 *
 * The read tools over the template gallery, the caller's pending
 * acknowledgements and the kiosk playlists. Each needs what its HTTP route
 * needs (the gallery its ADR-023 action, the others a signed-in caller) and
 * calls the same service, which scopes the result to the caller
 * (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\KioskService;

/**
 * The read tools over templates, acknowledgements and kiosk playlists.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpPersonalTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate       $gate             The caller and the ADR-023 action check.
	 * @param AdminTemplateService   $templates        The gallery `GET /api/templates/gallery` reads.
	 * @param AcknowledgementService $acknowledgements The service `GET /api/acknowledgements/pending` calls.
	 * @param KioskService           $kiosk            The service `GET /api/kiosk/playlists` calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly AdminTemplateService $templates,
		private readonly AcknowledgementService $acknowledgements,
		private readonly KioskService $kiosk,
	) {
	}//end __construct()

	/**
	 * The template gallery, optionally one category, sorted like the gallery page.
	 *
	 * @param array<string, mixed> $args Optional `category` and `sort`.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listTemplates(array $args): array {
		$refusal = $this->gate->requireAction(action: 'template.gallery');
		if ($refusal !== null) {
			return $refusal;
		}

		$category = null;
		if (is_string($args['category'] ?? null) === true) {
			$category = $args['category'];
		}

		$sort = 'name';
		if (is_string($args['sort'] ?? null) === true) {
			$sort = $args['sort'];
		}

		return ['items' => $this->templates->getGallery(category: $category, sortBy: $sort)];
	}//end listTemplates()

	/**
	 * The announcements on the caller's dashboards that wait for their acknowledgement.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listPendingAcknowledgements(array $args): array {
		$refusal = $this->gate->requireSignedIn();
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->acknowledgements->getPending(userId: $this->gate->currentUserId());
	}//end listPendingAcknowledgements()

	/**
	 * The kiosk playlists the caller may see.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listKioskPlaylists(array $args): array {
		$refusal = $this->gate->requireSignedIn();
		if ($refusal !== null) {
			return $refusal;
		}

		$items = [];
		foreach ($this->kiosk->listPlaylists(callerId: $this->gate->currentUserId()) as $playlist) {
			$items[] = $playlist->jsonSerialize();
		}

		return ['items' => $items];
	}//end listKioskPlaylists()
}//end class
