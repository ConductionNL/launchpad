/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Prefix shortcuts in the quick search (search-ai-prefix-shortcuts
 * REQ-SPX-002, REQ-SPX-003): a known prefix wins over tile ranking, `!` or
 * `?` lists the shortcuts, anything else searches tiles as before.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-a-prefix-sends-the-query-to-its-site-req-spx-002
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_, key) => vars[key] ?? '{' + key + '}'),
}))

import {
	isShortcutListQuery,
	parsePrefix,
	shortcutResults,
	shortcutUrl,
	useTileSearch,
} from '../useTileSearch.js'

const TOPDESK = { prefix: '!t', name: 'TOPdesk', urlTemplate: 'https://topdesk.gemeente.nl/tas/secure/search?q={query}' }
const ZAAK = { prefix: '!z', name: 'Zaaksysteem', urlTemplate: 'https://zaken.example.nl/?q={query}' }
const SHORTCUTS = [TOPDESK, ZAAK]

describe('parsePrefix', () => {
	it('matches a known prefix and keeps the rest', () => {
		expect(parsePrefix('!t printer 3e verdieping', SHORTCUTS)).toEqual({ shortcut: TOPDESK, rest: 'printer 3e verdieping' })
	})

	it('matches without regard to case', () => {
		expect(parsePrefix('!T printer', SHORTCUTS)).toEqual({ shortcut: TOPDESK, rest: 'printer' })
	})

	it('matches a bare prefix with an empty rest', () => {
		expect(parsePrefix('  !z  ', SHORTCUTS)).toEqual({ shortcut: ZAAK, rest: '' })
	})

	it('does not match an unknown prefix, a prefix inside a word, or a query without one', () => {
		expect(parsePrefix('!x rapport', SHORTCUTS)).toBeNull()
		expect(parsePrefix('!top printer', SHORTCUTS)).toBeNull()
		expect(parsePrefix('printer !t', SHORTCUTS)).toBeNull()
		expect(parsePrefix('!t printer', null)).toBeNull()
	})
})

describe('shortcutUrl', () => {
	it('puts the URL-encoded rest in the template', () => {
		expect(shortcutUrl(TOPDESK, 'printer 3e verdieping'))
			.toBe('https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping')
		expect(shortcutUrl(ZAAK, 'a&b=c')).toBe('https://zaken.example.nl/?q=a%26b%3Dc')
	})

	it('never opens a template that is not https with {query}', () => {
		expect(shortcutUrl({ ...TOPDESK, urlTemplate: 'http://x/?q={query}' }, 'a')).toBeNull()
		expect(shortcutUrl({ ...TOPDESK, urlTemplate: 'javascript:alert({query})' }, 'a')).toBeNull()
	})
})

describe('shortcutResults', () => {
	it('gives one "Search <name> for <rest>" result for a known prefix', () => {
		expect(shortcutResults('!t printer 3e verdieping', SHORTCUTS)).toEqual([{
			id: 'shortcut:!t',
			kind: 'shortcut',
			label: 'Search TOPdesk for printer 3e verdieping',
			url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping',
			item: null,
			rank: 0,
		}])
	})

	it('lists every shortcut for ? or ! alone', () => {
		expect(isShortcutListQuery('?')).toBe(true)
		expect(isShortcutListQuery(' ! ')).toBe(true)
		expect(isShortcutListQuery('!t')).toBe(false)
		expect(shortcutResults('?', SHORTCUTS).map((r) => r.label)).toEqual(['!t TOPdesk', '!z Zaaksysteem'])
		expect(shortcutResults('!', SHORTCUTS).map((r) => r.kind)).toEqual(['shortcut-hint', 'shortcut-hint'])
	})

	it('shows the shortcut itself for a bare prefix', () => {
		expect(shortcutResults('!z', SHORTCUTS).map((r) => r.label)).toEqual(['!z Zaaksysteem'])
	})

	it('has nothing to say for an unknown prefix or without shortcuts', () => {
		expect(shortcutResults('!x rapport', SHORTCUTS)).toBeNull()
		expect(shortcutResults('?', [])).toBeNull()
	})
})

describe('useTileSearch with shortcuts', () => {
	const tiles = [{ id: 'p1', label: 'Printers' }, { id: 'r1', label: '!x rapport' }]

	/**
	 * A search over the tiles with the shortcuts.
	 *
	 * @param {object} options extra factory options.
	 * @return {object} the search.
	 */
	function search(options = {}) {
		const instance = useTileSearch({ getShortcuts: () => SHORTCUTS, ...options })
		instance.setItems(tiles)
		return instance
	}

	it('a known prefix wins over tile ranking and Enter opens the site through the web-search path', () => {
		const onFallback = vi.fn()
		const onOpen = vi.fn()
		const s = search({ onFallback, onOpen })
		s.setQuery('!t printer 3e verdieping')

		expect(s.state.results.map((r) => r.label)).toEqual(['Search TOPdesk for printer 3e verdieping'])
		expect(s.pressEnter()).toEqual({ type: 'web-search', url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping' })
		expect(onFallback).toHaveBeenCalledWith({ type: 'web-search', url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping' })
		expect(onOpen).not.toHaveBeenCalled()
	})

	it('an unknown prefix filters tiles and applies the normal fallback', () => {
		const onFallback = vi.fn()
		const s = search({ onFallback, getFallbackTarget: () => 'unified-search' })
		s.setQuery('!x rapport')
		expect(s.state.results.map((r) => r.id)).toEqual(['r1'])

		s.setQuery('!y nothing')
		expect(s.state.results).toEqual([])
		s.pressEnter()
		expect(onFallback).toHaveBeenCalledWith({ type: 'unified-search', query: '!y nothing' })
	})

	it('choosing a listed shortcut starts a query with its prefix', () => {
		const s = search()
		s.setQuery('?')
		expect(s.state.results.map((r) => r.label)).toEqual(['!t TOPdesk', '!z Zaaksysteem'])
		s.moveSelection(1)
		expect(s.pressEnter()).toEqual({ type: 'shortcut-hint', prefix: '!z' })
		expect(s.state.query).toBe('!z ')
	})

	it('without shortcuts the search is unchanged', () => {
		const s = useTileSearch({})
		s.setItems(tiles)
		s.setQuery('print')
		expect(s.state.results.map((r) => r.id)).toEqual(['p1'])
	})
})
