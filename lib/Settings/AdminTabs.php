<?php

/**
 * AdminTabs
 *
 * The tabs of Launchpad's admin settings page at `/settings/admin/launchpad`
 * (REQ-ADM-032), in the order the LpBeheer boards draw them, the former tabs
 * that are now a section of a drawn tab, and the one tab a delegated
 * announcements manager holds (REQ-ADM-034). `src/utils/adminPage.js` knows
 * the same lists.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

/**
 * The admin settings page's tab ids.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 */
final class AdminTabs {
	/**
	 * The tab a page without a (known) tab opens on.
	 */
	public const DEFAULT_TAB = 'general';

	/**
	 * The tab a delegated announcements manager holds (REQ-ADM-034).
	 */
	public const ANNOUNCEMENTS = 'announcements';

	/**
	 * The thirteen tabs, in the boards' order: General, Announcements,
	 * Templates, Roles and rights, Sharing, Organisation menu, Kiosk, Versions
	 * and analytics, Controls, Demo data, Group dashboards, Detail fields,
	 * Profile fields.
	 */
	public const TABS = [
		'general',
		'announcements',
		'templates',
		'roles-permissions',
		'sharing',
		'org-navigation',
		'kiosk',
		'versioning-audit',
		'operations',
		'demo-data',
		'group-dashboards',
		'detail-fields',
		'profile-fields',
	];

	/**
	 * Former tabs that are a section of a drawn tab, and the tab holding each.
	 */
	public const FOLDED_TABS = [
		'embeds'           => 'general',
		'search-shortcuts' => 'general',
		'communities'      => 'group-dashboards',
		'app-catalogue'    => 'operations',
	];

	/**
	 * The fragment an address naming a tab opens: the tab itself when it
	 * exists, a former tab as it is (the page opens its host tab at that
	 * section), anything else General.
	 *
	 * @param string $tab The tab an address names.
	 *
	 * @return string A tab id or a key of FOLDED_TABS.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-old-admin-route-redirects-req-adm-033
	 */
	public static function fragment(string $tab): string {
		if (in_array(needle: $tab, haystack: self::TABS, strict: true) === true) {
			return $tab;
		}

		if (array_key_exists(key: $tab, array: self::FOLDED_TABS) === true) {
			return $tab;
		}

		return self::DEFAULT_TAB;
	}//end fragment()
}//end class
