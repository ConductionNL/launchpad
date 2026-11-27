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

import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import DashboardConfigModal from '../DashboardConfigModal.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))
vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: () => ({ nldesign: { tokenSet: { name: 'Huisstijl Zuiddrecht' } }, theming: { name: 'Nextcloud' } }),
}))
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

// Attached, because jsdom computes no style for a detached element and
// `isVisible()` would then miss the panels' `v-show`.
function mountModal(props = {}) {
	return mount(DashboardConfigModal, {
		attachTo: document.body,
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

enableAutoUnmount(afterEach)

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

	it('keeps the theme button look off the tab strip', () => {
		const wrapper = mountModal()
		expect(wrapper.find('[data-test="config-tabs"]').classes()).toContain('cn-look-board')
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

	it('has the board\'s tabs: Details, Languages, Sharing', () => {
		const wrapper = mountModal()
		const ids = wrapper
			.findAll('[role="tab"]')
			.map((tab) => tab.attributes('data-test'))
		// dashboard-theme-readonly REQ-THM-001: the look is a block on Details.
		expect(ids).toEqual([
			'config-tab-general',
			'config-tab-languages',
			'config-tab-sharing',
		])
		expect(wrapper.find('[data-test="config-tab-general"]').text()).toContain(
			'Details',
		)
	})

	it('puts the default switch under Details', () => {
		const wrapper = mountModal()
		const general = wrapper.find('[data-test="config-panel-general"]')
		expect(general.text()).toContain('Default dashboard')
		expect(wrapper.find('[data-test="config-panel-default"]').exists()).toBe(
			false,
		)
	})

	// @spec openspec/changes/archive/2026-11-24-dashboard-theme-readonly/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	it('draws Own look on the Details tab, with no layout preset field', () => {
		const wrapper = mountModal()
		const general = wrapper.find('[data-test="config-panel-general"]')
		const look = general.find('[data-test="config-look"]')
		expect(look.exists()).toBe(true)
		expect(look.text()).toContain('Own look')
		expect(look.find('[data-testid="look-accent"]').exists()).toBe(true)
		expect(look.find('[data-testid="look-background"]').exists()).toBe(true)
		expect(look.find('[data-testid="look-image-input"]').exists()).toBe(true)
		expect(general.text()).not.toMatch(/layout of this dashboard|Indeling van dit dashboard/i)
		expect(wrapper.find('[data-test="config-panel-look"]').exists()).toBe(false)
	})

	// @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	it('labels the image as the background image, with an upload button, a preview and remove', async () => {
		const wrapper = mountModal()
		const image = wrapper.find('[data-testid="look-image"]')
		expect(image.text()).toContain('Background image')
		expect(image.text()).toContain('leave empty to follow the theme')
		expect(image.text()).not.toMatch(/^Image$/m)
		// The bare browser input is hidden behind a styled button.
		const input = image.find('[data-testid="look-image-input"]')
		expect(input.attributes('aria-hidden')).toBe('true')
		expect(input.classes()).toContain('dashboard-config__file')
		expect(image.find('[data-testid="look-image-upload"]').text()).toBe('Upload image')
		expect(image.find('[data-testid="look-image-preview"]').exists()).toBe(false)

		wrapper.vm.look.resource = 'achtergrond.png'
		await wrapper.vm.$nextTick()
		const preview = image.find('[data-testid="look-image-preview"]')
		expect(preview.attributes('src')).toContain('/apps/launchpad/resource/achtergrond.png')
		expect(image.find('[data-testid="look-image-upload"]').text()).toBe('Choose another image')
		await image.find('[data-testid="look-image-clear"]').trigger('click')
		expect(wrapper.vm.look.resource).toBe('')
	})

	// @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	it('puts each colour picker on one row with its field', () => {
		const wrapper = mountModal()
		const rows = wrapper.findAll('.dashboard-config__look-row')
		expect(rows).toHaveLength(2)
		for (const row of rows) {
			expect(row.find('input[type="color"]').exists()).toBe(true)
		}
	})

	// @spec openspec/changes/archive/2026-11-24-dashboard-theme-readonly/specs/dashboards/spec.md#requirement-the-details-tab-shows-the-instance-theme-read-only-req-thm-004
	it('shows the instance theme read-only under Display, with no theme choice or layout field', () => {
		const wrapper = mountModal()
		const general = wrapper.find('[data-test="config-panel-general"]')
		const display = general.find('[data-test="config-display"]')
		expect(display.exists()).toBe(true)
		expect(display.text()).toContain('Display')
		const theme = display.find('[data-testid="display-theme"]')
		expect(theme.attributes('readonly')).toBeDefined()
		expect(wrapper.vm.themeName).toBe('Huisstijl Zuiddrecht')
		expect(display.find('[data-testid="display-theme-hint"]').text()).toContain('Own look')
		expect(display.find('.nc-select-stub').exists()).toBe(false)
		expect(general.text()).not.toMatch(/layout of this dashboard|Indeling van dit dashboard/i)
	})

	it('shows no theme field while creating a dashboard', () => {
		const wrapper = mountModal({ dashboard: null })
		expect(wrapper.find('[data-test="config-display"]').exists()).toBe(false)
	})

	it('offers no Own look while creating a dashboard', () => {
		const wrapper = mountModal({ dashboard: null })
		expect(wrapper.find('[data-test="config-look"]').exists()).toBe(false)
	})

	it('lands on Details when an old caller asks for the default tab', () => {
		const wrapper = mountModal({ initialTab: 'default' })
		expect(wrapper.vm.currentTab).toBe('general')
	})

	it('counts the shares on the Sharing tab', async () => {
		const wrapper = mountModal()
		// Let the modal's own share load settle first; it resets the list.
		await new Promise((resolve) => setTimeout(resolve, 0))
		wrapper.vm.localShares = [{ shareWith: 'a' }, { shareWith: 'b' }]
		await wrapper.vm.$nextTick()
		expect(
			wrapper.find('[data-test="config-tab-count-sharing"]').text(),
		).toBe('2')
	})
})
