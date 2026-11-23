/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `RecentWorkWidgetForm.vue`: the number of items the
 * recent-work widget lists, its default, its 1 to 20 clamp and what the form
 * emits to the add/edit modal.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RecentWorkWidgetForm from '../RecentWorkWidgetForm.vue'

/**
 * @param {object} [props] props for the form.
 * @return {import('@vue/test-utils').VueWrapper} the mounted form.
 */
function mountForm(props = {}) {
	return mount(RecentWorkWidgetForm, {
		propsData: props,
		global: { stubs: { NcTextField: true } },
	})
}

describe('RecentWorkWidgetForm: initial number of items', () => {
	it('starts at 6 in create mode', () => {
		expect(mountForm().vm.limit).toBe(6)
	})

	it('takes the limit of the placement being edited over the initial value', () => {
		const wrapper = mountForm({ editingWidget: { content: { limit: 9 } }, value: { limit: 3 } })
		expect(wrapper.vm.limit).toBe(9)
	})

	it('takes the initial value when nothing is being edited', () => {
		expect(mountForm({ value: { limit: '4' } }).vm.limit).toBe(4)
	})

	it('falls back to 6 for a stored limit that is not a positive number', () => {
		expect(mountForm({ value: { limit: 0 } }).vm.limit).toBe(6)
		expect(mountForm({ value: { limit: 'many' } }).vm.limit).toBe(6)
	})

	it('shows the limit in the number field', () => {
		const field = mountForm({ value: { limit: 7 } }).find('[data-testid="recent-work-form-limit"]')
		expect(field.attributes('modelvalue')).toBe('7')
	})
})

describe('RecentWorkWidgetForm: typing a number', () => {
	it('emits the typed number as the content limit', () => {
		const wrapper = mountForm()
		wrapper.vm.update('12')
		expect(wrapper.vm.limit).toBe(12)
		expect(wrapper.emitted('update:content')).toEqual([[{ limit: 12 }]])
	})

	it('rounds a fraction to the nearest whole number', () => {
		const wrapper = mountForm()
		wrapper.vm.update('3.6')
		expect(wrapper.emitted('update:content').at(-1)).toEqual([{ limit: 4 }])
	})

	it('clamps to at least 1 and at most 20', () => {
		const wrapper = mountForm()
		wrapper.vm.update('-5')
		expect(wrapper.emitted('update:content').at(-1)).toEqual([{ limit: 1 }])
		wrapper.vm.update(250)
		expect(wrapper.emitted('update:content').at(-1)).toEqual([{ limit: 20 }])
	})

	it('goes back to 6 when the field holds no number', () => {
		const wrapper = mountForm({ value: { limit: 10 } })
		wrapper.vm.update('abc')
		expect(wrapper.emitted('update:content').at(-1)).toEqual([{ limit: 6 }])
	})

	it('updates through the number field event', async () => {
		const wrapper = mountForm()
		await wrapper.findComponent({ name: 'NcTextField' }).vm.$emit('update:modelValue', '8')
		expect(wrapper.emitted('update:content').at(-1)).toEqual([{ limit: 8 }])
	})
})
