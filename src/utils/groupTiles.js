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

/**
 * The keys of a link child that the tile node replaces. A link's icon is an
 * icon name, which a tile does not draw, so it goes too.
 */
const LINK_ONLY_KEYS = Object.freeze(['label', 'url', 'actionType', 'icon', 'iconType'])

/**
 * Whether a container child is a tile the tile form can edit: a `tile` child
 * (not an office "New ..." tile, which has no address of its own) or a `link`
 * child that only opens one address (a bookmark import writes those), which
 * becomes a tile node when it is saved. A link that runs an action or lists
 * several links stays a link.
 *
 * @param {object} child The container child.
 * @return {boolean}
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
		iconType: 'svg',
		linkType: 'url',
		linkValue: content.url,
	})
}

/**
 * The tile the form edits, read from a group child.
 *
 * @param {object} child The container child.
 * @return {object} The tile in the form's shape.
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
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
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function withGroupTileRemoved(containerContent, index) {
	const placements = Array.isArray(containerContent?.placements) ? containerContent.placements : null
	if (placements === null || !isGroupTileChild(placements[index])) {
		return null
	}
	return { ...containerContent, placements: placements.filter((_child, position) => position !== index) }
}

/** The size of a tile a move puts into a group, in the group's grid cells. */
const MOVED_TILE_SIZE = 2

/**
 * The first free row at the bottom of a group's grid.
 *
 * @param {Array<object>} placements The group's children.
 * @return {number}
 */
function bottomRow(placements) {
	return placements.reduce((bottom, child) => {
		if (!isObject(child)) {
			return bottom
		}
		const top = Number(child.gridY) || 0
		const height = Number(child.gridHeight) || MOVED_TILE_SIZE
		return Math.max(bottom, top + height)
	}, 0)
}

/**
 * The container's content with a tile from the dashboard added as a tile
 * node at the bottom of the group. The node keeps every content key the
 * tile carried (launch, availability and office-network settings), takes
 * the form's fields, and the group takes the form's sort order when the
 * form carries one.
 *
 * @param {object} containerContent The container's content.
 * @param {object} tileContent The tile placement's content blob, as the save writes it.
 * @param {object} tileData The tile form's result.
 * @return {object|null} The new content; null when the target is not a group's content.
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
export function withTileMovedIn(containerContent, tileContent, tileData) {
	if (!isObject(containerContent)) {
		return null
	}
	const placements = Array.isArray(containerContent.placements) ? containerContent.placements : []
	const node = groupChildFromTile({ type: 'tile', content: isObject(tileContent) ? tileContent : {} }, tileData)
	node.gridX = 0
	node.gridY = bottomRow(placements)
	node.gridWidth = MOVED_TILE_SIZE
	node.gridHeight = MOVED_TILE_SIZE
	const content = { ...containerContent, placements: [...placements, node] }
	if (typeof tileData?.groupSortBy === 'string' && tileData.groupSortBy !== '') {
		content.sortBy = normaliseSortMode(tileData.groupSortBy)
	}
	return content
}

/**
 * The id of a tab as a group a tile can move into: `<placement id>:<tab id>`.
 *
 * @param {number|string} placementId The Tabs placement.
 * @param {string} tabId The tab.
 * @return {string}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/specs/tabs-widget/spec.md#requirement-each-tab-holds-widgets-req-tabs-002
 */
export function tabGroupId(placementId, tabId) {
	return `${placementId}:${tabId}`
}

/**
 * The Tabs placement and tab a group id names, or null for a well's id.
 *
 * @param {number|string|null} groupId The chosen group.
 * @return {{placementId: number, tabId: string}|null}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/specs/tabs-widget/spec.md#requirement-each-tab-holds-widgets-req-tabs-002
 */
export function parseTabGroupId(groupId) {
	if (typeof groupId !== 'string') {
		return null
	}
	const at = groupId.indexOf(':')
	const placementId = Number(groupId.slice(0, at))
	const tabId = groupId.slice(at + 1)
	return at > 0 && Number.isFinite(placementId) && tabId !== '' ? { placementId, tabId } : null
}

