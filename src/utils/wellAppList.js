/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A well's app tiles from the viewer's own app menu (layout-widgets-replace-
 * multi-tile, REQ-CONT-008, REQ-CONT-009). The app menu is Nextcloud's boot
 * data (`core`/`apps`, the entries of the top bar's app menu), so reading it
 * adds no request, and an app the viewer may not open is never in it.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
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
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function viewerAppMenu() {
	let raw
	try {
		raw = loadState('core', 'apps', null)
	} catch {
		return null
	}
	const list = Array.isArray(raw) ? raw : (raw && typeof raw === 'object' ? Object.values(raw) : null)
	if (list === null) {
		return null
	}
	return list
		.filter((app) => app && typeof app.id === 'string' && app.id !== '')
		.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
		.map((app) => ({
			id: app.id,
			label: String(app.name ?? app.id),
			href: String(app.href ?? ''),
			icon: String(app.icon ?? ''),
		}))
}

/**
 * The app a child placement opens when it is an App or link tile in app mode
 * (or a retired app tile); '' for every other child.
 *
 * @param {object} child The child placement.
 * @return {string}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-2
 */
export function childAppId(child) {
	const type = child?.type ?? child?.widgetId
	if (type === RETIRED_APP_TILE || (type === 'tile' && tileMode(child?.content) === 'app')) {
		return String(child?.content?.appId ?? '')
	}
	return ''
}

/**
 * The children the viewer may see: an app tile for an app outside the
 * viewer's app menu is left out (REQ-CONT-008). Without an app menu nothing
 * is left out.
 *
 * @param {Array<object>} children The well's children.
 * @param {Array<{id: string}>|null} menu The viewer's app menu.
 * @return {Array<object>}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-2
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
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function normaliseAppList(appList) {
	if (!appList || typeof appList !== 'object' || Array.isArray(appList)) {
		return null
	}
	const ids = (value) => (Array.isArray(value) ? value.map(String).filter((id) => id !== '') : [])
	const limit = Number(appList.mostUsedLimit)
	return {
		pinned: ids(appList.pinned),
		exclude: ids(appList.exclude),
		mostUsedLimit: Number.isFinite(limit) && limit > 0 ? Math.floor(limit) : 0,
		showFilter: appList.showFilter === true,
	}
}

/**
 * The use key of a dynamic tile: one per app per well, so its clicks count
 * like a placed tile's (REQ-TSO-002).
 *
 * @param {string|number} owner The well's own key.
 * @param {string} appId The app.
 * @return {string}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
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
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function listedApps(menu, list, options = {}) {
	const { use = {}, owner = '', query = '' } = options
	const left = new Set([...list.exclude, SELF])
	const apps = (Array.isArray(menu) ? menu : []).filter((app) => !left.has(app.id))
	const pinned = list.pinned
		.map((id) => apps.find((app) => app.id === id))
		.filter(Boolean)
		.map((app) => ({ ...app, pinned: true }))
	let others = apps.filter((app) => !list.pinned.includes(app.id)).map((app) => ({ ...app, pinned: false }))

	const text = query.trim().toLocaleLowerCase()
	if (text !== '') {
		return [...pinned, ...others].filter((app) => app.label.toLocaleLowerCase().includes(text))
	}
	if (list.mostUsedLimit > 0) {
		const count = (app) => Number(use?.[dynamicTileKey(owner, app.id)]?.count) || 0
		others = others
			.map((app, index) => ({ app, index }))
			.sort((a, b) => count(b.app) - count(a.app) || a.index - b.index)
			.slice(0, list.mostUsedLimit)
			.map(({ app }) => app)
	}
	return [...pinned, ...others]
}

/**
 * The listed apps as child placements: App or link tiles in app mode, one
 * cell wide and two high, keyed per app.
 *
 * @param {Array<{id: string, pinned: boolean}>} apps The listed apps.
 * @param {string|number} owner The well's own key.
 * @return {Array<object>}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
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
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
 */
export function childLabel(child, menu) {
	const appId = childAppId(child)
	const app = appId !== '' && Array.isArray(menu) ? menu.find((entry) => entry.id === appId) : null
	return String(app?.label ?? child?.content?.title ?? child?.title ?? childUseKey(child))
}
