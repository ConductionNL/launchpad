/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TabsWidget: the tabs render as a WAI-ARIA tab strip, the open tab's
 * placements go to the well's inner grid, the keyboard moves between tabs,
 * and a change on the inner grid is written back into that tab
 * (layout-widgets-replace-multi-tile D1).
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TabsWidget from '../TabsWidget.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('../ContainerWidget.vue', () => ({
	default: {
		name: 'ContainerWidget',
		props: ['content', 'placement', 'editMode'],
		emits: ['update:content'],
		template: '<div class="container-stub">{{ (content.placements || []).map((p) => p.widgetId).join(",") }}</div>',
	},
}))


const content = {
	title: 'Werk',
	tabs: [
		{ id: 'a', title: 'Team', placements: [{ id: 1, widgetId: 'clock' }] },
		{ id: 'b', title: 'Projects', placements: [{ id: 2, widgetId: 'weather' }] },
		{ id: 'c', title: '', placements: [] },
	],
}

function mountTabs(props = {}) {
	return mount(TabsWidget, {
		props: { content, placement: { id: 9 }, ...props },
		attachTo: document.body,
	})
}

describe('TabsWidget', () => {
	it('draws a tablist with one tab per stored tab and the first one open', () => {
		const wrapper = mountTabs()
		const tabs = wrapper.findAll('[role="tab"]')

		expect(wrapper.find('[role="tablist"]').attributes('aria-label')).toBe('Werk')
		expect(tabs.map((tab) => tab.text())).toEqual(['Team', 'Projects', 'Tab 3'])
		expect(tabs[0].attributes('aria-selected')).toBe('true')
		expect(tabs[0].attributes('tabindex')).toBe('0')
		expect(tabs[1].attributes('tabindex')).toBe('-1')
		const panel = wrapper.find('[role="tabpanel"]')
		expect(panel.attributes('aria-labelledby')).toBe(tabs[0].attributes('id'))
		expect(tabs[0].attributes('aria-controls')).toBe(panel.attributes('id'))
		expect(wrapper.find('.container-stub').text()).toBe('clock')
	})

	it('opens a tab on click and shows its widgets', async () => {
		const wrapper = mountTabs()
		await wrapper.findAll('[role="tab"]')[1].trigger('click')

		expect(wrapper.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true')
		expect(wrapper.find('.container-stub').text()).toBe('weather')
	})

	it('moves with the arrow keys, wrapping, and jumps with Home and End', async () => {
		const wrapper = mountTabs()
		const strip = wrapper.find('[role="tablist"]')

		await strip.trigger('keydown', { key: 'ArrowRight' })
		expect(wrapper.vm.activeIndex).toBe(1)
		await strip.trigger('keydown', { key: 'End' })
		expect(wrapper.vm.activeIndex).toBe(2)
		await strip.trigger('keydown', { key: 'ArrowRight' })
		expect(wrapper.vm.activeIndex).toBe(0)
		await strip.trigger('keydown', { key: 'ArrowLeft' })
		expect(wrapper.vm.activeIndex).toBe(2)
		await strip.trigger('keydown', { key: 'Home' })
		expect(wrapper.vm.activeIndex).toBe(0)
		await wrapper.vm.$nextTick()
		expect(document.activeElement).toBe(wrapper.findAll('[role="tab"]')[0].element)
		wrapper.unmount()
	})

	it('writes a change on the inner grid back into the open tab only', async () => {
		const wrapper = mountTabs()
		await wrapper.findAll('[role="tab"]')[1].trigger('click')
		const moved = [{ id: 2, widgetId: 'weather', gridX: 2 }]

		wrapper.findComponent({ name: 'ContainerWidget' }).vm.$emit('update:content', { placements: moved })

		const [emitted] = wrapper.emitted('update:content').at(-1)
		expect(emitted.title).toBe('Werk')
		expect(emitted.tabs[1].placements).toEqual(moved)
		expect(emitted.tabs[0].placements).toEqual(content.tabs[0].placements)
	})

	it('says there are no tabs when there are none', () => {
		const wrapper = mountTabs({ content: { tabs: [] } })

		expect(wrapper.find('[data-testid="tabs-widget-empty"]').exists()).toBe(true)
		expect(wrapper.find('[role="tabpanel"]').exists()).toBe(false)
	})

	it('keeps the open tab in range when tabs are removed', async () => {
		const wrapper = mountTabs()
		await wrapper.findAll('[role="tab"]')[2].trigger('click')
		await wrapper.setProps({ content: { tabs: content.tabs.slice(0, 1) } })

		expect(wrapper.vm.activeIndex).toBe(0)
	})
})
