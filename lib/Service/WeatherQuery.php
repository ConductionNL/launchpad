<?php

/**
 * WeatherQuery
 *
 * The location, units and language one weather reading is resolved for.
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
 * @spec openspec/specs/clock-weather-widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The location, units and language one weather reading is resolved for.
 *
 * @spec openspec/specs/clock-weather-widgets/spec.md
 */
final class WeatherQuery {
	/**
	 * Constructor.
	 *
	 * @param string $location The author-configured location, or `''`.
	 * @param string $units    `metric` or `imperial`.
	 * @param string $language The resolved forecast language.
	 */
	public function __construct(
		public readonly string $location,
		public readonly string $units,
		public readonly string $language,
	) {
	}//end __construct()
}//end class
