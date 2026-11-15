/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Hidden (n)" lists what the person hid, with "Show again" and a confirmed
 * "Reset my view" (dashboards-personal-hide-ui REQ-PERSUI-002).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import HiddenWidgetsControl from '../HiddenWidgetsControl.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text) => text,
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	n: (_app, one, many, count) =>
		(count === 1 ? one : many).replace('%n', String(count)),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	dashboardWidgetRegistry: { get: () => null, has: () => false, list: () => [] },
	NcPopover: {
		name: 'NcPopover',
		template: '<div><slot name="trigger" /><slot /></div>',
	},
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	CnConfirmDialog: {
		name: 'CnConfirmDialog',
		props: ['dialogTitle', 'message', 'confirmLabel'],
		emits: ['confirm', 'close'],
		template:
			'<div class="dialog">{{ message }}<button data-testid="cn-confirm-dialog-confirm" @click="$emit(\'confirm\')">{{ confirmLabel }}</button></div>',
	},
}))

const hidden = [
	{ id: 2, widgetId: 'weather', customTitle: 'Weather' },
	{ id: 5, widgetId: 'text', customTitle: 'Canteen menu' },
]

describe('HiddenWidgetsControl', () => {
	it('REQ-PERSUI-002: shows the count and names each hidden widget', () => {
		const wrapper = mount(HiddenWidgetsControl, {
			props: { hiddenPlacements: hidden },
		})
		expect(wrapper.text()).toContain('Hidden (2)')
		expect(wrapper.text()).toContain('Weather')
		expect(wrapper.text()).toContain('Canteen menu')
	})

	it('REQ-PERSUI-002: is absent when nothing is hidden', () => {
		const wrapper = mount(HiddenWidgetsControl, {
			props: { hiddenPlacements: [] },
		})
		expect(wrapper.find('[data-testid="hidden-widgets"]').exists()).toBe(false)
	})

	it('REQ-PERSUI-002: Show again emits the placement; reset asks first', async () => {
		const wrapper = mount(HiddenWidgetsControl, {
			props: { hiddenPlacements: hidden },
		})
		await wrapper
			.findAll('[data-testid="hidden-show-again"]')[0]
			.trigger('click')
		expect(wrapper.emitted('showAgain')[0][0]).toBe(2)
		expect(
			wrapper.find('[data-testid="cn-confirm-dialog-confirm"]').exists(),
		).toBe(false)
		await wrapper.find('[data-testid="hidden-reset"]').trigger('click')
		expect(wrapper.emitted('reset')).toBeUndefined()
		await wrapper
			.find('[data-testid="cn-confirm-dialog-confirm"]')
			.trigger('click')
		expect(wrapper.emitted('reset')).toHaveLength(1)
	})
})
