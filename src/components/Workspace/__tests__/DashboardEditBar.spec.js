/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit bar of board LpBewerken: Columns 6, 8 or 12, Add widget,
 * Dashboard settings and Done, and the dock for the More menu.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

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

	it('holds Columns, Add widget, Add tiles, Dashboard settings and Done, in that order', () => {
		const labels = mountBar().findAll('button').map((b) => b.text())
		expect(labels).toEqual(['6', '8', '12', 'Add widget', 'Add tiles', 'Dashboard settings', 'Done'])
	})

	it('emits addTiles from Add tiles (add-tiles-dialog REQ-TILE-043)', async () => {
		const wrapper = mountBar()
		await wrapper.find('[data-testid="launchpad-edit-bar-add-tiles"]').trigger('click')
		expect(wrapper.emitted('addTiles')).toHaveLength(1)
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

	it('emits Add widget and Done', async () => {
		const wrapper = mountBar()
		await wrapper.find('[data-testid="launchpad-edit-bar-add-widget"]').trigger('click')
		await wrapper.find('[data-testid="launchpad-edit-bar-done"]').trigger('click')
		expect(wrapper.emitted('addWidget')).toHaveLength(1)
		expect(wrapper.emitted('done')).toHaveLength(1)
	})

	it('hands its dock to the page while shown, and takes it back when gone', () => {
		const onDock = vi.fn()
		const wrapper = mountBar({ onDock })
		expect(onDock).toHaveBeenLastCalledWith(wrapper.find('.launchpad-edit-bar__dock').element)
		wrapper.unmount()
		expect(onDock).toHaveBeenLastCalledWith(null)
	})
})
