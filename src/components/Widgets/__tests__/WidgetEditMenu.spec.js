/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WidgetEditMenu.vue`: the widget's edit-mode menu
 * holds Edit widget, Who sees this widget? and Delete widget
 * (visibility-dialog-board-sync, REQ-CVUI-007).
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import WidgetEditMenu from '../WidgetEditMenu.vue'

const stubs = {
	NcActions: { template: '<div class="actions-stub"><slot name="icon" /><slot /></div>' },
	NcActionButton: {
		emits: ['click'],
		template: '<button class="action-stub" @click="$emit(\'click\')"><slot /></button>',
	},
}

function mountMenu(props = {}) {
	return mount(WidgetEditMenu, { propsData: props, global: { stubs } })
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('WidgetEditMenu', () => {
	it('lists Edit widget, Who sees this widget? and Delete widget in that order', () => {
		const labels = mountMenu().findAll('.action-stub').map((b) => b.text())
		expect(labels).toEqual(['Edit widget', 'Who sees this widget?', 'Delete widget'])
	})

	it('leaves Delete out when the widget may not be removed, and keeps the rules item', () => {
		const labels = mountMenu({ removeLocked: true }).findAll('.action-stub').map((b) => b.text())
		expect(labels).toEqual(['Edit widget', 'Who sees this widget?'])
	})

	it('emits edit, visibility and remove from its items', async () => {
		const wrapper = mountMenu()
		await wrapper.find('[data-testid="cn-widget-edit-cog-edit"]').trigger('click')
		await wrapper.find('[data-testid="widget-menu-visibility"]').trigger('click')
		await wrapper.find('[data-testid="cn-widget-edit-cog-delete"]').trigger('click')
		expect(Object.keys(wrapper.emitted())).toEqual(expect.arrayContaining(['edit', 'visibility', 'remove']))
		expect(wrapper.emitted('visibility')).toHaveLength(1)
	})
})
