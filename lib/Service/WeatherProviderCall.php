<?php

/**
 * WeatherProviderCall
 *
 * One call to the configured weather provider: the expanded URL and the
 * query it was built for.
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
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * One call to the configured weather provider.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */
final class WeatherProviderCall {
	/**
	 * Constructor.
	 *
	 * @param string       $url   The expanded provider URL.
	 * @param WeatherQuery $query The query the URL was built for.
	 */
	public function __construct(
		public readonly string $url,
		public readonly WeatherQuery $query,
	) {
	}//end __construct()
}//end class
