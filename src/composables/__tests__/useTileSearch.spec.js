/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `useTileSearch.js` (tile-quick-search:
 * REQ-QSEARCH-001..004).
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import {
	FALLBACK_TARGET_NONE,
	FALLBACK_TARGET_UNIFIED_SEARCH,
	MATCH_RANK,
	isCtrlKFocusShortcut,
	isSlashFocusShortcut,
	isTypingTarget,
	isValidFallbackTemplate,
	matchRank,
	normalizeForSearch,
	rankItems,
	resolveFallbackAction,
	useTileSearch,
} from '../useTileSearch.js'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('normalizeForSearch', () => {
	it('lower-cases', () => {
		expect(normalizeForSearch('Zaaksysteem')).toBe('zaaksysteem')
	})

	it('strips diacritics so accented and plain forms compare equal', () => {
		expect(normalizeForSearch('café')).toBe(normalizeForSearch('cafe'))
		expect(normalizeForSearch('Überzicht')).toBe('uberzicht')
	})

	it('treats a nullish value as an empty string', () => {
		expect(normalizeForSearch(null)).toBe('')
		expect(normalizeForSearch(undefined)).toBe('')
	})
})

describe('matchRank', () => {
	it('ranks a prefix match as MATCH_RANK.PREFIX', () => {
		expect(matchRank('zaak', 'Zaaksysteem')).toBe(MATCH_RANK.PREFIX)
	})

	it('ranks a mid-string substring match as MATCH_RANK.SUBSTRING', () => {
		expect(matchRank('brow', 'Zaakbrowser')).toBe(MATCH_RANK.SUBSTRING)
	})

	it('ranks a non-contiguous subsequence match as MATCH_RANK.SUBSEQUENCE', () => {
		// "vlf" matches Verlof as V-e-r-L-o-F, in order but not contiguous.
		expect(matchRank('vlf', 'Verlof')).toBe(MATCH_RANK.SUBSEQUENCE)
	})

	it('returns null for a non-match', () => {
		expect(matchRank('xyz', 'Verlof')).toBeNull()
	})

	it('returns null for an empty query', () => {
		expect(matchRank('', 'Verlof')).toBeNull()
	})

	it('is case- and diacritic-insensitive', () => {
		expect(matchRank('MARKET', 'marketplace')).toBe(MATCH_RANK.PREFIX)
		expect(matchRank('cafe', 'Café Central')).toBe(MATCH_RANK.PREFIX)
	})
})

describe('rankItems', () => {
	it('filters out non-matching items entirely', () => {
		const items = [
			{ id: '1', label: 'Zaaksysteem' },
			{ id: '2', label: 'Weer' },
			{ id: '3', label: 'Zaakbrowser' },
		]
		const results = rankItems('zaak', items)
		expect(results.map((r) => r.id)).toEqual(['1', '3'])
	})

	it('ranks a prefix match above a mid-string substring match (spec Ranking order scenario)', () => {
		const items = [
			{ id: 'overzicht', label: 'Overzicht verlof' },
			{ id: 'aanvragen', label: 'Verlof aanvragen' },
		]
		const results = rankItems('verlof', items)
		expect(results.map((r) => r.id)).toEqual(['aanvragen', 'overzicht'])
	})

	it('ranks a substring match above a subsequence-only match', () => {
		const items = [
			{ id: 'fuzzy', label: 'Volledig flexibel' }, // "vlf" is a subsequence (V-o-L-...-F-...), not a substring
			{ id: 'substring', label: 'Overzicht vlf-rapport' }, // "vlf" is a literal substring
		]
		const results = rankItems('vlf', items)
		expect(results.map((r) => r.id)).toEqual(['substring', 'fuzzy'])
	})

	it('preserves input order for ties within the same rank tier', () => {
		const items = [
			{ id: 'a', label: 'Zaak A' },
			{ id: 'b', label: 'Zaak B' },
		]
		const results = rankItems('zaak', items)
		expect(results.map((r) => r.id)).toEqual(['a', 'b'])
	})

	it('returns an empty array for an empty item list', () => {
		expect(rankItems('anything', [])).toEqual([])
	})

	it('tolerates items with a missing/non-string label', () => {
		const items = [{ id: '1' }, { id: '2', label: null }]
		expect(rankItems('anything', items)).toEqual([])
	})
})

