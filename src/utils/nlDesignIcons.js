/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { NL_DESIGN_ICON_GROUPS } from '@conduction/nextcloud-vue'

/**
 * The NL Design icon pack (RVO, Gemeente/OpenGemeenten, Den Haag), bundled into
 * the shared library as self-contained `data:` URIs.
 *
 * @deprecated NOTHING IN THE APP IMPORTS THIS ANY MORE — and it must stay that
 * way. CnIconBrowser now offers the three sets on its own tabs by default and
 * fetches RVO's ~1.9 MB only when its tab is opened. These helpers go through the
 * `NL_DESIGN_ICON_GROUPS` barrel export, which references all three sets eagerly,
 * so importing them anywhere drags the full ~2.3 MB back into LaunchPad's initial
 * bundle — which is exactly what the TileEditor and widget style editor used to
 * do. Kept only for a consumer that deliberately wants the whole pack up front.
 */
const GROUPS = NL_DESIGN_ICON_GROUPS ?? []
const FLAT = GROUPS.flatMap((group) => group.icons)

/**
 * The grouped icon sets for CnIconBrowser's `url-icon-groups` prop — renders a
 * searchable sub-tab per set (RVO / Gemeente / Den Haag).
 *
 * @return {Array<{key: string, label: string, icons: Array<{id: string, label: string, url: string}>}>} the groups.
 */
export function nlDesignIconGroups() {
	return GROUPS
}

/**
 * Flat tile-picker icon list ({label,url}) across all sets. Retained for any
 * call-site that still wants the ungrouped list.
 *
 * @return {Array<{label: string, url: string}>} icon options.
 */
export function nlDesignTileIcons() {
	return FLAT.map((icon) => ({ label: icon.label, url: icon.url }))
}

/**
 * Widget-style-editor icon list ({id,label,icon}) across all sets.
 *
 * @return {Array<{id: string, label: string, icon: string}>} icon options.
 */
export function nlDesignStyleIcons() {
	return FLAT.map((icon) => ({
		id: icon.id,
		label: icon.label,
		icon: icon.url,
	}))
}
