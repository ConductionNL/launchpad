/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The children of a well (container) or a tabs widget, by key (REQ-WDG-050):
 * a child's key is its `id`, else its `uuid` (the server keys every child as
 * it stores the content, `ContainerChildKeys`). An embed token names a child
 * as `<placement id>.<child key>`; Duplicate and Remove in a child's edit
 * menu change the content of the widget on the dashboard that holds it.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */

import { childUseKey } from './tileSort.js'

/** A key an embed subject may carry (the server's `splitSubject`). */
const EMBEDDABLE_KEY = /^[A-Za-z0-9_-]{1,64}$/

/**
 * The embed subject id of a child, or '' when it cannot be named yet.
 *
 * @param {number|string|null} ownerId The id of the widget on the dashboard that holds the child.
 * @param {object|null} child The child.
 * @return {string}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function childSubjectId(ownerId, child) {
	const key = childUseKey(child)
	if (!/^[1-9][0-9]*$/.test(String(ownerId ?? '')) || !EMBEDDABLE_KEY.test(key)) {
		return ''
	}
	return `${ownerId}.${key}`
}

/**
 * Every child of a content at any depth, each with the path of titles to it.
 *
 * @param {object|null} content The content of a well or tabs widget.
 * @param {Array<string>} trail The titles above this content.
 * @return {Array<{child: object, trail: Array<string>}>}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function allChildren(content, trail = []) {
	if (!isContent(content)) {
		return []
	}
	return childLists(content).flatMap(({ list, title }) => listChildren(list, title ? [...trail, title] : trail))
}

/**
 * Every child of one list and of the content of each, with their trails.
 *
 * @param {Array<object>} list The children.
 * @param {Array<string>} trail The titles above the list.
 * @return {Array<{child: object, trail: Array<string>}>}
 */
function listChildren(list, trail) {
	return list
		.filter((child) => child && typeof child === 'object')
		.flatMap((child) => [
			{ child, trail },
			...allChildren(child.content, [...trail, childLabel(child)]),
		])
}

/**
 * A short name for a child: its title, else its type.
 *
 * @param {object} child The child.
 * @return {string}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function childLabel(child) {
	const candidates = [child?.customTitle, child?.content?.title, child?.content?.label, child?.tileTitle, child?.title]
	const title = candidates.find((value) => typeof value === 'string' && value.trim() !== '')
	return title ? title.trim() : String(child?.type ?? '')
}

/**
 * The content with the child of this key copied: the copy follows the
 * original in the same list, without a key (the server gives it one), below
 * the other children on the inner grid. Null when there is no such child.
 *
 * @param {object} content The content of the widget on the dashboard.
 * @param {string} key The child's key.
 * @return {object|null}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function duplicateChildIn(content, key) {
	return changeChildIn(content, { key, change: (list, at) => {
		const copy = JSON.parse(JSON.stringify(list[at]))
		delete copy.id
		delete copy.uuid
		const bottom = list.reduce((max, child) => Math.max(max, (Number(child?.gridY) || 0) + (Number(child?.gridHeight) || 0)), 0)
		if (copy.gridY !== undefined || copy.gridHeight !== undefined) {
			copy.gridY = bottom
		}
		return [...list.slice(0, at + 1), copy, ...list.slice(at + 1)]
	} })
}

/**
 * The content without the child of this key; null when there is no such child.
 *
 * @param {object} content The content of the widget on the dashboard.
 * @param {string} key The child's key.
 * @return {object|null}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function removeChildIn(content, key) {
	return changeChildIn(content, { key, change: (list, at) => [...list.slice(0, at), ...list.slice(at + 1)] })
}

/**
 * The child lists of a content: a well's placements and every tab's.
 *
 * @param {object} content The content.
 * @return {Array<{list: Array<object>, title: string, tab: (number|null)}>}
 */
function childLists(content) {
	const lists = []
	if (Array.isArray(content?.placements)) {
		lists.push({ list: content.placements, title: '', tab: null })
	}
	if (Array.isArray(content?.tabs)) {
		content.tabs.forEach((tab, index) => {
			if (tab && Array.isArray(tab.placements)) {
				lists.push({ list: tab.placements, title: String(tab.title ?? ''), tab: index })
			}
		})
	}
	return lists
}

/**
 * The content with the list holding the child of this key changed, at any
 * depth; nothing else is touched. Null when no child has the key.
 *
 * @param {object} content The content.
 * @param {{key: string, change: function(Array<object>, number): Array<object>}} edit The child's key and how its list changes.
 * @return {object|null}
 */
function changeChildIn(content, edit) {
	if (!isContent(content) || edit.key === '') {
		return null
	}
	for (const entry of childLists(content)) {
		const list = changeList(entry.list, edit)
		if (list !== null) {
			return withList(content, { tab: entry.tab, list })
		}
	}
	return null
}

/**
 * One list changed: the child of the key here, or inside one of the children.
 *
 * @param {Array<object>} list The children.
 * @param {{key: string, change: function(Array<object>, number): Array<object>}} edit The child's key and how its list changes.
 * @return {Array<object>|null}
 */
function changeList(list, edit) {
	const at = list.findIndex((child) => childUseKey(child) === edit.key)
	if (at !== -1) {
		return edit.change(list, at)
	}
	for (let index = 0; index < list.length; index++) {
		const inner = changeChildIn(list[index]?.content, edit)
		if (inner !== null) {
			return list.map((child, i) => (i === index ? { ...child, content: inner } : child))
		}
	}
	return null
}

/**
 * The content with one child list replaced.
 *
 * @param {object} content The content.
 * @param {{tab: (number|null), list: Array<object>}} entry The tab the list belongs to (null: the well's own list) and the new list.
 * @return {object}
 */
function withList(content, entry) {
	if (entry.tab === null) {
		return { ...content, placements: entry.list }
	}
	return { ...content, tabs: content.tabs.map((t, i) => (i === entry.tab ? { ...t, placements: entry.list } : t)) }
}

/**
 * Whether this is a content object (a well's or a tab's holder).
 *
 * @param {object|null|undefined} content The value.
 * @return {boolean}
 */
function isContent(content) {
	return content !== null && typeof content === 'object'
}
