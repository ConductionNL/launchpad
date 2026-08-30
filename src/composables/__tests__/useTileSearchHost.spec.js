/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `useTileSearchHost.js` (tile-quick-search
 * REQ-QSEARCH-002/003/004) — the page-level effects the `search` widget
 * triggers: label resolution, tile activation, focus-grid and fallback.
 *
 * These moved here from `WorkspaceApp.spec.js` when quick search stopped
 * being page chrome; the launchpad#95 activation guard moved with them.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	activateSearchResult,
	buildSearchableTiles,
	focusGrid,
	GRID_CONTAINER_ID,
	performFallback,
	tileSearchLabel,
} from '../useTileSearchHost.js'

/**
 * Build the grid container the host queries, attached to the real document
 * so `getElementById` resolves it.
 *
 * @return {HTMLElement} the grid container.
 */
function mountGrid() {
	const grid = document.createElement('div')
	grid.id = GRID_CONTAINER_ID
	grid.tabIndex = -1
	document.body.appendChild(grid)
	return grid
}

/**
 * Append a rendered grid item, optionally carrying a link.
 *
 * @param {HTMLElement} grid the container.
 * @param {string} placementId the `data-placement-id` value.
 * @param {boolean} [withLink] whether to append an `<a href>`.
 * @return {{el: HTMLElement, link: HTMLElement|null}} the created nodes.
 */
function addTile(grid, placementId, withLink = true) {
	const el = document.createElement('div')
	el.className = 'launchpad-grid-item'
	el.setAttribute('data-placement-id', placementId)
	let link = null
	if (withLink) {
		// A hash-only href avoids jsdom's "navigation not implemented" noise
		// when `.click()` fires, while still exercising the real `<a href>`
		// activation path.
		link = document.createElement('a')
		link.setAttribute('href', '#deck')
		el.appendChild(link)
	}
	grid.appendChild(el)
	return { el, link }
}

