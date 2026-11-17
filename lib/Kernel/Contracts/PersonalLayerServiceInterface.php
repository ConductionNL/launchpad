<?php

/**
 * Contract of PersonalLayerService
 *
 * The public surface of PersonalLayerService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
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
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of PersonalLayerService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface PersonalLayerServiceInterface {
	/**
	 * Save a person's changes to one or more placements.
	 *
	 * @param string $userId The person.
	 * @param int $dashboardId The dashboard.
	 * @param array<int, array<string, mixed>> $overrides Per placement id, the
	 *                                                    keys they changed.
	 * @param array<int, int> $hide The placement ids they want hidden.
	 * @param array<int, \OCA\LaunchPad\Db\WidgetPlacement> $placements The shared placements, to
	 *                                                decide what is compulsory.
	 *
	 * @return array{saved: true}|array{error: string, placementId: int} The
	 *         refusal names the placement, so the message can say which widget.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function save(string $userId, int $dashboardId, array $overrides, array $hide, array $placements): array;

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
	public function reset(string $userId, int $dashboardId): bool;
}//end interface
