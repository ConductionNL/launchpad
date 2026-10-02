/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskPlaylistDialog: the playlist form keeps to KioskService's limits
 * (dwell 10 to 86400, refresh 30 to 86400) and emits the body the playlist
 * endpoints take (dashboard-kiosk-mode REQ-KIOSKUI-001).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import KioskPlaylistDialog, {
	DWELL_MIN,
	REFRESH_MIN,
} from '../KioskPlaylistDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open'],
		template: '<div v-if="open"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input class="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template:
			'<select class="select" @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])"><option v-for="o in options" :key="o.uuid">{{ o.name }}</option></select>',
	},
}))

const dashboards = [
	{ uuid: 'n', name: 'Nieuws' },
	{ uuid: 'k', name: 'Kantine' },
]

describe('KioskPlaylistDialog', () => {
	it('uses the service limits', () => {
		expect(DWELL_MIN).toBe(10)
		expect(REFRESH_MIN).toBe(30)
	})

	it('REQ-KIOSKUI-001: creates a lobby playlist with two dashboards', async () => {
		const wrapper = mount(KioskPlaylistDialog, {
			props: { open: true, dashboards },
		})
		await wrapper.find('input.text').setValue('Lobby')
		await wrapper.find('[data-testid="kiosk-add-entry"]').trigger('click')
		const selects = wrapper.findAll('select.select')
		selects[0].element.selectedIndex = 0
		await selects[0].trigger('change')
		selects[1].element.selectedIndex = 1
		await selects[1].trigger('change')
		const dwell = wrapper.findAll('[data-testid="kiosk-dwell"]')
		await dwell[0].setValue(30)
		await dwell[1].setValue(20)
		await wrapper.find('[data-testid="kiosk-refresh"]').setValue(300)
		await wrapper.find('[data-testid="kiosk-save"]').trigger('click')
		expect(wrapper.emitted('save')[0][0]).toEqual({
			name: 'Lobby',
			entries: [
				{ dashboardUuid: 'n', dwellSeconds: 30 },
				{ dashboardUuid: 'k', dwellSeconds: 20 },
			],
			refreshSeconds: 300,
		})
	})

	it('REQ-KIOSKUI-001: a dwell below the minimum is named and not saved', async () => {
		const wrapper = mount(KioskPlaylistDialog, {
			props: { open: true, dashboards },
		})
		await wrapper.find('input.text').setValue('Lobby')
		const select = wrapper.find('select.select')
		select.element.selectedIndex = 0
		await select.trigger('change')
		await wrapper.find('[data-testid="kiosk-dwell"]').setValue(5)
		expect(wrapper.find('[data-testid="kiosk-problem"]').text()).toContain(
			'at least 10 seconds',
		)
		await wrapper.find('[data-testid="kiosk-save"]').trigger('click')
		expect(wrapper.emitted('save')).toBeUndefined()
	})

	it('fills the form when editing', () => {
		const playlist = {
			id: 1,
			name: 'Lobby',
			refreshSeconds: 600,
			entries: [{ dashboardUuid: 'k', dwellSeconds: 45 }],
		}
		const wrapper = mount(KioskPlaylistDialog, {
			props: { open: true, dashboards, playlist },
		})
		expect(wrapper.find('input.text').element.value).toBe('Lobby')
		expect(wrapper.find('[data-testid="kiosk-dwell"]').element.value).toBe('45')
		expect(wrapper.find('[data-testid="kiosk-refresh"]').element.value).toBe(
			'600',
		)
	})
})
