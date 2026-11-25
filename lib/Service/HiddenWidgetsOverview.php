<?php

/**
 * HiddenWidgetsOverview
 *
 * Every widget one person hid on a shared dashboard, across all dashboards,
 * for the section Verborgen widgets of the personal settings dialog
 * (personal-settings REQ-PSET-003). It reads and changes only the caller's
 * own personal layers (REQ-DWMS-001): the shared dashboards stay as they are
 * for everyone else.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;

/**
 * Lists, and shows again, the widgets a person hid.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class HiddenWidgetsOverview {
	/**
	 * Constructor.
	 *
	 * @param PersonalLayerMapper   $layers     The personal layers.
	 * @param WidgetPlacementMapper $placements The shared placements, to name a hidden widget.
	 * @param DashboardService      $dashboards Decides whether the person may still see a dashboard.
	 */
	public function __construct(
		private readonly PersonalLayerMapper $layers,
		private readonly WidgetPlacementMapper $placements,
		private readonly DashboardService $dashboards,
	) {
	}//end __construct()

	/**
	 * The widgets this person hid, on the dashboards they may still see.
	 *
	 * A hidden id whose placement no longer exists is left out: the person
	 * cannot show again what is not there. A dashboard the person lost access
	 * to is left out too, so the list never names a dashboard they cannot open.
	 *
	 * @param string $userId The caller.
	 *
	 * @return array<int, array<string, mixed>> Per hidden widget: `placementId`,
	 *     `dashboardId`, `dashboardName` and the serialised `placement`.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function listFor(string $userId): array {
		if ($userId === '') {
			return [];
		}

		$out = [];
		foreach ($this->layers->findAllForUser(userId: $userId) as $layer) {
			$hidden = $layer->hiddenArray();
			if ($hidden === []) {
				continue;
			}

			$dashboardId = (int)$layer->getDashboardId();
			$envelope = $this->dashboards->getDashboardForUser(dashboardId: $dashboardId, userId: $userId);
			if ($envelope === null) {
				continue;
			}

			$name = self::dashboardName(dashboard: $envelope['dashboard'] ?? null);
			foreach ($this->placements->findByDashboardId(dashboardId: $dashboardId) as $placement) {
				if (in_array((int)$placement->getId(), $hidden, true) === false) {
					continue;
				}

				$out[] = [
					'placementId' => (int)$placement->getId(),
					'dashboardId' => $dashboardId,
					'dashboardName' => $name,
					'placement' => $placement->jsonSerialize(),
				];
			}
		}//end foreach

		return $out;
	}//end listFor()

	/**
	 * Show one hidden widget again, on the caller's own layer.
	 *
	 * @param string $userId      The caller.
	 * @param int    $placementId The hidden placement.
	 *
	 * @return bool True when it was hidden by this person and now is not.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function showAgain(string $userId, int $placementId): bool {
		if ($userId === '' || $placementId <= 0) {
			return false;
		}

		foreach ($this->layers->findAllForUser(userId: $userId) as $layer) {
			$hidden = $layer->hiddenArray();
			if (in_array($placementId, $hidden, true) === false) {
				continue;
			}

			$this->storeHidden(
				layer: $layer,
				hidden: array_values(array_diff($hidden, [$placementId]))
			);

			return true;
		}

		return false;
	}//end showAgain()

	/**
	 * Show every widget this person hid again, on every dashboard. Their
	 * other adjustments (position, size) stay.
	 *
	 * @param string $userId The caller.
	 *
	 * @return int How many widgets came back.
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function showAll(string $userId): int {
		if ($userId === '') {
			return 0;
		}

		$count = 0;
		foreach ($this->layers->findAllForUser(userId: $userId) as $layer) {
			$hidden = $layer->hiddenArray();
			if ($hidden === []) {
				continue;
			}

			$count += count($hidden);
			$this->storeHidden(layer: $layer, hidden: []);
		}

		return $count;
	}//end showAll()

	/**
	 * Write a new hidden set on a layer the caller owns.
	 *
	 * @param PersonalLayer   $layer  The caller's layer.
	 * @param array<int, int> $hidden The placement ids that stay hidden.
	 *
	 * @return void
	 */
	private function storeHidden(PersonalLayer $layer, array $hidden): void {
		$layer->setHidden(json_encode($hidden));
		$layer->setUpdatedAt((new DateTimeImmutable())->format(DATE_ATOM));
		$this->layers->update($layer);
	}//end storeHidden()

	/**
	 * The name of a dashboard from the read envelope, entity or array.
	 *
	 * @param mixed $dashboard The envelope's `dashboard`.
	 *
	 * @return string The name, or an empty string.
	 */
	private static function dashboardName(mixed $dashboard): string {
		if ($dashboard instanceof Dashboard) {
			return (string)$dashboard->getName();
		}

		if (is_array($dashboard) === true) {
			return (string)($dashboard['name'] ?? '');
		}

		return '';
	}//end dashboardName()
}//end class
