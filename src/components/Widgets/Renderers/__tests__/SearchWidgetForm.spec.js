/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `SearchWidgetForm.vue` (tile-quick-search
 * REQ-QSEARCH-005): the persisted content shape, the inherit/override
 * semantics of the fallback setting, and the web-search template validation.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import SearchWidgetForm from '../SearchWidgetForm.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

/**
 * @param {object} [content] initial content.
 * @return {import('@vue/test-utils').VueWrapper} the mounted form.
 */
function mountForm(content = {}) {
	return mount(SearchWidgetForm, {
		propsData: { value: content },
		global: { stubs: { NcTextField: true, NcSelect: true } },
	})
}

describe('SearchWidgetForm — persisted shape (REQ-QSEARCH-005)', () => {
	it('defaults both settings to "inherit / built-in"', () => {
		const wrapper = mountForm()
		expect(wrapper.vm.assembledContent).toEqual({
			placeholder: '',
			fallbackTarget: '',
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('persists a custom placeholder', () => {
		const wrapper = mountForm()
		wrapper.vm.onPlaceholderChange('Find an application…')
		expect(wrapper.vm.assembledContent.placeholder).toBe('Find an application…')
		expect(wrapper.emitted('update:content')).toBeTruthy()
	})

	it('persists an empty fallbackTarget when "inherit" is chosen', () => {
		const wrapper = mountForm({ fallbackTarget: 'none' })
		expect(wrapper.vm.fallbackMode).toBe('none')

		wrapper.vm.onFallbackModeChange('')
		expect(wrapper.vm.assembledContent.fallbackTarget).toBe('')
	})

	it('round-trips each non-template mode', () => {
		for (const mode of ['none', 'unified-search']) {
			const wrapper = mountForm({ fallbackTarget: mode })
			expect(wrapper.vm.fallbackMode).toBe(mode)
			expect(wrapper.vm.assembledContent.fallbackTarget).toBe(mode)
			expect(wrapper.vm.validate()).toEqual([])
		}
	})

	it('classifies a stored URL template as the web-search mode and round-trips it', () => {
		const template = 'https://example.org/search?q={query}'
		const wrapper = mountForm({ fallbackTarget: template })
		expect(wrapper.vm.fallbackMode).toBe('web-search')
		expect(wrapper.vm.fallbackTemplate).toBe(template)
		expect(wrapper.vm.assembledContent.fallbackTarget).toBe(template)
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('keeps the typed template when the author switches mode away and back', () => {
		const wrapper = mountForm({
			fallbackTarget: 'https://example.org/search?q={query}',
		})
		wrapper.vm.onFallbackModeChange('none')
		expect(wrapper.vm.assembledContent.fallbackTarget).toBe('none')

		wrapper.vm.onFallbackModeChange('web-search')
		expect(wrapper.vm.assembledContent.fallbackTarget).toBe(
			'https://example.org/search?q={query}',
		)
	})
})

describe('SearchWidgetForm — validation (REQ-QSEARCH-005)', () => {
	it('requires a template in the web-search mode', () => {
		const wrapper = mountForm()
		wrapper.vm.onFallbackModeChange('web-search')
		expect(wrapper.vm.validate()).toHaveLength(1)
	})

	it('rejects a non-https template', () => {
		const wrapper = mountForm()
		wrapper.vm.onFallbackModeChange('web-search')
		wrapper.vm.onTemplateChange('http://example.org/search?q={query}')
		expect(wrapper.vm.validate()).toHaveLength(1)
		expect(wrapper.vm.templateError).not.toBe('')
	})

	it('rejects a template with no {query} placeholder', () => {
		const wrapper = mountForm()
		wrapper.vm.onFallbackModeChange('web-search')
		wrapper.vm.onTemplateChange('https://example.org/search')
		expect(wrapper.vm.validate()).toHaveLength(1)
	})

	it('accepts a valid https template with {query}', () => {
		const wrapper = mountForm()
		wrapper.vm.onFallbackModeChange('web-search')
		wrapper.vm.onTemplateChange('https://example.org/search?q={query}')
		expect(wrapper.vm.validate()).toEqual([])
		expect(wrapper.vm.templateError).toBe('')
	})

	it('never blocks submission on an empty placeholder — empty means "use the default"', () => {
		const wrapper = mountForm({ placeholder: '' })
		expect(wrapper.vm.validate()).toEqual([])
	})
})
