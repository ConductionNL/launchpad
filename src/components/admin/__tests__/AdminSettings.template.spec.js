/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The template wiring of `AdminSettings.vue`: which component each tab slot
 * draws and with which props, which handler each General control calls,
 * and the wizard banner and group-default buttons. The admin shell is
 * replaced by a stand-in that renders every slot it is given, so the slot
 * contents are rendered here rather than only read as source text.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import AdminSettings from '../AdminSettings.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		getAdminSettings: vi.fn(),
		updateAdminSettings: vi.fn(),
		listGroupDashboards: vi.fn(),
		getSetupWizardState: vi.fn(),
		setGroupDashboardDefault: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn(), warn: vi.fn() } }))

const t = (_app, text) => text

/** Renders every slot it receives, each in a section named after the slot. */
const ShellStub = {
	name: 'CnAdminSettingsShell',
	emits: ['tabChange'],
	render() {
		return h(
			'div',
			Object.entries(this.$slots).map(([name, slot]) =>
				h('section', { 'data-slot': name }, slot()),
			),
		)
	},
}

/** Renders its default slot so the cards' contents mount. */
const SectionStub = {
	name: 'CnSettingsSection',
	props: ['name'],
	render() {
		return h('div', { 'data-section': this.name }, this.$slots.default?.())
	},
}

const TABS = [
	'TemplatesPage',
	'OperationsTab',
	'RolesPermissionsTab',
	'VersioningAuditTab',
	'SharingTab',
	'KioskTab',
	'OrgNavigationTab',
	'DemoDataTab',
	'GroupDashboardsTab',
	'MetadataFieldsSettings',
	'ProfileFieldsTab',
	'SetupWizardModal',
]

const GROUP_ROWS = {
	default: [{ uuid: 'd1', name: 'Main', icon: 'Star', isDefault: 1 }],
	staff: [
		{ uuid: 's1', name: 'Staff A', icon: 'Star', isDefault: 1 },
		{ uuid: 's2', name: 'Staff B', icon: 'Star', isDefault: 0 },
	],
	empty: [],
}

