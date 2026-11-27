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
 * The screen height to judge against: the given one, else the window's.
 *
 * @param {number} [viewportHeight] A height in pixels.
 * @return {number} The height.
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
function screenHeight(viewportHeight) {
	if (Number.isFinite(viewportHeight)) {
		return viewportHeight
	}
	return typeof window !== 'undefined' ? window.innerHeight : 0
}

/**
 * Whether a placement starts within the first screen.
 *
 * @param {object} placement The placement (`gridY` in rows).
 * @param {number} [viewportHeight] The screen height in pixels; the window's by default.
 * @return {boolean} True when its top row is on the first screen.
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
export function inFirstViewport(placement, viewportHeight) {
	const y = Number(placement ? placement.gridY : NaN)
	return Number.isFinite(y) && y * GRID_ROW_PITCH < screenHeight(viewportHeight)
}

/**
 * The widget types of the placements on the first screen, each once.
 *
 * @param {object[]} placements The placements.
 * @param {number} [viewportHeight] The screen height in pixels.
 * @return {string[]} The types.
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
function firstScreenTypes(placements, viewportHeight) {
	const onScreen = (placements || []).filter((p) => typeof p?.widgetId === 'string' && inFirstViewport(p, viewportHeight))
	return [...new Set(onScreen.map((p) => p.widgetId))]
}

/**
 * The loader of a lazily loaded renderer, or null.
 *
 * @param {object|null} entry A widget type's registry entry.
 * @return {Function|null} The loader.
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
function rendererLoad(entry) {
	const lazy = entry && entry.renderer && entry.renderer.__cnLazy
	return lazy && typeof lazy.load === 'function' ? lazy.load : null
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
	const loads = firstScreenTypes(placements, viewportHeight).map((type) => rendererLoad(resolveEntry(type))).filter(Boolean)
	loads.forEach((load) => load())
	return loads.length
}
