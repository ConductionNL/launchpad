<?php

/**
 * StoreUpdateFollowUps
 *
 * What a marketplace update does after it wrote the dashboard: bring copies
 * along with the merge re-sync, write the version note, and log a failure.
 * Kept together so {@see StoreUpdateService} takes one parameter for them.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Psr\Log\LoggerInterface;

/**
 * The re-sync, the version service and the logger of a marketplace update.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
 */
class StoreUpdateFollowUps {
	/**
	 * Constructor.
	 *
	 * @param TemplateResyncService   $resync   Brings copies along.
	 * @param DashboardVersionService $versions Writes the version note.
	 * @param LoggerInterface         $logger   Server-side diagnostics only.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly TemplateResyncService $resync,
		public readonly DashboardVersionService $versions,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
