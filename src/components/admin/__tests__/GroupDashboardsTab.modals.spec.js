/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * GroupDashboardsTab (admin-group-management): what the tab does when its
 * modals report back, the empty state, and a group whose dashboard count
 * cannot be loaded. The row actions themselves are in
 * GroupDashboardsTab.spec.js.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import GroupDashboardsTab from '../tabs/GroupDashboardsTab.vue'
import { api } from '../../../services/api.js'
import { useGroupDashboardsStore } from '../../../stores/groupDashboards.js'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, source) => source,
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		getAdminGroups: vi.fn(),
		listGroupDashboards: vi.fn(),
	},
}))

const stubs = {
	NcButton: {
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	NcEmptyContent: {
		props: ['name'],
		template: '<div class="nc-empty-content">{{ name }}<slot name="icon" /></div>',
	},
	NcLoadingIcon: { template: '<span />' },
	AccountMultipleIcon: { template: '<span class="icon-account-multiple" />' },
	CreateGroupDashboardModal: {
		name: 'CreateGroupDashboardModal',
		props: ['group'],
		emits: ['close', 'created'],
		template: '<div data-test="create-modal" />',
	},
	ManageGroupDashboardsModal: {
		name: 'ManageGroupDashboardsModal',
		props: ['group'],
		emits: ['close'],
		template: '<div data-test="manage-modal" />',
	},
}

const DASHBOARDS = {
	default: [{ uuid: 'd1' }],
	staff: [{ uuid: 's1' }, { uuid: 's2' }],
}

async function mountTab() {
	const wrapper = mount(GroupDashboardsTab, { global: { stubs, mocks: { t: (_a, s) => s } } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	api.getAdminGroups.mockResolvedValue({ data: { allKnown: [{ id: 'staff', displayName: 'Staff' }] } })
	api.listGroupDashboards.mockImplementation(async (groupId) => ({ data: DASHBOARDS[groupId] ?? [] }))
})

describe('GroupDashboardsTab modals', () => {
	it('refreshes the group count after a create and closes the modal', async () => {
		const wrapper = await mountTab()
		expect(wrapper.find('[data-test="group-dashboards-count-staff"]').text()).toBe('2')

		await wrapper.find('[data-test="group-dashboards-create-staff"]').trigger('click')
		const modal = wrapper.findComponent({ name: 'CreateGroupDashboardModal' })
		expect(modal.props('group')).toEqual({ id: 'staff', displayName: 'Staff' })

		DASHBOARDS.staff = [{ uuid: 's1' }, { uuid: 's2' }, { uuid: 's3' }]
		modal.vm.$emit('created')
		await flushPromises()

		expect(api.listGroupDashboards).toHaveBeenLastCalledWith('staff')
		expect(wrapper.find('[data-test="group-dashboards-count-staff"]').text()).toBe('3')
		expect(wrapper.find('[data-test="create-modal"]').exists()).toBe(false)
		DASHBOARDS.staff = [{ uuid: 's1' }, { uuid: 's2' }]
	})

	it('closes the create modal without refreshing', async () => {
		const wrapper = await mountTab()
		await wrapper.find('[data-test="group-dashboards-create-staff"]').trigger('click')
		const calls = api.listGroupDashboards.mock.calls.length

		wrapper.findComponent({ name: 'CreateGroupDashboardModal' }).vm.$emit('close')
		await flushPromises()

		expect(wrapper.find('[data-test="create-modal"]').exists()).toBe(false)
		expect(api.listGroupDashboards.mock.calls.length).toBe(calls)
	})

	it('closes the manage modal', async () => {
		const wrapper = await mountTab()
		await wrapper.find('[data-test="group-dashboards-manage-default"]').trigger('click')
		expect(wrapper.findComponent({ name: 'ManageGroupDashboardsModal' }).props('group').id).toBe('default')

		wrapper.findComponent({ name: 'ManageGroupDashboardsModal' }).vm.$emit('close')
		await flushPromises()

		expect(wrapper.find('[data-test="manage-modal"]').exists()).toBe(false)
	})

	it('a create report without an open modal refreshes nothing', async () => {
		const wrapper = await mountTab()
		const calls = api.listGroupDashboards.mock.calls.length

		await wrapper.vm.onCreated()

		expect(api.listGroupDashboards.mock.calls.length).toBe(calls)
	})
})

describe('GroupDashboardsTab states', () => {
	it('still lists every group when one count cannot be loaded', async () => {
		api.listGroupDashboards.mockImplementation(async (groupId) => {
			if (groupId === 'staff') {
				throw new Error('500')
			}
			return { data: DASHBOARDS[groupId] }
		})
		const wrapper = await mountTab()

		expect(wrapper.find('[data-test="group-dashboards-count-default"]').text()).toBe('1')
		expect(wrapper.find('[data-test="group-dashboards-count-staff"]').text()).toBe('0')
	})

	it('shows the empty state when there are no groups', async () => {
		const wrapper = await mountTab()

		useGroupDashboardsStore().groups = []
		await flushPromises()

		const empty = wrapper.find('.nc-empty-content')
		expect(empty.text()).toContain('No groups configured')
		expect(empty.find('.icon-account-multiple').exists()).toBe(true)
		expect(wrapper.find('[data-test="group-dashboards-list"]').exists()).toBe(false)
	})
})
