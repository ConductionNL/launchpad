/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Mounts the top bar's results list (header-search-dropdown, REQ-SRCH-013)
 * as its own small app, beside the page's app: the field it serves lives in
 * Nextcloud's header, outside `#workspace-vue`.
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

import { createApp } from 'vue'
import HeaderSearchDropdown from '../components/HeaderSearchDropdown.vue'

/** The id of the host element. */
export const HEADER_SEARCH_HOST_ID = 'launchpad-header-search'

/**
 * Mount the list once per page.
 *
 * @param {Array<object>} shortcuts The admin's search shortcuts (REQ-SPX-002).
 * @return {object|null} The app, or null when it was already mounted.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function mountHeaderSearch(shortcuts) {
	if (document.getElementById(HEADER_SEARCH_HOST_ID)) {
		return null
	}
	const host = document.createElement('div')
	host.id = HEADER_SEARCH_HOST_ID
	document.body.appendChild(host)
	const app = createApp(HeaderSearchDropdown, {
		shortcuts: Array.isArray(shortcuts) ? shortcuts : [],
	})
	app.mount(host)
	return app
}
