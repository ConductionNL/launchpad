/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widgets on the first screen render in the first pass, with their
 * renderer chunks fetched as soon as the placements are known.
 *
 * @spec openspec/changes/first-viewport-widgets/specs/widgets/spec.md#requirement-the-widgets-on-the-first-screen-render-in-the-first-pass-req-wdg-047
 */
import { describe, expect, it, vi } from 'vitest'
import { GRID_ROW_PITCH, inFirstViewport, preloadFirstViewportRenderers } from '../firstViewport.js'

describe('inFirstViewport', () => {
	it('is true for a placement whose top row starts on the first screen', () => {
		expect(inFirstViewport({ gridY: 0 }, 1000)).toBe(true)
		expect(inFirstViewport({ gridY: 14 }, 1000)).toBe(true)
		expect(inFirstViewport({ gridY: Math.ceil(1000 / GRID_ROW_PITCH) }, 1000)).toBe(false)
	})

	it('is false without a row', () => {
		expect(inFirstViewport({}, 1000)).toBe(false)
		expect(inFirstViewport(null, 1000)).toBe(false)
		expect(inFirstViewport({ gridY: 'x' }, 1000)).toBe(false)
	})

	it('reads the window height by default', () => {
		expect(inFirstViewport({ gridY: 0 })).toBe(window.innerHeight > 0)
	})
})

describe('preloadFirstViewportRenderers', () => {
	it('starts each first-screen renderer once and leaves the rest', () => {
		const tile = vi.fn()
		const files = vi.fn()
		const below = vi.fn()
		const entries = {
			tile: { renderer: { __cnLazy: { load: tile } } },
			files: { renderer: { __cnLazy: { load: files } } },
			chart: { renderer: { __cnLazy: { load: below } } },
			plain: { renderer: {} },
		}
		const started = preloadFirstViewportRenderers([
			{ widgetId: 'tile', gridY: 0 },
			{ widgetId: 'tile', gridY: 2 },
			{ widgetId: 'files', gridY: 3 },
			{ widgetId: 'plain', gridY: 0 },
			{ widgetId: 'unknown', gridY: 0 },
			{ widgetId: 'chart', gridY: 40 },
		], (type) => entries[type] || null, 1000)
		expect(started).toBe(2)
		expect(tile).toHaveBeenCalledTimes(1)
		expect(files).toHaveBeenCalledTimes(1)
		expect(below).not.toHaveBeenCalled()
	})

	it('takes no placements', () => {
		expect(preloadFirstViewportRenderers(undefined, () => null, 1000)).toBe(0)
	})
})
