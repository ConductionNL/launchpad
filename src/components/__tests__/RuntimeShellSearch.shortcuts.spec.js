/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for search shortcuts in `RuntimeShellSearch.vue` and
 * `useTileSearch.js` (search-ai-prefix-shortcuts, REQ-SPX-002, REQ-SPX-003):
 * a known prefix shows one result naming the site and opens it on Enter,
 * an unknown prefix falls through to tile filtering, and `?` or `!` lists
 * the shortcuts.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import RuntimeShellSearch from '../RuntimeShellSearch.vue'
import { parsePrefix, shortcutUrl } from '../../composables/useTileSearch.js'

const SHORTCUTS = [
	{
		prefix: '!t',
		name: 'TOPdesk',
		urlTemplate: 'https://topdesk.gemeente.nl/tas/secure/search?q={query}',
	},
	{
		prefix: '!z',
		name: 'Zaaksysteem',
		urlTemplate: 'https://zaken.gemeente.nl/zoek?q={query}',
	},
]
const ITEMS = [
	{ id: 'r1', label: '!x rapport' },
	{ id: 'a1', label: 'Agenda' },
]

describe('search shortcuts', () => {
	let wrapper

	beforeEach(() => {
		globalThis.t = (_app, key) => key
	})

	afterEach(() => {
		wrapper?.unmount()
	})

	function mountSearch() {
		wrapper = mount(RuntimeShellSearch, {
			attachTo: document.body,
			props: { items: ITEMS, fallbackTarget: 'none', shortcuts: SHORTCUTS },
		})
		return wrapper
	}

	async function type(value) {
		const input = wrapper.find('input')
		input.element.value = value
		await input.trigger('input')
	}

	it('parses the first word as a prefix, case-insensitively, and encodes the rest', () => {
		expect(parsePrefix('!T printer 3e verdieping', SHORTCUTS)).toEqual({
			shortcut: SHORTCUTS[0],
			rest: 'printer 3e verdieping',
		})
		expect(parsePrefix('!x rapport', SHORTCUTS)).toBeNull()
		expect(parsePrefix('printer !t', SHORTCUTS)).toBeNull()
		expect(shortcutUrl(SHORTCUTS[0], 'printer 3e verdieping')).toBe(
			'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping',
		)
		expect(
			shortcutUrl({ urlTemplate: 'javascript:alert({query})' }, 'x'),
		).toBeNull()
	})

	it('REQ-SPX-002: the only result names the site and Enter opens it, without dimming tiles', async () => {
		mountSearch()
		await type('!t printer 3e verdieping')

		const options = wrapper.findAll('[data-test="quick-search-option"]')
		expect(options).toHaveLength(1)
		expect(options[0].text()).toBe('Search TOPdesk for printer 3e verdieping')
		expect(wrapper.emitted('filter').at(-1)).toEqual([null])

		await wrapper.find('input').trigger('keydown', { key: 'Enter' })
		expect(wrapper.emitted('fallback').at(-1)).toEqual([
			{
				type: 'web-search',
				url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping',
			},
		])
		expect(wrapper.emitted('open')).toBeUndefined()
	})

	it('REQ-SPX-002: a prefix with nothing after it opens nothing', async () => {
		mountSearch()
		await type('!t')
		await wrapper.find('input').trigger('keydown', { key: 'Enter' })
		expect(wrapper.emitted('fallback')).toBeUndefined()
	})

	it('REQ-SPX-002: an unknown prefix filters tiles as before', async () => {
		mountSearch()
		await type('!x rapport')

		const options = wrapper.findAll('[data-test="quick-search-option"]')
		expect(options.map((option) => option.text())).toEqual(['!x rapport'])
		expect(wrapper.emitted('filter').at(-1)).toEqual([['r1']])
	})

	it('REQ-SPX-003: ? lists the shortcuts in a labelled list, and Enter types the prefix', async () => {
		mountSearch()
		await type('?')

		const list = wrapper.find('[role="listbox"]')
		expect(list.attributes('aria-label')).toBe('Search shortcuts')
		expect(
			wrapper
				.findAll('[data-test="quick-search-option"]')
				.map((option) => option.text()),
		).toEqual(['!t TOPdesk', '!z Zaaksysteem'])
		expect(wrapper.find('[data-test="quick-search-status"]').text()).toBe(
			'!t TOPdesk, !z Zaaksysteem',
		)

		await wrapper.find('input').trigger('keydown', { key: 'Enter' })
		expect(wrapper.vm.search.state.query).toBe('!t ')
		expect(wrapper.emitted('fallback')).toBeUndefined()
	})

	it('REQ-SPX-003: ! alone lists them too; without shortcuts ? is an ordinary query', async () => {
		mountSearch()
		await type('!')
		expect(wrapper.findAll('[data-test="quick-search-option"]')).toHaveLength(2)

		await wrapper.setProps({ shortcuts: [] })
		await type('?')
		expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
	})
})
