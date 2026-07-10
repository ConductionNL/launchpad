/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { NL_DESIGN_ICONS } from '@conduction/nextcloud-vue'

/**
 * The NL Design icon pack offered on the tile picker's Custom tab and in the
 * widget style editor.
 *
 * The pack is bundled INTO the shared library as self-contained `data:` URIs
 * (`NL_DESIGN_ICONS`), so it works with no external `nldesign` app installed —
 * unlike the old approach that resolved `/…/nldesign/img/icons/*.svg` URLs and
 * 404'd (spamming the console with broken images) whenever that app was
 * disabled. These builders just reshape the shared set for LaunchPad's two
 * picker call-sites.
 *
 * The `?? []` guard keeps LaunchPad safe against an older `@conduction/nextcloud-vue`
 * that predates this export (the pack is then simply absent rather than throwing).
 */
const ICONS = NL_DESIGN_ICONS ?? []

/**
 * Build the tile-picker icon list ({label,url}) from the bundled pack.
 *
 * @return {Array<{label: string, url: string}>} icon options.
 */
export function nlDesignTileIcons() {
	return ICONS.map((icon) => ({ label: icon.label, url: icon.url }))
}

/**
 * Build the widget-style-editor icon list ({id,label,icon}) from the bundled
 * pack.
 *
 * @return {Array<{id: string, label: string, icon: string}>} icon options.
 */
export function nlDesignStyleIcons() {
	return ICONS.map((icon) => ({
		id: icon.id,
		label: icon.label,
		icon: icon.url,
	}))
}
