/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The "Office networks" card of `AdminSettings.vue`: it shows the stored
 * ranges one per line, saves the edited list on its own, shows the server's
 * refusal of a malformed range, and says whether the administrator's own
 * address is inside.
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
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

const t = (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_, key) => vars[key] ?? `{${key}}`)

const ShellStub = {
	name: 'CnAdminSettingsShell',
	render() {
		return h('div', Object.entries(this.$slots).map(([name, slot]) => h('section', { 'data-slot': name }, slot())))
	},
}
const SectionStub = {
	name: 'CnSettingsSection',
	props: ['name'],
	render() {
		return h('div', { 'data-section': this.name }, this.$slots.default?.())
	},
}

/**
 * Mount the admin page with the settings answer given.
 *
 * @param {object} settings What GET admin settings answers.
 * @return {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountPage(settings) {
	api.getAdminSettings.mockResolvedValue({ data: settings })
	const stubs = { CnAdminSettingsShell: ShellStub, CnSettingsSection: SectionStub }
	for (const name of ['TemplatesPage', 'OperationsTab', 'RolesPermissionsTab', 'VersioningAuditTab', 'SharingTab', 'KioskTab', 'OrgNavigationTab', 'DemoDataTab', 'GroupDashboardsTab', 'MetadataFieldsSettings', 'ProfileFieldsTab', 'SetupWizardModal']) {
		stubs[name] = { name, render: () => h('div') }
	}
	const wrapper = mount(AdminSettings, {
		global: { provide: { configuredGroups: [], allGroups: [] }, mocks: { t }, stubs },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	api.updateAdminSettings.mockResolvedValue({})
	api.listGroupDashboards.mockResolvedValue({ data: [] })
	api.getSetupWizardState.mockResolvedValue({ data: { complete: true } })
})

describe('AdminSettings office networks', () => {
	it('shows the stored ranges one per line and whether the current address is inside', async () => {
		const wrapper = await mountPage({ officeNetworks: ['10.20.0.0/16', '2001:db8::/32'], currentAddress: '10.20.4.7', currentAddressInOfficeNetworks: true })

		expect(wrapper.find('[data-testid="admin-office-networks-input"]').element.value).toBe('10.20.0.0/16\n2001:db8::/32')
		expect(wrapper.find('[data-testid="admin-office-networks-current"]').text())
			.toBe('Your current address is 10.20.4.7, which is inside the office networks.')
	})

	it('says when the current address is outside', async () => {
		const wrapper = await mountPage({ officeNetworks: [], currentAddress: '84.1.2.3', currentAddressInOfficeNetworks: false })
		expect(wrapper.find('[data-testid="admin-office-networks-current"]').text())
			.toBe('Your current address is 84.1.2.3, which is outside the office networks.')
	})

	it('saves the edited list on its own, trimmed and without blank lines, then reloads', async () => {
		const wrapper = await mountPage({ officeNetworks: [], currentAddress: '10.20.4.7', currentAddressInOfficeNetworks: false })

		await wrapper.find('[data-testid="admin-office-networks-input"]').setValue(' 10.20.0.0/16 \n\n2001:db8::/32')
		await wrapper.find('[data-testid="admin-office-networks-save"]').trigger('click')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledTimes(1)
		expect(api.updateAdminSettings).toHaveBeenCalledWith({ officeNetworks: ['10.20.0.0/16', '2001:db8::/32'] })
		expect(api.getAdminSettings).toHaveBeenCalledTimes(2)
		expect(wrapper.find('[data-testid="admin-office-networks-error"]').exists()).toBe(false)
	})

	it('shows the refusal of a malformed range and does not reload', async () => {
		const wrapper = await mountPage({ officeNetworks: ['10.20.0.0/16'], currentAddress: '', currentAddressInOfficeNetworks: false })
		api.updateAdminSettings.mockRejectedValue({ response: { status: 400, data: { error: 'This is not a valid network range: 10.20.0.0/40' } } })

		await wrapper.find('[data-testid="admin-office-networks-input"]').setValue('10.20.0.0/40')
		await wrapper.find('[data-testid="admin-office-networks-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-office-networks-error"]').text()).toBe('This is not a valid network range: 10.20.0.0/40')
		expect(api.getAdminSettings).toHaveBeenCalledTimes(1)
	})
})
