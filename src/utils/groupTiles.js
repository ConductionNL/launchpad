/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tiles inside a group (a `container` widget) and the tile form
 * (tile-form-board-sync REQ-TILE-041).
 *
 * A tile in a group is a child of the container's `content.placements`, not a
 * placement of its own. These helpers turn such a child into the shape the
 * tile form edits, and write the form's result back into the container's
 * content, together with the group's sort order (`content.sortBy`). Nothing
 * the form does not edit is dropped: a child keeps every other content key,
 * including launch, availability and office-network settings it carries.
 */

import { tileBackground } from './tileColour.js'
import { normaliseSortMode } from './tileSort.js'

/** The tile fields the form edits and a group tile stores in its content. */
const FORM_KEYS = Object.freeze([
	'title',
	'icon',
	'iconType',
	'backgroundColor',
	'textColor',
	'linkType',
	'linkValue',
	'colourToken',
])

/** The link actions that only open an address, so a tile can take them over. */
const ADDRESS_ACTIONS = Object.freeze(['', 'external', 'internal'])

/** The keys of a link child that the tile node replaces. */
const LINK_ONLY_KEYS = Object.freeze(['label', 'url', 'actionType'])

/**
 * Whether a container child is a tile the tile form can edit: a `tile` child
 * (not an office "New ..." tile, which has no address of its own) or a `link`
 * child that only opens one address (a bookmark import writes those), which
 * becomes a tile node when it is saved. A link that runs an action or lists
 * several links stays a link.
 *
 * @param {object} child The container child.
 * @return {boolean}
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function isGroupTileChild(child) {
	if (!isObject(child)) {
		return false
	}
	if (child.type === 'link') {
		return isAddressLink(contentOf(child))
	}
	return child.type === 'tile' && contentOf(child).linkType !== 'create'
}

/**
 * Whether a value is a plain object.
 *
 * @param {unknown} value The value.
 * @return {boolean}
 */
function isObject(value) {
	return value !== null && typeof value === 'object'
}

/**
 * A child's content, or an empty object.
 *
 * @param {object|null|undefined} child The container child.
 * @return {object}
 */
function contentOf(child) {
	return isObject(child) && isObject(child.content) ? child.content : {}
}

/**
 * Whether a value is set: neither absent, null nor an empty string.
 *
 * @param {unknown} value The value.
 * @return {boolean}
 */
function isSet(value) {
	return value !== undefined && value !== null && value !== ''
}

/**
 * Whether a link's content only opens one address.
 *
 * @param {object} content The link child's content.
 * @return {boolean}
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
function isAddressLink(content) {
	const url = typeof content.url === 'string' ? content.url.trim() : ''
	return url !== '' && !Array.isArray(content.links) && ADDRESS_ACTIONS.includes(String(content.actionType ?? ''))
}

/**
 * The form fields of a content blob that are set.
 *
 * @param {object} source The content or form result.
 * @return {object}
 */
function setFormFields(source) {
	return Object.fromEntries(FORM_KEYS.filter((key) => isSet(source[key])).map((key) => [key, source[key]]))
}

/**
 * The tile the form edits, read from a link child.
 *
 * @param {object} content The link child's content.
 * @return {object}
 */
function linkToTile(content) {
	return setFormFields({
		title: content.label,
		icon: content.icon,
		iconType: isSet(content.iconType) ? content.iconType : 'svg',
		linkType: 'url',
		linkValue: content.url,
	})
}

/**
 * The tile the form edits, read from a group child.
 *
 * @param {object} child The container child.
 * @return {object} The tile in the form's shape.
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function groupChildToTile(child) {
	const content = contentOf(child)
	return child?.type === 'link' ? linkToTile(content) : setFormFields(content)
}

/**
 * A group child carrying the form's result. The child becomes (or stays) a
 * `tile` node; its other content keys stay as they were.
 *
 * @param {object} child The container child.
 * @param {object} tileData The tile form's result.
 * @return {object} The new child.
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function groupChildFromTile(child, tileData) {
	const dropped = child?.type === 'link' ? [...FORM_KEYS, ...LINK_ONLY_KEYS] : FORM_KEYS
	const kept = Object.fromEntries(Object.entries(contentOf(child)).filter(([key]) => !dropped.includes(key)))
	const content = { ...kept, ...setFormFields(isObject(tileData) ? tileData : {}) }
	// The group's tile renderer paints `backgroundColor`, so a theme colour
	// is written there as its CSS variable as well.
	if (content.colourToken) {
		content.backgroundColor = tileBackground(content, content)
	}
	return { ...child, type: 'tile', content }
}

/**
 * The container's content with one tile saved from the form, and with the
 * group's sort order when the form carries one.
 *
 * @param {object} containerContent The container's content.
 * @param {number} index The child's position in `content.placements`.
 * @param {object} tileData The tile form's result.
 * @return {object|null} The new content; null when the child is not there.
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function withGroupTileSaved(containerContent, index, tileData) {
	const placements = Array.isArray(containerContent?.placements) ? containerContent.placements : null
	if (placements === null || !isGroupTileChild(placements[index])) {
		return null
	}
	const next = placements.slice()
	next[index] = groupChildFromTile(placements[index], tileData)
	const content = { ...containerContent, placements: next }
	if (typeof tileData?.groupSortBy === 'string' && tileData.groupSortBy !== '') {
		content.sortBy = normaliseSortMode(tileData.groupSortBy)
	}
	return content
}

/**
 * The container's content without one tile.
 *
 * @param {object} containerContent The container's content.
 * @param {number} index The child's position in `content.placements`.
 * @return {object|null} The new content; null when the child is not there.
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function withGroupTileRemoved(containerContent, index) {
	const placements = Array.isArray(containerContent?.placements) ? containerContent.placements : null
	if (placements === null || !isGroupTileChild(placements[index])) {
		return null
	}
	return { ...containerContent, placements: placements.filter((_child, position) => position !== index) }
}
