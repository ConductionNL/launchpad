/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TemplatesPage: updating an installed ready-made template (admin-templates
 * REQ-TMPL-020). The page is mounted with the real update dialog, so the
 * test covers the wiring from the button to the two server calls. Payloads
 * are the shapes ShippedTemplateService::listTemplates and
 * ShippedTemplateUpdateService::update return.
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
	NcDialog: {
		name: 'NcDialog',
		props: ['open', 'name'],
		emits: ['update:open'],
		template:
			'<div v-if="open" class="dialog"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>',
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
		updateShippedTemplate: vi.fn(),
		exportDashboards: vi.fn(),
	},
}))
vi.mock('../../../../services/api.js', () => ({ api }))
vi.mock('../../../../modals/TemplateEditorModal.vue', () => ({
	default: { name: 'TemplateEditorModal', template: '<div />' },
}))
vi.mock('../../../../modals/TemplateResyncModal.vue', () => ({
	default: { name: 'TemplateResyncModal', template: '<div />' },
}))

import TemplatesPage from '../TemplatesPage.vue'

function shipped(overrides = {}) {
	return {
		id: 'mijn-werkdag',
		name: 'Mijn werkdag',
		description: 'Startpagina voor medewerkers',
		language: 'nl',
		version: 3,
		widgetCount: 7,
		isInstalled: true,
		installedUuid: 'uuid-4',
		installedVersion: 2,
		updateAvailable: true,
		missingWidgets: [],
		...overrides,
	}
}

function plan(overrides = {}) {
	return {
		templateId: 'mijn-werkdag',
		uuid: 'uuid-4',
		id: 4,
		name: 'Mijn werkdag',
		installedVersion: 2,
		version: 3,
		upToDate: false,
		dryRun: true,
		applied: false,
		added: ['Mijn tickets (object-list)'],
		removed: ['Eigen notitie (text)'],
		changed: [{ widget: 'Mijn zaken (object-list)', fields: ['position'] }],
		unchanged: 4,
		copies: 12,
		resync: null,
		...overrides,
	}
}

function mountPage() {
	return mount(TemplatesPage, {
		global: { stubs: { Plus: true, ViewDashboard: true } },
	})
}

const updateButton = '[data-testid="admin-shipped-template-update"]'

beforeEach(() => {
	vi.clearAllMocks()
	api.getAdminTemplates.mockResolvedValue({ data: [] })
	api.getShippedTemplates.mockResolvedValue({ data: [shipped()] })
})

describe('TemplatesPage update of a ready-made template', () => {
	it('offers the update only when a newer version ships', async () => {
		const wrapper = mountPage()
		await flushPromises()
		expect(wrapper.find(updateButton).text()).toBe('Update to version 3')

		api.getShippedTemplates.mockResolvedValue({
			data: [shipped({ installedVersion: 3, updateAvailable: false })],
		})
		const upToDate = mountPage()
		await flushPromises()
		expect(upToDate.find(updateButton).exists()).toBe(false)
		expect(
			upToDate.find('[data-testid="admin-shipped-template-added"]').exists(),
		).toBe(true)
	})

	it('shows what changes before anything is written', async () => {
		api.updateShippedTemplate.mockResolvedValue({ data: plan() })
		const wrapper = mountPage()
		await flushPromises()

		await wrapper.find(updateButton).trigger('click')
		await flushPromises()

		expect(api.updateShippedTemplate).toHaveBeenCalledTimes(1)
		expect(api.updateShippedTemplate).toHaveBeenCalledWith('mijn-werkdag', {
			dryRun: true,
		})
		const dialog = wrapper.find('[data-testid="shipped-update-plan"]')
		expect(dialog.text()).toContain('from version 2 to version 3')
		expect(wrapper.find('[data-testid="shipped-update-added"]').text()).toBe(
			'Mijn tickets (object-list)',
		)
		expect(wrapper.find('[data-testid="shipped-update-removed"]').text()).toBe(
			'Eigen notitie (text)',
		)
		expect(wrapper.find('[data-testid="shipped-update-changed"]').text()).toBe(
			'Mijn zaken (object-list)',
		)
		expect(
			wrapper.find('[data-testid="shipped-update-copies"]').text(),
		).toContain('Members with a copy of this template: 12.')
	})

	it('cancelling writes nothing', async () => {
		api.updateShippedTemplate.mockResolvedValue({ data: plan() })
		const wrapper = mountPage()
		await flushPromises()
		await wrapper.find(updateButton).trigger('click')
		await flushPromises()

		await wrapper.find('[data-testid="shipped-update-cancel"]').trigger('click')
		await flushPromises()

		expect(api.updateShippedTemplate).toHaveBeenCalledTimes(1)
		expect(wrapper.find('[data-testid="shipped-update-plan"]').exists()).toBe(
			false,
		)
	})

	it('updates on confirm, says so and drops the button', async () => {
		api.updateShippedTemplate.mockResolvedValue({ data: plan() })
		const wrapper = mountPage()
		await flushPromises()
		await wrapper.find(updateButton).trigger('click')
		await flushPromises()

		api.updateShippedTemplate.mockResolvedValue({
			data: plan({
				dryRun: false,
				applied: true,
				resync: { async: false, affectedCount: 12, totalCopies: 12 },
			}),
		})
		api.getShippedTemplates.mockResolvedValue({
			data: [shipped({ installedVersion: 3, updateAvailable: false })],
		})
		await wrapper.find('[data-testid="shipped-update-confirm"]').trigger('click')
		await flushPromises()

		expect(api.updateShippedTemplate).toHaveBeenCalledTimes(2)
		expect(api.updateShippedTemplate).toHaveBeenLastCalledWith('mijn-werkdag')
		expect(wrapper.find('[data-testid="admin-templates-status"]').text()).toBe(
			'Updated "Mijn werkdag" to version 3. Members with a copy: 12.',
		)
		expect(wrapper.find(updateButton).exists()).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-plan"]').exists()).toBe(
			false,
		)
	})

	it('says so when the plan cannot be loaded, and cannot be confirmed', async () => {
		api.updateShippedTemplate.mockRejectedValue(new Error('500'))
		const wrapper = mountPage()
		await flushPromises()

		await wrapper.find(updateButton).trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="shipped-update-error"]').text()).toBe(
			'The changes could not be worked out. Nothing was updated.',
		)
		expect(
			wrapper
				.find('[data-testid="shipped-update-confirm"]')
				.attributes('disabled'),
		).toBeDefined()
	})

	it('says so when the update fails and keeps the button', async () => {
		api.updateShippedTemplate.mockResolvedValueOnce({ data: plan() })
		api.updateShippedTemplate.mockRejectedValueOnce(new Error('500'))
		const wrapper = mountPage()
		await flushPromises()
		await wrapper.find(updateButton).trigger('click')
		await flushPromises()

		await wrapper.find('[data-testid="shipped-update-confirm"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="shipped-update-error"]').text()).toBe(
			'The template "Mijn werkdag" could not be updated.',
		)
		expect(wrapper.find(updateButton).exists()).toBe(true)
	})
})
