/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskTab: lists playlists with their public link, creates and revokes
 * through the playlist endpoints (dashboard-kiosk-mode REQ-KIOSKUI-001).
 * Responses are KioskController's: a playlist is KioskPlaylist::jsonSerialize.
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
	NcDialog: { name: 'NcDialog', template: '<div />' },
	NcSelect: { name: 'NcSelect', template: '<div />' },
	NcTextField: { name: 'NcTextField', template: '<div />' },
}))

const lobby = {
	id: 1,
	name: 'Lobby',
	token: 'abc123',
	url: null,
	entries: [{ dashboardUuid: 'n', dwellSeconds: 30 }],
	refreshSeconds: 300,
	createdBy: 'admin',
	createdAt: '2026-09-29 10:00:00',
	revokedAt: null,
}

function mountTab() {
	return mount(KioskTab, {
		global: {
			stubs: {
				KioskPlaylistDialog: {
					name: 'KioskPlaylistDialog',
					props: ['open', 'playlist', 'dashboards'],
					emits: ['save', 'update:open'],
					template: '<div class="form-stub" />',
				},
				RevokeKioskPlaylistDialog: {
					name: 'RevokeKioskPlaylistDialog',
					props: ['open', 'name'],
					emits: ['confirm', 'update:open'],
					template: '<div class="revoke-stub" />',
				},
			},
		},
	})
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	axios.get.mockImplementation((url) =>
		Promise.resolve({
			data: url.includes('/api/kiosk/playlists')
				? [lobby]
				: [{ uuid: 'n', name: 'Nieuws' }],
		}),
	)
})

describe('KioskTab', () => {
	it('REQ-KIOSKUI-001: lists playlists with a copyable public link', async () => {
		const wrapper = mountTab()
		await flushPromises()
		const rows = wrapper.findAll('[data-testid="kiosk-row"]')
		expect(rows).toHaveLength(1)
		expect(rows[0].text()).toContain('Lobby')
		expect(rows[0].text()).toContain('/index.php/apps/launchpad/kiosk/abc123')
		expect(
			wrapper
				.findComponent({ name: 'KioskPlaylistDialog' })
				.props('dashboards'),
		).toEqual([{ uuid: 'n', name: 'Nieuws' }])
	})

	it('REQ-KIOSKUI-001: saving a new playlist posts it', async () => {
		axios.post.mockResolvedValue({ data: { ...lobby, id: 2, name: 'Kantine' } })
		const wrapper = mountTab()
		await flushPromises()
		await wrapper.find('[data-testid="kiosk-new"]').trigger('click')
		const body = {
			name: 'Kantine',
			entries: [{ dashboardUuid: 'n', dwellSeconds: 20 }],
			refreshSeconds: 300,
		}
		wrapper.findComponent({ name: 'KioskPlaylistDialog' }).vm.$emit('save', body)
		await flushPromises()
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/kiosk/playlists',
			body,
		)
		expect(wrapper.findAll('[data-testid="kiosk-row"]')).toHaveLength(2)
	})

	it('REQ-KIOSKUI-001: revoking after confirmation deletes the playlist', async () => {
		axios.delete.mockResolvedValue({ status: 204 })
		const wrapper = mountTab()
		await flushPromises()
		await wrapper.find('[data-testid="kiosk-revoke"]').trigger('click')
		expect(
			wrapper
				.findComponent({ name: 'RevokeKioskPlaylistDialog' })
				.props('open'),
		).toBe(true)
		wrapper
			.findComponent({ name: 'RevokeKioskPlaylistDialog' })
			.vm.$emit('confirm')
		await flushPromises()
		expect(axios.delete).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/kiosk/playlists/1',
		)
		expect(wrapper.findAll('[data-testid="kiosk-row"]')).toHaveLength(0)
	})
})
