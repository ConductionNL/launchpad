/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `TemplateEditorModal.vue`: seeding the working copy
 * from the `template` prop on open, the create/edit title, and saving
 * through the create or update endpoint.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TemplateEditorModal from '../TemplateEditorModal.vue'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))
vi.mock('../../services/api.js', () => ({
	api: { createAdminTemplate: vi.fn(), updateAdminTemplate: vi.fn() },
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { error: vi.fn() },
}))

const ButtonStub = {
	emits: ['click'],
	template: '<button class="btn" @click="$emit(\'click\')"><slot /></button>',
}

const stubs = {
	NcModal: {
		props: ['name'],
		emits: ['close'],
		template: '<div class="nc-modal-stub" :data-name="name"><slot /></div>',
	},
	NcButton: ButtonStub,
	NcTextField: true,
	NcSelect: true,
	NcSelectTags: true,
	NcCheckboxRadioSwitch: true,
}

function mountModal(props = {}) {
	return mount(TemplateEditorModal, {
		propsData: { open: true, template: null, ...props },
		stubs,
	})
}

/**
 * Click the button whose text is `label`.
 *
 * @param {object} wrapper the mounted modal.
 * @param {string} label the button text.
 */
async function click(wrapper, label) {
	const button = wrapper.findAll('button.btn').find((b) => b.text() === label)
	await button.trigger('click')
	await flushPromises()
}

beforeEach(() => {
	api.createAdminTemplate.mockReset().mockResolvedValue({})
	api.updateAdminTemplate.mockReset().mockResolvedValue({})
	logger.error.mockReset()
})

describe('TemplateEditorModal', () => {
	it('renders nothing while closed', () => {
		expect(mountModal({ open: false }).find('.nc-modal-stub').exists()).toBe(false)
	})

	it('opens a blank create form with the add-only permission', () => {
		const wrapper = mountModal()

		expect(wrapper.vm.form).toEqual({
			id: null,
			name: '',
			description: '',
			targetGroups: [],
			permissionLevel: { id: 'add_only', label: 'Add only' },
			isDefault: false,
		})
		expect(wrapper.vm.isEdit).toBe(false)
		expect(wrapper.find('h2').text()).toBe('Create template')
	})

	it('seeds an edit form from the template, mapping the permission id to its option', () => {
		const wrapper = mountModal({
			template: { id: 7, name: 'Sales', description: 'd', targetGroups: ['sales'], permissionLevel: 'full', isDefault: true },
		})

		expect(wrapper.vm.form).toEqual({
			id: 7,
			name: 'Sales',
			description: 'd',
			targetGroups: ['sales'],
			permissionLevel: { id: 'full', label: 'Full customization' },
			isDefault: true,
		})
		expect(wrapper.vm.isEdit).toBe(true)
		expect(wrapper.find('h2').text()).toBe('Edit template')
		expect(wrapper.find('.nc-modal-stub').attributes('data-name')).toBe('Edit template')
	})

	it('falls back to add-only for an unknown permission level', () => {
		const wrapper = mountModal({ template: { id: 1, name: 'x', permissionLevel: 'bogus' } })
		expect(wrapper.vm.form.permissionLevel).toEqual({ id: 'add_only', label: 'Add only' })
	})

	it('re-seeds when reopened, dropping unsaved edits', async () => {
		const wrapper = mountModal({ template: { id: 2, name: 'Original', permissionLevel: 'view_only' } })
		wrapper.vm.form.name = 'Edited but cancelled'

		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })

		expect(wrapper.vm.form.name).toBe('Original')
	})

	it('re-seeds when the template changes while open, but not while closed', async () => {
		const wrapper = mountModal({ template: { id: 2, name: 'A' } })
		await wrapper.setProps({ template: { id: 3, name: 'B' } })
		expect(wrapper.vm.form.name).toBe('B')

		await wrapper.setProps({ open: false })
		wrapper.vm.form.name = 'kept'
		await wrapper.setProps({ template: { id: 4, name: 'C' } })
		expect(wrapper.vm.form.name).toBe('kept')
	})

	it('creates a new template with the permission id and emits saved', async () => {
		const wrapper = mountModal()
		Object.assign(wrapper.vm.form, { name: 'New', description: 'desc', targetGroups: ['g'], isDefault: true })

		await click(wrapper, 'Save')

		expect(api.createAdminTemplate).toHaveBeenCalledWith({
			name: 'New',
			description: 'desc',
			targetGroups: ['g'],
			permissionLevel: 'add_only',
			isDefault: true,
		})
		expect(api.updateAdminTemplate).not.toHaveBeenCalled()
		expect(wrapper.emitted('saved')).toHaveLength(1)
	})

	it('updates an existing template by id', async () => {
		const wrapper = mountModal({
			template: { id: 9, name: 'T', description: '', targetGroups: [], permissionLevel: 'view_only', isDefault: false },
		})

		await click(wrapper, 'Save')

		expect(api.updateAdminTemplate).toHaveBeenCalledWith(9, {
			name: 'T',
			description: '',
			targetGroups: [],
			permissionLevel: 'view_only',
			isDefault: false,
		})
		expect(api.createAdminTemplate).not.toHaveBeenCalled()
		expect(wrapper.emitted('saved')).toHaveLength(1)
	})

	it('logs a failed save and does not emit saved', async () => {
		const failure = new Error('500')
		api.createAdminTemplate.mockRejectedValue(failure)
		const wrapper = mountModal()

		await click(wrapper, 'Save')

		expect(logger.error).toHaveBeenCalledWith('Failed to save template:', failure)
		expect(wrapper.emitted('saved')).toBeUndefined()
	})

	it('emits close from Cancel', async () => {
		const wrapper = mountModal()
		await click(wrapper, 'Cancel')
		expect(wrapper.emitted('close')).toHaveLength(1)
	})
})
