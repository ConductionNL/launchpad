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

/** The nesting followed at most (REQ-CONT-006 allows 3). */
const MAX_DEPTH = 4

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
 * @param {number} depth How deep this content sits.
 * @return {Array<{child: object, trail: Array<string>}>}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function allChildren(content, trail = [], depth = 0) {
	if (depth > MAX_DEPTH || content === null || typeof content !== 'object') {
		return []
	}
	const found = []
	for (const { list, title } of childLists(content)) {
		const here = title ? [...trail, title] : trail
		for (const child of list) {
			if (child && typeof child === 'object') {
				found.push({ child, trail: here })
				found.push(...allChildren(child.content, [...here, childLabel(child)], depth + 1))
			}
		}
	}
	return found
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
	return changeChildIn(content, key, (list, at) => {
		const copy = JSON.parse(JSON.stringify(list[at]))
		delete copy.id
		delete copy.uuid
		const bottom = list.reduce((max, child) => Math.max(max, (Number(child?.gridY) || 0) + (Number(child?.gridHeight) || 0)), 0)
		if (copy.gridY !== undefined || copy.gridHeight !== undefined) {
			copy.gridY = bottom
		}
		return [...list.slice(0, at + 1), copy, ...list.slice(at + 1)]
	})
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
	return changeChildIn(content, key, (list, at) => [...list.slice(0, at), ...list.slice(at + 1)])
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
 * @param {string} key The child's key.
 * @param {function(Array<object>, number): Array<object>} change Makes the new list.
 * @param {number} depth How deep this content sits.
 * @return {object|null}
 */
function changeChildIn(content, key, change, depth = 0) {
	if (key === '' || depth > MAX_DEPTH || content === null || typeof content !== 'object') {
		return null
	}
	for (const { list, tab } of childLists(content)) {
		const at = list.findIndex((child) => childUseKey(child) === key)
		if (at !== -1) {
			return withList(content, tab, change(list, at))
		}
		for (let index = 0; index < list.length; index++) {
			const inner = changeChildIn(list[index]?.content, key, change, depth + 1)
			if (inner !== null) {
				const next = list.map((child, i) => (i === index ? { ...child, content: inner } : child))
				return withList(content, tab, next)
			}
		}
	}
	return null
}

/**
 * The content with one child list replaced.
 *
 * @param {object} content The content.
 * @param {number|null} tab The tab the list belongs to, or null for the well's own list.
 * @param {Array<object>} list The new list.
 * @return {object}
 */
function withList(content, tab, list) {
	if (tab === null) {
		return { ...content, placements: list }
	}
	return { ...content, tabs: content.tabs.map((t, i) => (i === tab ? { ...t, placements: list } : t)) }
}
