/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared icon catalogue for CnIconBrowser across LaunchPad's icon-input
 * surfaces (tiles, org-nav, dashboards). LaunchPad already depends on `@mdi/js`
 * and stores icons as SVG path strings, so we use the path-based `mdiCatalogue`
 * adapter — the emitted/stored value is a self-contained path that
 * CnDashboardIcon / CnTileWidget render anywhere. Built once and frozen so it
 * isn't made reactive when referenced from components.
 */

import { mdiCatalogue } from '@conduction/nextcloud-vue'
import * as mdi from '@mdi/js'

export const ICON_CATALOGUE = Object.freeze(mdiCatalogue(mdi))

// A bare icon-name token: a letter followed by letters/digits and word
// separators only. SVG path strings (commas, dots — see CnDashboardIcon's
// `isSvgPath`) and custom URLs (`/…`, `http…`) deliberately fail this and are
// left untouched.
const BARE_ICON_NAME = /^[A-Za-z][A-Za-z0-9 _-]*$/

/**
 * Rescue a legacy free-text icon name into a renderable value.
 *
 * The org-nav editor was once a free-text `<input>`, so existing nav trees
 * hold arbitrary MDI-style names (`star`, `folder`, `chart-bar`) rather than
 * the SVG paths the icon picker now emits. CnDashboardIcon can't resolve those
 * lowercase names against its PascalCase registry, so it silently falls back to
 * the default icon. Map such names to their `@mdi/js` path so the admin's
 * original choice still renders; anything already renderable (an SVG path, a
 * URL, or a name with no `@mdi/js` match) is returned unchanged.
 *
 * This is what lets the single `icon` column hold legacy names, SVG paths and
 * custom URLs side by side and still render, with no data migration
 * (REQ-ICON-009 "Mixed values across rows render without migration").
 *
 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
 * @param {string|null|undefined} value stored `icon` field value.
 * @return {string|null|undefined} an SVG path when a legacy name resolves,
 *   otherwise the input untouched.
 */
export function normaliseIconValue(value) {
	if (typeof value !== 'string' || !BARE_ICON_NAME.test(value)) {
		return value
	}
	const pascal = value
		.split(/[\s_-]+/)
		.filter(Boolean)
		.map((word) => word.charAt(0).toUpperCase() + word.slice(1))
		.join('')
	const path = mdi['mdi' + pascal]
	return typeof path === 'string' ? path : value
}
