<?php

/**
 * DashboardOutlets
 *
 * Where DashboardService reports to: the log, events, URLs and the activity stream.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Where DashboardService reports to: the log, events, URLs and the activity stream.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardOutlets {
	/**
	 * Constructor.
	 *
	 * @param LoggerInterface               $logger PSR logger.
	 * @param IEventDispatcher|null         $eventDispatcher Event dispatcher for DashboardDeletedEvent. Nullable
	 *                                                       for backwards- compat with existing test doubles.
	 * @param IURLGenerator|null            $urlGenerator URL generator used to build widget / app image paths
	 *                                                    and to resolve app image paths for default tile logos,
	 *                                                    so they render regardless of the app's install
	 *                                                    location.
	 * @param DashboardActivityEmitter|null $activity Sends dashboard_published and dashboard_updated to the
	 *                                                audience (#713).
	 */
	public function __construct(
		public readonly LoggerInterface $logger,
		public readonly ?IEventDispatcher $eventDispatcher=null,
		public readonly ?IURLGenerator $urlGenerator=null,
		public readonly ?DashboardActivityEmitter $activity=null,
	) {
	}//end __construct()
}//end class
