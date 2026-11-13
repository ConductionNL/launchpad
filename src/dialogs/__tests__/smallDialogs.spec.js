/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The small dashboard dialogs mounted: what the delete, rename, public-share
 * password and role layout-default dialogs show, when their buttons are
 * enabled, and what each emits.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DeleteDashboardDialog from '../DeleteDashboardDialog.vue'
import GroupDashboardRenameDialog from '../GroupDashboardRenameDialog.vue'
import PublicSharePasswordDialog from '../PublicSharePasswordDialog.vue'
import RoleLayoutDefaultEditorDialog from '../RoleLayoutDefaultEditorDialog.vue'

/**
 * Translate as the identity, filling {placeholders}.
 *
 * @param {string} app The app id.
 * @param {string} text The text.
 * @param {object} vars The placeholders.
 * @return {string} The text.
 */
const tr = (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? String(vars[k]) : m))

const stubs = vi.hoisted(() => {
	const dialog = {
		name: 'NcDialog',
		props: { open: { type: Boolean, default: true }, name: String },
		template: '<div v-if="open" class="dialog" :data-name="name"><slot /><slot name="actions" /></div>',
	}
	const button = {
		name: 'NcButton',
		props: ['disabled', 'variant'],
		emits: ['click'],
		template: '<button :disabled="disabled" :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
	}
	const textField = {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue', 'keyup'],
		template: '<input class="field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @keyup.enter="$emit(\'keyup\', $event)">',
	}
	const checkbox = {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input type="checkbox" class="check" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)">',
	}
	const noteCard = { name: 'NcNoteCard', props: ['type'], template: '<div class="note" :data-type="type"><slot /></div>' }
	return { dialog, button, textField, checkbox, noteCard }
})

vi.mock('@nextcloud/l10n', () => ({
	t: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? String(vars[k]) : m)),
	translate: (app, text) => text,
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: stubs.dialog,
	NcButton: stubs.button,
	NcTextField: stubs.textField,
	NcCheckboxRadioSwitch: stubs.checkbox,
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: stubs.dialog }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: stubs.button }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: stubs.noteCard }))
vi.mock('@nextcloud/vue/components/NcPasswordField', () => ({ default: stubs.textField }))

/**
 * Mount a dialog with a global t().
 *
 * @param {object} component The dialog.
 * @param {object} props Its props.
 * @return {object} The wrapper.
 */
const mounted = (component, props) => mount(component, { props, global: { mocks: { t: tr } } })

/**
 * The button with this text.
 *
 * @param {object} wrapper The wrapper.
 * @param {string} text The button text.
 * @return {object} The DOM wrapper.
 */
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text)

describe('DeleteDashboardDialog', () => {
	it('names the dashboard and confirms or cancels', async () => {
		const wrapper = mounted(DeleteDashboardDialog, { open: true, name: 'Finance' })

		expect(wrapper.text()).toContain('Delete Finance? This cannot be undone.')
		expect(wrapper.find('[data-testid="delete-dashboard-children"]').exists()).toBe(false)

		await wrapper.get('[data-testid="delete-dashboard-confirm"]').trigger('click')
		await button(wrapper, 'Cancel').trigger('click')

		expect(wrapper.emitted('confirm')).toHaveLength(1)
		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})

	it('warns that the dashboards under it go too', () => {
		const wrapper = mounted(DeleteDashboardDialog, { open: true, name: 'Finance', childCount: 3 })

		expect(wrapper.get('[data-testid="delete-dashboard-children"]').text()).toBe('Dashboards under Finance: 3. They are deleted with it.')
	})
})

describe('GroupDashboardRenameDialog', () => {
	it('enables Rename only for a changed, non-blank name and emits it trimmed', async () => {
		const wrapper = mounted(GroupDashboardRenameDialog, { open: true, currentName: 'Team' })
		const rename = () => button(wrapper, 'Rename')

		expect(wrapper.get('input.field').element.value).toBe('Team')
		expect(rename().attributes('disabled')).toBeDefined()

		await wrapper.get('input.field').setValue('   ')
		expect(rename().attributes('disabled')).toBeDefined()

		await wrapper.get('input.field').setValue('  Team Alpha  ')
		expect(rename().attributes('disabled')).toBeUndefined()
		await rename().trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([['Team Alpha']])
	})

	it('submits on Enter, ignores Enter on an unchanged name, and cancels', async () => {
		const wrapper = mounted(GroupDashboardRenameDialog, { open: true, currentName: 'Team' })

		await wrapper.get('input.field').trigger('keyup', { key: 'Enter' })
		expect(wrapper.emitted('confirm')).toBeUndefined()

		await wrapper.get('input.field').setValue('Ops')
		await wrapper.get('input.field').trigger('keyup', { key: 'Enter' })
		expect(wrapper.emitted('confirm')).toEqual([['Ops']])

		await button(wrapper, 'Cancel').trigger('click')
		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})

	it('starts again from the current name each time it opens', async () => {
		const wrapper = mounted(GroupDashboardRenameDialog, { open: true, currentName: 'Team' })
		await wrapper.get('input.field').setValue('Draft')

		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true, currentName: 'Renamed' })

		expect(wrapper.get('input.field').element.value).toBe('Renamed')
	})
})

