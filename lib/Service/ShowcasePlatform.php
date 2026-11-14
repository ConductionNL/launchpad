<?php

/**
 * ShowcasePlatform
 *
 * The Nextcloud services the demo showcases use: app config, the dashboard manager, URLs and
 * events.
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

use OCP\Dashboard\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IURLGenerator;

/**
 * The Nextcloud services the demo showcases use: app config, the dashboard manager, URLs and
 * events.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ShowcasePlatform {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig            $appConfig App config service.
	 * @param IManager              $dashboardManager Nextcloud dashboard registry.
	 * @param IURLGenerator         $urlGenerator URL generator used to build routed thumbnail URLs that resolve
	 *                                            from any apps directory.
	 * @param IEventDispatcher|null $eventDispatcher Event dispatcher for DashboardDeletedEvent (SB1 fix,
	 *                                               REQ-CSC-001). Nullable for backwards- compat.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly IManager $dashboardManager,
		public readonly IURLGenerator $urlGenerator,
		public readonly ?IEventDispatcher $eventDispatcher=null,
	) {
	}//end __construct()
}//end class
