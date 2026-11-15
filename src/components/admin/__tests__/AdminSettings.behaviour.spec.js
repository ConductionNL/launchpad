/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the behaviour of `AdminSettings.vue` (the tab
 * markup has its own spec): loading and saving the general settings with
 * quota clamping, the group dashboards it loads per configured group, the
 * optimistic group default with rollback, the setup wizard, and the page
 * title and breadcrumbs for the open tab. Child tabs are shallow-stubbed.
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettings from '../AdminSettings.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

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

const t = (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`)

const GROUP_ROWS = {
	default: [{ uuid: 'd1', isDefault: 1 }],
	staff: [{ uuid: 's1', isDefault: 1 }, { uuid: 's2', isDefault: 0 }],
}

async function mountSettings(provide = { configuredGroups: ['staff'] }) {
	const wrapper = shallowMount(AdminSettings, {
		global: { provide, mocks: { t } },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getAdminSettings.mockResolvedValue({ data: { defaultPermissionLevel: 'full', maxDashboardsPerUser: 5 } })
	api.updateAdminSettings.mockResolvedValue({})
	api.listGroupDashboards.mockImplementation(async (groupId) => ({ data: GROUP_ROWS[groupId] ?? [] }))
	api.getSetupWizardState.mockResolvedValue({ data: { completed: false } })
	api.setGroupDashboardDefault.mockResolvedValue({})
})

describe('AdminSettings: general settings', () => {
	it('merges the stored settings and maps the permission level to its option', async () => {
		const wrapper = await mountSettings()

		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.vm.settings).toMatchObject({
			defaultPermissionLevel: { id: 'full', label: 'Full customization' },
			maxDashboardsPerUser: 5,
			allowMultipleDashboards: true,
			defaultGridColumns: 12,
		})
	})

	it('falls back to add only, reads a wrapped payload, and keeps defaults on failure', async () => {
		api.getAdminSettings.mockResolvedValueOnce({ data: { data: { defaultPermissionLevel: 'odd', defaultGridColumns: 8 } } })
		const wrapped = await mountSettings()
		expect(wrapped.vm.settings.defaultPermissionLevel.id).toBe('add_only')
		expect(wrapped.vm.settings.defaultGridColumns).toBe(8)

		api.getAdminSettings.mockRejectedValueOnce(new Error('x'))
		const failed = await mountSettings()
		expect(failed.vm.settings.defaultGridColumns).toBe(12)
		expect(failed.vm.loading).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('Failed to load admin data:', expect.any(Error))
	})

	it('saves every setting under the API keys with clamped quotas', async () => {
		const wrapper = await mountSettings()
		wrapper.vm.settings.maxWidgetsPerDashboard = '20000'

		wrapper.vm.updateSetting('startPageWithoutNavigation', 'yes')
		await flushPromises()

		expect(api.updateAdminSettings).toHaveBeenCalledWith({
			defaultPermLevel: 'full',
			allowUserDash: false,
			allowMultiDash: true,
			startPageWithoutNavigation: false,
			defaultGridCols: 12,
			maxDashboardsPerUser: 5,
			maxWidgetsPerDashboard: 10000,
		})
	})

	it.each([
		['12', 12],
		[7.9, 7],
		['-3', 0],
		['abc', 0],
		[undefined, 0],
		[10000, 10000],
		[10001, 10000],
	])('clamps a quota of %s to %s', async (value, clamped) => {
		const wrapper = await mountSettings()
		expect(wrapper.vm.clampQuota(value)).toBe(clamped)
	})

	it('stores the clamped quota on input and saves', async () => {
		const wrapper = await mountSettings()
		wrapper.vm.onQuotaInput('maxDashboardsPerUser', '-1')
		await flushPromises()

		expect(wrapper.vm.settings.maxDashboardsPerUser).toBe(0)
		expect(api.updateAdminSettings.mock.calls[0][0].maxDashboardsPerUser).toBe(0)
	})

	it('logs a failed save', async () => {
		api.updateAdminSettings.mockRejectedValue(new Error('x'))
		const wrapper = await mountSettings()
		await wrapper.vm.saveSettings()
		expect(logger.error).toHaveBeenCalledWith('Failed to save settings:', expect.any(Error))
	})
})

describe('AdminSettings: group dashboards', () => {
	it.each([
		[{}, ['default']],
		[{ configuredGroups: ['staff'] }, ['default', 'staff']],
		[{ configuredGroups: ['staff', 'default'] }, ['staff', 'default']],
		[{ configuredGroups: 'nope' }, ['default']],
	])('loads the dashboards of the admin groups for %j', async (provide, groups) => {
		const wrapper = await mountSettings(provide)

		expect(api.listGroupDashboards.mock.calls.map((c) => c[0])).toEqual(groups)
		expect(Object.keys(wrapper.vm.groupSharedDashboards).sort()).toEqual([...groups].sort())
		expect(wrapper.vm.loadingGroupDashboards).toBe(false)
	})

	it('treats a failed or malformed group list as empty', async () => {
		api.listGroupDashboards.mockImplementation(async (groupId) => {
			if (groupId === 'staff') {
				throw new Error('403')
			}
			return { data: 'nope' }
		})
		const wrapper = await mountSettings()

		expect(wrapper.vm.groupSharedDashboards).toEqual({ default: [], staff: [] })
		expect(logger.warn).toHaveBeenCalledWith('Failed to load group dashboards for staff:', expect.any(Error))
	})

	it('sets a group default optimistically', async () => {
		const wrapper = await mountSettings()
		await wrapper.vm.setGroupDefault('staff', 's2')

		expect(api.setGroupDashboardDefault).toHaveBeenCalledWith('staff', 's2')
		expect(wrapper.vm.groupSharedDashboards.staff).toEqual([
			{ uuid: 's1', isDefault: 0 },
			{ uuid: 's2', isDefault: 1 },
		])
		expect(wrapper.vm.groupSharedDashboards.default).toEqual(GROUP_ROWS.default)
		expect(wrapper.vm.settingGroupDefault).toBeNull()
	})

	it('rolls the default back when saving it fails', async () => {
		api.setGroupDashboardDefault.mockRejectedValue(new Error('x'))
		const wrapper = await mountSettings()

		const pending = wrapper.vm.setGroupDefault('staff', 's2')
		expect(wrapper.vm.settingGroupDefault).toBe('s2')
		expect(wrapper.vm.groupSharedDashboards.staff[1].isDefault).toBe(1)
		await pending

		expect(wrapper.vm.groupSharedDashboards.staff).toEqual(GROUP_ROWS.staff)
		expect(logger.error).toHaveBeenCalledWith('Failed to set the group default dashboard', expect.any(Error))
		expect(wrapper.vm.settingGroupDefault).toBeNull()
	})

	it('handles a default for a group it has not loaded', async () => {
		const wrapper = await mountSettings()
		await wrapper.vm.setGroupDefault('unknown', 'x')
		expect(wrapper.vm.groupSharedDashboards.unknown).toEqual([])
	})
})

describe('AdminSettings: setup wizard', () => {
	it('loads the wizard state, null when empty or failing', async () => {
		expect((await mountSettings()).vm.wizardState).toEqual({ completed: false })

		api.getSetupWizardState.mockResolvedValueOnce({ data: '' })
		expect((await mountSettings()).vm.wizardState).toBeNull()

		api.getSetupWizardState.mockRejectedValueOnce(new Error('x'))
		expect((await mountSettings()).vm.wizardState).toBeNull()
		expect(logger.warn).toHaveBeenCalledWith('Failed to load setup wizard state:', expect.any(Error))
	})

	it('opens and closes, and reloads everything once completed', async () => {
		const wrapper = await mountSettings()
		wrapper.vm.openWizard()
		expect(wrapper.vm.showWizard).toBe(true)
		wrapper.vm.closeWizard()
		expect(wrapper.vm.showWizard).toBe(false)

		wrapper.vm.openWizard()
		vi.clearAllMocks()
		await wrapper.vm.onWizardCompleted()

		expect(wrapper.vm.showWizard).toBe(false)
		expect(api.getAdminSettings).toHaveBeenCalledTimes(1)
		expect(api.listGroupDashboards).toHaveBeenCalledTimes(2)
		expect(api.getSetupWizardState).toHaveBeenCalledTimes(1)
	})
})

describe('AdminSettings: title and breadcrumbs', () => {
	it('names the open tab and notes organisation scope on General', async () => {
		const wrapper = await mountSettings()

		expect(wrapper.vm.pageTitle).toBe('Launchpad administration: general')
		expect(wrapper.vm.breadcrumbs).toEqual([
			{ label: 'Admin settings', href: '/index.php/settings/admin' },
			{ label: 'Launchpad' },
			{ label: 'General' },
		])
		expect(wrapper.vm.breadcrumbNote).toBe('applies to the whole organisation')
	})

	it('follows a tab change, and drops the tab crumb for an unknown tab', async () => {
		const wrapper = await mountSettings()

		wrapper.vm.onTabChange('roles-permissions')
		expect(wrapper.vm.pageTitle).toBe('Launchpad administration: roles & permissions')
		expect(wrapper.vm.breadcrumbs[2]).toEqual({ label: 'Roles & Permissions' })
		expect(wrapper.vm.breadcrumbNote).toBe('')

		wrapper.vm.onTabChange('gone')
		expect(wrapper.vm.pageTitle).toBe('Launchpad administration: ')
		expect(wrapper.vm.breadcrumbs).toHaveLength(2)
	})
})
