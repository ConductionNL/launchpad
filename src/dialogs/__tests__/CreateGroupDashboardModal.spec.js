/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
vi.mock('../../services/api.js', () => ({
	api: {
		createGroupDashboard: createGroupDashboardMock,
	},
}))

const stubs = {
	NcDialog: { template: '<div class="nc-dialog" :data-test="$attrs[\'data-test\']"><slot /><div class="actions"><slot name="actions" /></div></div>' },
	NcButton: { emits: ['click'], template: '<button class="nc-button" :data-test="$attrs[\'data-test\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>' },
	// The component binds all three of these with `v-model`, which Vue 3
	// compiles to `:modelValue` + `@update:modelValue` (Vue 2 used `value`
	// + `input`, and NcCheckboxRadioSwitch used `checked`/`update:checked`).
	// A stub still declaring the old contract neither receives the value nor
	// writes it back, so the form silently stayed empty.
	NcTextField: {
		props: ['modelValue', 'label', 'error', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text-field" :data-test="$attrs[\'data-test\']" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	// `options` is declared so the array is consumed as a prop rather than
	// falling through onto the native <select> element, which rejects it.
	NcSelect: {
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template: '<select class="nc-select" :data-test="$attrs[\'data-test\']" />',
	},
	NcCheckboxRadioSwitch: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label class="nc-cbs" :data-test="$attrs[\'data-test\']"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
	},
}

let CreateGroupDashboardModal

beforeEach(async () => {
	setActivePinia(createPinia())
	globalThis.t = (_app, key) => key
	createGroupDashboardMock.mockReset()
	CreateGroupDashboardModal = (await import('../CreateGroupDashboardModal.vue')).default
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
