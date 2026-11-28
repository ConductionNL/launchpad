/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the data side of the top bar's results list
 * (header-search-dropdown, REQ-SRCH-013): provider order, grouping, the
 * detail line, the shortcut tip and the keyboard selection arithmetic.
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const get = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args) } }))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path, params = {}) => '/ocs/v2.php/' + path.replace('{providerId}', params.providerId ?? ''),
}))

const {
	firstShortcut,
	groupResults,
	isSearchable,
	moveSelection,
	orderProviders,
	PER_GROUP_LIMIT,
	searchAll,
	toResult,
} = await import('../headerSearch.js')

describe('headerSearch', () => {
	beforeEach(() => {
		get.mockReset()
	})

	it('opens from two characters on', () => {
		expect(isSearchable('t')).toBe(false)
		expect(isSearchable(' t ')).toBe(false)
		expect(isSearchable('te')).toBe(true)
		expect(isSearchable(null)).toBe(false)
	})

	it('puts LaunchPad dashboards first, then the providers by order and name', () => {
		const ordered = orderProviders([
			{ id: 'files', name: 'Files', order: 5 },
			{ id: 'launchpad', name: 'Dashboards', order: 50 },
			{ id: 'contacts', name: 'Contacts', order: 5 },
			{ id: 'mail', name: 'Mail' },
			null,
		])
		expect(ordered.map((p) => p.id)).toEqual(['launchpad', 'contacts', 'files', 'mail'])
	})

	it('keeps a result\'s icon, name, detail line and link', () => {
		expect(toResult({
			title: 'Permits team',
			subline: 'Shared by Marieke Jansen · 14 widgets',
			thumbnailUrl: '/icon.svg',
			resourceUrl: '/apps/launchpad/#dashboard/u1',
		}, 'launchpad', 0)).toEqual({
			key: 'launchpad-0',
			title: 'Permits team',
			detail: 'Shared by Marieke Jansen · 14 widgets',
			icon: '/icon.svg',
			url: '/apps/launchpad/#dashboard/u1',
		})
		expect(toResult({ title: 'x', icon: 'icon-files' }, 'files', 2).icon).toBe('icon-files')
	})

	it('groups per provider, skips empty answers and caps each group', () => {
		const many = Array.from({ length: PER_GROUP_LIMIT + 3 }, (_, i) => ({ title: `f${i}` }))
		const groups = groupResults(
			[{ id: 'launchpad', name: 'Dashboards' }, { id: 'mail', name: 'Mail' }, { id: 'files', name: 'Files' }],
			{ launchpad: [{ title: 'Permits team' }, { title: '' }], mail: [], files: many },
		)
		expect(groups.map((g) => g.name)).toEqual(['Dashboards', 'Files'])
		expect(groups[0].results.map((r) => r.title)).toEqual(['Permits team'])
		expect(groups[1].results).toHaveLength(PER_GROUP_LIMIT)
	})

	it('takes the first usable shortcut for the tip', () => {
		expect(firstShortcut([{ prefix: '', name: 'X' }, { prefix: '!top', name: 'TOPdesk' }])).toEqual({ prefix: '!top', name: 'TOPdesk' })
		expect(firstShortcut([])).toBeNull()
		expect(firstShortcut(undefined)).toBeNull()
	})

	it('moves the selection with wrap-around', () => {
		expect(moveSelection(0, 1, 3)).toBe(1)
		expect(moveSelection(2, 1, 3)).toBe(0)
		expect(moveSelection(0, -1, 3)).toBe(2)
		expect(moveSelection(-1, 1, 3)).toBe(0)
		expect(moveSelection(-1, -1, 3)).toBe(2)
		expect(moveSelection(0, 1, 0)).toBe(-1)
	})

	it('asks every provider and returns the grouped answers, LaunchPad first', async () => {
		get.mockImplementation((url) => {
			if (url === '/ocs/v2.php/search/providers') {
				return Promise.resolve({ data: { ocs: { data: [{ id: 'files', name: 'Files', order: 5 }, { id: 'launchpad', name: 'Dashboards', order: 50 }] } } })
			}
			if (url.endsWith('/launchpad/search')) {
				return Promise.resolve({ data: { ocs: { data: { entries: [{ title: 'Permits team' }] } } } })
			}
			return Promise.reject(new Error('down'))
		})
		const groups = await searchAll('team', { route: '/apps/launchpad/' })
		expect(groups).toHaveLength(1)
		expect(groups[0].id).toBe('launchpad')
		const providerCall = get.mock.calls.find(([url]) => url.endsWith('/launchpad/search'))
		expect(providerCall[1].params.term).toBe('team')
		expect(providerCall[1].headers['OCS-APIRequest']).toBe('true')
	})

	it('returns no groups when the provider list fails', async () => {
		get.mockRejectedValue(new Error('offline'))
		expect(await searchAll('team', { route: '/' })).toEqual([])
	})
})
