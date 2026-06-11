/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest tests for `CreateGroupDashboardModal.vue` (admin-group-management
 * Task 7). Covers the happy path + validation surface. Uses stubs for
 * NcDialog/NcTextField/etc so we exercise our form logic, not the
 * library's render layer.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, source) => source,
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))
vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const createGroupDashboardMock = vi.fn()
vi.mock('../../../services/api.js', () => ({
	api: {
		createGroupDashboard: createGroupDashboardMock,
	},
}))

const stubs = {
	NcDialog: { template: '<div class="nc-dialog" :data-test="$attrs[\'data-test\']"><slot /><div class="actions"><slot name="actions" /></div></div>' },
	NcButton: { template: '<button class="nc-button" :data-test="$attrs[\'data-test\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcTextField: {
		props: ['value', 'label', 'error', 'helperText'],
		template: '<input class="nc-text-field" :data-test="$attrs[\'data-test\']" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
	},
	NcSelect: { template: '<select class="nc-select" :data-test="$attrs[\'data-test\']" />' },
	NcCheckboxRadioSwitch: {
		props: ['checked'],
		template: '<label class="nc-cbs" :data-test="$attrs[\'data-test\']"><input type="checkbox" :checked="checked" @change="$emit(\'update:checked\', $event.target.checked)" /><slot /></label>',
	},
}

let CreateGroupDashboardModal

beforeEach(async () => {
	setActivePinia(createPinia())
	globalThis.t = (_app, key) => key
	createGroupDashboardMock.mockReset()
	CreateGroupDashboardModal = (await import('../group/CreateGroupDashboardModal.vue')).default
})

function mountModal() {
	return mount(CreateGroupDashboardModal, {
		propsData: { group: { id: 'admins', displayName: 'Admins' } },
		stubs,
	})
}

describe('CreateGroupDashboardModal', () => {
	it('disables submit until the name has 2+ characters', async () => {
		const wrapper = mountModal()
		const submit = wrapper.find('[data-test="create-group-dashboard-submit"]')
		expect(submit.attributes('disabled')).toBeDefined()
		// Type 1 char — still invalid.
		const nameInput = wrapper.find('[data-test="create-group-dashboard-name"]')
		await nameInput.setValue('a')
		expect(wrapper.vm.canSubmit).toBe(false)
		// Type 2+ chars — submit unlocks.
		await nameInput.setValue('Ops')
		expect(wrapper.vm.canSubmit).toBe(true)
	})

	it('surfaces the long-name validation error after 64 chars', async () => {
		const wrapper = mountModal()
		await wrapper.find('[data-test="create-group-dashboard-name"]').setValue('a'.repeat(65))
		expect(wrapper.vm.canSubmit).toBe(false)
		expect(wrapper.vm.nameError).toBe('Name must be at most 64 characters')
	})

	it('calls api.createGroupDashboard with the trimmed payload and emits "created"', async () => {
		createGroupDashboardMock.mockResolvedValueOnce({
			data: { data: { uuid: 'u1', name: 'Ops' } },
		})
		const wrapper = mountModal()
		await wrapper.find('[data-test="create-group-dashboard-name"]').setValue('Ops')
		await wrapper.find('[data-test="create-group-dashboard-submit"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		expect(createGroupDashboardMock).toHaveBeenCalledWith('admins', expect.objectContaining({
			name: 'Ops',
			isDefault: false,
		}))
		expect(wrapper.emitted('created')).toBeTruthy()
	})

	it('emits "close" when Cancel is clicked', async () => {
		const wrapper = mountModal()
		await wrapper.find('[data-test="create-group-dashboard-cancel"]').trigger('click')
		expect(wrapper.emitted('close')).toBeTruthy()
	})
})
