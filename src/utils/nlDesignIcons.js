/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { NL_DESIGN_ICON_GROUPS } from '@conduction/nextcloud-vue'

/**
 * The NL Design icon pack offered on the tile picker's Custom tab and in the
 * widget style editor.
 *
 * The pack is bundled INTO the shared library as self-contained `data:` URIs
 * across three CC0/EUPL sets (RVO, Gemeente/OpenGemeenten, Den Haag), grouped by
 * `NL_DESIGN_ICON_GROUPS`. It works with NO external `nldesign` app installed —
 * unlike the old approach that resolved `/…/nldesign/img/icons/*.svg` URLs and
 * 404'd whenever that (proprietary Amsterdam) app was disabled.
 *
 * The `?? []` guard keeps LaunchPad safe against an older `@conduction/nextcloud-vue`
 * that predates these exports (the pack is then simply absent rather than throwing).
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