describe('PublicSharePasswordDialog', () => {
	it('emits the typed password from the button and from Enter', async () => {
		const wrapper = mounted(PublicSharePasswordDialog, {})

		await wrapper.get('input.field').setValue('s3cret')
		await button(wrapper, 'Unlock').trigger('click')
		await wrapper.get('input.field').trigger('keyup', { key: 'Enter' })

		expect(wrapper.emitted('unlock')).toEqual([['s3cret'], ['s3cret']])
		expect(wrapper.find('.note').exists()).toBe(false)
	})

	it('shows the error and does not submit while loading', async () => {
		const wrapper = mounted(PublicSharePasswordDialog, { error: 'Wrong password', loading: true })

		expect(wrapper.get('.note').text()).toBe('Wrong password')
		expect(wrapper.get('.note').attributes('data-type')).toBe('error')
		expect(button(wrapper, 'Unlock').attributes('disabled')).toBeDefined()

		await wrapper.get('input.field').trigger('keyup', { key: 'Enter' })
		expect(wrapper.emitted('unlock')).toBeUndefined()
	})
})

describe('RoleLayoutDefaultEditorDialog', () => {
	const row = { id: 7, name: 'Managers', groupId: 'managers', widgetId: 'analytics', gridX: 0, gridY: 1, gridWidth: 4, gridHeight: 3, sortOrder: 2, isCompulsory: false, description: '' }

	/**
	 * The field with this aria-label.
	 *
	 * @param {object} wrapper The wrapper.
	 * @param {string} label The label.
	 * @return {object} The input.
	 */
	const field = (wrapper, label) => wrapper.get(`input[aria-label="${label}"]`)

	it('titles an existing row Edit and a new one Add', () => {
		expect(mounted(RoleLayoutDefaultEditorDialog, { open: true, row }).get('.dialog').attributes('data-name')).toBe('Edit layout default')
		expect(mounted(RoleLayoutDefaultEditorDialog, { open: true, row: { ...row, id: null } }).get('.dialog').attributes('data-name')).toBe('Add layout default')
	})

	it('emits the whole row with each edited field, numbers as numbers', async () => {
		const wrapper = mounted(RoleLayoutDefaultEditorDialog, { open: true, row })

		await field(wrapper, 'Name').setValue('Leads')
		await field(wrapper, 'Group ID').setValue('leads')
		await field(wrapper, 'Widget ID').setValue('calendar')
		await field(wrapper, 'Grid X').setValue('2')
		await field(wrapper, 'Grid Y').setValue('5')
		await field(wrapper, 'Width (columns)').setValue('6')
		await field(wrapper, 'Height (rows)').setValue('2')
		await field(wrapper, 'Sort order').setValue('9')
		await field(wrapper, 'Description (optional)').setValue('For leads')
		await wrapper.get('input.check').setValue(true)

		expect(wrapper.emitted('update:row')).toEqual([
			[{ ...row, name: 'Leads' }],
			[{ ...row, groupId: 'leads' }],
			[{ ...row, widgetId: 'calendar' }],
			[{ ...row, gridX: 2 }],
			[{ ...row, gridY: 5 }],
			[{ ...row, gridWidth: 6 }],
			[{ ...row, gridHeight: 2 }],
			[{ ...row, sortOrder: 9 }],
			[{ ...row, description: 'For leads' }],
			[{ ...row, isCompulsory: true }],
		])
	})

	it('saves or cancels, and disables both while saving', async () => {
		const idle = mounted(RoleLayoutDefaultEditorDialog, { open: true, row })
		await button(idle, 'Save').trigger('click')
		await button(idle, 'Cancel').trigger('click')
		expect(idle.emitted('save')).toHaveLength(1)
		expect(idle.emitted('update:open')).toEqual([[false]])

		const busy = mounted(RoleLayoutDefaultEditorDialog, { open: true, row, saving: true })
		expect(button(busy, 'Saving…').attributes('disabled')).toBeDefined()
		expect(button(busy, 'Cancel').attributes('disabled')).toBeDefined()
	})
})
