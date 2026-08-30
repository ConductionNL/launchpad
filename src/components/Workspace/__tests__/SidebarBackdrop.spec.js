/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `SidebarBackdrop.vue`. The backdrop is a tiny
 * presentational component — no props, no state — so its only contract
 * is "emits a `close` event when clicked". The parent is responsible
 * for closing whichever sidebar is open.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SidebarBackdrop from '../SidebarBackdrop.vue'

describe('SidebarBackdrop', () => {
	it('renders a single .sidebar-backdrop element', () => {
		const wrapper = mount(SidebarBackdrop)
		expect(wrapper.find('.sidebar-backdrop').exists()).toBe(true)
	})

	it('emits "close" once per click', async () => {
		const wrapper = mount(SidebarBackdrop)
		await wrapper.find('.sidebar-backdrop').trigger('click')
		expect(wrapper.emitted('close')).toBeTruthy()
		expect(wrapper.emitted('close').length).toBe(1)
	})
})
