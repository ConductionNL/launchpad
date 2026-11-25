/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for `HeaderSearchDropdown.vue` (header-search-dropdown,
 * REQ-SRCH-013): the list under the core search field, its grouping and
 * detail lines, the combobox keyboard, Escape, the shortcut tip, the
 * "Show all results" hand-off, and that thematiq's field words stay.
 *
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const searchAll = vi.fn()
vi.mock('../../services/headerSearch.js', async (importOriginal) => ({
	...(await importOriginal()),
	searchAll: (...args) => searchAll(...args),
}))
const performFallback = vi.fn()
vi.mock('../../composables/useTileSearchHost.js', () => ({
	performFallback: (...args) => performFallback(...args),
}))
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
}))

const { default: HeaderSearchDropdown } = await import('../HeaderSearchDropdown.vue')

const GROUPS = [
	{
		id: 'launchpad',
		name: 'Dashboards',
		results: [
			{ key: 'launchpad-0', title: 'Permits team', detail: 'Shared by Marieke Jansen · 14 widgets', icon: '/i.svg', url: '/apps/launchpad/#dashboard/permits' },
			{ key: 'launchpad-1', title: 'Zuiddrecht intranet', detail: 'Group dashboard of Communications', icon: '', url: '/apps/launchpad/#dashboard/intranet' },
		],
	},
]

describe('HeaderSearchDropdown', () => {
	let field
	let wrapper
	let assign

	beforeEach(() => {
		vi.useFakeTimers()
		searchAll.mockReset().mockResolvedValue(GROUPS)
		performFallback.mockReset()
		field = document.createElement('input')
		field.className = 'unified-search-input'
		field.placeholder = 'Zoek in Zuiddrecht'
		document.body.appendChild(field)
		assign = vi.fn()
		vi.stubGlobal('location', { ...window.location, assign, pathname: '/apps/launchpad/' })
	})

	afterEach(() => {
		wrapper?.unmount()
		wrapper = null
		field.remove()
		vi.useRealTimers()
		vi.unstubAllGlobals()
	})

	/**
	 * Mount, type the query and let the debounce and the search settle.
	 *
	 * @param {string} query What to type.
	 * @param {object} [props] Props.
	 */
	async function typeQuery(query, props = {}) {
		wrapper = mount(HeaderSearchDropdown, { props, attachTo: document.body })
		field.value = query
		field.dispatchEvent(new Event('input'))
		await vi.advanceTimersByTimeAsync(300)
		await flushPromises()
	}

	/**
	 * Press a key on the field.
	 *
	 * @param {string} key The key.
	 */
	async function press(key) {
		field.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
		await flushPromises()
	}

	const panel = () => document.querySelector('[data-testid="header-search-dropdown"]')
	const selected = () => document.querySelector('[role="option"][aria-selected="true"]')

	it('makes the core field a combobox without touching its words', () => {
		wrapper = mount(HeaderSearchDropdown, { attachTo: document.body })
		expect(field.getAttribute('role')).toBe('combobox')
		expect(field.getAttribute('aria-expanded')).toBe('false')
		expect(field.getAttribute('aria-controls')).toMatch(/^lp-header-search-/)
		expect(field.placeholder).toBe('Zoek in Zuiddrecht')
	})

	it('stays closed under two characters', async () => {
		await typeQuery('t')
		expect(searchAll).not.toHaveBeenCalled()
		expect(panel()).toBeNull()
	})

	it('opens under the field with the query, the group and the detail lines, first result selected', async () => {
		await typeQuery('team')
		expect(searchAll).toHaveBeenCalledWith('team', expect.any(Object))
		expect(panel().querySelector('[data-testid="header-search-query"]').textContent.trim()).toBe('You searched for team')
		expect(panel().querySelector('[data-testid="header-search-group"]').textContent.trim()).toBe('DASHBOARDS')
		const options = [...panel().querySelectorAll('[data-testid="header-search-option"]')]
		expect(options.map((o) => [
			o.querySelector('.lp-header-search__title').textContent,
			o.querySelector('.lp-header-search__detail').textContent,
		])).toEqual([
			['Permits team', 'Shared by Marieke Jansen · 14 widgets'],
			['Zuiddrecht intranet', 'Group dashboard of Communications'],
		])
		expect(panel().querySelector('[role="listbox"]')).not.toBeNull()
		expect(selected().textContent).toContain('Permits team')
		expect(field.getAttribute('aria-expanded')).toBe('true')
		expect(field.getAttribute('aria-activedescendant')).toBe(selected().id)
		expect(field.value).toBe('team')
	})

	it('Arrow down then Enter opens the next result', async () => {
		await typeQuery('team')
		await press('ArrowDown')
		expect(selected().textContent).toContain('Zuiddrecht intranet')
		await press('Enter')
		expect(assign).toHaveBeenCalledWith('/apps/launchpad/#dashboard/intranet')
		expect(panel()).toBeNull()
	})

	it('Arrow up from the first result wraps to "Show all results", which hands the query to Nextcloud', async () => {
		await typeQuery('team')
		await press('ArrowUp')
		expect(selected().getAttribute('data-testid')).toBe('header-search-show-all')
		await press('Enter')
		expect(performFallback).toHaveBeenCalledWith({ type: 'unified-search', query: 'team' })
	})

	it('Escape closes the list and keeps the query and the focus', async () => {
		await typeQuery('team')
		field.focus()
		await press('Escape')
		expect(panel()).toBeNull()
		expect(field.value).toBe('team')
		expect(document.activeElement).toBe(field)
		expect(field.getAttribute('aria-expanded')).toBe('false')
		expect(field.hasAttribute('aria-activedescendant')).toBe(false)
	})

	it('ends with the tip of the first shortcut', async () => {
		await typeQuery('team', { shortcuts: [{ prefix: '!top', name: 'TOPdesk', urlTemplate: 'https://t/{query}' }] })
		expect(panel().querySelector('[data-testid="header-search-tip"]').textContent.trim())
			.toBe('Tip: type !top to search TOPdesk directly')
	})

	it('shows no tip without shortcuts', async () => {
		await typeQuery('team')
		expect(panel().querySelector('[data-testid="header-search-tip"]')).toBeNull()
	})

	it('attaches once the top bar renders the field later', async () => {
		field.remove()
		wrapper = mount(HeaderSearchDropdown, { attachTo: document.body })
		document.body.appendChild(field)
		await flushPromises()
		await vi.advanceTimersByTimeAsync(0)
		expect(field.getAttribute('role')).toBe('combobox')
	})

	it('lets go of the field on unmount', async () => {
		await typeQuery('team')
		wrapper.unmount()
		wrapper = null
		expect(field.getAttribute('aria-expanded')).toBe('false')
		field.value = 'other'
		field.dispatchEvent(new Event('input'))
		await vi.advanceTimersByTimeAsync(300)
		expect(searchAll).toHaveBeenCalledTimes(1)
	})
})
