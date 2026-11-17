<?php

/**
 * Contract of WeatherService
 *
 * The public surface of WeatherService that other components call, so they depend
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
 * Contract of WeatherService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface WeatherServiceInterface {
	/**
	 * Resolve the weather reading for one placement, on behalf of one
	 * viewing user. Never throws — every failure path returns either a
	 * stale cached reading or the `{error: ...}` shape (REQ-WEATHER-002
	 * "upstream failure degrades gracefully").
	 *
	 * @param integer $placementId The widget placement id.
	 * @param string $userId The viewing user's UID (drives locale-derived units/language).
	 *
	 * @return array<string,mixed> `{location, tempValue, units, condition, conditionText, language, fetchedAt, stale}` or `{error: string}`.
	 *
	 * @spec openspec/specs/clock-weather-widgets/spec.md
	 */
	public function resolveForPlacement(int $placementId, string $userId): array;
}//end interface
