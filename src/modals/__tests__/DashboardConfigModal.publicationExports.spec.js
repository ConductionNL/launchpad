/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard settings hold publication, the scheduled exports and the
 * versions in their own tabs (REQ-SCHEDUI-001, REQ-SCH-011, board
 * LpVersies): which person gets which tab, and what each panel is handed.
 */

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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

vi.mock('../../components/Workspace/config/PublicationTab.vue', () => ({
	__esModule: true,
	default: {
		name: 'PublicationTab',
		props: ['dashboard'],
		render() {
			return h('div', { class: 'publication-stub' })
		},
	},
}))
vi.mock('../../components/Workspace/config/ExportsTab.vue', () => ({
	__esModule: true,
	default: {
		name: 'ExportsTab',
		props: ['subjects', 'defaultSubject'],
		render() {
			return h('div', { class: 'exports-stub' })
		},
	},
}))

vi.mock('../../components/Workspace/config/VersionsTab.vue', () => ({
	__esModule: true,
	default: {
		name: 'VersionsTab',
		props: ['dashboardUuid'],
		emits: ['restored'],
		render() {
			return h('div', { class: 'versions-stub' })
		},
	},
}))

const dashboard = {
	id: 3,
	uuid: 'uuid-3',
	name: 'Marketing',
	description: '',
	icon: 'ViewDashboard',
	isOwner: true,
	permissionLevel: 'full',
}

const subjects = [
	{ type: 'dashboard', id: 'uuid-3', label: 'Marketing' },
	{ type: 'widget', id: '41', label: 'KPI' },
]

/**
 * @param {object} props Props on top of an open, owned dashboard.
 * @return {object} The wrapper.
 */
function mountModal(props = {}) {
	return mount(DashboardConfigModal, {
		attachTo: document.body,
		props: { open: true, dashboard, ...props },
	})
}

const tabIds = (wrapper) => wrapper.findAll('[role="tab"]').map((tab) => tab.attributes('data-test'))

enableAutoUnmount(afterEach)

describe('DashboardConfigModal publication and exports tabs', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('adds Publication and Exports after Sharing when the host allows them', () => {
		const wrapper = mountModal({ canManagePublication: true, canScheduleExports: true, exportSubjects: subjects })
		expect(tabIds(wrapper)).toEqual([
			'config-tab-general',
			'config-tab-languages',
			'config-tab-sharing',
			'config-tab-publication',
			'config-tab-exports',
		])
		expect(wrapper.find('[data-test="config-tab-publication"]').text()).toContain('Publication')
		expect(wrapper.find('[data-test="config-tab-exports"]').text()).toContain('Exports')
	})

	it('leaves both tabs out without the host\'s permission, and while creating', () => {
		expect(tabIds(mountModal())).not.toContain('config-tab-publication')
		expect(tabIds(mountModal())).not.toContain('config-tab-exports')
		const create = mountModal({ mode: 'create', dashboard: null, canManagePublication: true, canScheduleExports: true })
		expect(create.find('[data-test="config-tabs"]').exists()).toBe(false)
	})

	it('offers an administrator the Publication tab on a dashboard they do not own', () => {
		const wrapper = mountModal({ dashboard: { ...dashboard, isOwner: false }, canManagePublication: true })
		expect(tabIds(wrapper)).toEqual(['config-tab-general', 'config-tab-publication'])
	})

	it('hands the Publication panel the dashboard', async () => {
		const wrapper = mountModal({ canManagePublication: true, initialTab: 'publication' })
		await vi.dynamicImportSettled()
		await flushPromises()
		expect(wrapper.find('[data-test="config-panel-publication"]').exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'PublicationTab' }).props('dashboard')).toEqual(dashboard)
	})

	it('hands the Exports panel this dashboard and its widgets, a new export starting on the dashboard', async () => {
		const wrapper = mountModal({ canScheduleExports: true, exportSubjects: subjects })
		await wrapper.find('[data-test="config-tab-exports"]').trigger('click')
		await vi.dynamicImportSettled()
		await flushPromises()
		const tab = wrapper.findComponent({ name: 'ExportsTab' })
		expect(tab.props('subjects')).toEqual(subjects)
		expect(tab.props('defaultSubject')).toEqual({ type: 'dashboard', id: 'uuid-3' })
	})

	it('ends the strip with Versions for the owner or an administrator', () => {
		const wrapper = mountModal({ canManagePublication: true, canScheduleExports: true, canViewVersions: true })
		expect(tabIds(wrapper).at(-1)).toBe('config-tab-versions')
		expect(wrapper.find('[data-test="config-tab-versions"]').text()).toContain('Versions')
		expect(tabIds(mountModal())).not.toContain('config-tab-versions')
	})

	it('hands the Versions panel the dashboard, and passes a restore on', async () => {
		const wrapper = mountModal({ canViewVersions: true, initialTab: 'versions' })
		await vi.dynamicImportSettled()
		await flushPromises()
		const tab = wrapper.findComponent({ name: 'VersionsTab' })
		expect(tab.props('dashboardUuid')).toBe('uuid-3')
		tab.vm.$emit('restored', { versionNumber: 2 })
		expect(wrapper.emitted('versionRestored')).toEqual([[{ versionNumber: 2 }]])
	})
})
