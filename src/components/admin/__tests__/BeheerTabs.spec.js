/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `BeheerTabs.vue` (admin-settings spec). Covers:
 *  - tab switching renders only the active tab's slot
 *  - selecting a tab persists the slug to localStorage
 *  - the `?tab=` query string wins over the persisted value on first paint
 *  - the default tab is used when neither query nor storage is set
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BeheerTabs, { ACTIVE_TAB_STORAGE_KEY } from '../BeheerTabs.vue'

const tabs = [
	{ slug: 'templates', label: 'Templates' },
	{ slug: 'operations', label: 'Operations' },
	{ slug: 'roles-permissions', label: 'Roles & Permissions' },
]

function mountTabs(props = {}, search = '') {
	// Stub window.location.search for the deep-link path.
	delete window.location
	window.location = { search }
	return mount(BeheerTabs, {
		propsData: { tabs, defaultTab: 'templates', ...props },
		scopedSlots: {
			templates: '<div data-test="slot-templates">TEMPLATES</div>',
			operations: '<div data-test="slot-operations">OPERATIONS</div>',
			'roles-permissions': '<div data-test="slot-roles">ROLES</div>',
		},
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	localStorage.clear()
})

describe('BeheerTabs', () => {
	it('renders only the default tab slot on first visit', () => {
		const wrapper = mountTabs()
		expect(wrapper.find('[data-test="slot-templates"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="slot-operations"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="slot-roles"]').exists()).toBe(false)
	})

	it('switches the active tab and renders only that slot', async () => {
		const wrapper = mountTabs()
		await wrapper.find('[data-test="tab-operations"]').trigger('click')
		expect(wrapper.find('[data-test="slot-operations"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="slot-templates"]').exists()).toBe(false)
	})

	it('persists the selected tab to localStorage', async () => {
		const wrapper = mountTabs()
		await wrapper.find('[data-test="tab-roles-permissions"]').trigger('click')
		expect(localStorage.getItem(ACTIVE_TAB_STORAGE_KEY)).toBe('roles-permissions')
	})

	it('restores the persisted tab on next mount', () => {
		localStorage.setItem(ACTIVE_TAB_STORAGE_KEY, 'operations')
		const wrapper = mountTabs()
		expect(wrapper.find('[data-test="slot-operations"]').exists()).toBe(true)
	})

	it('deep-links to a tab via the ?tab= query string (wins over storage)', () => {
		localStorage.setItem(ACTIVE_TAB_STORAGE_KEY, 'operations')
		const wrapper = mountTabs({}, '?tab=roles-permissions')
		expect(wrapper.find('[data-test="slot-roles"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="slot-operations"]').exists()).toBe(false)
	})

	it('emits change with the active slug on mount and on switch', async () => {
		const wrapper = mountTabs()
		expect(wrapper.emitted('change')[0]).toEqual(['templates'])
		await wrapper.find('[data-test="tab-operations"]').trigger('click')
		expect(wrapper.emitted('change').at(-1)).toEqual(['operations'])
	})

	it('ignores an unknown ?tab= value and falls back to the default', () => {
		const wrapper = mountTabs({}, '?tab=does-not-exist')
		expect(wrapper.find('[data-test="slot-templates"]').exists()).toBe(true)
	})
})
