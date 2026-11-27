/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ContainerWidgetForm: "Apps from the app menu" turns the well's app list on
 * and off and stores its four settings (REQ-CONT-009).
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ContainerWidgetForm from '../ContainerWidgetForm.vue'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (app, key, fallback) => (app === 'core' && key === 'apps'
		? [{ id: 'launchpad', name: 'Launchpad', order: 0 }, { id: 'files', name: 'Files', order: 1 }, { id: 'spreed', name: 'Talk', order: 2 }]
		: fallback),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnContainerWidgetForm: { name: 'CnContainerWidgetForm', template: '<div />' },
}))
vi.mock('@nextcloud/vue', () => ({
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<button @click="$emit(\'update:modelValue\', !modelValue)" />',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template: '<div class="nc-select" :data-label="inputLabel" />',
	},
}))

function last(wrapper) {
	return wrapper.emitted('update:content').at(-1)[0]
}

describe('ContainerWidgetForm app list (REQ-CONT-009)', () => {
	it('is off by default, and on lists the menu without Launchpad', async () => {
		const wrapper = mount(ContainerWidgetForm)
		expect(wrapper.find('[data-testid="container-app-list-pinned"]').exists()).toBe(false)

		await wrapper.find('[data-testid="container-app-list-on"]').trigger('click')

		expect(last(wrapper).appList).toEqual({ pinned: [], exclude: ['launchpad'], mostUsedLimit: 0, showFilter: false })
		const pinned = wrapper.findComponent('[data-testid="container-app-list-pinned"]')
		expect(pinned.props('options').map((option) => option.value)).toEqual(['files', 'spreed'])
	})

	it('stores pinned, excluded, the limit and the filter', async () => {
		const wrapper = mount(ContainerWidgetForm, { props: { editingWidget: { content: { appList: { pinned: ['files'] } } } } })

		wrapper.findComponent('[data-testid="container-app-list-exclude"]').vm.$emit('update:modelValue', ['spreed'])
		await wrapper.find('[data-testid="container-app-list-limit"]').setValue('8')
		await wrapper.find('[data-testid="container-app-list-filter"]').trigger('click')

		expect(last(wrapper).appList).toEqual({ pinned: ['files'], exclude: ['spreed'], mostUsedLimit: 8, showFilter: true })
	})

	it('offers the drawn limits 4, 8, 12 and All, and keeps a stored limit that is none of them (board LpWellInstellen)', () => {
		const options = (wrapper) => wrapper.findAll('[data-testid="container-app-list-limit"] option')
			.map((option) => [option.attributes('value'), option.text()])

		const fresh = mount(ContainerWidgetForm, { props: { editingWidget: { content: { appList: {} } } } })
		expect(options(fresh)).toEqual([['4', '4'], ['8', '8'], ['12', '12'], ['0', 'All']])
		expect(fresh.find('[data-testid="container-app-list-limit"]').element.value).toBe('0')

		const migrated = mount(ContainerWidgetForm, { props: { editingWidget: { content: { appList: { mostUsedLimit: 6 } } } } })
		expect(options(migrated)).toEqual([['4', '4'], ['6', '6'], ['8', '8'], ['12', '12'], ['0', 'All']])
		expect(migrated.find('[data-testid="container-app-list-limit"]').element.value).toBe('6')
	})

	it('draws the introduction and the switch row with its title and hint', () => {
		const wrapper = mount(ContainerWidgetForm)
		const section = wrapper.find('[data-testid="container-app-list"]')
		expect(section.find('.container-widget-form__intro').text()).toContain('A well puts tiles together')
		expect(section.find('#container-app-list-title').text()).toBe('Apps from the app menu')
		expect(section.attributes('aria-labelledby')).toBe('container-app-list-title')
		expect(section.find('[data-testid="container-app-list-settings"]').exists()).toBe(false)
	})

	it('turned off, the content carries no app list', async () => {
		const wrapper = mount(ContainerWidgetForm, { props: { editingWidget: { content: { appList: { pinned: ['files'] } } } } })
		await wrapper.find('[data-testid="container-app-list-on"]').trigger('click')

		expect('appList' in last(wrapper)).toBe(false)
	})
})