describe('useTileSearchHost', () => {
	beforeEach(() => {
		document.body.innerHTML = ''
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	describe('tileSearchLabel / buildSearchableTiles (REQ-QSEARCH-002)', () => {
		it('derives a tile placement label from tileTitle', () => {
			expect(
				tileSearchLabel(
					{ id: 'p1', tileType: 'custom', tileTitle: 'Zaaksysteem' },
					[],
				),
			).toBe('Zaaksysteem')
		})

		it('falls back through customTitle, then widget.title, then a default', () => {
			const widgets = [{ id: 'w1', title: 'Weather' }]
			const placements = [
				{ id: 'p1', widgetId: 'w1', customTitle: 'My custom title' },
				{ id: 'p2', widgetId: 'w1' },
				{ id: 'p3', widgetId: 'unknown' },
			]
			expect(placements.map((p) => tileSearchLabel(p, widgets))).toEqual([
				'My custom title',
				'Weather',
				'Widget',
			])
		})

		it('builds {id, label, placement} triples off the live placement list', () => {
			const placements = [
				{ id: 7, tileType: 'custom', tileTitle: 'Zaaksysteem' },
			]
			expect(buildSearchableTiles(placements, [])).toEqual([
				{ id: 7, label: 'Zaaksysteem', placement: placements[0] },
			])
		})

		it('tolerates a missing placement list', () => {
			expect(buildSearchableTiles(null, null)).toEqual([])
		})
	})

	describe('activateSearchResult (REQ-QSEARCH-003)', () => {
		it("scrolls to and clicks the matched tile's rendered link", () => {
			const grid = mountGrid()
			const { el, link } = addTile(grid, 'p1')
			const scrollSpy = vi.fn()
			el.scrollIntoView = scrollSpy
			const clickSpy = vi.spyOn(link, 'click')

			expect(activateSearchResult({ id: 'p1', placement: { id: 'p1' } })).toBe(
				true,
			)
			expect(scrollSpy).toHaveBeenCalled()
			expect(clickSpy).toHaveBeenCalled()
		})

		it('focuses a linkless tile instead of clicking', () => {
			const grid = mountGrid()
			const { el } = addTile(grid, 'p1', false)
			el.scrollIntoView = vi.fn()
			const focusSpy = vi.spyOn(el, 'focus')

			activateSearchResult({ id: 'p1', placement: { id: 'p1' } })
			expect(focusSpy).toHaveBeenCalled()
			expect(el.getAttribute('tabindex')).toBe('-1')
		})

		it('does nothing when the grid is not on the page', () => {
			expect(activateSearchResult({ id: 'p1', placement: { id: 'p1' } })).toBe(
				false,
			)
		})

		/*
		 * launchpad#95 — `placement.id` is an INTEGER off the API row, and the
		 * original line called `placementId.replace(...)` on it.
		 * `Number.prototype.replace` does not exist, so this threw a TypeError
		 * on EVERY activation — inside a Vue event handler, where nothing
		 * surfaces it, so pressing Enter on a result silently did nothing. The
		 * truthiness guard did not catch it: a non-zero integer is truthy.
		 *
		 * Written against the production shape on purpose: every fixture in the
		 * spec this replaced used string ids, which is exactly why a green
		 * suite never saw the defect.
		 */
		it('activates a tile whose placement id is an INTEGER (launchpad#95)', () => {
			const grid = mountGrid()
			const { el, link } = addTile(grid, '7')
			const scrollSpy = vi.fn()
			el.scrollIntoView = scrollSpy
			const clickSpy = vi.spyOn(link, 'click')

			expect(() =>
				activateSearchResult({ id: 7, placement: { id: 7 } }),
			).not.toThrow()
			expect(
				scrollSpy,
				'the matched tile must be scrolled into view',
			).toHaveBeenCalled()
			expect(
				clickSpy,
				"Enter must activate the tile's rendered link",
			).toHaveBeenCalled()
		})

		it('ignores a null id rather than hunting for the string "null" (launchpad#95)', () => {
			const grid = mountGrid()
			// A cell literally labelled "null" — the shape a bare String() cast
			// would go looking for, and find.
			const { link } = addTile(grid, 'null')
			const clickSpy = vi.spyOn(link, 'click')

			activateSearchResult({ id: null, placement: { id: null } })
			expect(
				clickSpy,
				'a null id must not activate the tile that happens to be called "null"',
			).not.toHaveBeenCalled()
		})
	})

	describe('focusGrid (REQ-QSEARCH-003)', () => {
		it('moves focus to the grid container', () => {
			const grid = mountGrid()
			const focusSpy = vi.spyOn(grid, 'focus')
			expect(focusGrid()).toBe(true)
			expect(focusSpy).toHaveBeenCalledWith({ preventScroll: true })
		})

		it('reports failure when there is no grid', () => {
			expect(focusGrid()).toBe(false)
		})
	})

	describe('performFallback (REQ-QSEARCH-004)', () => {
		it('opens a web-search URL in a new tab', () => {
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			performFallback({
				type: 'web-search',
				url: 'https://example.org/search?q=x',
			})
			expect(openSpy).toHaveBeenCalledWith(
				'https://example.org/search?q=x',
				'_blank',
				'noopener,noreferrer',
			)
		})

		it('dispatches a unified-search CustomEvent without navigating', () => {
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			const dispatchSpy = vi.spyOn(window, 'dispatchEvent')
			performFallback({ type: 'unified-search', query: 'invoices' })

			expect(openSpy).not.toHaveBeenCalled()
			const dispatched = dispatchSpy.mock.calls.find(
				([e]) => e.type === 'nextcloud:unified-search.search',
			)
			expect(dispatched).toBeTruthy()
			expect(dispatched[0].detail).toEqual({ query: 'invoices' })
		})

		it('does nothing observable for a "none" action or a null action', () => {
			const openSpy = vi.spyOn(window, 'open').mockImplementation(() => {})
			expect(() => performFallback({ type: 'none' })).not.toThrow()
			expect(() => performFallback(null)).not.toThrow()
			expect(openSpy).not.toHaveBeenCalled()
		})
	})
})
