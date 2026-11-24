/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget's three-dot menu in edit mode holds "Who sees this widget?",
 * so the rules dialog is reachable by keyboard and not only by right-click
 * (conditional-visibility-editor REQ-CVUI-007, scenario "From the menu").
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-dialog-is-in-the-widgets-menu-req-cvui-007
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetEditMenu from '../WidgetEditMenu.vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	NcActions: { name: 'NcActions', props: ['ariaLabel'], template: '<div class="actions" role="menu"><slot name="icon" /><slot /></div>' },
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button role="menuitem" @click="$emit(\'click\')"><slot /></button>',
	},
}))

/**
 * The menu, with Delete offered unless told otherwise.
 *
 * @param {object} props Prop overrides.
 * @return {object} the wrapper.
 */
function mountMenu(props = {}) {
	return mount(WidgetEditMenu, {
		props: { menuLabel: 'Widget menu', ...props },
		global: { stubs: { DotsVertical: true, Pencil: true, Delete: true, AccountEye: true } },
	})
}

describe('WidgetEditMenu', () => {
	it('offers Edit widget, Who sees this widget? and Delete widget, in that order', () => {
		const items = mountMenu().findAll('[role="menuitem"]').map((item) => item.text())
		expect(items).toEqual(['Edit widget', 'Who sees this widget?', 'Delete widget'])
	})

	it('choosing Who sees this widget? asks for the dialog', async () => {
		const wrapper = mountMenu()
		await wrapper.find('[data-testid="widget-who-sees"]').trigger('click')
		expect(wrapper.emitted('whoSees')).toHaveLength(1)
		expect(wrapper.emitted('edit')).toBeUndefined()
	})

	it('passes Edit and Delete on, and leaves Delete out when removal is locked', async () => {
		const wrapper = mountMenu()
		await wrapper.find('[data-testid="cn-widget-edit-cog-edit"]').trigger('click')
		await wrapper.find('[data-testid="cn-widget-edit-cog-delete"]').trigger('click')
		expect(wrapper.emitted('edit')).toHaveLength(1)
		expect(wrapper.emitted('remove')).toHaveLength(1)
		expect(mountMenu({ removeLocked: true }).find('[data-testid="cn-widget-edit-cog-delete"]').exists()).toBe(false)
	})
})
