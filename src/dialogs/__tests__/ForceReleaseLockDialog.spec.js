/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ForceReleaseLockDialog: the confirmation before an administrator takes
 * over a colleague's lock (dashboard-locking REQ-LOCKUI-003).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ForceReleaseLockDialog from '../ForceReleaseLockDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open'],
		template:
			'<div v-if="open" class="dialog"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

describe('ForceReleaseLockDialog', () => {
	it('names the holder and confirms or cancels', async () => {
		const wrapper = mount(ForceReleaseLockDialog, {
			props: { open: true, holderName: 'Sanne' },
		})
		expect(wrapper.text()).toContain('Sanne')
		const [cancel, confirm] = wrapper.findAll('button')
		await confirm.trigger('click')
		expect(wrapper.emitted('confirm')).toHaveLength(1)
		await cancel.trigger('click')
		expect(wrapper.emitted('update:open')[0]).toEqual([false])
	})
})
