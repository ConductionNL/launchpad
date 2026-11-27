<?php

/**
 * AdminPageStateBuilder
 *
 * The initial-state builder of the admin settings page: the shared contract
 * of {@see InitialStateBuilder} plus the page's own optional keys, the tabs
 * the viewer may open (REQ-ADM-034) and the running app version its version
 * card reads (REQ-ADM-032).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Initial state of the admin settings page.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
class AdminPageStateBuilder extends InitialStateBuilder {
	/**
	 * The tabs the viewer may open and the app version.
	 *
	 * @param list<string> $tabs    Tab ids, in the page's order.
	 * @param string       $version The app version.
	 *
	 * @return self For chaining.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function setAdminPage(array $tabs, string $version): self {
		$this->values['adminTabs'] = $tabs;
		$this->values['version']   = $version;
		return $this;
	}//end setAdminPage()
}//end class
