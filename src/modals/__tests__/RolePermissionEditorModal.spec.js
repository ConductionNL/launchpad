/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `RolePermissionEditorModal.vue`: the title, the
 * field updates it emits without mutating the row, the locked group id when
 * editing, and when Save is enabled.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RolePermissionEditorModal from '../RolePermissionEditorModal.vue'

const TextFieldStub = {
	props: ['modelValue', 'label', 'disabled'],
	emits: ['update:modelValue'],
	template: '<input class="field" :data-label="label" :disabled="disabled" :value="modelValue" />',
}

const ButtonStub = {
	props: ['disabled'],
	emits: ['click'],
	template: '<button class="btn" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const stubs = {
	NcModal: { emits: ['close'], template: '<div class="nc-modal-stub"><slot /></div>' },
	NcTextField: TextFieldStub,
	NcButton: ButtonStub,
}

function mountModal(props = {}) {
	return mount(RolePermissionEditorModal, {
		propsData: { row: { name: 'Editors', groupId: 'editors', description: '' }, ...props },
		stubs,
	})
}

function field (wrapper, label) {
  return wrapper.findAllComponents(TextFieldStub).find((f) => f.props('label') === label)
}
function button (wrapper, label) {
  return wrapper.findAll('button.btn').find((b) => b.text() === label)
}

describe('RolePermissionEditorModal', () => {
	it('titles a new row "Add" and an existing one "Edit"', () => {
		expect(mountModal().find('h3').text()).toBe('Add role permission')
		expect(mountModal({ row: { id: 3, name: 'a', groupId: 'g' } }).find('h3').text()).toBe('Edit role permission')
	})

	it('emits a copy of the row with the edited field, leaving the prop untouched', () => {
		const row = { name: 'Editors', groupId: 'editors', description: '' }
		const wrapper = mountModal({ row })

		field(wrapper, 'Name').vm.$emit('update:modelValue', 'Writers')
		field(wrapper, 'Nextcloud group ID').vm.$emit('update:modelValue', 'writers')
		field(wrapper, 'Description (optional)').vm.$emit('update:modelValue', 'Can write')

		expect(wrapper.emitted('update:row')).toEqual([
			[{ name: 'Writers', groupId: 'editors', description: '' }],
			[{ name: 'Editors', groupId: 'writers', description: '' }],
			[{ name: 'Editors', groupId: 'editors', description: 'Can write' }],
		])
		expect(row).toEqual({ name: 'Editors', groupId: 'editors', description: '' })
	})

	it('forwards the widget CSV fields as their own update events', () => {
		const wrapper = mountModal({ allowedWidgetsCsv: 'a', deniedWidgetsCsv: 'b' })
		expect(field(wrapper, 'Allowed widget IDs (comma separated)').props('modelValue')).toBe('a')

		field(wrapper, 'Allowed widget IDs (comma separated)').vm.$emit('update:modelValue', 'a,c')
		field(wrapper, 'Denied widget IDs (comma separated)').vm.$emit('update:modelValue', 'd')

		expect(wrapper.emitted('update:allowedWidgetsCsv')).toEqual([['a,c']])
		expect(wrapper.emitted('update:deniedWidgetsCsv')).toEqual([['d']])
		expect(wrapper.emitted('update:row')).toBeUndefined()
	})

	it('locks the group id only when editing an existing row', () => {
		expect(field(mountModal(), 'Nextcloud group ID').props('disabled')).toBe(false)
		expect(field(mountModal({ row: { id: 1, name: 'a', groupId: 'g' } }), 'Nextcloud group ID').props('disabled')).toBe(true)
	})

	it.each([
		['a complete row', { name: 'n', groupId: 'g' }, false, false],
		['a missing name', { name: '', groupId: 'g' }, false, true],
		['a missing group', { name: 'n', groupId: '' }, false, true],
		['a save in progress', { name: 'n', groupId: 'g' }, true, true],
	])('Save with %s is disabled: %s', (_case, row, saving, disabled) => {
		const save = button(mountModal({ row, saving }), 'Save')
		expect(save.attributes('disabled') !== undefined).toBe(disabled)
	})

	it('emits save and close from its buttons', async () => {
		const wrapper = mountModal()
		await button(wrapper, 'Save').trigger('click')
		await button(wrapper, 'Cancel').trigger('click')
		expect(wrapper.emitted('save')).toHaveLength(1)
		expect(wrapper.emitted('close')).toHaveLength(1)
	})
})