describe('isValidFallbackTemplate', () => {
	it('accepts an https URL containing {query}', () => {
		expect(isValidFallbackTemplate('https://example.org/search?q={query}')).toBe(true)
	})

	it('rejects a non-https URL', () => {
		expect(isValidFallbackTemplate('http://example.org/search?q={query}')).toBe(false)
	})

	it('rejects a template missing the {query} placeholder', () => {
		expect(isValidFallbackTemplate('https://example.org/search')).toBe(false)
	})

	it('rejects a malformed URL', () => {
		expect(isValidFallbackTemplate('not a url {query}')).toBe(false)
	})

	it('rejects non-string input', () => {
		expect(isValidFallbackTemplate(null)).toBe(false)
		expect(isValidFallbackTemplate(undefined)).toBe(false)
		expect(isValidFallbackTemplate(42)).toBe(false)
	})

	it('rejects an empty/whitespace-only string', () => {
		expect(isValidFallbackTemplate('')).toBe(false)
		expect(isValidFallbackTemplate('   ')).toBe(false)
	})
})

describe('resolveFallbackAction', () => {
	it('resolves "unified-search" to a unified-search action carrying the query', () => {
		expect(resolveFallbackAction(FALLBACK_TARGET_UNIFIED_SEARCH, 'invoices')).toEqual({
			type: 'unified-search',
			query: 'invoices',
		})
	})

	it('resolves a valid web-search template to a URL with the query substituted + encoded', () => {
		const action = resolveFallbackAction('https://example.org/search?q={query}', 'hello world')
		expect(action).toEqual({
			type: 'web-search',
			url: 'https://example.org/search?q=hello%20world',
		})
	})

	it('resolves "none" to a none action', () => {
		expect(resolveFallbackAction(FALLBACK_TARGET_NONE, 'invoices')).toEqual({ type: 'none' })
	})

	it('resolves an invalid/malformed template to a none action (fail-safe)', () => {
		expect(resolveFallbackAction('not-a-template', 'invoices')).toEqual({ type: 'none' })
		expect(resolveFallbackAction(undefined, 'invoices')).toEqual({ type: 'none' })
	})
})

describe('isTypingTarget', () => {
	it.each(['input', 'textarea', 'select'])('treats a %s element as a typing target', (tagName) => {
		expect(isTypingTarget({ tagName })).toBe(true)
		expect(isTypingTarget({ tagName: tagName.toUpperCase() })).toBe(true)
	})

	it('treats a contenteditable element as a typing target', () => {
		expect(isTypingTarget({ tagName: 'DIV', isContentEditable: true })).toBe(true)
	})

	it('does not treat a plain element as a typing target', () => {
		expect(isTypingTarget({ tagName: 'DIV', isContentEditable: false })).toBe(false)
		expect(isTypingTarget({ tagName: 'BUTTON' })).toBe(false)
	})

	it('handles a null/undefined target', () => {
		expect(isTypingTarget(null)).toBe(false)
		expect(isTypingTarget(undefined)).toBe(false)
	})
})

