/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Filter by detail": offered only for select fields with options, and emits
 * the filter to apply, or null once the field or value is cleared.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DetailFilter from '../DetailFilter.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template: '<div class="select">{{ inputLabel }}</div>',
	},
}))

const fields = [
	{ key: 'team', label: 'Team', type: 'select', options: ['Red', 'Blue'] },
	{ key: 'tags', label: 'Tags', type: 'multi-select', options: ['a'] },
	{ key: 'empty', label: 'Empty', type: 'select', options: [] },
	{ key: 'note', label: 'Note', type: 'text', options: ['x'] },
	{ key: 'broken', label: 'Broken', type: 'select' },
]

describe('DetailFilter', () => {
	it('offers only select fields that have options', () => {
		const wrapper = mount(DetailFilter, { props: { fields } })

		expect(wrapper.findComponent({ name: 'NcSelect' }).props('options').map((f) => f.key)).toEqual(['team', 'tags'])
		expect(wrapper.findAllComponents({ name: 'NcSelect' })).toHaveLength(1)
	})

	it('renders nothing without a filterable field', () => {
		const wrapper = mount(DetailFilter, { props: { fields: [fields[2], fields[3]] } })

		expect(wrapper.find('[data-testid="detail-filter"]').exists()).toBe(false)
	})

	it('emits the filter once a field and a value are chosen, and null when either is cleared', async () => {
		const wrapper = mount(DetailFilter, { props: { fields } })

		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', fields[0])
		const valueSelect = wrapper.findAllComponents({ name: 'NcSelect' })[1]
		expect(valueSelect.props('inputLabel')).toBe('Team')
		expect(valueSelect.props('options')).toEqual(['Red', 'Blue'])

		await valueSelect.vm.$emit('update:modelValue', 'Blue')
		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', fields[1])
		expect(wrapper.vm.value).toBeNull()

		await wrapper.findAllComponents({ name: 'NcSelect' })[1].vm.$emit('update:modelValue', 'a')
		await wrapper.findAllComponents({ name: 'NcSelect' })[1].vm.$emit('update:modelValue', null)

		expect(wrapper.emitted('change')).toEqual([[null], [{ team: 'Blue' }], [null], [{ tags: 'a' }], [null]])
	})
})
