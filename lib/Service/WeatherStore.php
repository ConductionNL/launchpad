<?php

/**
 * WeatherStore
 *
 * The placement mapper and the logger of the weather service.
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

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use Psr\Log\LoggerInterface;

/**
 * The placement mapper and the logger of the weather service.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */
class WeatherStore {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placementMapper Resolves placements by id.
	 * @param LoggerInterface       $logger PSR logger.
	 */
	public function __construct(
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