describe('isSlashFocusShortcut (REQ-QSEARCH-001 "Slash focuses the bar")', () => {
	it('is true for a bare "/" outside a text field', () => {
		expect(isSlashFocusShortcut({ key: '/', target: { tagName: 'DIV' } })).toBe(true)
	})

	it('is false while focus is in an input/textarea/contenteditable', () => {
		expect(isSlashFocusShortcut({ key: '/', target: { tagName: 'INPUT' } })).toBe(false)
		expect(isSlashFocusShortcut({ key: '/', target: { tagName: 'TEXTAREA' } })).toBe(false)
		expect(isSlashFocusShortcut({ key: '/', target: { tagName: 'DIV', isContentEditable: true } })).toBe(false)
	})

	it('is false for a modified "/" press', () => {
		expect(isSlashFocusShortcut({ key: '/', ctrlKey: true, target: { tagName: 'DIV' } })).toBe(false)
		expect(isSlashFocusShortcut({ key: '/', metaKey: true, target: { tagName: 'DIV' } })).toBe(false)
		expect(isSlashFocusShortcut({ key: '/', altKey: true, target: { tagName: 'DIV' } })).toBe(false)
	})

	it('is false for any other key', () => {
		expect(isSlashFocusShortcut({ key: 'a', target: { tagName: 'DIV' } })).toBe(false)
	})
})

describe('isCtrlKFocusShortcut (REQ-QSEARCH-001 "Ctrl+K focuses the bar")', () => {
	it('is true for Ctrl+K', () => {
		expect(isCtrlKFocusShortcut({ key: 'k', ctrlKey: true })).toBe(true)
	})

	it('is true for Cmd+K (metaKey, macOS)', () => {
		expect(isCtrlKFocusShortcut({ key: 'k', metaKey: true })).toBe(true)
	})

	it('fires even while focus is inside a text field (explicit chord, not a printable char)', () => {
		expect(isCtrlKFocusShortcut({ key: 'k', ctrlKey: true, target: { tagName: 'INPUT' } })).toBe(true)
	})

	it('is false for a bare "k"', () => {
		expect(isCtrlKFocusShortcut({ key: 'k' })).toBe(false)
	})

	it('is false when Shift or Alt is also held', () => {
		expect(isCtrlKFocusShortcut({ key: 'k', ctrlKey: true, shiftKey: true })).toBe(false)
		expect(isCtrlKFocusShortcut({ key: 'k', ctrlKey: true, altKey: true })).toBe(false)
	})
})

