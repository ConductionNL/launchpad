// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The choices of the Add tiles dialog (REQ-TILE-043): what each section
 * offers as a tile, which tiles a dashboard already holds, and the chosen
 * tiles without duplicates.
 *
 * A choice is `{key, kind, tile}`: `kind` is `tile` for an ordinary tile
 * (`{title, icon, iconType, linkType, linkValue}`) or `app-tile` for a
 * discovered app (`{appId}`). The key is what makes two choices the same
 * tile: the link for a tile, the app for an app tile.
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */

import { tileFromCatalogueApp } from './discoveredTile.js'

/**
 * The key of an ordinary tile: its link type and value; an app tile and an
 * app-link tile to the same app share it.
 *
 * @param {{linkType?: string, linkValue?: string}} tile The tile.
 * @return {string} the key.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function tileKey(tile) {
	const type = String(tile?.linkType || 'url')
	let value = String(tile?.linkValue || '')
	if (type === 'app') {
		value = value.replace(/^\/apps\//, '').replace(/\/$/, '')
	}
	return `${type}:${value}`
}

/**
 * The keys of the tiles a dashboard already holds.
 *
 * @param {Array<object>} placements The dashboard's placements.
 * @return {Set<string>} the keys.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function existingTileKeys(placements) {
	const keys = new Set()
	for (const placement of placements || []) {
		if (placement?.widgetId === 'app-tile' && placement.content?.appId) {
			keys.add(tileKey({ linkType: 'app', linkValue: placement.content.appId }))
		} else if (placement?.tileLinkType) {
			keys.add(tileKey({ linkType: placement.tileLinkType, linkValue: placement.tileLinkValue }))
		}
	}
	return keys
}

/**
 * A catalogue entry as a choice.
 *
 * @param {object} app The catalogue entry.
 * @return {{key: string, kind: string, tile: object}} the choice.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function catalogueChoice(app) {
	const tile = tileFromCatalogueApp(app)
	return { key: tileKey(tile), kind: 'tile', tile }
}

/**
 * A discovered app as a choice: its own app tile.
 *
 * @param {{appId: string}} app The discovered app.
 * @return {{key: string, kind: string, tile: object}} the choice.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */
export function discoveredChoice(app) {
	return {
		key: tileKey({ linkType: 'app', linkValue: app.appId }),
		kind: 'app-tile',
		tile: { appId: String(app.appId) },
	}
}

/**
 * An office pack tile as a choice: one tile of its own.
 *
 * @param {{title: string, linkType: string, linkValue: string, icon?: string, iconType?: string}} pack The pack tile.
 * @return {{key: string, kind: string, tile: object}} the choice.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function officeChoice(pack) {
	const tile = {
		title: String(pack.title || ''),
		icon: pack.icon || '',
		iconType: pack.iconType === 'url' ? 'url' : 'class',
		linkType: pack.linkType,
		linkValue: String(pack.linkValue || ''),
	}
	return { key: tileKey(tile), kind: 'tile', tile }
}

/**
 * The chosen tiles to add: one per key, leaving out what the dashboard holds.
 *
 * @param {Array<{key: string}>} choices The chosen choices, in order.
 * @param {Set<string>} existing The keys on the dashboard.
 * @return {Array<{key: string, kind: string, tile: object}>} the tiles to add.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function tilesToAdd(choices, existing = new Set()) {
	const seen = new Set(existing)
	const out = []
	for (const choice of choices || []) {
		if (!choice?.key || seen.has(choice.key)) {
			continue
		}
		seen.add(choice.key)
		out.push(choice)
	}
	return out
}

/**
 * How a catalogue app signs on: with the person's Nextcloud account, by
 * single sign-on, or on the site itself.
 *
 * @param {{linkType?: string, signOn?: string}} app The catalogue entry.
 * @return {'nextcloud'|'sso'|'own'} the kind.
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export function signOnKind(app) {
	if (app?.signOn === 'sso') {
		return 'sso'
	}
	return app?.linkType === 'app' ? 'nextcloud' : 'own'
}