/**
 * Move a dashboard tile into one tab of a Tabs widget (REQ-TABS-002): the
 * tab gains it as a tile node at its bottom, as a well does (withTileMovedIn).
 *
 * @param {object} tabsContent The Tabs placement's content.
 * @param {string} tabId The tab.
 * @param {object} tileContent The tile placement's content blob.
 * @param {object} tileData The tile form's result.
 * @return {object|null} The new content; null when the tab is not there.
 * @spec openspec/changes/layout-widgets-replace-multi-tile/specs/tabs-widget/spec.md#requirement-each-tab-holds-widgets-req-tabs-002
 */
export function withTileMovedIntoTab(tabsContent, tabId, tileContent, tileData) {
	if (!isObject(tabsContent) || !Array.isArray(tabsContent.tabs)) {
		return null
	}
	const at = tabsContent.tabs.findIndex((tab) => isObject(tab) && tab.id === tabId)
	if (at === -1) {
		return null
	}
	const tab = tabsContent.tabs[at]
	const placements = Array.isArray(tab.placements) ? tab.placements : []
	const node = groupChildFromTile({ type: 'tile', content: isObject(tileContent) ? tileContent : {} }, tileData)
	node.gridX = 0
	node.gridY = bottomRow(placements)
	node.gridWidth = MOVED_TILE_SIZE
	node.gridHeight = MOVED_TILE_SIZE
	const tabs = [...tabsContent.tabs]
	tabs[at] = { ...tab, placements: [...placements, node] }
	return { ...tabsContent, tabs }
}

/**
 * The groups on a dashboard a tile can move into: each `container`
 * placement with its title and stored sort order, and each tab of a `tabs`
 * placement as "<Tabs title> > <tab title>" (REQ-TABS-002).
 *
 * @param {Array<object>} placements The dashboard's placements.
 * @return {Array<{id: number|string, title: string, sortBy: string}>}
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 * @spec openspec/changes/layout-widgets-replace-multi-tile/specs/tabs-widget/spec.md#requirement-each-tab-holds-widgets-req-tabs-002
 */
export function dashboardGroups(placements) {
	if (!Array.isArray(placements)) {
		return []
	}
	return placements.filter(hasPlacementId).flatMap(groupsOfPlacement)
}

/**
 * Whether a placement is an object with an id.
 *
 * @param {object|null|undefined} placement A dashboard placement.
 * @return {boolean}
 */
function hasPlacementId(placement) {
	return isObject(placement) && placement.id !== undefined && placement.id !== null
}

/**
 * A text field of an object, or '' when it is not a string.
 *
 * @param {object} source The object.
 * @param {string} field The field name.
 * @return {string}
 */
function textOf(source, field) {
	return typeof source[field] === 'string' ? source[field] : ''
}

/**
 * The groups one placement offers: itself for a `container`, one per tab
 * for a `tabs` placement, none otherwise.
 *
 * @param {object} placement A dashboard placement with an id.
 * @return {Array<{id: number|string, title: string, sortBy: string}>}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/specs/tabs-widget/spec.md#requirement-each-tab-holds-widgets-req-tabs-002
 */
function groupsOfPlacement(placement) {
	const content = contentOf(placement)
	const title = textOf(content, 'title')
	if (placement.widgetId === 'container') {
		return [{ id: placement.id, title, sortBy: textOf(content, 'sortBy') }]
	}
	if (placement.widgetId !== 'tabs' || !Array.isArray(content.tabs)) {
		return []
	}
	return content.tabs
		.filter((tab) => isObject(tab) && typeof tab.id === 'string' && tab.id !== '')
		.map((tab) => ({
			id: tabGroupId(placement.id, tab.id),
			title: [title, textOf(tab, 'title')].filter((part) => part !== '').join(' > '),
			sortBy: '',
		}))
}
