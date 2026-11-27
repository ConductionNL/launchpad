/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * firstViewport: which workspace placements a viewer sees without scrolling.
 *
 * Widgets in the first screen are mounted in the first render and their
 * renderer chunks are fetched as soon as the placements are known, instead
 * of after an IntersectionObserver round (one frame after the first paint)
 * and a chunk fetch that only starts then.
 *
 * @module utils/firstViewport
 */

/** Height of one workspace grid row including the gap below it, in pixels (cellHeight 60 + margin 8). */
export const GRID_ROW_PITCH = 68

/**
 * Whether a placement starts within the first screen.
 *
 * @param {object} placement The placement (`gridY` in rows).
 * @param {number} [viewportHeight] The screen height in pixels; the window's by default.
 * @return {boolean} True when its top row is on the first screen.
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
export function inFirstViewport(placement, viewportHeight) {
	const y = Number(placement?.gridY)
	if (!Number.isFinite(y)) {
		return false
	}
	const height = Number.isFinite(viewportHeight) ? viewportHeight : (typeof window !== 'undefined' ? window.innerHeight : 0)
	return y * GRID_ROW_PITCH < height
}

/**
 * Start fetching the renderer chunk of every widget on the first screen.
 *
 * @param {object[]} placements The dashboard's placements.
 * @param {(type: string) => (object|null)} resolveEntry Looks up a widget type's registry entry.
 * @param {number} [viewportHeight] The screen height in pixels.
 * @return {number} How many renderer loads were started (or were already done).
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
export function preloadFirstViewportRenderers(placements, resolveEntry, viewportHeight) {
	let started = 0
	const seen = new Set()
	for (const placement of placements || []) {
		const type = placement?.widgetId
		if (typeof type !== 'string' || seen.has(type) || !inFirstViewport(placement, viewportHeight)) {
			continue
		}
		seen.add(type)
		const load = resolveEntry(type)?.renderer?.__cnLazy?.load
		if (typeof load === 'function') {
			load()
			started++
		}
	}
	return started
}
