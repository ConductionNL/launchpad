/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one More menu of board LpMeer: its groups, the expandable Settings
 * item at the bottom of the right column and the keyboard.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcPopover: {
		name: 'NcPopover',
		props: ['shown', 'setReturnFocus'],
		emits: ['update:shown', 'afterShow'],
		template: '<div><slot name="trigger" :attrs="{}" /><div v-if="shown" class="popup"><slot /></div></div>',
	},
	NcButton: {
		name: 'NcButton',
		template: '<button type="button" v-bind="$attrs"><slot name="icon" /><slot /></button>',
	},
}))

vi.mock('@nextcloud/router', () => ({ generateFilePath: (app, dir, file) => `/apps/${app}/${dir}/${file}` }))

import LaunchpadMoreMenu from '../LaunchpadMoreMenu.vue'

const DASHBOARDS = [
	{ id: 'dashboard-1', label: 'Vandaag', meta: 'default', checked: true },
	{ id: 'dashboard-2', label: 'Team Vergunningen', meta: 'shared', checked: false },
	{ id: 'all-dashboards', label: 'All dashboards' },
]
const SETTINGS = [
	{ id: 'personal-settings', label: 'Personal settings' },
	{ id: 'StoreMenu', label: 'Marketplace', href: '/store' },
]
const ACTIONS = [
	{ id: 'edit', label: 'Edit' },
	{ id: 'delete', label: 'Delete', destructive: true },
]

/**
 * Mount the menu, open.
 *
 * @param {object} props Props.
 * @return {Promise<object>} The wrapper.
 */
async function openMenu(props = {}) {
	const wrapper = mount(LaunchpadMoreMenu, {
		attachTo: document.body,
		props: { dashboards: DASHBOARDS, settings: SETTINGS, actions: ACTIONS, ...props },
	})
	wrapper.vm.open = true
	await flushPromises()
	return wrapper
}

const ids = (wrapper) => wrapper.findAll('.lp-more-menu__item').map((el) => el.attributes('data-testid'))
const panel = (wrapper) => wrapper.find('[data-testid="launchpad-more-menu-panel"]')

