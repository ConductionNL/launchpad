/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ContainerWidgetForm.vue` (launcher-tile-sorting,
 * REQ-CONT-007): the three communal fields plus exactly one "Sort tiles"
 * select, seeded from the widget being edited, and every emit carrying the
 * communal fields together with `sortBy`.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ContainerWidgetForm from '../ContainerWidgetForm.vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnContainerWidgetForm: {
		name: 'CnContainerWidgetForm',
		props: ['editingWidget', 'value'],
		emits: ['update:content'],
		template: '<div class="communal-form" />',
	},
}))

vi.mock('@nextcloud/vue', () => ({
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template: '<div class="nc-select" :data-label="inputLabel" />',
	},
}))

describe('ContainerWidgetForm', () => {
	it('renders the communal form plus one "Sort tiles" select, by hand by default', () => {
		const wrapper = mount(ContainerWidgetForm)
		expect(wrapper.findAll('.communal-form')).toHaveLength(1)
		const selects = wrapper.findAllComponents({ name: 'NcSelect' })
		expect(selects).toHaveLength(1)
		expect(selects[0].props('inputLabel')).toBe('Sort tiles')
		expect(selects[0].props('modelValue')).toBe('manual')
		expect(selects[0].props('options').map((o) => o.value)).toEqual(['manual', 'alphabetical', 'most-used', 'last-used', 'random'])
	})

	it('seeds the sort from the widget being edited, and an unknown one as by hand', () => {
		const edited = mount(ContainerWidgetForm, { props: { editingWidget: { content: { title: 'Apps', sortBy: 'most-used' } } } })
		expect(edited.vm.sortBy).toBe('most-used')
		const unknown = mount(ContainerWidgetForm, { props: { value: { sortBy: 'colour' } } })
		expect(unknown.vm.sortBy).toBe('manual')
	})

	it('emits the communal fields together with the sort', async () => {
		const placements = [{ id: 1 }]
		const wrapper = mount(ContainerWidgetForm, { props: { editingWidget: { content: { placements, title: 'Apps', sortBy: 'random' } } } })

		wrapper.findComponent({ name: 'CnContainerWidgetForm' }).vm.$emit('update:content', { placements, backgroundColor: '#fff', padding: 'small', title: 'Applicaties' })
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ placements, backgroundColor: '#fff', padding: 'small', title: 'Applicaties', sortBy: 'random' })

		wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', 'alphabetical')
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ placements, backgroundColor: '#fff', padding: 'small', title: 'Applicaties', sortBy: 'alphabetical' })
		expect(wrapper.vm.validate()).toEqual([])
	})
})
