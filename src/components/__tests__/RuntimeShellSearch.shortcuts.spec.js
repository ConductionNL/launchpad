/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for the prefix shortcuts in `RuntimeShellSearch.vue`
 * (search-ai-prefix-shortcuts REQ-SPX-002, REQ-SPX-003): the single
 * "Search <name> for <rest>" result, Enter opening the site through the
 * `fallback` event, the `?` / `!` list, and what the live region and the
 * listbox name say for them.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md
 */

import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import RuntimeShellSearch from '../RuntimeShellSearch.vue'

const ITEMS = [
	{ id: 'z1', label: 'Zaaksysteem' },
	{ id: 'r1', label: 'Rapportage' },
]

const SHORTCUTS = [
	{ prefix: '!t', name: 'TOPdesk', urlTemplate: 'https://topdesk.gemeente.nl/tas/secure/search?q={query}' },
	{ prefix: '!z', name: 'Zaaksysteem', urlTemplate: 'https://zaken.gemeente.nl/zoek?q={query}' },
]

let wrappers = []

afterEach(() => {
	wrappers.forEach((wrapper) => wrapper.unmount())
	wrappers = []
})

/**
 * @param {object} [props] prop overrides.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountSearch(props = {}) {
	const wrapper = mount(RuntimeShellSearch, {
		attachTo: document.body,
		props: { items: ITEMS, fallbackTarget: 'none', shortcuts: SHORTCUTS, ...props },
	})
	wrappers.push(wrapper)
	return wrapper
}

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper the bar.
 * @param {string} text the query.
 * @return {Promise<void>}
 */
async function type(wrapper, text) {
	await wrapper.find('input').setValue(text)
}

describe('RuntimeShellSearch prefix shortcuts (REQ-SPX-002)', () => {
	it('shows one result naming the site and the rest of the query', async () => {
		const wrapper = mountSearch()
		await type(wrapper, '!t printer 3e verdieping')

		const options = wrapper.findAll('[data-test="quick-search-option"]')
		expect(options).toHaveLength(1)
		expect(options[0].text()).toBe('Search TOPdesk for printer 3e verdieping')
		expect(wrapper.find('[role="listbox"]').attributes('aria-label')).toBe('Search shortcuts')
		expect(wrapper.find('[data-test="quick-search-status"]').text()).toBe('Search TOPdesk for printer 3e verdieping')
	})

	it('opens the encoded address through the fallback event on Enter, not a tile', async () => {
		const wrapper = mountSearch()
		await type(wrapper, '!T printer 3e verdieping')
		await wrapper.find('input').trigger('keydown', { key: 'Enter' })

		expect(wrapper.emitted('open')).toBeUndefined()
		expect(wrapper.emitted('fallback')).toEqual([[{
			type: 'web-search',
			url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping',
		}]])
	})

	it('filters tiles as before for an unknown prefix', async () => {
		const wrapper = mountSearch()
		await type(wrapper, '!x rapport')

		expect(wrapper.findAll('[data-test="quick-search-option"]')).toHaveLength(0)
		expect(wrapper.find('[data-test="quick-search-empty"]').exists()).toBe(true)
	})

	it('ranks tiles as before without shortcuts', async () => {
		const wrapper = mountSearch({ shortcuts: [] })
		await type(wrapper, 'rapport')

		const options = wrapper.findAll('[data-test="quick-search-option"]')
		expect(options.map((option) => option.text())).toEqual(['Rapportage'])
		expect(wrapper.find('[role="listbox"]').attributes('aria-label')).toBe('Matching tiles')
	})
})

describe('RuntimeShellSearch shortcut list (REQ-SPX-003)', () => {
	it.each(['?', '!'])('lists every shortcut with its prefix and name for %s', async (query) => {
		const wrapper = mountSearch()
		await type(wrapper, query)

		const options = wrapper.findAll('[data-test="quick-search-option"]')
		expect(options.map((option) => option.text())).toEqual(['!t TOPdesk', '!z Zaaksysteem'])
		expect(wrapper.find('[data-test="quick-search-status"]').text())
			.toBe('Search shortcuts: !t TOPdesk, !z Zaaksysteem')
	})

	it('starts a query with the chosen shortcut on Enter, opening nothing', async () => {
		const wrapper = mountSearch()
		await type(wrapper, '?')
		await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' })
		await wrapper.find('input').trigger('keydown', { key: 'Enter' })

		expect(wrapper.find('input').element.value).toBe('!z ')
		expect(wrapper.emitted('fallback')).toBeUndefined()
		expect(wrapper.emitted('open')).toBeUndefined()
	})
})
