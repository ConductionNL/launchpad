<?php

/**
 * PlacementPayloadHydrator
 *
 * Builds a WidgetPlacement from the array a placement is exported as — the
 * shape `WidgetPlacement::jsonSerialize()` writes into a
 * `launchpad-export-v1` archive. Every path that reads such an archive back
 * goes through here: the dashboard importer (`ImportService`, which the
 * store's install path also uses) and the bundled showcase installer
 * (`DemoShowcasesService`).
 *
 * WHY ONE BUILDER. Each of those paths used to carry its own copy, and each
 * copy kept a different subset of the fields export writes. The importer kept
 * the grid, the style and the title and dropped the rest, so a dashboard that
 * went out through export and came back through import lost every widget's
 * configuration (`content`) and every tile's identity (`tileType` and its
 * fields). The showcase installer dropped `content` too, until launchpad#606.
 * Two copies drifted; one cannot.
 *
 * WHAT IS DELIBERATELY NOT CARRIED. Fields that point at THIS instance or at a
 * workflow on it rather than describing the widget: `id`, `dashboardId`,
 * `templatePlacementId` (the id of another placement row), `isCompulsory`
 * (an admin push, not part of a dashboard's layout), the mandatory-read
 * `acknowledgement*` fields and `announcementKey`. A fresh placement gets
 * fresh timestamps.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-export-import/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Build a placement entity from its exported array form.
 */
class PlacementPayloadHydrator {
	/**
	 * Tile fields, copied as strings when present.
	 *
	 * Mirrors the block `WidgetPlacement::jsonSerialize()` emits when a
	 * placement is a tile.
	 *
	 * @var array<int, string>
	 */
	private const TILE_FIELDS = [
		'tileIcon',
		'tileIconType',
		'tileBackgroundColor',
		'tileTextColor',
		'tileLinkType',
		'tileLinkValue',
	];

	/**
	 * Build an unsaved placement on a dashboard from an exported payload.
	 *
	 * @param int                  $dashboardId The dashboard the placement joins.
	 * @param array<string, mixed> $payload     One entry of an exported dashboard's
	 *                                          `widgets` array.
	 *
	 * @return WidgetPlacement The placement, not yet persisted.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function hydrate(int $dashboardId, array $payload): WidgetPlacement {
		$placement = new WidgetPlacement();
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		// Entity setters resolve through __call, which reads $args[0]; named
		// arguments would break that forwarding.
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId((string)($payload['widgetId'] ?? ''));
		$placement->setGridX((int)($payload['gridX'] ?? 0));
		$placement->setGridY((int)($payload['gridY'] ?? 0));
		$placement->setGridWidth((int)($payload['gridWidth'] ?? 4));
		$placement->setGridHeight((int)($payload['gridHeight'] ?? 4));
		$placement->setIsVisible((int)($payload['isVisible'] ?? 1));
		$placement->setShowTitle((int)($payload['showTitle'] ?? 1));
		$placement->setSortOrder((int)($payload['sortOrder'] ?? 0));
		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		if (isset($payload['customTitle']) === true) {
			$placement->setCustomTitle((string)$payload['customTitle']);
		}

		if (isset($payload['customIcon']) === true) {
			$placement->setCustomIcon((string)$payload['customIcon']);
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->applyTile(placement: $placement, payload: $payload);
		$this->applyBlobs(placement: $placement, payload: $payload);

		return $placement;
	}//end hydrate()

	/**
	 * Copy a tile's type and fields, when the payload is a tile.
	 *
	 * @param WidgetPlacement      $placement The placement being built.
	 * @param array<string, mixed> $payload   The exported payload.
	 *
	 * @return void
	 */
	private function applyTile(WidgetPlacement $placement, array $payload): void {
		if (isset($payload['tileType']) === false) {
			return;
		}

		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setTileType((string)$payload['tileType']);
		// A tile always gets a title, empty if the payload has none.
		$placement->setTileTitle((string)($payload['tileTitle'] ?? ''));
		foreach (self::TILE_FIELDS as $field) {
			if (isset($payload[$field]) === true) {
				$placement->{'set' . ucfirst($field)}((string)$payload[$field]);
			}
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end applyTile()

	/**
	 * Copy the two JSON blobs: `styleConfig` and `content`.
	 *
	 * A registry widget's configuration lives in `content`: which register an
	 * object-list reads, which Nextcloud widget an nc-widget proxies, what a
	 * text widget says. Without it the placement lands and renders as an
	 * unconfigured widget, which nothing on the way in can notice. Export
	 * writes an empty blob as `{}`, which decodes to `[]`; that carries
	 * nothing, so the column is left NULL rather than set to `[]`.
	 *
	 * @param WidgetPlacement      $placement The placement being built.
	 * @param array<string, mixed> $payload   The exported payload.
	 *
	 * @return void
	 */
	private function applyBlobs(WidgetPlacement $placement, array $payload): void {
		if (isset($payload['styleConfig']) === true && is_array($payload['styleConfig']) === true) {
			$placement->setStyleConfigArray(config: $payload['styleConfig']);
		}

		if (isset($payload['content']) === true
			&& is_array($payload['content']) === true
			&& $payload['content'] !== []
		) {
			$placement->setContentArray(content: $payload['content']);
		}
	}//end applyBlobs()
}//end class
