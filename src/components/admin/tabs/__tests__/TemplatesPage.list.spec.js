/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TemplatesPage: the admin template list itself (admin-templates spec):
 * the empty state, each row's groups summary and default badge, and the
 * create, edit, delete and re-sync actions with the modals they drive.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		props: ['disabled', 'variant'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
	NcEmptyContent: {
		name: 'NcEmptyContent',
		props: ['description'],
		template: '<div class="empty">{{ description }}<slot name="icon" /></div>',
	},
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const { api } = vi.hoisted(() => ({
	api: {
		getAdminTemplates: vi.fn(),
		getShippedTemplates: vi.fn(),
		deleteAdminTemplate: vi.fn(),
	},
}))
vi.mock('../../../../services/api.js', () => ({ api }))
vi.mock('../../../../modals/TemplateEditorModal.vue', () => ({
	default: {
		name: 'TemplateEditorModal',
		props: ['open', 'template'],
		emits: ['close', 'saved'],
		template: '<div />',
	},
}))
vi.mock('../../../../modals/TemplateResyncModal.vue', () => ({
	default: {
		name: 'TemplateResyncModal',
		props: ['open', 'template'],
		emits: ['close', 'resynced'],
		template: '<div />',
	},
}))
vi.mock('../../../../dialogs/ShippedTemplateUpdateDialog.vue', () => ({
	default: { name: 'ShippedTemplateUpdateDialog', template: '<div />' },
}))

import TemplatesPage from '../TemplatesPage.vue'
import { logger } from '../../../../utils/logger.js'

const TEMPLATES = [
	{ id: 1, name: 'Staff start', icon: 'Star', isDefault: 1, targetGroups: ['staff', 'hr'] },
	{ id: 2, name: 'Everyone', icon: 'Star', isDefault: 0, targetGroups: [] },
]

async function mountPage() {
	const wrapper = mount(TemplatesPage)
	await flushPromises()
	return wrapper
}

function rowButton(wrapper, rowIndex, label) {
	return wrapper
		.findAll('.launchpad-admin__template')
		[rowIndex].findAll('button')
		.find((b) => b.text() === label)
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getAdminTemplates.mockResolvedValue({ data: TEMPLATES })
	api.getShippedTemplates.mockResolvedValue({ data: [] })
	api.deleteAdminTemplate.mockResolvedValue({})
})

afterEach(() => {
	vi.unstubAllGlobals()
})

describe('TemplatesPage list', () => {
	it('shows the empty state without templates', async () => {
		api.getAdminTemplates.mockResolvedValue({ data: null })
		const wrapper = await mountPage()

		expect(wrapper.find('.empty').text()).toContain('No templates yet')
		expect(wrapper.findAll('.launchpad-admin__template')).toHaveLength(0)
	})

	it('keeps the list empty and logs when loading fails', async () => {
		api.getAdminTemplates.mockRejectedValue(new Error('down'))
		const wrapper = await mountPage()

		expect(wrapper.vm.templates).toEqual([])
		expect(logger.error).toHaveBeenCalledWith('Failed to load templates:', expect.any(Error))
	})

	it('lists each template with its groups and the default badge', async () => {
		const wrapper = await mountPage()
		const rows = wrapper.findAll('.launchpad-admin__template')

		expect(rows).toHaveLength(2)
		expect(rows[0].find('.launchpad-admin__template-groups').text()).toBe('staff, hr')
		expect(rows[0].find('.launchpad-admin__badge').exists()).toBe(true)
		expect(rows[1].find('.launchpad-admin__template-groups').text()).toBe('All users')
		expect(rows[1].find('.launchpad-admin__badge').exists()).toBe(false)
	})
})

describe('TemplatesPage editor', () => {
	it('opens an empty editor from Create template', async () => {
		const wrapper = await mountPage()

		await wrapper.find('[data-testid="admin-create-template"]').trigger('click')

		const editor = wrapper.findComponent({ name: 'TemplateEditorModal' })
		expect(editor.props('open')).toBe(true)
		expect(editor.props('template')).toBeNull()
	})

	it('opens the editor on a row\'s template and closes it again', async () => {
		const wrapper = await mountPage()

		await rowButton(wrapper, 1, 'Edit').trigger('click')
		const editor = wrapper.findComponent({ name: 'TemplateEditorModal' })
		expect(editor.props('open')).toBe(true)
		expect(editor.props('template')).toEqual(TEMPLATES[1])

		editor.vm.$emit('close')
		await flushPromises()
		expect(editor.props('open')).toBe(false)
		expect(editor.props('template')).toBeNull()
	})

	it('reloads the list and closes the editor after a save', async () => {
		const wrapper = await mountPage()
		await rowButton(wrapper, 0, 'Edit').trigger('click')
		api.getAdminTemplates.mockResolvedValue({ data: [TEMPLATES[0]] })

		wrapper.findComponent({ name: 'TemplateEditorModal' }).vm.$emit('saved')
		await flushPromises()

		expect(api.getAdminTemplates).toHaveBeenCalledTimes(2)
		expect(wrapper.findAll('.launchpad-admin__template')).toHaveLength(1)
		expect(wrapper.findComponent({ name: 'TemplateEditorModal' }).props('open')).toBe(false)
	})
})

describe('TemplatesPage delete', () => {
	it('deletes after confirmation and reloads', async () => {
		vi.stubGlobal('confirm', vi.fn(() => true))
		const wrapper = await mountPage()

		await rowButton(wrapper, 1, 'Delete').trigger('click')
		await flushPromises()

		expect(confirm).toHaveBeenCalledWith('Are you sure you want to delete this template?')
		expect(api.deleteAdminTemplate).toHaveBeenCalledWith(2)
		expect(api.getAdminTemplates).toHaveBeenCalledTimes(2)
	})

	it('does nothing when the confirmation is declined', async () => {
		vi.stubGlobal('confirm', vi.fn(() => false))
		const wrapper = await mountPage()

		await rowButton(wrapper, 1, 'Delete').trigger('click')
		await flushPromises()

		expect(api.deleteAdminTemplate).not.toHaveBeenCalled()
		expect(api.getAdminTemplates).toHaveBeenCalledTimes(1)
	})

	it('logs a failed delete without reloading', async () => {
		vi.stubGlobal('confirm', vi.fn(() => true))
		api.deleteAdminTemplate.mockRejectedValue(new Error('403'))
		const wrapper = await mountPage()

		await rowButton(wrapper, 0, 'Delete').trigger('click')
		await flushPromises()

		expect(logger.error).toHaveBeenCalledWith('Failed to delete template:', expect.any(Error))
		expect(api.getAdminTemplates).toHaveBeenCalledTimes(1)
	})
})

describe('TemplatesPage re-sync', () => {
	it('opens the re-sync modal on a row and closes it on close or after a re-sync', async () => {
		const wrapper = await mountPage()
		const modal = () => wrapper.findComponent({ name: 'TemplateResyncModal' })
		expect(modal().props('open')).toBe(false)

		await wrapper.findAll('[data-testid="admin-resync-template"]')[0].trigger('click')
		expect(modal().props('open')).toBe(true)
		expect(modal().props('template')).toEqual(TEMPLATES[0])

		modal().vm.$emit('resynced')
		await flushPromises()
		expect(modal().props('open')).toBe(false)

		await wrapper.findAll('[data-testid="admin-resync-template"]')[1].trigger('click')
		modal().vm.$emit('close')
		await flushPromises()
		expect(modal().props('template')).toBeNull()
	})
})
