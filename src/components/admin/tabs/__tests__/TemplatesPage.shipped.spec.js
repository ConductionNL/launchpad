/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TemplatesPage: the ready-made templates section and the per-template
 * download (admin-templates REQ-TMPL-018, dashboard-export-import
 * REQ-EXIM-012). Payloads are the shapes ShippedTemplateService::listTemplates
 * and Dashboard::jsonSerialize return.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcEmptyContent: { name: 'NcEmptyContent', template: '<div class="empty" />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const { api } = vi.hoisted(() => ({
	api: {
		getAdminTemplates: vi.fn(),
		getShippedTemplates: vi.fn(),
		installShippedTemplate: vi.fn(),
		exportDashboards: vi.fn(),
	},
}))
vi.mock('../../../../services/api.js', () => ({ api }))
// The two modals are the page's own children and not under test here.
vi.mock('../../../../modals/TemplateEditorModal.vue', () => ({
	default: { name: 'TemplateEditorModal', template: '<div />' },
}))
vi.mock('../../../../modals/TemplateResyncModal.vue', () => ({
	default: { name: 'TemplateResyncModal', template: '<div />' },
}))

vi.mock('../../../../dialogs/ShippedTemplateUpdateDialog.vue', () => ({
	default: { name: 'ShippedTemplateUpdateDialog', template: '<div />' },
}))

import TemplatesPage from '../TemplatesPage.vue'

function shipped(overrides = {}) {
	return {
		id: 'mijn-werkdag',
		name: 'Mijn werkdag',
		description: 'Startpagina voor medewerkers',
		language: 'nl',
		version: 1,
		widgetCount: 8,
		isInstalled: false,
		installedUuid: '',
		installedVersion: null,
		missingWidgets: [],
		...overrides,
	}
}

const installedTemplate = {
	id: 4,
	uuid: 'uuid-4',
	name: 'Mijn werkdag',
	icon: null,
	slug: null,
	isDefault: 0,
	targetGroups: [],
}

function mountPage() {
	return mount(TemplatesPage, {
		global: {
			stubs: {
				TemplateResyncModal: true,
				TemplateEditorModal: true,
				Plus: true,
				ViewDashboard: true,
			},
		},
	})
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getAdminTemplates.mockResolvedValue({ data: [] })
	api.getShippedTemplates.mockResolvedValue({ data: [shipped()] })
})

describe('TemplatesPage ready-made templates', () => {
	it('lists a shipped template with an add button', async () => {
		const wrapper = mountPage()
		await flushPromises()

		const row = wrapper.find(
			'[data-testid="admin-shipped-template-mijn-werkdag"]',
		)
		expect(row.exists()).toBe(true)
		expect(row.text()).toContain('Mijn werkdag')
		expect(row.text()).toContain('Startpagina voor medewerkers')
		expect(row.find('[data-testid="admin-shipped-template-add"]').exists()).toBe(
			true,
		)
		expect(
			row.find('[data-testid="admin-shipped-template-added"]').exists(),
		).toBe(false)
		expect(
			row.find('[data-testid="admin-shipped-template-missing"]').exists(),
		).toBe(false)
	})

	it('adds the template, then shows it in the list and marks it added', async () => {
		api.installShippedTemplate.mockResolvedValue({
			data: { uuid: 'uuid-4', alreadyInstalled: false },
		})
		const wrapper = mountPage()
		await flushPromises()
		api.getAdminTemplates.mockResolvedValue({ data: [installedTemplate] })
		api.getShippedTemplates.mockResolvedValue({
			data: [shipped({ isInstalled: true, installedUuid: 'uuid-4' })],
		})

		await wrapper
			.find('[data-testid="admin-shipped-template-add"]')
			.trigger('click')
		await flushPromises()

		expect(api.installShippedTemplate).toHaveBeenCalledTimes(1)
		expect(api.installShippedTemplate).toHaveBeenCalledWith('mijn-werkdag')
		expect(
			wrapper.find('[data-testid="admin-shipped-template-added"]').exists(),
		).toBe(true)
		expect(
			wrapper.find('[data-testid="admin-shipped-template-add"]').exists(),
		).toBe(false)
		expect(wrapper.find('.launchpad-admin__templates').text()).toContain(
			'Mijn werkdag',
		)
		expect(wrapper.find('[data-testid="admin-templates-error"]').exists()).toBe(
			false,
		)
	})

	it('says so when adding fails, and leaves the button', async () => {
		api.installShippedTemplate.mockRejectedValue(new Error('500'))
		const wrapper = mountPage()
		await flushPromises()

		await wrapper
			.find('[data-testid="admin-shipped-template-add"]')
			.trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-templates-error"]').text()).toBe(
			'The template "Mijn werkdag" could not be added.',
		)
		expect(
			wrapper
				.find('[data-testid="admin-shipped-template-add"]')
				.attributes('disabled'),
		).toBeUndefined()
	})

	it('names the widgets no app provides', async () => {
		api.getShippedTemplates.mockResolvedValue({
			data: [shipped({ missingWidgets: ['decidesk', 'activity'] })],
		})
		const wrapper = mountPage()
		await flushPromises()

		expect(
			wrapper.find('[data-testid="admin-shipped-template-missing"]').text(),
		).toBe(
			'No app here provides these widgets, so they will show empty: decidesk, activity',
		)
	})

	it('says so when the shipped list fails, and still lists the templates', async () => {
		api.getShippedTemplates.mockRejectedValue(new Error('500'))
		api.getAdminTemplates.mockResolvedValue({ data: [installedTemplate] })
		const wrapper = mountPage()
		await flushPromises()

		expect(
			wrapper.find('[data-testid="admin-shipped-templates"]').exists(),
		).toBe(false)
		expect(wrapper.find('[data-testid="admin-templates-error"]').text()).toBe(
			'The ready-made templates could not be loaded.',
		)
		expect(wrapper.find('.launchpad-admin__templates').text()).toContain(
			'Mijn werkdag',
		)
	})
})

describe('TemplatesPage download', () => {
	it('asks for a one-dashboard export of that template', async () => {
		api.getAdminTemplates.mockResolvedValue({ data: [installedTemplate] })
		api.exportDashboards.mockResolvedValue({ data: new Blob(['zip']) })
		window.URL.createObjectURL = vi.fn(() => 'blob:x')
		window.URL.revokeObjectURL = vi.fn()
		const click = vi
			.spyOn(HTMLAnchorElement.prototype, 'click')
			.mockImplementation(() => {})
		const wrapper = mountPage()
		await flushPromises()

		await wrapper
			.find('[data-testid="admin-download-template"]')
			.trigger('click')
		await flushPromises()

		expect(api.exportDashboards).toHaveBeenCalledWith({
			scope: 'dashboard',
			dashboardUuid: 'uuid-4',
		})
		expect(click).toHaveBeenCalledTimes(1)
		expect(click.mock.instances[0].download).toBe(
			'launchpad-template-uuid-4.zip',
		)
		expect(window.URL.revokeObjectURL).toHaveBeenCalledWith('blob:x')
		click.mockRestore()
	})

	it('says so when the download fails', async () => {
		api.getAdminTemplates.mockResolvedValue({ data: [installedTemplate] })
		api.exportDashboards.mockRejectedValue(new Error('404'))
		const wrapper = mountPage()
		await flushPromises()

		await wrapper
			.find('[data-testid="admin-download-template"]')
			.trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-templates-error"]').text()).toBe(
			'The template "Mijn werkdag" could not be downloaded.',
		)
	})
})
