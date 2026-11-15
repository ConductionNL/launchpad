/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The role layout default editor: every field emits the whole row with only
 * its own key changed (numbers as numbers), the title says add or edit, and
 * cancel and save are disabled while saving.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text) => text,
	translate: (_app, text) => text,
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['open', 'name'],
		emits: ['update:open'],
		template: '<div class="dialog" :data-name="name"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button :class="variant" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'type', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
	},
}))

const { default: RoleLayoutDefaultEditorDialog } = await import('../RoleLayoutDefaultEditorDialog.vue')

const row = {
	id: 7,
	name: 'Managers',
	groupId: 'managers',
	widgetId: 'analytics_dashboard',
	gridX: 0,
	gridY: 2,
	gridWidth: 6,
	gridHeight: 4,
	sortOrder: 1,
	isCompulsory: false,
	description: null,
}

/**
 * Mount the editor.
 *
 * @param {object} props Props over the defaults.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountEditor(props = {}) {
	return mount(RoleLayoutDefaultEditorDialog, { props: { open: true, row, ...props } })
}

/**
 * The text field with the given label.
 *
 * @param {import('@vue/test-utils').VueWrapper} wrapper The editor.
 * @param {string} label Its label.
 * @return {import('@vue/test-utils').DOMWrapper}
 */
function input(wrapper, label) {
	return wrapper.find(`input[aria-label="${label}"]`)
}

describe('RoleLayoutDefaultEditorDialog', () => {
	it('says edit for a stored row and add for a new one', () => {
		expect(mountEditor().find('.dialog').attributes('data-name')).toBe('Edit layout default')
		expect(mountEditor({ row: { ...row, id: null } }).find('.dialog').attributes('data-name')).toBe('Add layout default')
	})

	it('shows the row, numbers as text and a missing description as empty', () => {
		const wrapper = mountEditor()

		expect(input(wrapper, 'Name').element.value).toBe('Managers')
		expect(input(wrapper, 'Grid Y').element.value).toBe('2')
		expect(input(wrapper, 'Height (rows)').element.value).toBe('4')
		expect(input(wrapper, 'Description (optional)').element.value).toBe('')
	})

	it.each([
		['Name', 'Directors', { name: 'Directors' }],
		['Group ID', 'directors', { groupId: 'directors' }],
		['Widget ID', 'kpi', { widgetId: 'kpi' }],
		['Description (optional)', 'For the board', { description: 'For the board' }],
	])('the %s field emits the row with only that text changed', async (label, value, change) => {
		const wrapper = mountEditor()

		await input(wrapper, label).setValue(value)

		expect(wrapper.emitted('update:row')).toEqual([[{ ...row, ...change }]])
	})

	it.each([
		['Grid X', 'gridX'],
		['Grid Y', 'gridY'],
		['Width (columns)', 'gridWidth'],
		['Height (rows)', 'gridHeight'],
		['Sort order', 'sortOrder'],
	])('the %s field emits its value as a number', async (label, key) => {
		const wrapper = mountEditor()

		await input(wrapper, label).setValue('12')

		expect(wrapper.emitted('update:row')).toEqual([[{ ...row, [key]: 12 }]])
	})

	it('the compulsory switch emits the row with the flag set', async () => {
		const wrapper = mountEditor()

		await wrapper.find('input[type="checkbox"]').setValue(true)

		expect(wrapper.emitted('update:row')).toEqual([[{ ...row, isCompulsory: true }]])
	})

	it('cancel closes and save saves', async () => {
		const wrapper = mountEditor()
		const buttons = wrapper.findAll('button')

		await buttons.find((b) => b.text() === 'Cancel').trigger('click')
		await buttons.find((b) => b.text() === 'Save').trigger('click')

		expect(wrapper.emitted('update:open')).toEqual([[false]])
		expect(wrapper.emitted('save')).toHaveLength(1)
	})

	it('while saving both buttons are disabled and save says so', () => {
		const buttons = mountEditor({ saving: true }).findAll('button')

		expect(buttons.map((b) => b.text())).toEqual(['Cancel', 'Saving…'])
		expect(buttons.every((b) => b.attributes('disabled') !== undefined)).toBe(true)
	})
})
