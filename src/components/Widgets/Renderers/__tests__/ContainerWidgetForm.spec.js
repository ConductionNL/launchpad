/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ContainerWidgetForm.vue` (REQ-CONT-007 as modified
 * by launcher-tile-sorting): the three communal fields plus exactly one
 * "Sort tiles" select, and the emitted content carries `sortBy`.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ContainerWidgetForm from '../ContainerWidgetForm.vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnContainerWidgetForm: {
		name: 'CnContainerWidgetForm',
		emits: ['update:content'],
		template:
			'<div class="communal"><span class="bg" /><span class="padding" /><span class="title" /></div>',
	},
}))

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel'],
	emits: ['update:modelValue'],
	template:
		'<div class="nc-select" :data-label="inputLabel">{{ modelValue }}</div>',
}

describe('ContainerWidgetForm', () => {
	beforeEach(() => {
		globalThis.t = (_app, key) => key
	})

	function mountForm(props = {}) {
		return mount(ContainerWidgetForm, {
			props,
			global: { stubs: { NcSelect: NcSelectStub } },
		})
	}

	it('renders the communal form plus exactly one Sort tiles select, defaulting to by hand', () => {
		const wrapper = mountForm()
		expect(wrapper.find('.communal').exists()).toBe(true)
		const selects = wrapper.findAll('.nc-select')
		expect(selects).toHaveLength(1)
		expect(selects[0].attributes('data-label')).toBe('Sort tiles')
		expect(selects[0].text()).toBe('manual')
		const values = wrapper
			.findComponent(NcSelectStub)
			.props('options')
			.map((option) => option.value)
		expect(values).toEqual([
			'manual',
			'alphabetical',
			'most-used',
			'last-used',
			'random',
		])
	})

	it('emits the communal fields together with sortBy', async () => {
		const wrapper = mountForm({
			editingWidget: {
				content: {
					title: 'Applicaties',
					padding: 'small',
					placements: [{ id: 1 }],
					sortBy: 'most-used',
				},
			},
		})
		expect(wrapper.find('.nc-select').text()).toBe('most-used')

		wrapper
			.findComponent(NcSelectStub)
			.vm.$emit('update:modelValue', 'alphabetical')
		wrapper
			.findComponent({ name: 'CnContainerWidgetForm' })
			.vm.$emit('update:content', {
				placements: [{ id: 1 }],
				backgroundColor: '#fff',
				padding: 'small',
				title: 'Apps',
			})

		const emitted = wrapper.emitted('update:content')
		expect(emitted[0][0]).toMatchObject({
			title: 'Applicaties',
			sortBy: 'alphabetical',
		})
		expect(emitted[1][0]).toEqual({
			placements: [{ id: 1 }],
			backgroundColor: '#fff',
			padding: 'small',
			title: 'Apps',
			sortBy: 'alphabetical',
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('drops an unknown sort mode back to by hand', () => {
		expect(
			mountForm({ value: { sortBy: 'sideways' } })
				.find('.nc-select')
				.text(),
		).toBe('manual')
	})
})
