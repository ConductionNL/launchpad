/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `DashboardConfigModal.vue` tab split
 * (dashboard-sharing spec). Covers:
 *  - the sharee picker is NOT in the General panel (REMOVED inline field set)
 *  - sharing markup lives in the Sharing panel only
 *  - the `initialTab` prop lands the modal on the requested tab
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import DashboardConfigModal from '../DashboardConfigModal.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))

vi.mock('../../services/api.js', () => ({
	api: {
		listShares: vi.fn().mockResolvedValue({ data: [] }),
		searchSharees: vi.fn().mockResolvedValue({ data: [] }),
		replaceShares: vi.fn().mockResolvedValue({ data: {} }),
	},
}))

vi.mock('@nextcloud/vue', () => ({
	NcModal: {
		props: ['name', 'size'],
		render() {
			return h('div', { class: 'nc-modal-stub' }, this.$slots.default?.())
		},
	},
	NcButton: {
		props: ['type', 'disabled'],
		render() {
			return h('button', this.$slots.default?.())
		},
	},
	NcTextField: {
		props: ['value', 'label', 'placeholder'],
		render() {
			return h('input')
		},
	},
	NcSelect: {
		render() {
			return h('div', { class: 'nc-select-stub' }, this.$slots.default?.())
		},
	},
	NcCheckboxRadioSwitch: {
		props: ['checked', 'type'],
		render() {
			return h('label', this.$slots.default?.())
		},
	},
}))

const ownedDashboard = {
	id: 3,
	uuid: 'uuid-3',
	name: 'Marketing',
	description: '',
	icon: 'ViewDashboard',
	isOwner: true,
	permissionLevel: 'full',
}

function mountModal(props = {}) {
	return mount(DashboardConfigModal, {
		propsData: {
			open: true,
			dashboard: ownedDashboard,
			mode: 'edit',
			...props,
		},
		stubs: {
			IconRenderer: { template: '<span />' },
			Delete: true,
			ContentSave: true,
			Plus: true,
			Close: true,
			Account: true,
			AccountGroup: true,
		},
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
})

describe('DashboardConfigModal tab split', () => {
	it('renders a tab strip with General and Sharing tabs for an owned dashboard', () => {
		const wrapper = mountModal()
		expect(wrapper.find('[data-test="config-tabs"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="config-tab-general"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="config-tab-sharing"]').exists()).toBe(true)
	})

	it('does not show the sharee picker on the General panel', async () => {
		const wrapper = mountModal()
		await wrapper.vm.$nextTick()
		// General is active by default; the sharing panel is v-show=false.
		const sharingPanel = wrapper.find('[data-test="config-panel-sharing"]')
		expect(sharingPanel.exists()).toBe(true)
		expect(sharingPanel.isVisible()).toBe(false)
		const generalPanel = wrapper.find('[data-test="config-panel-general"]')
		expect(generalPanel.isVisible()).toBe(true)
		// The share label lives inside the (hidden) sharing panel, not general.
		expect(generalPanel.text()).not.toContain('Share with users and groups')
	})

	it('shows the sharing panel when the Sharing tab is selected', async () => {
		const wrapper = mountModal()
		await wrapper.find('[data-test="config-tab-sharing"]').trigger('click')
		expect(wrapper.vm.currentTab).toBe('sharing')
		expect(wrapper.find('[data-test="config-panel-sharing"]').isVisible()).toBe(
			true,
		)
		expect(wrapper.find('[data-test="config-panel-general"]').isVisible()).toBe(
			false,
		)
	})

	it('lands on the Sharing tab when initialTab is "sharing"', () => {
		const wrapper = mountModal({ initialTab: 'sharing' })
		expect(wrapper.vm.currentTab).toBe('sharing')
		expect(wrapper.find('[data-test="config-panel-sharing"]').isVisible()).toBe(
			true,
		)
	})

	it('falls back to General when initialTab is sharing but shares cannot be managed', () => {
		const wrapper = mountModal({
			dashboard: { ...ownedDashboard, isOwner: false },
			initialTab: 'sharing',
		})
		expect(wrapper.vm.currentTab).toBe('general')
	})
})
