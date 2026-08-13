/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `GroupDashboardsTab.vue` (admin-group-management
 * Task 7). Covers row rendering, count badge, and the View / Create /
 * Manage action wiring (the actions toggle modal state owned by the
 * tab). Modal internals are smoke-tested by mounting with stub children
 * — full per-modal coverage lives in the dedicated specs.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
}))

vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		translate: (_app, source) => source,
	}
})

vi.mock('@nextcloud/router', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		generateUrl: (path) => `/index.php${path}`,
	}
})

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('../../../services/api.js', () => ({
	api: {
		getAdminGroups: vi.fn(() =>
			Promise.resolve({
				data: { data: [{ id: 'admins', displayName: 'Admins' }] },
			}),
		),
		listGroupDashboards: vi.fn(() =>
			Promise.resolve({
				data: { data: [{ uuid: 'u1', name: 'A', isDefault: false }] },
			}),
		),
		createGroupDashboard: vi.fn(),
		updateGroupDashboard: vi.fn(),
		deleteGroupDashboard: vi.fn(),
		setGroupDashboardDefault: vi.fn(),
	},
}))

const stubs = {
	NcButton: {
		emits: ['click'],
		template:
			'<button class="nc-button" :data-test="$attrs[\'data-test\']" @click="$emit(\'click\')"><slot /></button>',
	},
	NcEmptyContent: {
		template:
			'<div class="nc-empty-content" :data-test="$attrs[\'data-test\']"><slot name="icon" /><slot /></div>',
	},
	NcLoadingIcon: { template: '<span class="nc-loading-icon" />' },
	AccountMultipleIcon: { template: '<span class="icon-account-multiple" />' },
	CreateGroupDashboardModal: {
		template: '<div data-test="create-modal-mounted" />',
	},
	ManageGroupDashboardsModal: {
		template: '<div data-test="manage-modal-mounted" />',
	},
}

let GroupDashboardsTab

beforeEach(async () => {
	setActivePinia(createPinia())
	globalThis.t = (_app, key) => key
	GroupDashboardsTab = (await import('../tabs/GroupDashboardsTab.vue')).default
})

async function mountTab() {
	const wrapper = mount(GroupDashboardsTab, { stubs })
	// Drain the async created() hooks (fetchGroups + per-group fetches).
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
	await wrapper.vm.$nextTick()
	return wrapper
}

describe('GroupDashboardsTab', () => {
	it('renders one row per group plus the default sentinel', async () => {
		const wrapper = await mountTab()
		expect(wrapper.find('[data-test="tab-group-dashboards"]').exists()).toBe(
			true,
		)
		expect(
			wrapper.find('[data-test="group-dashboards-row-default"]').exists(),
		).toBe(true)
		expect(
			wrapper.find('[data-test="group-dashboards-row-admins"]').exists(),
		).toBe(true)
	})

	it('renders a count badge per group from the store', async () => {
		const wrapper = await mountTab()
		const badge = wrapper.find('[data-test="group-dashboards-count-admins"]')
		expect(badge.exists()).toBe(true)
		expect(badge.text()).toBe('1')
	})

	it('opens the create modal when the Create action is clicked', async () => {
		const wrapper = await mountTab()
		expect(wrapper.find('[data-test="create-modal-mounted"]').exists()).toBe(
			false,
		)
		await wrapper
			.find('[data-test="group-dashboards-create-admins"]')
			.trigger('click')
		expect(wrapper.find('[data-test="create-modal-mounted"]').exists()).toBe(
			true,
		)
	})

	it('opens the manage modal when the Manage action is clicked', async () => {
		const wrapper = await mountTab()
		await wrapper
			.find('[data-test="group-dashboards-manage-admins"]')
			.trigger('click')
		expect(wrapper.find('[data-test="manage-modal-mounted"]').exists()).toBe(
			true,
		)
	})

	it('opens the manage modal when the View action is clicked (View is an alias for Manage today)', async () => {
		const wrapper = await mountTab()
		await wrapper
			.find('[data-test="group-dashboards-view-admins"]')
			.trigger('click')
		expect(wrapper.find('[data-test="manage-modal-mounted"]').exists()).toBe(
			true,
		)
	})
})