async function mountPage(provide = {}) {
	const stubs = { CnAdminSettingsShell: ShellStub, CnSettingsSection: SectionStub }
	for (const name of TABS) {
		stubs[name] = { name, props: ['groups', 'configuredGroups'], render: () => h('div', { 'data-tab': name }) }
	}
	const wrapper = mount(AdminSettings, {
		global: {
			provide: { configuredGroups: ['staff', 'empty'], allGroups: ['admin', 'staff'], ...provide },
			mocks: { t },
			stubs,
		},
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getAdminSettings.mockResolvedValue({ data: { defaultPermissionLevel: 'full' } })
	api.updateAdminSettings.mockResolvedValue({})
	api.listGroupDashboards.mockImplementation(async (groupId) => ({ data: GROUP_ROWS[groupId] ?? [] }))
	api.getSetupWizardState.mockResolvedValue({ data: { complete: false } })
	api.setGroupDashboardDefault.mockResolvedValue({})
})

describe('AdminSettings tab slots', () => {
	it('draws each tab component in its own slot', async () => {
		const wrapper = await mountPage()
		const expected = {
			'tab-templates': 'TemplatesPage',
			'tab-operations': 'OperationsTab',
			'tab-roles-permissions': 'RolesPermissionsTab',
			'tab-versioning-audit': 'VersioningAuditTab',
			'tab-sharing': 'SharingTab',
			'tab-kiosk': 'KioskTab',
			'tab-org-navigation': 'OrgNavigationTab',
			'tab-demo-data': 'DemoDataTab',
			'tab-group-dashboards': 'GroupDashboardsTab',
			'tab-detail-fields': 'MetadataFieldsSettings',
			'tab-profile-fields': 'ProfileFieldsTab',
		}
		for (const [slot, tab] of Object.entries(expected)) {
			const section = wrapper.find(`[data-slot="${slot}"]`)
			expect(section.exists()).toBe(true)
			expect(section.find(`[data-tab="${tab}"]`).exists()).toBe(true)
		}
	})

	it('hands the injected groups to the sharing and org-navigation tabs', async () => {
		const wrapper = await mountPage()

		const sharing = wrapper.findComponent({ name: 'SharingTab' })
		expect(sharing.props('groups')).toEqual(['admin', 'staff'])
		expect(sharing.props('configuredGroups')).toEqual(['staff', 'empty'])
		expect(wrapper.findComponent({ name: 'OrgNavigationTab' }).props('groups')).toEqual(['admin', 'staff'])
	})

	it('follows the tab the shell reports and drops the General cards elsewhere', async () => {
		const wrapper = await mountPage()
		expect(wrapper.find('[data-section="Default settings"]').exists()).toBe(true)

		wrapper.findComponent({ name: 'CnAdminSettingsShell' }).vm.$emit('tabChange', 'kiosk')
		await flushPromises()

		expect(wrapper.vm.activeTab).toBe('kiosk')
		expect(wrapper.find('[data-section="Default settings"]').exists()).toBe(false)
	})
})

describe('AdminSettings setup wizard banner', () => {
	it('opens the wizard from the banner while setup is not complete', async () => {
		const wrapper = await mountPage()
		expect(wrapper.find('[data-test="setup-wizard-rerun"]').exists()).toBe(false)

		await wrapper.find('[data-test="setup-wizard-open"]').trigger('click')

		expect(wrapper.find('[data-tab="SetupWizardModal"]').exists()).toBe(true)
	})

	it('offers a re-run once setup is complete', async () => {
		api.getSetupWizardState.mockResolvedValue({ data: { complete: true } })
		const wrapper = await mountPage()
		expect(wrapper.find('[data-test="setup-wizard-banner"]').exists()).toBe(false)

		await wrapper.find('[data-test="setup-wizard-rerun-open"]').trigger('click')

		expect(wrapper.vm.showWizard).toBe(true)
	})

	it('shows neither without a wizard state', async () => {
		api.getSetupWizardState.mockRejectedValue(new Error('down'))
		const wrapper = await mountPage()

		expect(wrapper.find('[data-test="setup-wizard-banner"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="setup-wizard-rerun"]').exists()).toBe(false)
	})
})

describe('AdminSettings General controls', () => {
	const savedPayload = () => api.updateAdminSettings.mock.calls.at(-1)[0]

	it('saves the permission level and grid columns picked in the selects', async () => {
		const wrapper = await mountPage()
		const [permission, columns] = wrapper.findAllComponents({ name: 'NcSelect' })

		permission.vm.$emit('update:modelValue', { id: 'view_only', label: 'View only' })
		await flushPromises()
		expect(savedPayload().defaultPermLevel).toBe('view_only')

		columns.vm.$emit('update:modelValue', 8)
		await flushPromises()
		expect(savedPayload().defaultGridCols).toBe(8)
	})

	it('saves each switch under its own setting', async () => {
		const wrapper = await mountPage()
		const [allowUser, allowMultiple, noNavigation] = wrapper.findAllComponents({ name: 'NcCheckboxRadioSwitch' })

		allowUser.vm.$emit('update:modelValue', true)
		await flushPromises()
		expect(savedPayload().allowUserDash).toBe(true)

		allowMultiple.vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(savedPayload().allowMultiDash).toBe(false)

		noNavigation.vm.$emit('update:modelValue', true)
		await flushPromises()
		expect(savedPayload().startPageWithoutNavigation).toBe(true)
	})

	it('clamps and saves each quota field', async () => {
		const wrapper = await mountPage()
		const [dashboards, widgets] = wrapper.findAllComponents({ name: 'NcTextField' })

		dashboards.vm.$emit('update:modelValue', '25')
		await flushPromises()
		expect(savedPayload().maxDashboardsPerUser).toBe(25)

		widgets.vm.$emit('update:modelValue', '99999')
		await flushPromises()
		expect(savedPayload().maxWidgetsPerDashboard).toBe(10000)
		expect(wrapper.vm.settings.maxWidgetsPerDashboard).toBe(10000)
	})
})

describe('AdminSettings group-shared dashboards card', () => {
	it('lists each group, marks its default and notes an empty group', async () => {
		const wrapper = await mountPage()
		const card = wrapper.find('[data-section="Group-shared dashboards"]')

		expect(card.findAll('h4').map((h4) => h4.text())).toEqual(['default', 'staff', 'empty'])
		expect(card.findAll('[data-test="group-default-badge"]')).toHaveLength(2)
		expect(card.findAll('[data-test="set-group-default"]')).toHaveLength(1)
		expect(card.text()).toContain('No group-shared dashboards in this group yet.')
	})

	it('promotes a dashboard from its button', async () => {
		const wrapper = await mountPage()

		await wrapper.find('[data-test="set-group-default"]').trigger('click')
		await flushPromises()

		expect(api.setGroupDashboardDefault).toHaveBeenCalledWith('staff', 's2')
		expect(wrapper.vm.groupSharedDashboards.staff.map((d) => d.isDefault)).toEqual([0, 1])
	})
})
