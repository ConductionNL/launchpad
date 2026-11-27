/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskTab: the screen link switch and the screen link rows
 * (dashboard-show-on-a-screen REQ-KIOSK-007, REQ-KIOSK-008).
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import KioskTab from '../KioskTab.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'disabled'],
		emits: ['update:modelValue'],
		template: '<label><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)"><slot /></label>',
	},
	CnConfirmDialog: { name: 'CnConfirmDialog', template: '<div />' },
	NcDialog: { name: 'NcDialog', template: '<div />' },
	NcSelect: { name: 'NcSelect', template: '<div />' },
	NcTextField: { name: 'NcTextField', template: '<div />' },
}))

const lobby = {
	id: 1,
	name: 'Lobby',
	token: 'abc123',
	entries: [{ dashboardUuid: 'n', dwellSeconds: 30 }],
	createdBy: 'admin',
	screenDashboardUuid: null,
}

const kantine = {
	id: 2,
	name: 'Kantine',
	token: 'kan123',
	entries: [{ dashboardUuid: 'dash-kantine', dwellSeconds: 10 }],
	createdBy: 'pieter',
	screenDashboardUuid: 'dash-kantine',
}

let settings

function mountTab() {
	return mount(KioskTab, {
		global: {
			stubs: { KioskPlaylistDialog: { name: 'KioskPlaylistDialog', template: '<div />' } },
		},
	})
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	settings = {}
	axios.get.mockImplementation((url) => {
		if (url.includes('/api/kiosk/playlists')) {
			return Promise.resolve({ data: [kantine, lobby] })
		}
		if (url.includes('/api/admin/settings')) {
			return Promise.resolve({ data: settings })
		}
		return Promise.resolve({ data: [{ uuid: 'dash-kantine', name: 'Kantine' }] })
	})
	axios.put.mockResolvedValue({ data: { status: 'ok' } })
})

describe('KioskTab screen links (REQ-KIOSK-007, REQ-KIOSK-008)', () => {
	it('lists the screen link after the playlists, labelled, with its dashboard and creator and without Edit', async () => {
		const wrapper = mountTab()
		await flushPromises()

		const rows = wrapper.findAll('tbody tr')
		expect(rows).toHaveLength(2)
		expect(rows[0].attributes('data-testid')).toBe('kiosk-row')
		expect(rows[1].attributes('data-testid')).toBe('kiosk-screen-link-row')
		expect(rows[1].find('[data-testid="kiosk-screen-link-label"]').text()).toBe('Screen link')
		expect(rows[1].text()).toContain('Kantine, made by pieter')
		expect(rows[1].find('[data-testid="kiosk-edit"]').exists()).toBe(false)
		expect(rows[1].find('[data-testid="kiosk-revoke"]').exists()).toBe(true)
		expect(rows[1].find('[data-testid="kiosk-copy"]').exists()).toBe(true)
		expect(rows[0].find('[data-testid="kiosk-edit"]').exists()).toBe(true)
	})

	it('reads the switch as on when the setting was never stored', async () => {
		const wrapper = mountTab()
		await flushPromises()

		expect(wrapper.vm.screenLinksAllowed).toBe(true)
	})

	it('reads a stored off', async () => {
		settings = { screenLinksAllowed: false }
		const wrapper = mountTab()
		await flushPromises()

		expect(wrapper.vm.screenLinksAllowed).toBe(false)
	})

	it('saves the switch at once', async () => {
		const wrapper = mountTab()
		await flushPromises()

		await wrapper.find('[data-testid="kiosk-screen-links-allowed"] input').setValue(false)
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith(
			expect.stringContaining('/api/admin/sharing-policy'),
			expect.objectContaining({ screenLinksAllowed: false }),
		)
		expect(wrapper.vm.screenLinksAllowed).toBe(false)
	})

	it('puts the switch back when saving fails', async () => {
		axios.put.mockRejectedValueOnce(new Error('500'))
		const wrapper = mountTab()
		await flushPromises()

		await wrapper.vm.setScreenLinksAllowed(false)

		expect(wrapper.vm.screenLinksAllowed).toBe(true)
		expect(wrapper.text()).toContain('The setting could not be saved.')
	})
})
