/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A well's app tiles from the viewer's own app menu (layout-widgets-replace-
 * multi-tile, REQ-CONT-008, REQ-CONT-009). The app menu is Nextcloud's boot
 * data (`core`/`apps`, the entries of the top bar's app menu), so reading it
 * adds no request, and an app the viewer may not open is never in it.
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */

import { loadState } from '@nextcloud/initial-state'
import { appTileContent, RETIRED_APP_TILE, tileMode } from './tileMode.js'
import { childUseKey } from './tileSort.js'

/** Launchpad itself is never a tile in its own well. */
const SELF = 'launchpad'

/**
 * The viewer's app menu, in menu order: `{id, label, href, icon}` per app.
 * Null when the page carries no app menu, so callers leave tiles alone.
 *
 * @return {Array<{id: string, label: string, href: string, icon: string}>|null}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function viewerAppMenu() {
	const list = bootAppEntries()
	if (list === null) {
		return null
	}
	return list
		.filter((app) => typeof app?.id === 'string' && app.id !== '')
		.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
		.map(menuEntry)
}

/**
 * The raw entries of the boot app menu, or null when the page has none.
 *
 * @return {Array<object>|null}
 */
function bootAppEntries() {
	let raw
	try {
		raw = loadState('core', 'apps', null)
	} catch {
		return null
	}
	if (Array.isArray(raw)) {
		return raw
	}
	return raw !== null && typeof raw === 'object' ? Object.values(raw) : null
}

/**
 * One app of the menu in the shape the well uses.
 *
 * @param {object} app The boot entry.
 * @return {{id: string, label: string, href: string, icon: string}}
 */
function menuEntry(app) {
	return {
		id: app.id,
		label: String(app.name ?? app.id),
		href: String(app.href ?? ''),
		icon: String(app.icon ?? ''),
	}
}

/**
 * The app a child placement opens when it is an App or link tile in app mode
 * (or a retired app tile); '' for every other child.
 *
 * @param {object} child The child placement.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-2
 */
export function childAppId(child) {
	return isAppTile(child) ? String(child.content?.appId ?? '') : ''
}

/**
 * Whether a child is an App or link tile in app mode, or a retired app tile.
 *
 * @param {object} child The child placement.
 * @return {boolean}
 */
function isAppTile(child) {
	const type = child?.type ?? child?.widgetId
	return type === RETIRED_APP_TILE || (type === 'tile' && tileMode(child.content) === 'app')
}

/**
 * The children the viewer may see: an app tile for an app outside the
 * viewer's app menu is left out (REQ-CONT-008). Without an app menu nothing
 * is left out.
 *
 * @param {Array<object>} children The well's children.
 * @param {Array<{id: string}>|null} menu The viewer's app menu.
 * @return {Array<object>}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-2
 */
export function openableChildren(children, menu) {
	if (!Array.isArray(menu)) {
		return children
	}
	const ids = new Set(menu.map((app) => app.id))
	return children.filter((child) => {
		const appId = childAppId(child)
		return appId === '' || ids.has(appId)
	})
}

/**
 * A stored app list in its read shape; null when the well has none.
 *
 * @param {object|null|undefined} appList The stored `content.appList`.
 * @return {{pinned: string[], exclude: string[], mostUsedLimit: number, showFilter: boolean}|null}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function normaliseAppList(appList) {
	if (appList === null || typeof appList !== 'object' || Array.isArray(appList)) {
		return null
	}
	return {
		pinned: appIds(appList.pinned),
		exclude: appIds(appList.exclude),
		mostUsedLimit: positiveWhole(appList.mostUsedLimit),
		showFilter: appList.showFilter === true,
	}
}

/**
 * A stored list of app ids; anything else reads as none.
 *
 * @param {Array<string>|null|undefined} value The stored list.
 * @return {string[]}
 */
function appIds(value) {
	return Array.isArray(value) ? value.map(String).filter((id) => id !== '') : []
}

/**
 * A stored limit as a whole number above 0, or 0 for no limit.
 *
 * @param {number|string|null|undefined} value The stored limit.
 * @return {number}
 */
function positiveWhole(value) {
	const number = Number(value)
	return Number.isFinite(number) && number > 0 ? Math.floor(number) : 0
}

/**
 * The use key of a dynamic tile: one per app per well, so its clicks count
 * like a placed tile's (REQ-TSO-002).
 *
 * @param {string|number} owner The well's own key.
 * @param {string} appId The app.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function dynamicTileKey(owner, appId) {
	return `${owner}-app-${appId}`
}

/**
 * The apps the list draws for this viewer: the pinned ones first in their
 * order, then the others in menu order, without the excluded ones and
 * without Launchpad. With a limit only that many follow the pinned ones, the
 * most used first; a search query looks through every app instead.
 *
 * @param {Array<{id: string, label: string}>} menu The viewer's app menu.
 * @param {object} list The normalised app list.
 * @param {object} options How to list them.
 * @param {object} [options.use] This browser's tile use.
 * @param {string|number} [options.owner] The well's own key.
 * @param {string} [options.query] The search text.
 * @return {Array<{id: string, label: string, pinned: boolean}>}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function listedApps(menu, list, options = {}) {
	const { pinned, others } = splitPinned(menu, list)
	const text = searchText(options.query)
	if (text !== '') {
		return [...pinned, ...others].filter((app) => app.label.toLocaleLowerCase().includes(text))
	}
	return [...pinned, ...mostUsedFirst(others, list.mostUsedLimit, (app) => useCount(options.use, options.owner, app.id))]
}

/**
 * The search text, trimmed and lower case; '' for none.
 *
 * @param {string|undefined} query What the viewer typed.
 * @return {string}
 */
function searchText(query) {
	return typeof query === 'string' ? query.trim().toLocaleLowerCase() : ''
}

/**
 * The menu without the excluded apps and Launchpad, split into the pinned
 * ones (in the order the list pins them) and the others (in menu order).
 *
 * @param {Array<{id: string}>} menu The viewer's app menu.
 * @param {{pinned: string[], exclude: string[]}} list The normalised app list.
 * @return {{pinned: Array<object>, others: Array<object>}}
 */
function splitPinned(menu, list) {
	const left = new Set([...list.exclude, SELF])
	const apps = (Array.isArray(menu) ? menu : []).filter((app) => !left.has(app.id))
	return {
		pinned: list.pinned
			.map((id) => apps.find((app) => app.id === id))
			.filter(Boolean)
			.map((app) => ({ ...app, pinned: true })),
		others: apps.filter((app) => !list.pinned.includes(app.id)).map((app) => ({ ...app, pinned: false })),
	}
}

/**
 * At most `limit` apps, the most used first (ties keep menu order); all of
 * them in menu order without a limit.
 *
 * @param {Array<object>} apps The apps in menu order.
 * @param {number} limit The limit, 0 for none.
 * @param {function(object): number} count The use count of an app.
 * @return {Array<object>}
 */
function mostUsedFirst(apps, limit, count) {
	if (limit <= 0) {
		return apps
	}
	return apps
		.map((app, index) => ({ app, index, uses: count(app) }))
		.sort((a, b) => b.uses - a.uses || a.index - b.index)
		.slice(0, limit)
		.map(({ app }) => app)
}

/**
 * How often this browser opened an app from this well.
 *
 * @param {object} use This browser's tile use.
 * @param {string|number} owner The well's own key.
 * @param {string} appId The app.
 * @return {number}
 */
function useCount(use, owner, appId) {
	return Number(use?.[dynamicTileKey(owner, appId)]?.count) || 0
}

/**
 * The listed apps as child placements: App or link tiles in app mode, one
 * cell wide and two high, keyed per app.
 *
 * @param {Array<{id: string, pinned: boolean}>} apps The listed apps.
 * @param {string|number} owner The well's own key.
 * @return {Array<object>}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function appTilePlacements(apps, owner) {
	return apps.map((app) => ({
		uuid: dynamicTileKey(owner, app.id),
		type: 'tile',
		gridWidth: 1,
		gridHeight: 2,
		dynamic: true,
		content: { ...appTileContent(app.id, { pinned: app.pinned }), title: app.label },
	}))
}

/**
 * A child's name for the search field: an app tile's app, else its title.
 *
 * @param {object} child The child placement.
 * @param {Array<{id: string, label: string}>|null} menu The viewer's app menu.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function childLabel(child, menu) {
	const appId = childAppId(child)
	const app = appId !== '' && Array.isArray(menu) ? menu.find((entry) => entry.id === appId) : null
	return app ? app.label : placedTitle(child)
}

/**
 * The title a placed child shows, or its key.
 *
 * @param {object} child The child placement.
 * @return {string}
 */
function placedTitle(child) {
	return String(child?.content?.title ?? child?.title ?? childUseKey(child))
}
