/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manage-group-dashboards modal: it loads the group's dashboards, shows
 * loading, empty and list states, marks the default, and sends set-default,
 * rename and delete to the store for that group. A failed delete keeps the
 * modal open and re-enables the row.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useGroupDashboardsStore } from '../../stores/groupDashboards.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({ ...(await importOriginal()), generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many),
}))

const { default: ManageGroupDashboardsModal } = await import('../ManageGroupDashboardsModal.vue')

const stubs = {
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open', 'size'],
		emits: ['update:open'],
		template: '<div class="dialog" :data-name="name"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcEmptyContent: { name: 'NcEmptyContent', props: ['name'], template: '<div class="empty">{{ name }}</div>' },
	NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span />' },
	ViewDashboardIcon: { name: 'ViewDashboardIcon', template: '<span />' },
	GroupDashboardRenameDialog: {
		name: 'GroupDashboardRenameDialog',
		props: ['open', 'currentName'],
		emits: ['update:open', 'confirm'],
		template: '<div v-if="open" class="rename" :data-current="currentName" />',
	},
	CnConfirmDialog: {
		name: 'CnConfirmDialog',
		props: ['dialogTitle', 'message', 'confirmLabel', 'variant'],
		emits: ['confirm', 'close'],
		template: '<div class="confirm">{{ message }}</div>',
	},
}

const group = { id: 'staff', displayName: 'Staff' }
const dashboards = [
	{ uuid: 'd-1', name: 'Roster', isDefault: true },
	{ uuid: 'd-2', name: 'Leave', isDefault: false },
]

let store

/**
 * Mount the modal over a store whose fetch resolves when told to.
 *
 * @param {Array} rows The dashboards the fetch stores for the group.
 * @return {{wrapper: import('@vue/test-utils').VueWrapper, finishFetch: Function}}
 */
function mountModal(rows = dashboards) {
	let finishFetch
	store.fetchGroupDashboards = vi.fn(() => new Promise((resolve) => {
		finishFetch = () => {
			store.dashboardsByGroup = { ...store.dashboardsByGroup, [group.id]: rows }
			resolve()
		}
	}))
	const wrapper = mount(ManageGroupDashboardsModal, { props: { group }, global: { stubs } })
	return { wrapper, finishFetch: () => finishFetch() }
}

/**
 * Mount the modal with its dashboards loaded.
 *
 * @param {Array} rows The dashboards.
 * @return {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function loaded(rows = dashboards) {
	const { wrapper, finishFetch } = mountModal(rows)
	finishFetch()
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	setActivePinia(createPinia())
	store = useGroupDashboardsStore()
	store.setDefault = vi.fn(() => Promise.resolve())
	store.update = vi.fn(() => Promise.resolve())
	store.delete = vi.fn(() => Promise.resolve())
})

describe('ManageGroupDashboardsModal', () => {
	it('names the group and shows loading until the group\'s dashboards arrive', async () => {
		const { wrapper, finishFetch } = mountModal()

		expect(store.fetchGroupDashboards).toHaveBeenCalledWith('staff')
		expect(wrapper.find('.dialog').attributes('data-name')).toMatch(/^Manage dashboards for /)
		expect(wrapper.find('.empty').text()).toBe('Loading dashboards')

		finishFetch()
		await flushPromises()

		expect(wrapper.findAll('.mgd__row-name').map((n) => n.text())).toEqual(['Roster', 'Leave'])
	})

	it('says so when the group has no dashboards', async () => {
		const wrapper = await loaded([])

		expect(wrapper.find('[data-test="manage-group-dashboards-empty"]').text()).toBe('No dashboards in this group yet')
	})

	it('badges the default and offers set-default only on the others', async () => {
		const wrapper = await loaded()

		expect(wrapper.find('[data-test="mgd-row-d-1"] [data-test="mgd-default-badge"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="mgd-row-d-2"] [data-test="mgd-default-badge"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="mgd-set-default-d-1"]').exists()).toBe(false)

		await wrapper.find('[data-test="mgd-set-default-d-2"]').trigger('click')

		expect(store.setDefault).toHaveBeenCalledWith('staff', 'd-2')
	})

	it('renames through the rename dialog, for that group', async () => {
		const wrapper = await loaded()

		await wrapper.find('[data-test="mgd-rename-d-2"]').trigger('click')
		expect(wrapper.find('.rename').attributes('data-current')).toBe('Leave')

		wrapper.findComponent({ name: 'GroupDashboardRenameDialog' }).vm.$emit('confirm', 'Holidays')
		await flushPromises()

		expect(store.update).toHaveBeenCalledWith('staff', 'd-2', { name: 'Holidays' })
		expect(wrapper.find('.rename').exists()).toBe(false)
	})

	it('closing the rename dialog renames nothing', async () => {
		const wrapper = await loaded()

		await wrapper.find('[data-test="mgd-rename-d-2"]').trigger('click')
		wrapper.findComponent({ name: 'GroupDashboardRenameDialog' }).vm.$emit('update:open', false)
		await flushPromises()

		expect(wrapper.find('.rename').exists()).toBe(false)
		await wrapper.vm.onRenameConfirm('Late answer')
		expect(store.update).not.toHaveBeenCalled()
	})

	it('a refused delete keeps the modal open and re-enables the row', async () => {
		let reject
		store.delete = vi.fn(() => new Promise((_resolve, r) => {
			reject = r
		}))
		const wrapper = await loaded()

		await wrapper.find('[data-test="mgd-delete-d-2"]').trigger('click')
		wrapper.findComponent({ name: 'CnConfirmDialog' }).vm.$emit('confirm')
		await flushPromises()

		expect(store.delete).toHaveBeenCalledWith('staff', 'd-2')
		expect(wrapper.find('[data-test="mgd-delete-d-2"]').attributes('disabled')).toBeDefined()

		reject(new Error('refused'))
		await flushPromises()

		expect(wrapper.find('[data-test="mgd-delete-d-2"]').attributes('disabled')).toBeUndefined()
		expect(wrapper.emitted('close')).toBeUndefined()
	})

	it('close and dismissing the dialog both close the modal', async () => {
		const wrapper = await loaded()

		await wrapper.find('[data-test="mgd-close"]').trigger('click')
		wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)

		expect(wrapper.emitted('close')).toHaveLength(2)
	})
})
