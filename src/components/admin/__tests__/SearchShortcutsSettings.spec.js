/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `SearchShortcutsSettings.vue`, the admin section that
 * edits the quick-search prefix shortcuts (search-ai-prefix-shortcuts
 * REQ-SPX-001). The `api` module is mocked at the import boundary; the
 * field stubs round-trip `v-model` through a real `<input>`.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
 */

import { showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SearchShortcutsSettings, { MAX_SEARCH_SHORTCUTS, validateSearchShortcuts } from '../SearchShortcutsSettings.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: { updateAdminSettings: vi.fn() },
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

const stubs = {
	CnSettingsSection: { name: 'CnSettingsSection', props: ['name'], template: '<section><h2>{{ name }}</h2><slot /></section>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

const TOPDESK = { prefix: '!t', name: 'TOPdesk', urlTemplate: 'https://topdesk.gemeente.nl/tas/secure/search?q={query}' }

/**
 * @param {Array} [stored] the `searchShortcuts` initial state.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountWith(stored) {
	const provide = stored === undefined ? {} : { searchShortcuts: stored }
	return mount(SearchShortcutsSettings, { global: { stubs, provide } })
}

/**
 * Fill the last row.
 *
 * @param {import('@vue/test-utils').VueWrapper} wrapper the section.
 * @param {object} shortcut the values.
 * @return {Promise<void>}
 */
async function fillLastRow(wrapper, shortcut) {
	const row = wrapper.findAll('[data-test="search-shortcut-row"]').at(-1)
	const inputs = row.findAll('input')
	await inputs[0].setValue(shortcut.prefix)
	await inputs[1].setValue(shortcut.name)
	await inputs[2].setValue(shortcut.urlTemplate)
}

beforeEach(() => {
	api.updateAdminSettings.mockReset().mockResolvedValue({ data: { status: 'ok' } })
	showSuccess.mockReset()
})

describe('SearchShortcutsSettings (REQ-SPX-001)', () => {
	it('starts empty without stored shortcuts', () => {
		const wrapper = mountWith()
		expect(wrapper.findAll('[data-test="search-shortcut-row"]')).toHaveLength(0)
		expect(wrapper.find('[data-test="search-shortcuts-empty"]').exists()).toBe(true)
	})

	it('lists the stored shortcuts', () => {
		const wrapper = mountWith([TOPDESK])
		const inputs = wrapper.find('[data-test="search-shortcut-row"]').findAll('input')
		expect(inputs.map((input) => input.element.value)).toEqual(['!t', 'TOPdesk', TOPDESK.urlTemplate])
	})

	it('adds the TOPdesk shortcut and saves the whole trimmed list', async () => {
		const wrapper = mountWith([])
		await wrapper.find('[data-test="search-shortcut-add"]').trigger('click')
		await fillLastRow(wrapper, { ...TOPDESK, name: '  TOPdesk ' })
		await wrapper.find('[data-test="search-shortcuts-save"]').trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledExactlyOnceWith({ searchShortcuts: [TOPDESK] })
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(wrapper.find('[data-test="search-shortcuts-error"]').exists()).toBe(false)
	})

	it('refuses plain http before saving and names the rule', async () => {
		const wrapper = mountWith([])
		await wrapper.find('[data-test="search-shortcut-add"]').trigger('click')
		await fillLastRow(wrapper, { prefix: '!i', name: 'Intranet', urlTemplate: 'http://intranet.gemeente.nl/zoek?q={query}' })
		await wrapper.find('[data-test="search-shortcuts-save"]').trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).not.toHaveBeenCalled()
		const error = wrapper.find('[data-test="search-shortcuts-error"]')
		expect(error.attributes('role')).toBe('alert')
		expect(error.text()).toBe('Use an https address that contains {query}')
	})

	it('removes a row and saves the empty list', async () => {
		const wrapper = mountWith([TOPDESK])
		await wrapper.find('[data-test="search-shortcut-remove"]').trigger('click')
		await wrapper.find('[data-test="search-shortcuts-save"]').trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledExactlyOnceWith({ searchShortcuts: [] })
	})

	it('shows the server refusal when the save fails', async () => {
		api.updateAdminSettings.mockRejectedValue({ response: { data: { error: 'The search shortcut prefix !t is used twice.' } } })
		const wrapper = mountWith([TOPDESK])
		await wrapper.find('[data-test="search-shortcuts-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-test="search-shortcuts-error"]').text()).toBe('The search shortcut prefix !t is used twice.')
		expect(showSuccess).not.toHaveBeenCalled()
	})

	it('disables adding at the limit', () => {
		const stored = Array.from({ length: MAX_SEARCH_SHORTCUTS }, (_, i) => ({ ...TOPDESK, prefix: '!s' + i }))
		const wrapper = mountWith(stored)
		expect(wrapper.find('[data-test="search-shortcut-add"]').attributes('disabled')).toBeDefined()
	})
})

describe('validateSearchShortcuts', () => {
	it('accepts a valid list', () => {
		expect(validateSearchShortcuts([TOPDESK, { ...TOPDESK, prefix: '!Z9' }])).toBeNull()
	})

	it.each([
		['a prefix without !', [{ ...TOPDESK, prefix: 't' }], 'A prefix is ! followed by 1 to 10 letters or digits.'],
		['a prefix of 11 characters', [{ ...TOPDESK, prefix: '!abcdefghijk' }], 'A prefix is ! followed by 1 to 10 letters or digits.'],
		['a repeated prefix in another case', [TOPDESK, { ...TOPDESK, prefix: '!T' }], 'The prefix !T is used twice.'],
		['an empty name', [{ ...TOPDESK, name: '' }], 'The shortcut !t needs a name of at most 60 characters.'],
		['a template without {query}', [{ ...TOPDESK, urlTemplate: 'https://t.example/' }], 'Use an https address that contains {query}'],
	])('refuses %s', (_case, list, message) => {
		expect(validateSearchShortcuts(list)).toBe(message)
	})

	it('refuses more than the limit', () => {
		const list = Array.from({ length: MAX_SEARCH_SHORTCUTS + 1 }, (_, i) => ({ ...TOPDESK, prefix: '!s' + i }))
		expect(validateSearchShortcuts(list)).toBe('At most 30 search shortcuts are allowed.')
	})
})
