<?php

/**
 * FilesWidgetSources
 *
 * The files widget service, placements and log of the files widget API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\FilesWidgetService;
use Psr\Log\LoggerInterface;

/**
 * The files widget service, placements and log of the files widget API.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class FilesWidgetSources {
	/**
	 * Constructor.
	 *
	 * @param FilesWidgetService    $service Files widget service.
	 * @param WidgetPlacementMapper $placementMapper Placement entity mapper.
	 * @param LoggerInterface       $logger PSR logger.
	 */
	public function __construct(
		public readonly FilesWidgetService $service,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
