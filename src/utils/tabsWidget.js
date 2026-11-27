/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tabs of a Tabs widget (layout-widgets-replace-multi-tile D1):
 * `content.tabs` is a list of `{id, title, placements}`. These helpers read a
 * stored list safely and make the edits the widget's form offers, always
 * returning a new list.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */

let tabCounter = 0

/**
 * A stored tabs list as `{id, title, placements}` entries; anything that is
 * not a tab is left out, and a tab without an id gets one from its position.
 *
 * @param {Array<object>|null|undefined} tabs The stored `content.tabs`.
 * @return {Array<{id: string, title: string, placements: Array<object>}>}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export function normaliseTabs(tabs) {
	if (!Array.isArray(tabs)) {
		return []
	}
	return tabs
		.filter((tab) => tab !== null && typeof tab === 'object')
		.map((tab, index) => ({
			...tab,
			id: typeof tab.id === 'string' && tab.id !== '' ? tab.id : `tab-${index + 1}`,
			title: typeof tab.title === 'string' ? tab.title : '',
			placements: Array.isArray(tab.placements) ? tab.placements : [],
		}))
}

/**
 * A new, empty tab with an id unique in this list.
 *
 * @param {Array<object>} tabs The current tabs.
 * @param {string} title The new tab's title.
 * @return {{id: string, title: string, placements: Array}}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export function newTab(tabs, title) {
	const taken = new Set(tabs.map((tab) => tab.id))
	let id
	do {
		tabCounter += 1
		id = `tab-${Date.now().toString(36)}-${tabCounter}`
	} while (taken.has(id))
	return { id, title, placements: [] }
}

/**
 * The list with one tab moved one place up (-1) or down (+1); unchanged at an end.
 *
 * @param {Array<object>} tabs The tabs.
 * @param {number} index The tab to move.
 * @param {number} step -1 or +1.
 * @return {Array<object>}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export function moveTab(tabs, index, step) {
	const target = index + step
	if (index < 0 || index >= tabs.length || target < 0 || target >= tabs.length) {
		return [...tabs]
	}
	const moved = [...tabs]
	const [tab] = moved.splice(index, 1)
	moved.splice(target, 0, tab)
	return moved
}

/**
 * Whether removing this tab loses widgets, so the form asks first.
 *
 * @param {object} tab The tab.
 * @return {boolean}
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export function tabHasWidgets(tab) {
	return Array.isArray(tab?.placements) && tab.placements.length > 0
}
