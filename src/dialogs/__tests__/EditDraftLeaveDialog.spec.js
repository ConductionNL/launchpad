/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The question when edit mode ends with unsaved changes (board LpBewerken):
 * Save changes? (Don't save, Keep editing, Save) or, on Cancel, Discard
 * changes? (Discard, Keep editing). Closing it keeps editing.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-leaving-with-unsaved-changes-asks-first-req-edit-003
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => {
	const fill = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
	return {
		t: (_app, text, vars = {}) => fill(text, vars),
		n: (_app, one, many, count, vars = {}) => fill(count === 1 ? one : many, vars),
	}
})
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open'],
		emits: ['update:open'],
		template: '<div class="dialog"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'text'],
		emits: ['click'],
		template: '<button @click="$emit(\'click\')">{{ text }}<slot /></button>',
	},
}))

const { default: EditDraftLeaveDialog } = await import('../EditDraftLeaveDialog.vue')

describe('EditDraftLeaveDialog', () => {
	it('asks to save on leaving, with Don\'t save, Keep editing and Save', async () => {
		const wrapper = mount(EditDraftLeaveDialog, { props: { open: true, mode: 'leave', count: 2 } })
		expect(wrapper.find('h2').text()).toBe('Save changes?')
		expect(wrapper.text()).toContain('This dashboard has 2 unsaved changes.')
		expect(wrapper.findAll('button').map((b) => b.text())).toEqual(['Don\'t save', 'Keep editing', 'Save'])
		await wrapper.find('[data-testid="edit-draft-save"]').trigger('click')
		await wrapper.find('[data-testid="edit-draft-discard"]').trigger('click')
		expect(wrapper.emitted('choose')).toEqual([['save'], ['discard']])
	})

	// @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-cancel-discards-the-draft-req-edit-004
	it('asks to discard on Cancel, with Discard and Keep editing', () => {
		const wrapper = mount(EditDraftLeaveDialog, { props: { open: true, mode: 'cancel', count: 1 } })
		expect(wrapper.find('h2').text()).toBe('Discard changes?')
		expect(wrapper.findAll('button').map((b) => b.text())).toEqual(['Discard', 'Keep editing'])
	})

	it('keeps editing when it is closed', async () => {
		const wrapper = mount(EditDraftLeaveDialog, { props: { open: true } })
		wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
		expect(wrapper.emitted('choose')).toEqual([['keep']])
	})
})
