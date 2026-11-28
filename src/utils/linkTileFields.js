// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The fields of a link tile in one place (REQ-TILE-PLACEMENT, REQ-APPTILE-004).
 *
 * A link tile made with the App or link tile form is a `tile` widget with its
 * data in `content.{title, icon, iconType, backgroundColor, textColor,
 * linkType, linkValue}`, like an app-mode tile; the legacy flat `tile*`
 * columns stay empty. Tiles stored before (a `tile-<id>` with `tileType`
 * custom) keep their columns and are read from them.
 *
 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
 */

import { tileMode } from './tileMode.js'

/** The tile fields a link tile carries in its content, in form order. */
export const LINK_TILE_FIELDS = Object.freeze(['title', 'icon', 'iconType', 'backgroundColor', 'textColor', 'linkType', 'linkValue'])

/** A link tile's fields when nothing is stored, as the tile route defaults them. */
const FIELD_DEFAULTS = Object.freeze({
	title: '',
	icon: 'icon-link',
	iconType: 'class',
	backgroundColor: '#0082c9',
	textColor: '#ffffff',
	linkType: 'url',
	linkValue: '',
})

/** The flat column each field is stored in on a tile made before. */
const COLUMNS = Object.freeze({
	title: 'tileTitle',
	icon: 'tileIcon',
	iconType: 'tileIconType',
	backgroundColor: 'tileBackgroundColor',
	textColor: 'tileTextColor',
	linkType: 'tileLinkType',
	linkValue: 'tileLinkValue',
})

/**
 * Whether a placement is a link tile that keeps its data in content: a
 * `tile` widget without the legacy `tileType`, not in app mode.
 *
 * @param {object|null} placement The placement.
 * @return {boolean}
 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
 */
export function isContentLinkTile(placement) {
	return placement?.widgetId === 'tile'
		&& !placement?.tileType
		&& tileMode(placement?.content) === 'link'
}

/**
 * A tile's link fields: from its content when it keeps them there, else
 * from its flat columns.
 *
 * @param {object} placement The tile placement.
 * @return {{title: string, icon: string, iconType: string, backgroundColor: string, textColor: string, linkType: string, linkValue: string}}
 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
 */
export function linkTileFields(placement) {
	const fromContent = isContentLinkTile(placement)
	const content = placement?.content || {}
	const fields = {}
	for (const field of LINK_TILE_FIELDS) {
		const value = fromContent ? content[field] : placement?.[COLUMNS[field]]
		fields[field] = fromContent && (value === undefined || value === null) ? FIELD_DEFAULTS[field] : value
	}
	return fields
}

/**
 * The link fields of the tile form's result, as a link tile stores them in
 * its content.
 *
 * @param {object} tileData The tile form's result.
 * @return {object} The content fields; a field the form left empty takes the default.
 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
 */
export function linkTileFieldContent(tileData) {
	const content = {}
	for (const field of LINK_TILE_FIELDS) {
		const value = tileData?.[field]
		content[field] = value === undefined || value === null || value === '' ? FIELD_DEFAULTS[field] : value
	}
	return content
}
