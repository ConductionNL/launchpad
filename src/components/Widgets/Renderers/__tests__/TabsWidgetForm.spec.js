/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TabsWidgetForm: add, rename, move and remove tabs; removing a tab with
 * widgets asks first (layout-widgets-replace-multi-tile D1).
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TabsWidgetForm from '../TabsWidgetForm.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcTextField: { name: 'NcTextField', props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">' },
	CnConfirmDialog: { name: 'CnConfirmDialog', props: ['message'], emits: ['confirm', 'close'], template: '<div class="confirm">{{ message }}<button class="yes" @click="$emit(\'confirm\')" /></div>' },
}))

const stored = {
	title: 'Werk',
	tabs: [
		{ id: 'a', title: 'Team', placements: [{ id: 1 }, { id: 2 }] },
		{ id: 'b', title: 'Projects', placements: [] },
	],
}

function last(wrapper) {
	return wrapper.emitted('update:content').at(-1)[0]
}

describe('TabsWidgetForm', () => {
	it('starts a new Tabs widget with one tab', () => {
		const wrapper = mount(TabsWidgetForm, { props: { value: { tabs: [] } } })

		expect(last(wrapper).tabs).toHaveLength(1)
		expect(last(wrapper).tabs[0].title).toBe('Tab 1')
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('adds a tab at the end with a fresh id', async () => {
		const wrapper = mount(TabsWidgetForm, { props: { editingWidget: { content: stored } } })
		await wrapper.find('[data-testid="tabs-widget-form-add"]').trigger('click')

		const tabs = last(wrapper).tabs
		expect(tabs.map((tab) => tab.title)).toEqual(['Team', 'Projects', 'Tab 3'])
		expect(new Set(tabs.map((tab) => tab.id)).size).toBe(3)
		expect(tabs[2].placements).toEqual([])
	})

	it('renames and moves a tab, keeping its widgets', async () => {
		const wrapper = mount(TabsWidgetForm, { props: { editingWidget: { content: stored } } })
		await wrapper.find('[data-testid="tabs-widget-form-name-0"] input, input[data-testid="tabs-widget-form-name-0"]').setValue('Mijn team')
		await wrapper.find('[data-testid="tabs-widget-form-down-0"]').trigger('click')

		const tabs = last(wrapper).tabs
		expect(tabs.map((tab) => tab.title)).toEqual(['Projects', 'Mijn team'])
		expect(tabs[1].placements).toHaveLength(2)
		expect(last(wrapper).title).toBe('Werk')
	})

	it('removes an empty tab at once', async () => {
		const wrapper = mount(TabsWidgetForm, { props: { editingWidget: { content: stored } } })
		await wrapper.find('[data-testid="tabs-widget-form-remove-1"]').trigger('click')

		expect(wrapper.find('.confirm').exists()).toBe(false)
		expect(last(wrapper).tabs.map((tab) => tab.id)).toEqual(['a'])
	})

	it('asks before removing a tab with widgets', async () => {
		const wrapper = mount(TabsWidgetForm, { props: { editingWidget: { content: stored } } })
		await wrapper.find('[data-testid="tabs-widget-form-remove-0"]').trigger('click')

		expect(wrapper.find('.confirm').text()).toContain('The tab Team holds 2 widgets.')
		expect(last(wrapper).tabs).toHaveLength(2)
		await wrapper.find('.confirm .yes').trigger('click')
		expect(last(wrapper).tabs.map((tab) => tab.id)).toEqual(['b'])
	})

	it('needs at least one tab', async () => {
		const wrapper = mount(TabsWidgetForm, { props: { editingWidget: { content: { tabs: [stored.tabs[1]] } } } })
		await wrapper.find('[data-testid="tabs-widget-form-remove-0"]').trigger('click')

		expect(wrapper.vm.validate()).toEqual(['Add at least one tab.'])
	})
})
