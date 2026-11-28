// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The App or link tile (REQ-APPTILE-001, -004, -006): one widget type,
 * `tile`, with a mode. `mode: 'app'` opens one app of the viewer's own app
 * menu; `mode: 'link'` (or no mode, every tile stored before) opens a web
 * address. A retired `app-tile` met later (an import file, an old template)
 * is read as a `tile` in app mode.
 *
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */

import { imagePath } from '@nextcloud/router'
import { DEFAULT_TEXT_COLOUR } from './tileTextColour.js'

/** The retired widget type an app tile used to be. */
export const RETIRED_APP_TILE = 'app-tile'

/**
 * The mode of a tile: `app` only when it says so, else `link`.
 *
 * @param {{mode?: string}|null} content The tile's content.
 * @return {'app'|'link'} The mode.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 */
export function tileMode(content) {
	return content?.mode === 'app' ? 'app' : 'link'
}

/**
 * The palette key of a tile colour token (`--sc-accent` → `accent`), for the
 * shared app tile, which takes the key.
 *
 * @param {{colourToken?: string, colour?: string}|null} content The tile's content.
 * @return {string} The key, or '' for the app's own colour.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-an-app-tile-shows-one-app-the-viewer-may-open-req-apptile-002
 */
export function appTileColourKey(content) {
	const token = String(content?.colourToken || '')
	if (token.startsWith('--sc-')) {
		return token.slice('--sc-'.length)
	}
	return String(content?.colour || '')
}

/**
 * The content of a new tile in app mode.
 *
 * @param {string} appId The app.
 * @param {object} [appearance] Tile colour, text colour, count and pin from the form.
 * @return {object} The content.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
 */
export function appTileContent(appId, appearance = {}) {
	return {
		mode: 'app',
		appId: String(appId),
		colourToken: appearance.colourToken || '',
		textColour: appearance.textColour || DEFAULT_TEXT_COLOUR,
		showCount: appearance.showCount !== false,
		pinned: appearance.pinned === true,
	}
}

/**
 * The content of an app tile read as an App or link tile: its mode set to
 * app, its palette colour also as a token, everything else kept.
 *
 * @param {object|null} content The `app-tile` content.
 * @return {object} The `tile` content.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */
export function appTileAsTileContent(content) {
	const out = { ...(content || {}), mode: 'app' }
	if (!out.colourToken && typeof out.colour === 'string' && out.colour !== '') {
		out.colourToken = '--sc-' + out.colour
	}
	return out
}

/**
 * A placement or group child as the App or link tile: an `app-tile` becomes a
 * `tile` in app mode with the same id and everything else; any other
 * placement is returned as it is. Works on placements (`widgetId`) and on
 * group children (`type`).
 *
 * @param {object} item The placement or child.
 * @return {object} The same object, or a converted copy.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */
export function asAppOrLinkTile(item) {
	if (!item || typeof item !== 'object') {
		return item
	}
	const key = item.widgetId !== undefined ? 'widgetId' : 'type'
	if (item[key] !== RETIRED_APP_TILE) {
		return item
	}
	return { ...item, [key]: 'tile', content: appTileAsTileContent(item.content) }
}

/**
 * The brands whose tile defaults to the white logo on the brand colour,
 * without payoff, with white text (REQ-DWB-007; lib/Support/SeedTileBrands.php).
 */
const BRANDS = Object.freeze([
	{ name: 'Conduction', hosts: ['conduction.nl'], icon: 'conduction-white.svg', backgroundColor: '#21468B' },
	{ name: 'Sendent', hosts: ['sendent.com', 'sendent.nl'], icon: 'sendent-white.svg', backgroundColor: '#141B62' },
	{ name: 'Nextcloud', hosts: ['nextcloud.com'], icon: 'nextcloud-white.svg', backgroundColor: '#0082C9' },
])

/**
 * The brand defaults for a web address or a title: the white logo, the
 * brand colour and white text, or null for any other address.
 *
 * @param {string} address The web address the tile opens, or its title.
 * @return {{brand: string, icon: string, iconType: string, backgroundColor: string, colourToken: string, textColour: string}|null} The defaults.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 */
export function brandDefaults(address) {
	const text = String(address || '').trim().toLowerCase()
	if (text === '') {
		return null
	}
	let host = ''
	try {
		host = new URL(/^[a-z][a-z0-9+.-]*:\/\//.test(text) ? text : 'https://' + text).hostname
	} catch {
		host = ''
	}
	const brand = BRANDS.find((entry) => entry.name.toLowerCase() === text
		|| entry.hosts.some((domain) => host === domain || host.endsWith('.' + domain)))
	if (!brand) {
		return null
	}
	return {
		brand: brand.name,
		icon: imagePath('launchpad', brand.icon),
		iconType: 'url',
		backgroundColor: brand.backgroundColor,
		colourToken: '',
		textColour: 'white',
	}
}
