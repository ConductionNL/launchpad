/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit bar of board LpBewerken: the draft pill, Columns 6, 8 or 12, Add
 * widget, Dashboard settings, Cancel and Save, and the dock for the More menu
 * (REQ-EDIT-001).
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => {
	const fill = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
	return {
		t: (_app, text, vars = {}) => fill(text, vars),
		n: (_app, one, many, count, vars = {}) => fill(count === 1 ? one : many, vars),
	}
})

import DashboardEditBar from '../DashboardEditBar.vue'

/**
 * Mount the bar.
 *
 * @param {object} props Props.
 * @return {object} The wrapper.
 */
function mountBar(props = {}) {
	return mount(DashboardEditBar, {
		props: { dashboardName: 'Mijn werkplek', columns: 12, canConfigure: true, ...props },
	})
}

describe('DashboardEditBar', () => {
	it('is a toolbar naming the dashboard being edited', () => {
		const wrapper = mountBar()
		const bar = wrapper.find('[data-testid="launchpad-edit-bar"]')
		expect(bar.attributes('role')).toBe('toolbar')
		expect(bar.attributes('aria-label')).toBe('Edit dashboard')
		expect(bar.text()).toContain('You are editing Mijn werkplek')
	})

	// add-widget-dialog-with-miniatures REQ-WDG-049: Add widget is the one way in, no Add tiles.
	it('holds Columns, Add widget, Dashboard settings, Cancel and Save, in that order, and no Add tiles or Done', () => {
		const wrapper = mountBar()
		const labels = wrapper.findAll('button').map((b) => b.text())
		expect(labels).toEqual(['6', '8', '12', 'Add widget', 'Dashboard settings', 'Cancel', 'Save'])
		expect(wrapper.find('[data-testid="launchpad-edit-bar-done"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('saved immediately')
		expect(wrapper.find('[data-testid="launchpad-edit-bar-add-tiles"]').exists()).toBe(false)
		expect(DashboardEditBar.emits).not.toContain('addTiles')
	})

	it('marks the current column count pressed and emits a chosen one', async () => {
		const wrapper = mountBar({ columns: 8 })
		const pressed = wrapper.findAll('[aria-pressed="true"]')
		expect(pressed).toHaveLength(1)
		expect(pressed[0].text()).toBe('8')
		const group = wrapper.find('[data-testid="launchpad-edit-bar-columns"]')
		expect(group.attributes('role')).toBe('group')
		expect(wrapper.find(`#${group.attributes('aria-labelledby')}`).text()).toBe('Columns')

		await wrapper.find('[data-testid="launchpad-edit-bar-columns-6"]').trigger('click')
		expect(wrapper.emitted('setColumns')).toEqual([[6]])
	})

	it('opens the settings as a dialog, and only for who may configure', async () => {
		const wrapper = mountBar()
		const settings = wrapper.find('[data-testid="launchpad-edit-bar-settings"]')
		expect(settings.attributes('aria-haspopup')).toBe('dialog')
		await settings.trigger('click')
		expect(wrapper.emitted('openSettings')).toHaveLength(1)

		expect(mountBar({ canConfigure: false }).find('[data-testid="launchpad-edit-bar-settings"]').exists()).toBe(false)
	})

	it('emits Add widget, Cancel and Save', async () => {
		const wrapper = mountBar({ changeCount: 2 })
		await wrapper.find('[data-testid="launchpad-edit-bar-add-widget"]').trigger('click')
		await wrapper.find('[data-testid="launchpad-edit-bar-cancel"]').trigger('click')
		await wrapper.find('[data-testid="launchpad-edit-bar-save"]').trigger('click')
		expect(wrapper.emitted('addWidget')).toHaveLength(1)
		expect(wrapper.emitted('cancel')).toHaveLength(1)
		expect(wrapper.emitted('save')).toHaveLength(1)
	})

	it('shows No changes with Save disabled, and counts the draft once there are changes', async () => {
		const wrapper = mountBar()
		const pill = () => wrapper.find('[data-testid="launchpad-edit-bar-draft"]')
		expect(pill().text()).toBe('No changes')
		expect(wrapper.find('[data-testid="launchpad-edit-bar-save"]').attributes('disabled')).toBeDefined()

		await wrapper.setProps({ changeCount: 1 })
		expect(pill().text()).toBe('Draft · 1 change')
		await wrapper.setProps({ changeCount: 3 })
		expect(pill().text()).toBe('Draft · 3 changes')
		expect(wrapper.find('[data-testid="launchpad-edit-bar-save"]').attributes('disabled')).toBeUndefined()

		await wrapper.setProps({ saving: true })
		expect(wrapper.find('[data-testid="launchpad-edit-bar-save"]').attributes('disabled')).toBeDefined()
	})

	it('hands its dock to the page while shown, and takes it back when gone', () => {
		const onDock = vi.fn()
		const wrapper = mountBar({ onDock })
		expect(onDock).toHaveBeenLastCalledWith(wrapper.find('.launchpad-edit-bar__dock').element)
		wrapper.unmount()
		expect(onDock).toHaveBeenLastCalledWith(null)
	})
})
