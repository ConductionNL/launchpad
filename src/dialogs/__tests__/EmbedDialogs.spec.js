/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed dialogs: revoking asks a reason (the first by default) and
 * optional notes; deleting a theme warns how many tokens use it
 * (embedded-analytics tasks 16 and 27).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DeleteTenantThemeDialog from '../DeleteTenantThemeDialog.vue'
import RevokeEmbedTokenDialog from '../RevokeEmbedTokenDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', count),
}))

const Select = { name: 'NcSelect', props: ['modelValue', 'options'], emits: ['update:modelValue'], template: '<div class="select" />' }
const stubs = {
	NcDialog: { props: ['name', 'open'], template: '<div class="dialog"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>' },
	NcButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
	NcSelect: Select,
	NcTextArea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
}

describe('RevokeEmbedTokenDialog', () => {
	it('offers the admin reasons and confirms the chosen reason with trimmed notes', async () => {
		const wrapper = mount(RevokeEmbedTokenDialog, { props: { open: true, name: 'Zeist WOO' }, global: { stubs } })

		expect(wrapper.text()).toContain('Revoke Zeist WOO?')
		const select = wrapper.findComponent(Select)
		expect(select.props('options').map((option) => option.id)).toEqual(['compromised', 'no_longer_needed', 'host_decommissioned', 'other'])
		expect(select.props('modelValue').id).toBe('compromised')

		select.vm.$emit('update:modelValue', select.props('options')[2])
		await wrapper.find('textarea').setValue('  site offline  ')
		await wrapper.find('[data-test="revoke-embed-token-confirm"]').trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([[{ reason: 'host_decommissioned', notes: 'site offline' }]])
	})
})

describe('DeleteTenantThemeDialog', () => {
	it('warns only when tokens use the theme', () => {
		const used = mount(DeleteTenantThemeDialog, { props: { open: true, name: 'Zeist', tokenCount: 2 }, global: { stubs } })
		expect(used.find('[data-test="delete-theme-in-use"]').text()).toBe('2 embed tokens use this theme and fall back to the default theme.')

		const unused = mount(DeleteTenantThemeDialog, { props: { open: true, name: 'Zeist' }, global: { stubs } })
		expect(unused.find('[data-test="delete-theme-in-use"]').exists()).toBe(false)
	})
})
