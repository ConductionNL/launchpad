/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The three small dashboard dialogs: rename (only a changed, non-blank name
 * is confirmed, trimmed), delete (names the dashboards deleted with it) and
 * the public-share password prompt (unlocks unless a check is running).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (match, key) => (key in vars ? String(vars[key]) : match)),
	translate: (_app, text) => text,
}))

const dialog = {
	name: 'NcDialog',
	props: ['open', 'name', 'noClose'],
	emits: ['update:open'],
	template: '<div class="dialog" :data-name="name"><slot /><slot name="actions" /></div>',
}
const button = {
	name: 'NcButton',
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :class="variant" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}
const field = {
	name: 'NcTextField',
	props: ['modelValue', 'label'],
	emits: ['update:modelValue', 'keyup'],
	template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @keyup="$emit(\'keyup\', $event)" />',
}

vi.mock('@conduction/nextcloud-vue', () => ({ NcDialog: dialog, NcButton: button, NcTextField: field }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: dialog }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: button }))
vi.mock('@nextcloud/vue/components/NcPasswordField', () => ({ default: { ...field, name: 'NcPasswordField' } }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: { name: 'NcNoteCard', props: ['type'], template: '<div class="note"><slot /></div>' },
}))

const { default: GroupDashboardRenameDialog } = await import('../GroupDashboardRenameDialog.vue')
const { default: DeleteDashboardDialog } = await import('../DeleteDashboardDialog.vue')
const { default: PublicSharePasswordDialog } = await import('../PublicSharePasswordDialog.vue')

describe('GroupDashboardRenameDialog', () => {
	const rename = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Rename')

	it('starts from the current name with Rename disabled', () => {
		const wrapper = mount(GroupDashboardRenameDialog, { props: { open: true, currentName: 'Team' } })

		expect(wrapper.find('input').element.value).toBe('Team')
		expect(rename(wrapper).attributes('disabled')).toBeDefined()
	})

	it('confirms a changed name, trimmed', async () => {
		const wrapper = mount(GroupDashboardRenameDialog, { props: { open: true, currentName: 'Team' } })

		await wrapper.find('input').setValue('  Sales  ')
		await rename(wrapper).trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([['Sales']])
	})

	it('confirms on Enter too', async () => {
		const wrapper = mount(GroupDashboardRenameDialog, { props: { open: true, currentName: 'Team' } })

		await wrapper.find('input').setValue('Ops')
		await wrapper.find('input').trigger('keyup.enter')

		expect(wrapper.emitted('confirm')).toEqual([['Ops']])
	})

	it('does not confirm a blank or unchanged name', async () => {
		const wrapper = mount(GroupDashboardRenameDialog, { props: { open: true, currentName: 'Team' } })

		await wrapper.find('input').setValue('   ')
		await wrapper.find('input').trigger('keyup.enter')
		await wrapper.find('input').setValue(' Team ')
		await wrapper.find('input').trigger('keyup.enter')

		expect(wrapper.emitted('confirm')).toBeUndefined()
	})

	it('resets the draft when reopened and closes on Cancel', async () => {
		const wrapper = mount(GroupDashboardRenameDialog, { props: { open: true, currentName: 'Team' } })
		await wrapper.find('input').setValue('Draft')
		await wrapper.setProps({ open: false, currentName: 'Renamed' })
		await wrapper.setProps({ open: true })

		expect(wrapper.find('input').element.value).toBe('Renamed')

		await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click')
		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})
})

describe('DeleteDashboardDialog', () => {
	it('names the dashboard and confirms on Delete', async () => {
		const wrapper = mount(DeleteDashboardDialog, { props: { open: true, name: 'Team' } })

		expect(wrapper.text()).toContain('Delete Team? This cannot be undone.')
		expect(wrapper.find('[data-testid="delete-dashboard-children"]').exists()).toBe(false)

		await wrapper.find('[data-testid="delete-dashboard-confirm"]').trigger('click')
		expect(wrapper.emitted('confirm')).toHaveLength(1)
	})

	it('names how many dashboards are deleted with it', () => {
		const wrapper = mount(DeleteDashboardDialog, { props: { open: true, name: 'Team', childCount: 3 } })

		expect(wrapper.find('[data-testid="delete-dashboard-children"]').text())
			.toBe('Dashboards under Team: 3. They are deleted with it.')
	})

	it('closes on Cancel without confirming', async () => {
		const wrapper = mount(DeleteDashboardDialog, { props: { open: true, name: 'Team' } })

		await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click')

		expect(wrapper.emitted('update:open')).toEqual([[false]])
		expect(wrapper.emitted('confirm')).toBeUndefined()
	})
})

describe('PublicSharePasswordDialog', () => {
	it('unlocks with the typed password', async () => {
		const wrapper = mount(PublicSharePasswordDialog)

		await wrapper.find('input').setValue('s3cret')
		await wrapper.find('button').trigger('click')
		await wrapper.find('input').trigger('keyup.enter')

		expect(wrapper.emitted('unlock')).toEqual([['s3cret'], ['s3cret']])
	})

	it('does not unlock while a check is running', async () => {
		const wrapper = mount(PublicSharePasswordDialog, { props: { loading: true } })

		await wrapper.find('input').setValue('x')
		await wrapper.find('input').trigger('keyup.enter')

		expect(wrapper.find('button').attributes('disabled')).toBeDefined()
		expect(wrapper.emitted('unlock')).toBeUndefined()
	})

	it('shows the error only when there is one', async () => {
		const wrapper = mount(PublicSharePasswordDialog)
		expect(wrapper.find('.note').exists()).toBe(false)

		await wrapper.setProps({ error: 'Wrong password' })
		expect(wrapper.find('.note').text()).toBe('Wrong password')
	})
})
