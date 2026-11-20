<?php

/**
 * RenderServices
 *
 * The Nextcloud services a render needs: the root folder, the URL
 * generator and the clock (kept together to keep the pipeline's
 * constructor short).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;

/**
 * Files, URLs and the clock for the render pipeline.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
 */
final class RenderServices {

	/**
	 * Constructor.
	 *
	 * @param IRootFolder   $rootFolder The users' Files.
	 * @param IURLGenerator $urls       The page URL.
	 * @param ITimeFactory  $time       The clock.
	 */
	public function __construct(
		public readonly IRootFolder $rootFolder,
		public readonly IURLGenerator $urls,
		public readonly ITimeFactory $time,
	) {
	}//end __construct()
}//end class
