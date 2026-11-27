<?php

/**
 * StoreInstallFlow
 *
 * The install record (REQ-STORE-011) and the in-place update (REQ-STORE-012)
 * the store endpoints use together, so the controller takes one parameter for
 * them. Either may be absent; the controller then skips what it needs.
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

/**
 * The install record and the updater of the marketplace.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
 */
class StoreInstallFlow {
	/**
	 * Constructor.
	 *
	 * @param StoreInstalls|null      $installs Which item and version an install created which dashboard.
	 * @param StoreUpdateService|null $updates  Brings an installed item to a newer version in place.
	 * @param StorePublishService|null $publications Publishes a dashboard to the connected register (REQ-STORE-013).
	 *
	 * @return void
	 */
	public function __construct(
		public readonly ?StoreInstalls $installs = null,
		public readonly ?StoreUpdateService $updates = null,
		public readonly ?StorePublishService $publications = null,
	) {
	}//end __construct()
}//end class