describe('useTileSearch()', () => {
	const items = [
		{ id: 'z1', label: 'Zaaksysteem' },
		{ id: 'z2', label: 'Zaakbrowser' },
		{ id: 'v1', label: 'Verlof aanvragen' },
	]

	it('starts with an empty query and no results', () => {
		const search = useTileSearch()
		expect(search.state.query).toBe('')
		expect(search.state.results).toEqual([])
		expect(search.state.activeIndex).toBe(-1)
	})

	it('filters live as the query changes (REQ-QSEARCH-002)', () => {
		const search = useTileSearch()
		search.setItems(items)
		search.setQuery('zaak')
		expect(search.state.results.map((r) => r.id)).toEqual(['z1', 'z2'])
		expect(search.state.activeIndex).toBe(0)
	})

	it('clearing the query back to empty clears results without dropping the item list', () => {
		const search = useTileSearch()
		search.setItems(items)
		search.setQuery('zaak')
		search.setQuery('')
		expect(search.state.results).toEqual([])
		expect(search.state.activeIndex).toBe(-1)
		// The item list itself is still there for the next query.
		search.setQuery('verlof')
		expect(search.state.results.map((r) => r.id)).toEqual(['v1'])
	})

	it('re-filters against a fresh item list after setItems() (dashboard switch)', () => {
		const search = useTileSearch()
		search.setItems(items)
		search.setQuery('zaak')
		expect(search.state.results).toHaveLength(2)

		search.setItems([{ id: 'other', label: 'Weer' }])
		expect(search.state.results).toEqual([])
	})

	describe('moveSelection (REQ-QSEARCH-003 wrap-around)', () => {
		it('moves forward and wraps past the last result back to the first', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('za') // matches z1, z2
			expect(search.state.activeIndex).toBe(0)
			search.moveSelection(1)
			expect(search.state.activeIndex).toBe(1)
			search.moveSelection(1)
			expect(search.state.activeIndex).toBe(0)
		})

		it('moves backward and wraps past the first result to the last', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('za')
			expect(search.state.activeIndex).toBe(0)
			search.moveSelection(-1)
			expect(search.state.activeIndex).toBe(1)
		})

		it('is a no-op (stays at -1) with zero results', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('nonexistent')
			search.moveSelection(1)
			expect(search.state.activeIndex).toBe(-1)
		})
	})

	describe('pressEnter — opening a match (REQ-QSEARCH-003)', () => {
		it('calls onOpen with the matched item and returns an "open" action', () => {
			const onOpen = vi.fn()
			const search = useTileSearch({ onOpen })
			search.setItems(items)
			search.setQuery('verlof')
			const action = search.pressEnter()
			expect(onOpen).toHaveBeenCalledWith(items[2])
			expect(action.type).toBe('open')
		})

		it('opens whichever result is currently active after arrow-key movement', () => {
			const onOpen = vi.fn()
			const search = useTileSearch({ onOpen })
			search.setItems(items)
			search.setQuery('za')
			search.moveSelection(1)
			search.pressEnter()
			expect(onOpen).toHaveBeenCalledWith(items[1])
		})
	})

	describe('pressEnter — no-match fallback (REQ-QSEARCH-004)', () => {
		it('resolves and dispatches the unified-search fallback when configured', () => {
			const onFallback = vi.fn()
			const search = useTileSearch({ onFallback, getFallbackTarget: () => FALLBACK_TARGET_UNIFIED_SEARCH })
			search.setItems(items)
			search.setQuery('nonexistent')
			const action = search.pressEnter()
			expect(action).toEqual({ type: 'unified-search', query: 'nonexistent' })
			expect(onFallback).toHaveBeenCalledWith(action)
		})

		it('resolves and dispatches the web-search fallback when configured', () => {
			const onFallback = vi.fn()
			const search = useTileSearch({
				onFallback,
				getFallbackTarget: () => 'https://duckduckgo.com/?q={query}',
			})
			search.setItems(items)
			search.setQuery('nonexistent tile')
			const action = search.pressEnter()
			expect(action).toEqual({ type: 'web-search', url: 'https://duckduckgo.com/?q=nonexistent%20tile' })
			expect(onFallback).toHaveBeenCalledWith(action)
		})

		it('does nothing beyond the "none" action when no fallback is configured', () => {
			const onFallback = vi.fn()
			const onOpen = vi.fn()
			const search = useTileSearch({ onOpen, onFallback, getFallbackTarget: () => FALLBACK_TARGET_NONE })
			search.setItems(items)
			search.setQuery('nonexistent')
			const action = search.pressEnter()
			expect(action).toEqual({ type: 'none' })
			expect(onFallback).toHaveBeenCalledWith({ type: 'none' })
			expect(onOpen).not.toHaveBeenCalled()
		})

		it('defaults to "none" when no getFallbackTarget is supplied', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('nonexistent')
			const action = search.pressEnter()
			expect(action).toEqual({ type: 'none' })
		})
	})

	describe('clear (REQ-QSEARCH-003 "Escape clears and returns focus")', () => {
		it('empties the query and results and resets the selection', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('zaak')
			search.clear()
			expect(search.state.query).toBe('')
			expect(search.state.results).toEqual([])
			expect(search.state.activeIndex).toBe(-1)
		})
	})

	describe('activeResult', () => {
		it('returns null when there is no active selection', () => {
			const search = useTileSearch()
			expect(search.activeResult()).toBeNull()
		})

		it('returns the ranked result object at the active index', () => {
			const search = useTileSearch()
			search.setItems(items)
			search.setQuery('zaak')
			expect(search.activeResult().id).toBe('z1')
		})
	})

	it('the query is never written to any persistence API (no query stored — REQ-QSEARCH-002)', () => {
		const setItemSpy = vi.spyOn(Storage.prototype, 'setItem')
		const search = useTileSearch()
		search.setItems(items)
		search.setQuery('zaak')
		search.pressEnter()
		search.clear()
		expect(setItemSpy).not.toHaveBeenCalled()
		setItemSpy.mockRestore()
	})
})
