/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * View-time ordering and reflow of the tiles in a container
 * (launcher-tile-sorting, REQ-TSO-001..004). Pure functions: the caller
 * supplies the viewer's own use map and the page-load random ranks, and
 * gets back copies with new inner-grid positions. Nothing is written back.
 */

export const SORT_MODES = Object.freeze([
	'manual',
	'alphabetical',
	'most-used',
	'last-used',
	'random',
])

/**
 * The display title of a child placement, for the alphabetical order.
 *
 * @param {object} child The child placement.
 * @return {string} Its title, or ''.
 * @spec openspec/specs/container-widget/spec.md
 */
export function childTitle(child) {
	const content = child?.content || {}
	const candidates = [
		content.title,
		content.label,
		content.text,
		child?.tileTitle,
		child?.customTitle,
		child?.title,
	]
	const found = candidates.find(
		(value) => typeof value === 'string' && value.trim() !== '',
	)
	return found ? found.trim() : ''
}

/**
 * Order the children for a sort mode. Most and last used fall back to
 * alphabetical for equal values, so unused tiles sort after used ones.
 *
 * @param {Array<object>} children Child placements in stored order.
 * @param {string} sortBy One of SORT_MODES.
 * @param {object} options Sort inputs.
 * @param {object} options.use The viewer's use per key.
 * @param {function(object): string} options.keyOf The key of a child.
 * @param {function(string): number} options.rankOf The page-load random rank of a key.
 * @param {Intl.Collator} options.collator Locale collator for titles.
 * @return {Array<object>} A new, ordered array.
 * @spec openspec/specs/container-widget/spec.md
 */
export function orderChildren(
	children,
	sortBy,
	{ use = {}, keyOf, rankOf, collator },
) {
	const list = [...children]
	const byTitle = (a, b) => collator.compare(childTitle(a), childTitle(b))
	const stat = (child, field) => Number(use[keyOf(child)]?.[field]) || 0

	switch (sortBy) {
		case 'alphabetical':
			return list.sort(byTitle)
		case 'most-used':
			return list.sort(
				(a, b) => stat(b, 'count') - stat(a, 'count') || byTitle(a, b),
			)
		case 'last-used':
			return list.sort(
				(a, b) =>
					stat(b, 'lastUsedAt') - stat(a, 'lastUsedAt') || byTitle(a, b),
			)
		case 'random':
			return list.sort((a, b) => rankOf(keyOf(a)) - rankOf(keyOf(b)))
		default:
			return list
	}
}

/**
 * Reflow ordered children row by row into a grid of `columns`, keeping
 * each child's width and height. A row is as tall as its tallest child.
 *
 * @param {Array<object>} ordered Ordered child placements.
 * @param {number} columns Inner-grid column count.
 * @return {Array<object>} Copies with new `gridX` and `gridY`.
 * @spec openspec/specs/container-widget/spec.md
 */
export function reflowChildren(ordered, columns) {
	let x = 0
	let y = 0
	let rowHeight = 0
	return ordered.map((child) => {
		const width = Math.min(Math.max(Number(child.gridWidth) || 2, 1), columns)
		const height = Math.max(Number(child.gridHeight) || 2, 1)
		if (x + width > columns) {
			x = 0
			y += rowHeight
			rowHeight = 0
		}
		const placed = {
			...child,
			gridX: x,
			gridY: y,
			gridWidth: width,
			gridHeight: height,
		}
		x += width
		rowHeight = Math.max(rowHeight, height)
		return placed
	})
}
