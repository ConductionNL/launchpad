<?php

/**
 * PersonalLayerService
 *
 * A person rearranges a dashboard somebody else owns, and only they see the
 * result (REQ-DWMS-001, REQ-DWMS-002).
 *
 * The rule that shapes every method here: the shared placements are the ones
 * that render. This service never copies them. It reads one row of
 * differences and lays it over what came back from the placement mapper, so a
 * change the organisation makes tomorrow is seen tomorrow rather than
 * diverging quietly into a personal copy.
 *
 * Two refusals. A placement the administrator marked compulsory cannot be
 * hidden, because the point of marking it was that everybody reads it; it can
 * still be moved. And a reset deletes the whole layer, never part of it: a
 * half-reset dashboard is one nobody can reason about.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Applies, saves and resets a personal layer.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class PersonalLayerService {
	/**
	 * The placement keys a person may change about a placement. Anything else
	 * a client sends is dropped: a layer holds geometry and order, never
	 * content, a title or a permission.
	 */
	public const ADJUSTABLE = ['gridX', 'gridY', 'gridWidth', 'gridHeight', 'sortOrder'];

	/**
	 * Constructor.
	 *
	 * @param PersonalLayerMapper $layers Reads and writes the layer row.
	 */
	public function __construct(
		private readonly PersonalLayerMapper $layers,
	) {
	}//end __construct()

	/**
	 * Lay a person's arrangement over the shared placements.
	 *
	 * @param array<int, WidgetPlacement> $placements What the owner composed.
	 * @param string $userId The person reading.
	 * @param int $dashboardId The dashboard.
	 *
	 * @return array<int, WidgetPlacement> The placements this person sees, in
	 *         their order. A person with no layer gets the input back.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function applyTo(array $placements, string $userId, int $dashboardId): array {
		$layer = $this->layerFor(userId: $userId, dashboardId: $dashboardId);
		if ($layer === null) {
			// Nothing to apply, and nothing was read from the database beyond
			// one miss: the feature costs nothing until somebody uses it.
			return $placements;
		}

		$overrides = $layer->overridesArray();
		$hidden = $layer->hiddenArray();

		$out = [];
		foreach ($placements as $placement) {
			$id = (int)$placement->getId();
			if (in_array($id, $hidden, true) === true && $this->isCompulsory(placement: $placement) === false) {
				continue;
			}

			$this->adjust(placement: $placement, values: ($overrides[$id] ?? []));
			$out[] = $placement;
		}

		usort(
			$out,
			static function (WidgetPlacement $first, WidgetPlacement $second): int {
				return ((int)$first->getSortOrder() <=> (int)$second->getSortOrder());
			}
		);

		return $out;
	}//end applyTo()

	/**
	 * Save a person's changes to one or more placements.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 * @param array<int, array<string, mixed>> $overrides Per placement id, the
	 *                                                    keys they changed.
	 * @param array<int, int> $hide The placement ids they want hidden.
	 * @param array<int, WidgetPlacement> $placements The shared placements, to
	 *                                                decide what is compulsory.
	 *
	 * @return array{saved: true}|array{error: string, placementId: int} The
	 *         refusal names the placement, so the message can say which widget.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function save(string $userId, int $dashboardId, array $overrides, array $hide, array $placements): array {
		$compulsory = [];
		$known = [];
		foreach ($placements as $placement) {
			$known[] = (int)$placement->getId();
			if ($this->isCompulsory(placement: $placement) === true) {
				$compulsory[] = (int)$placement->getId();
			}
		}

		foreach ($hide as $placementId) {
			if (in_array((int)$placementId, $compulsory, true) === true) {
				// Refused before anything is written, so a batch that touches
				// a compulsory widget lands nothing at all.
				return ['error' => 'placement_compulsory', 'placementId' => (int)$placementId];
			}
		}

		$layer = $this->layerFor(userId: $userId, dashboardId: $dashboardId);
		$isNew = ($layer === null);
		if ($layer === null) {
			$layer = new PersonalLayer();
			$layer->setUserId($userId);
			$layer->setDashboardId($dashboardId);
		}

		$layer->setOverrides(json_encode($this->cleanOverrides(overrides: $overrides, known: $known)));
		$layer->setHidden(json_encode(array_values(array_intersect(array_map('intval', $hide), $known))));
		$layer->setUpdatedAt((new DateTimeImmutable())->format(DATE_ATOM));

		if ($isNew === true) {
			$this->layers->insert($layer);

			return ['saved' => true];
		}

		$this->layers->update($layer);

		return ['saved' => true];
	}//end save()

	/**
	 * Put the dashboard back to what its owner composed.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 *
	 * @return bool True when a layer was there and is now gone. False means
	 *         there was nothing to reset, which is not a failure.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function reset(string $userId, int $dashboardId): bool {
		return ($this->layers->deleteForUser(userId: $userId, dashboardId: $dashboardId) > 0);
	}//end reset()

	/**
	 * Drop layer entries for placements that no longer exist.
	 *
	 * An admin template re-sync replaces placements, and the ids it removed
	 * would otherwise sit in the layer forever, invisible and growing. When
	 * the sweep empties the layer the row goes too, so the person is back on
	 * the owner's arrangement rather than on an empty personal one.
	 *
	 * NOTHING CALLS THIS YET. Read the two tests below as what they are:
	 * proof that the sweep is right, not proof that anything runs it. Task
	 * 1.5 in the change is open again for that reason, and it says what
	 * wiring needs: a re-sync replaces one dashboard's placements for every
	 * reader, this takes one user id, and `PersonalLayerMapper` has no query
	 * that lists the layers on a dashboard. Eight callers of
	 * `WidgetPlacementMapper::deleteByDashboardId()` each have to say whether
	 * they are a re-sync that prunes or a deletion that drops the row.
	 *
	 * Nobody sees a wrong number while it waits. `applyTo()` walks the live
	 * placements and reads overrides by id, so a stale entry is ignored, and
	 * placement ids are autoincrement and never reused, so it cannot attach
	 * to another widget later. The cost is stale keys in the row.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 * @param array<int, int> $liveIds The placement ids that still exist.
	 *
	 * @return int How many entries were dropped.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function pruneOrphans(string $userId, int $dashboardId, array $liveIds): int {
		$layer = $this->layerFor(userId: $userId, dashboardId: $dashboardId);
		if ($layer === null) {
			return 0;
		}

		$live = array_map('intval', $liveIds);
		$overrides = $layer->overridesArray();
		$hidden = $layer->hiddenArray();

		$keptOverrides = array_filter(
			$overrides,
			static function (int $placementId) use ($live): bool {
				return in_array($placementId, $live, true);
			},
			ARRAY_FILTER_USE_KEY
		);
		$keptHidden = array_values(array_intersect($hidden, $live));

		$dropped = ((count($overrides) - count($keptOverrides)) + (count($hidden) - count($keptHidden)));
		if ($dropped === 0) {
			return 0;
		}

		if ($keptOverrides === [] && $keptHidden === []) {
			$this->layers->deleteForUser(userId: $userId, dashboardId: $dashboardId);
			return $dropped;
		}

		$layer->setOverrides(json_encode($keptOverrides));
		$layer->setHidden(json_encode($keptHidden));
		$layer->setUpdatedAt((new DateTimeImmutable())->format(DATE_ATOM));
		$this->layers->update($layer);

		return $dropped;
	}//end pruneOrphans()

	/**
	 * The person's layer, or null.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 *
	 * @return PersonalLayer|null
	 */
	private function layerFor(string $userId, int $dashboardId): ?PersonalLayer {
		if ($userId === '' || $dashboardId <= 0) {
			return null;
		}

		return $this->layers->findForUser(userId: $userId, dashboardId: $dashboardId);
	}//end layerFor()

	/**
	 * Whether the administrator marked this placement compulsory.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return bool
	 */
	private function isCompulsory(WidgetPlacement $placement): bool {
		return ((int)$placement->getIsCompulsory() === 1);
	}//end isCompulsory()

	/**
	 * Write the adjustable values onto a placement.
	 *
	 * @param WidgetPlacement $placement The placement.
	 * @param array<string, int> $values What this person set.
	 *
	 * @return void
	 */
	private function adjust(WidgetPlacement $placement, array $values): void {
		foreach (self::ADJUSTABLE as $key) {
			if (array_key_exists($key, $values) === false) {
				continue;
			}

			$setter = 'set' . ucfirst($key);
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$placement->$setter((int)$values[$key]);
		}
	}//end adjust()

	/**
	 * Keep only known placements and adjustable keys.
	 *
	 * @param array<int, array<string, mixed>> $overrides What the client sent.
	 * @param array<int, int> $known The placement ids on the dashboard.
	 *
	 * @return array<int, array<string, int>>
	 */
	private function cleanOverrides(array $overrides, array $known): array {
		$clean = [];
		foreach ($overrides as $placementId => $values) {
			$id = (int)$placementId;
			if (in_array($id, $known, true) === false || is_array($values) === false) {
				continue;
			}

			$kept = [];
			foreach (self::ADJUSTABLE as $key) {
				if (array_key_exists($key, $values) === true && is_numeric($values[$key]) === true) {
					$kept[$key] = (int)$values[$key];
				}
			}

			if ($kept !== []) {
				$clean[$id] = $kept;
			}
		}

		return $clean;
	}//end cleanOverrides()
}//end class
