/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for AdminDemoData (install / uninstall demo showcases),
 * ConditionalVisibilityOverview (the widgets-with-rules table) and the
 * export half of DashboardExportImport plus its import paths not covered by
 * DashboardExportImport.spec.js.
 */

import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminDemoData from '../AdminDemoData.vue'
import ConditionalVisibilityOverview from '../ConditionalVisibilityOverview.vue'
import DashboardExportImport from '../DashboardExportImport.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, key) => key,
	translate: (_app, key) => key,
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		listDemoShowcases: vi.fn(),
		installDemoShowcase: vi.fn(),
		uninstallDemoShowcase: vi.fn(),
		getAdminWidgetsWithRules: vi.fn(),
		exportDashboards: vi.fn(),
		importDashboards: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const t = (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`)

beforeEach(() => {
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
	vi.restoreAllMocks()
})

describe('AdminDemoData', () => {
	const SHOWCASES = [
		{ id: 'gov', title: 'Municipality', language: 'nl', isInstalled: false },
		{ id: 'edu', title: 'School', language: 'en', isInstalled: true },
	]

	async function mountDemo() {
		const wrapper = shallowMount(AdminDemoData, { global: { mocks: { t } } })
		await flushPromises()
		return wrapper
	}

	beforeEach(() => {
		api.listDemoShowcases.mockResolvedValue({ data: SHOWCASES })
		api.installDemoShowcase.mockResolvedValue({ data: {} })
		api.uninstallDemoShowcase.mockResolvedValue({})
	})

	it('lists the showcases on mount', async () => {
		const wrapper = await mountDemo()
		expect(wrapper.vm.showcases).toEqual(SHOWCASES)
		expect(wrapper.find('[data-test="showcase-card-gov"]').exists()).toBe(true)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('shows a load error, and an empty list for no data', async () => {
		api.listDemoShowcases.mockRejectedValueOnce(new Error('x'))
		const failed = await mountDemo()
		expect(failed.find('.launchpad-demo-showcases__error').text()).toBe('Could not load demo showcases. Please try again.')

		api.listDemoShowcases.mockResolvedValueOnce({ data: null })
		expect((await mountDemo()).vm.showcases).toEqual([])
	})

	it('installs a showcase, keeps skipped widgets as a warning and reloads', async () => {
		api.installDemoShowcase.mockResolvedValue({ data: { skippedWidgets: ['weather'] } })
		const wrapper = await mountDemo()

		const pending = wrapper.vm.install(SHOWCASES[0])
		expect(wrapper.vm.busy.gov).toBe(true)
		await pending

		expect(api.installDemoShowcase).toHaveBeenCalledWith('gov')
		expect(wrapper.vm.warnings).toEqual({ gov: ['weather'] })
		expect(api.listDemoShowcases).toHaveBeenCalledTimes(2)
		expect(wrapper.vm.busy.gov).toBe(false)

		api.installDemoShowcase.mockResolvedValue({ data: { skippedWidgets: [] } })
		await wrapper.vm.install(SHOWCASES[0])
		expect(wrapper.vm.warnings).toEqual({})
	})

	it.each([
		[404, 'Showcase not found.'],
		[403, 'You need admin privileges to install showcases.'],
		[500, 'Could not install showcase. Please try again.'],
	])('explains a failed install with status %s', async (status, message) => {
		api.installDemoShowcase.mockRejectedValue({ response: { status } })
		const wrapper = await mountDemo()

		await wrapper.vm.install(SHOWCASES[0])

		expect(wrapper.vm.actionError).toBe(message)
		expect(wrapper.find('[data-test="showcase-action-error"]').exists()).toBe(true)
		expect(wrapper.vm.busy.gov).toBe(false)
	})

	it('uninstalls only after confirming, dropping its warning', async () => {
		const wrapper = await mountDemo()
		wrapper.vm.warnings.edu = ['x']

		wrapper.vm.confirmUninstall(SHOWCASES[1])
		expect(wrapper.vm.uninstallTarget).toEqual(SHOWCASES[1])
		expect(api.uninstallDemoShowcase).not.toHaveBeenCalled()

		await wrapper.vm.onUninstallConfirm()

		expect(api.uninstallDemoShowcase).toHaveBeenCalledWith('edu')
		expect(wrapper.vm.uninstallTarget).toBeNull()
		expect(wrapper.vm.warnings).toEqual({})
		expect(api.listDemoShowcases).toHaveBeenCalledTimes(2)
	})

	it('does nothing when confirming without a target, and reports a failed uninstall', async () => {
		const wrapper = await mountDemo()
		await wrapper.vm.onUninstallConfirm()
		expect(api.uninstallDemoShowcase).not.toHaveBeenCalled()

		api.uninstallDemoShowcase.mockRejectedValue(new Error('x'))
		wrapper.vm.confirmUninstall(SHOWCASES[1])
		await wrapper.vm.onUninstallConfirm()
		expect(wrapper.vm.actionError).toBe('Could not uninstall showcase. Please try again.')
		expect(wrapper.vm.busy.edu).toBe(false)
	})

	it('hides a thumbnail that fails to load', async () => {
		const wrapper = await mountDemo()
		const target = { style: {} }
		wrapper.vm.onThumbError({ target })
		expect(target.style.display).toBe('none')
	})
})

describe('ConditionalVisibilityOverview', () => {
	const stubs = {
		NcEmptyContent: { props: ['name'], template: '<div class="empty-stub">{{ name }}</div>' },
		NcLoadingIcon: { template: '<span class="loading-stub" />' },
		EyeOff: true,
	}
	const ROW = { placementId: 3, dashboardName: 'Ops', widgetType: 'news', ruleCount: 2, includeCount: 1, excludeCount: 1 }

	async function mountOverview() {
		const wrapper = mount(ConditionalVisibilityOverview, { global: { stubs } })
		await flushPromises()
		return wrapper
	}

	it.each([
		['a list', { data: [ROW] }],
		['a wrapped list', { data: { data: [ROW] } }],
	])('renders the rows from %s', async (_case, response) => {
		api.getAdminWidgetsWithRules.mockResolvedValue(response)
		const wrapper = await mountOverview()

		const cells = wrapper.findAll('[data-test="cv-overview-table"] tbody td').map((c) => c.text())
		expect(cells.slice(1)).toEqual(['news', '2', '1', '1'])
	})

	it('shows the empty state for no rows or a failure', async () => {
		api.getAdminWidgetsWithRules.mockResolvedValueOnce({ data: {} })
		const empty = await mountOverview()
		expect(empty.find('[data-test="cv-overview-table"]').exists()).toBe(false)

		api.getAdminWidgetsWithRules.mockRejectedValueOnce(new Error('x'))
		const failed = await mountOverview()
		expect(failed.vm.rows).toEqual([])
		expect(failed.vm.loading).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('Failed to load conditional visibility overview:', expect.any(Error))
	})
})

describe('DashboardExportImport', () => {
	function mountExportImport() {
		return shallowMount(DashboardExportImport, { global: { mocks: { t } } })
	}

	it('downloads the site export with a timestamped name', async () => {
		vi.useFakeTimers({ toFake: ['Date'] })
		vi.setSystemTime(new Date('2026-03-11T09:08:07.123Z'))
		const blob = new Blob(['zip'])
		api.exportDashboards.mockResolvedValue({ data: blob })
		window.URL.createObjectURL = vi.fn(() => 'blob:zip')
		window.URL.revokeObjectURL = vi.fn()
		const clicks = []
		vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function() {
			clicks.push({ href: this.getAttribute('href'), download: this.download })
		})
		const wrapper = mountExportImport()

		await wrapper.vm.exportSite()

		expect(api.exportDashboards).toHaveBeenCalledWith({ scope: 'site' })
		expect(window.URL.createObjectURL).toHaveBeenCalledWith(blob)
		expect(clicks).toEqual([{ href: 'blob:zip', download: 'launchpad-export-20260311090807.zip' }])
		expect(window.URL.revokeObjectURL).toHaveBeenCalledWith('blob:zip')
		expect(wrapper.vm.exporting).toBe(false)
	})

	it('shows an export error and logs it', async () => {
		api.exportDashboards.mockRejectedValue(new Error('x'))
		const wrapper = mountExportImport()

		await wrapper.vm.exportSite()

		expect(wrapper.vm.exportError).toBe('Export failed. Please try again.')
		expect(wrapper.vm.exporting).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('launchpad export failed', expect.any(Error))
	})

	it('imports the picked archive with the preserve-UUIDs choice', async () => {
		const file = new File(['zip'], 'a.zip')
		api.importDashboards.mockResolvedValue({ data: { imported: 2, errors: [] } })
		const wrapper = mountExportImport()

		await wrapper.vm.runImport()
		expect(api.importDashboards).not.toHaveBeenCalled()

		wrapper.vm.onFileSelected({ target: { files: [file] } })
		wrapper.vm.preserveUuids = true
		await wrapper.vm.runImport()

		expect(api.importDashboards).toHaveBeenCalledWith(file, { preserveUuids: true })
		expect(wrapper.vm.importResult).toEqual({ imported: 2, errors: [] })
		expect(wrapper.vm.importing).toBe(false)
	})

	it('clears the earlier result when another file is picked', async () => {
		const wrapper = mountExportImport()
		wrapper.vm.importResult = { imported: 1 }
		wrapper.vm.importError = 'old'

		wrapper.vm.onFileSelected({ target: { files: [] } })

		expect(wrapper.vm.selectedFile).toBeNull()
		expect(wrapper.vm.importResult).toBeNull()
		expect(wrapper.vm.importError).toBe('')
	})
})
