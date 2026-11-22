/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * WikiPagesWidgetForm: the space and the list length (wiki-widget REQ-WIKI-003).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({ ...(await importOriginal()), translate: (_app, text) => text }))

import WikiPagesWidgetForm from '../WikiPagesWidgetForm.vue'

const NcTextField = { name: 'NcTextField', props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<input :value="modelValue">' }

/**
 * Mount the form.
 *
 * @param {object} props The props.
 * @return {object} The wrapper.
 */
function mountForm(props = {}) {
	return mount(WikiPagesWidgetForm, { props, global: { stubs: { NcTextField } } })
}

describe('WikiPagesWidgetForm', () => {
	it('starts from the placement content and emits the trimmed space with the limit', async () => {
		const wrapper = mountForm({ editingWidget: { content: { space: 'Vergunningen', limit: 5 } } })
		const fields = wrapper.findAllComponents(NcTextField)
		expect(fields[0].props('modelValue')).toBe('Vergunningen')
		expect(fields[1].props('modelValue')).toBe('5')
		fields[0].vm.$emit('update:modelValue', ' Kennisbank ')
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ space: 'Kennisbank', limit: 5 })
	})

	it('accepts 1 to 20 pages per list', () => {
		const wrapper = mountForm()
		expect(wrapper.vm.validate()).toEqual([])
		wrapper.vm.update({ limit: 0 })
		expect(wrapper.vm.validate()).toEqual(['Show 1 to 20 pages per list'])
		wrapper.vm.update({ limit: 21 })
		expect(wrapper.vm.validate()).toHaveLength(1)
	})
})
