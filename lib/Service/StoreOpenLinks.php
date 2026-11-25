<?php

/**
 * LaunchPad Store Open Links.
 *
 * Where the Open action of an installed marketplace item goes: the installed
 * dashboard's own address in the workspace (REQ-STORE-011).
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
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IURLGenerator;
use Throwable;

/**
 * The address an installed item opens.
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
 */
class StoreOpenLinks {

	/**
	 * Constructor.
	 *
	 * @param IURLGenerator        $urls The route builder.
	 * @param DashboardTreeService $tree The dashboard's slug path.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IURLGenerator $urls,
		private readonly DashboardTreeService $tree,
	) {
	}//end __construct()

	/**
	 * The workspace address of a dashboard: its deep link when it has a slug
	 * path, the workspace itself otherwise; '' when the dashboard is gone.
	 *
	 * @param string $uuid The dashboard uuid.
	 *
	 * @return string The address, or ''.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function openUrl(string $uuid): string {
		if ($uuid === '') {
			return '';
		}

		try {
			$path = trim(string: $this->tree->computePath(uuid: $uuid), characters: '/');
		} catch (Throwable) {
			return '';
		}

		if ($path === '') {
			return $this->urls->linkToRoute(routeName: 'launchpad.page.index');
		}

		return $this->urls->linkToRoute(routeName: 'launchpad.page.deepLink', arguments: ['deepLink' => $path]);
	}//end openUrl()

}//end class