describe('LaunchpadMoreMenu', () => {
	it('is a menu named More with Dashboards left, This dashboard and then Settings right', async () => {
		const wrapper = await openMenu()
		expect(panel(wrapper).attributes('role')).toBe('menu')
		expect(panel(wrapper).attributes('aria-label')).toBe('More')
		const columns = wrapper.findAll('.lp-more-menu__column')
		expect(columns).toHaveLength(2)
		expect(columns[0].text()).toContain('Dashboards')
		expect(columns[0].find('[data-testid="launchpad-more-menu-settings"]').exists()).toBe(false)
		expect(columns[1].text()).toContain('This dashboard')
		const right = [...columns[1].element.children]
		expect(right.map((el) => el.getAttribute('data-testid'))).toEqual([
			'launchpad-more-menu-group-this-dashboard',
			'launchpad-more-menu-settings',
			'launchpad-more-menu-group-settings',
		])
		wrapper.unmount()
	})

	it('puts Settings alone in the right column off the dashboard view', async () => {
		const wrapper = await openMenu({ actions: [] })
		const columns = wrapper.findAll('.lp-more-menu__column')
		expect(columns).toHaveLength(2)
		expect(panel(wrapper).classes()).not.toContain('lp-more-menu__panel--single')
		expect(columns[0].text()).toContain('Dashboards')
		expect(columns[1].find('[data-testid="launchpad-more-menu-group-this-dashboard"]').exists()).toBe(false)
		expect(columns[1].element.firstElementChild.getAttribute('data-testid')).toBe('launchpad-more-menu-settings')
		wrapper.unmount()
	})

	it('marks the open dashboard as the checked radio item, with its marker', async () => {
		const wrapper = await openMenu()
		const today = wrapper.find('[data-testid="launchpad-more-menu-item-dashboard-1"]')
		expect(today.attributes('role')).toBe('menuitemradio')
		expect(today.attributes('aria-checked')).toBe('true')
		expect(today.text()).toContain('default')
		expect(wrapper.find('[data-testid="launchpad-more-menu-item-dashboard-2"]').attributes('aria-checked')).toBe('false')
		const all = wrapper.find('[data-testid="launchpad-more-menu-item-all-dashboards"]')
		expect(all.attributes('role')).toBe('menuitem')
		expect(all.attributes('aria-checked')).toBeUndefined()
		wrapper.unmount()
	})

	it('folds Settings into one item that unfolds in place', async () => {
		const wrapper = await openMenu()
		const toggle = wrapper.find('[data-testid="launchpad-more-menu-settings"]')
		expect(toggle.attributes('role')).toBe('menuitem')
		expect(toggle.attributes('aria-haspopup')).toBe('true')
		expect(toggle.attributes('aria-expanded')).toBe('false')
		const group = wrapper.find('[data-testid="launchpad-more-menu-group-settings"]')
		expect(group.isVisible()).toBe(false)

		await toggle.trigger('click')
		expect(toggle.attributes('aria-expanded')).toBe('true')
		expect(group.isVisible()).toBe(true)
		expect(group.attributes('role')).toBe('group')
		expect(toggle.attributes('aria-controls')).toBe(group.attributes('id'))
		expect(group.findAll('[role="menuitem"]').map((el) => el.text())).toEqual(['Personal settings', 'Marketplace'])
		expect(wrapper.vm.open).toBe(true)
		wrapper.unmount()
	})

	it('starts folded again every time it opens', async () => {
		const wrapper = await openMenu()
		await wrapper.find('[data-testid="launchpad-more-menu-settings"]').trigger('click')
		wrapper.vm.open = false
		await flushPromises()
		wrapper.vm.open = true
		await flushPromises()
		expect(wrapper.find('[data-testid="launchpad-more-menu-settings"]').attributes('aria-expanded')).toBe('false')
		expect(wrapper.emitted('open')).toHaveLength(2)
		wrapper.unmount()
	})

	it('puts Delete last in the This dashboard group', async () => {
		const wrapper = await openMenu()
		const items = wrapper.find('[data-testid="launchpad-more-menu-group-this-dashboard"]').findAll('.lp-more-menu__item')
		expect(items.at(-1).text()).toBe('Delete')
		expect(items.at(-1).classes()).toContain('lp-more-menu__item--destructive')
		wrapper.unmount()
	})

	it('draws one column without a dashboard to act on, and no Settings item without settings', async () => {
		const wrapper = await openMenu({ actions: [], settings: [] })
		expect(wrapper.findAll('.lp-more-menu__column')).toHaveLength(1)
		expect(panel(wrapper).classes()).toContain('lp-more-menu__panel--single')
		expect(wrapper.find('[data-testid="launchpad-more-menu-settings"]').exists()).toBe(false)
		wrapper.unmount()
	})

	it('runs a chosen item and closes', async () => {
		const onClick = vi.fn()
		const wrapper = await openMenu({ actions: [{ id: 'edit', label: 'Edit', onClick }] })
		await wrapper.find('[data-testid="launchpad-more-menu-item-edit"]').trigger('click')
		expect(onClick).toHaveBeenCalledTimes(1)
		expect(wrapper.emitted('select')[0][0].id).toBe('edit')
		expect(wrapper.vm.open).toBe(false)
		wrapper.unmount()
	})

	it('renders a link item as a link', async () => {
		const wrapper = await openMenu()
		const store = wrapper.find('[data-testid="launchpad-more-menu-item-StoreMenu"]')
		expect(store.element.tagName).toBe('A')
		expect(store.attributes('href')).toBe('/store')
		wrapper.unmount()
	})

	it('moves with the arrow keys over every visible item, across both columns', async () => {
		const wrapper = await openMenu()
		wrapper.vm.focusItem(0)
		const active = () => document.activeElement.getAttribute('data-testid')
		expect(active()).toBe('launchpad-more-menu-item-dashboard-1')
		await panel(wrapper).trigger('keydown', { key: 'ArrowUp' })
		expect(active()).toBe('launchpad-more-menu-settings')
		await panel(wrapper).trigger('keydown', { key: 'ArrowUp' })
		expect(active()).toBe('launchpad-more-menu-item-delete')
		await panel(wrapper).trigger('keydown', { key: 'Home' })
		expect(active()).toBe('launchpad-more-menu-item-dashboard-1')
		await panel(wrapper).trigger('keydown', { key: 'End' })
		expect(active()).toBe('launchpad-more-menu-settings')
		// The folded Settings items are skipped.
		expect(ids(wrapper)).toContain('launchpad-more-menu-item-personal-settings')
		expect(wrapper.vm.itemElements().map((el) => el.getAttribute('data-testid'))).toEqual([
			'launchpad-more-menu-item-dashboard-1',
			'launchpad-more-menu-item-dashboard-2',
			'launchpad-more-menu-item-all-dashboards',
			'launchpad-more-menu-item-edit',
			'launchpad-more-menu-item-delete',
			'launchpad-more-menu-settings',
		])
		wrapper.unmount()
	})

	it('unfolds Settings with Right and folds it with Left, keeping focus', async () => {
		const wrapper = await openMenu()
		const toggle = wrapper.find('[data-testid="launchpad-more-menu-settings"]')
		toggle.element.focus()
		await panel(wrapper).trigger('keydown', { key: 'ArrowRight' })
		await flushPromises()
		expect(toggle.attributes('aria-expanded')).toBe('true')
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-item-personal-settings')
		await panel(wrapper).trigger('keydown', { key: 'ArrowDown' })
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-item-StoreMenu')
		await panel(wrapper).trigger('keydown', { key: 'End' })
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-item-StoreMenu')
		await panel(wrapper).trigger('keydown', { key: 'ArrowDown' })
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-item-dashboard-1')
		await panel(wrapper).trigger('keydown', { key: 'ArrowUp' })
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-item-StoreMenu')

		await panel(wrapper).trigger('keydown', { key: 'ArrowLeft' })
		expect(toggle.attributes('aria-expanded')).toBe('false')
		expect(document.activeElement).toBe(toggle.element)
		wrapper.unmount()
	})

	it('closes on Escape and returns focus to More', async () => {
		const wrapper = await openMenu()
		wrapper.vm.focusItem(0)
		await panel(wrapper).trigger('keydown', { key: 'Escape' })
		expect(wrapper.vm.open).toBe(false)
		expect(document.activeElement.getAttribute('data-testid')).toBe('launchpad-more-menu-trigger')
		expect(wrapper.vm.returnFocus()).toBe(wrapper.find('[data-testid="launchpad-more-menu-trigger"]').element)
		wrapper.unmount()
	})

	it('keeps the menu open for More and draws a star per row of the full list (review items 5, 6)', async () => {
		const onToggleExpanded = vi.fn()
		const onToggleFavourite = vi.fn()
		const wrapper = await openMenu({
			dashboards: [
				{ id: 'dashboard-1', label: 'Vandaag', checked: true, favourite: true, onToggleFavourite },
				{ id: 'dashboard-2', label: 'Team', checked: false, favourite: false, onToggleFavourite },
				{ id: 'all-dashboards', label: 'Fewer', keepOpen: true, expanded: true, onClick: onToggleExpanded },
			],
		})
		const stars = wrapper.findAll('.lp-more-menu__star')
		expect(stars.map((el) => el.attributes('aria-pressed'))).toEqual(['true', 'false'])
		expect(stars[0].classes()).toContain('lp-more-menu__star--on')
		expect(wrapper.find('[data-testid="launchpad-more-menu-dashboard-list"]').classes()).toContain('lp-more-menu__dashboard-list--all')
		const vandaag = wrapper.find('[data-testid="launchpad-more-menu-item-dashboard-1"]')
		vandaag.element.focus()
		await panel(wrapper).trigger('keydown', { key: 'ArrowDown' })
		expect(document.activeElement).toBe(stars[0].element)
		await stars[1].trigger('click')
		expect(onToggleFavourite).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.open).toBe(true)
		await wrapper.find('[data-testid="launchpad-more-menu-item-all-dashboards"]').trigger('click')
		expect(onToggleExpanded).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.open).toBe(true)
		wrapper.unmount()
	})

	it('draws no star on a folded list', async () => {
		const wrapper = await openMenu()
		expect(wrapper.findAll('.lp-more-menu__star')).toHaveLength(0)
		wrapper.unmount()
	})

	it('ends in the Powered by footer under both columns, unless left out (review item 7)', async () => {
		const wrapper = await openMenu()
		const footer = wrapper.find('[data-testid="more-menu-footer"]')
		expect(footer.exists()).toBe(true)
		expect(footer.classes()).toContain('lp-more-menu__footer')
		expect(footer.findAll('a[target="_blank"][rel="noopener noreferrer"]').length).toBeGreaterThanOrEqual(2)
		wrapper.unmount()
		const without = await openMenu({ hideFooter: true })
		expect(without.find('[data-testid="more-menu-footer"]').exists()).toBe(false)
		without.unmount()
	})

	it('draws the line about starring as text, not as a menu item (Ruben, 27 Nov)', async () => {
		const wrapper = await openMenu({
			dashboards: [
				{ id: 'dashboard-1', label: 'Vandaag', checked: true },
				{ id: 'favourites-hint', hint: true, label: 'Star a dashboard under More to see it here.' },
				{ id: 'all-dashboards', label: 'More', keepOpen: true, expanded: false },
			],
		})
		const hint = wrapper.find('[data-testid="launchpad-more-menu-favourites-hint"]')
		expect(hint.element.tagName).toBe('P')
		expect(hint.text()).toBe('Star a dashboard under More to see it here.')
		expect(ids(wrapper)).not.toContain('launchpad-more-menu-item-favourites-hint')
		wrapper.unmount()
	})
})
