/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * Sorting the tiles in a container (launcher-tile-sorting).
 *
 * The order is computed when the container is viewed and never written back
 * (REQ-TSO-001). Most used and last used read counts kept in this browser
 * only, under `launchpad.tileUse` (REQ-TSO-002); every read and write is
 * guarded, so blocked storage means the stored order, never an error.
 */

/** Storage key of the per-placement usage counts. */
export const TILE_USE_KEY = 'launchpad.tileUse'

/** The sort modes a container offers; `manual` keeps the author's layout. */
export const SORT_MODES = Object.freeze(['manual', 'alphabetical', 'most-used', 'last-used', 'random'])

/** The modes that read the viewer's usage, and offer "Forget my usage". */
export const USAGE_MODES = Object.freeze(['most-used', 'last-used'])

/**
 * Random ranks per child, drawn once per page load (REQ-TSO-004).
 *
 * @type {Map<string, number>}
 */
const loadRanks = new Map()

/**
 * The container's sort mode, `manual` for anything unknown.
 *
 * @param {string|undefined|null} value The stored `content.sortBy`.
 * @return {string}
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function normaliseSortMode(value) {
	return SORT_MODES.includes(value) ? value : 'manual'
}

/**
 * The browser storage, or null when it cannot be reached.
 *
 * @return {Storage|null}
 */
function storage() {
	try {
		return globalThis.localStorage ?? null
	} catch {
		return null
	}
}

/**
 * Every placement's usage in this browser: `{count, lastUsedAt}` by placement id.
 *
 * @return {Record<string, {count: number, lastUsedAt: number}>} Empty when storage is blocked or holds nothing usable.
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function readTileUse() {
	try {
		const raw = storage()?.getItem(TILE_USE_KEY)
		const parsed = raw ? JSON.parse(raw) : {}
		return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
	} catch {
		return {}
	}
}

/**
 * Write the usage map; a blocked storage is ignored.
 *
 * @param {object} use The usage map.
 * @return {void}
 */
function writeTileUse(use) {
	try {
		storage()?.setItem(TILE_USE_KEY, JSON.stringify(use))
	} catch {
		// Blocked or full storage: the order stays as it was.
	}
}

/**
 * Count one use of a placement in this browser. Nothing is sent anywhere.
 *
 * @param {string|number} placementId The placement.
 * @param {number} [now] The time of use in ms.
 * @return {void}
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function recordTileUse(placementId, now = Date.now()) {
	if (placementId === undefined || placementId === null || placementId === '') {
		return
	}
	const use = readTileUse()
	const key = String(placementId)
	const previous = use[key] ?? {}
	use[key] = { count: (Number(previous.count) || 0) + 1, lastUsedAt: now }
	writeTileUse(use)
}

/**
 * Forget this browser's usage of the given placements (REQ-TSO-003).
 *
 * @param {Array<string|number>} placementIds The placements.
 * @return {void}
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function forgetTileUse(placementIds) {
	const use = readTileUse()
	for (const id of placementIds) {
		delete use[String(id)]
	}
	writeTileUse(use)
}

/**
 * The id a child is counted under.
 *
 * @param {object} child The child placement.
 * @return {string}
 */
export function childUseKey(child) {
	if (child?.id !== undefined && child?.id !== null) {
		return String(child.id)
	}
	return typeof child?.uuid === 'string' ? child.uuid : ''
}

/**
 * The title a child is sorted by.
 *
 * @param {object} child The child placement.
 * @return {string}
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function childTitle(child) {
	const candidates = [child?.content?.title, child?.content?.label, child?.tileTitle, child?.title]
	const found = candidates.find((value) => typeof value === 'string' && value.trim() !== '')
	return found ? found.trim() : ''
}

/**
 * The random rank of a child for this page load.
 *
 * @param {string} key The child's key.
 * @return {number}
 */
function loadRank(key) {
	if (!loadRanks.has(key)) {
		loadRanks.set(key, Math.random())
	}
	return loadRanks.get(key)
}

/**
 * The children in the order the mode asks for. Manual keeps the given order;
 * equal counts or times fall back to alphabetical, and equal titles to the
 * given order, so the result is stable.
 *
 * @param {Array<object>} children The children in their stored order.
 * @param {string} mode The sort mode.
 * @param {object} [options] Options.
 * @param {object} [options.use] The usage map, as {@link readTileUse} returns it.
 * @param {string} [options.locale] The viewer's locale for alphabetical order.
 * @return {Array<object>} A new array.
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function sortChildren(children, mode, { use = {}, locale } = {}) {
	const indexed = children.map((child, index) => ({ child, index }))
	if (mode === 'manual' || !SORT_MODES.includes(mode)) {
		return children.slice()
	}

	if (mode === 'random') {
		// Draw in the stored order, so a child's rank does not depend on how the sort compares.
		indexed.forEach((entry) => loadRank(childUseKey(entry.child) || `#${entry.index}`))
	}

	const collator = new Intl.Collator(locale, { sensitivity: 'base', numeric: true })
	const byTitle = (a, b) => collator.compare(childTitle(a.child), childTitle(b.child)) || a.index - b.index
	const usage = (entry) => use[childUseKey(entry.child)] ?? {}

	const compare = {
		alphabetical: byTitle,
		'most-used': (a, b) => (Number(usage(b).count) || 0) - (Number(usage(a).count) || 0) || byTitle(a, b),
		'last-used': (a, b) => (Number(usage(b).lastUsedAt) || 0) - (Number(usage(a).lastUsedAt) || 0) || byTitle(a, b),
		random: (a, b) => loadRank(childUseKey(a.child) || `#${a.index}`) - loadRank(childUseKey(b.child) || `#${b.index}`) || a.index - b.index,
	}[mode]

	return indexed.sort(compare).map((entry) => entry.child)
}

/**
 * Place children row by row from the top left, keeping each child's size.
 *
 * @param {Array<object>} children The children in display order.
 * @param {number} columns The inner grid's width in columns.
 * @return {Array<object>} Copies with new `gridX` and `gridY`.
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export function reflowChildren(children, columns) {
	let x = 0
	let y = 0
	let rowHeight = 0
	return children.map((child) => {
		const width = Math.min(Math.max(1, Number(child.gridWidth) || 2), columns)
		const height = Math.max(1, Number(child.gridHeight) || 2)
		if (x + width > columns) {
			x = 0
			y += rowHeight
			rowHeight = 0
		}
		const placed = { ...child, gridX: x, gridY: y }
		x += width
		rowHeight = Math.max(rowHeight, height)
		return placed
	})
}

/**
 * Test-only: draw new random ranks, as a page reload does.
 *
 * @spec exclude test-only harness hook, the `__` prefix marks it; it implements no product behaviour.
 * @return {void}
 */
export function __resetTileSortForTest() {
	loadRanks.clear()
}
