/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * KioskTab: the row actions and failure messages the listing spec leaves
 * out (dashboard-kiosk-mode REQ-KIOSKUI-001): copying the public link,
 * editing an existing playlist, the save, revoke and load errors, and
 * dismissing the revoke confirmation. Responses are KioskController's.
 */

import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
	CnConfirmDialog: {
		name: 'CnConfirmDialog',
		props: ['dialogTitle', 'message', 'confirmLabel', 'variant'],
		emits: ['confirm', 'close'],
		template: '<div class="revoke-stub">{{ message }}</div>',
	},
	// The playlist dialog is stubbed per mount; these keep its import resolvable.
	NcDialog: { name: 'NcDialog', template: '<div />' },
	NcSelect: { name: 'NcSelect', template: '<div />' },
	NcTextField: { name: 'NcTextField', template: '<div />' },
}))

const lobby = {
	id: 1,
	name: 'Lobby',
	token: 'a b/c',
	entries: [{ dashboardUuid: 'n', dwellSeconds: 30 }],
	refreshSeconds: 300,
}

const body = {
	name: 'Lobby 2',
	entries: [{ dashboardUuid: 'n', dwellSeconds: 15 }],
	refreshSeconds: 120,
}

let dashboardsResponse

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
			},
		},
	})
}

async function mounted() {
	const wrapper = mountTab()
	await flushPromises()
	return wrapper
}

const errorText = (wrapper) => wrapper.find('[role="alert"]').text()
const dialog = (wrapper) => wrapper.findComponent({ name: 'KioskPlaylistDialog' })

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	dashboardsResponse = { data: { items: [{ uuid: 'n', name: 'Nieuws' }] } }
	axios.get.mockImplementation((url) =>
		url.includes('/api/kiosk/playlists')
			? Promise.resolve({ data: [lobby] })
			: Promise.resolve(dashboardsResponse),
	)
})

afterEach(() => {
	vi.unstubAllGlobals()
})

describe('KioskTab loading', () => {
	it('reads the dashboards from an items envelope', async () => {
		const wrapper = await mounted()

		expect(dialog(wrapper).props('dashboards')).toEqual([{ uuid: 'n', name: 'Nieuws' }])
		expect(wrapper.find('[role="alert"]').exists()).toBe(false)
	})

	it('falls back to no dashboards for an unexpected payload', async () => {
		dashboardsResponse = { data: null }
		const wrapper = await mounted()

		expect(dialog(wrapper).props('dashboards')).toEqual([])
	})

	it('says so when the playlists cannot be loaded', async () => {
		axios.get.mockRejectedValue(new Error('down'))
		const wrapper = await mounted()

		expect(errorText(wrapper)).toBe('The playlists could not be loaded.')
		expect(wrapper.find('.kiosk-tab__empty').exists()).toBe(true)
	})
})

describe('KioskTab copy link', () => {
	it('copies the encoded absolute link and confirms', async () => {
		const writeText = vi.fn().mockResolvedValue()
		vi.stubGlobal('navigator', { clipboard: { writeText } })
		const wrapper = await mounted()

		await wrapper.find('[data-testid="kiosk-copy"]').trigger('click')
		await flushPromises()

		expect(writeText).toHaveBeenCalledWith(
			window.location.origin + '/index.php/apps/launchpad/kiosk/a%20b%2Fc',
		)
		expect(showSuccess).toHaveBeenCalledWith('Link copied')
	})

	it('tells the admin to copy by hand when the clipboard refuses', async () => {
		vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } })
		const wrapper = await mounted()

		await wrapper.find('[data-testid="kiosk-copy"]').trigger('click')
		await flushPromises()

		expect(showSuccess).not.toHaveBeenCalled()
		expect(errorText(wrapper)).toBe('The link could not be copied. Select it and copy it by hand.')
	})
})

describe('KioskTab edit and save', () => {
	it('opens the form on a row and saves it with a PUT', async () => {
		axios.put.mockResolvedValue({ data: { ...lobby, ...body } })
		const wrapper = await mounted()

		await wrapper.find('[data-testid="kiosk-edit"]').trigger('click')
		expect(dialog(wrapper).props('open')).toBe(true)
		expect(dialog(wrapper).props('playlist')).toEqual(lobby)

		dialog(wrapper).vm.$emit('save', body)
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith('/index.php/apps/launchpad/api/kiosk/playlists/1', body)
		expect(axios.post).not.toHaveBeenCalled()
		expect(dialog(wrapper).props('open')).toBe(false)
		expect(wrapper.find('[data-testid="kiosk-row"]').text()).toContain('Lobby 2')
	})

	it('closes the form when the dialog asks to', async () => {
		const wrapper = await mounted()
		await wrapper.find('[data-testid="kiosk-new"]').trigger('click')

		dialog(wrapper).vm.$emit('update:open', false)
		await flushPromises()

		expect(dialog(wrapper).props('open')).toBe(false)
	})

	it('explains a 403 and keeps the form open', async () => {
		axios.post.mockRejectedValue({ response: { status: 403 } })
		const wrapper = await mounted()
		await wrapper.find('[data-testid="kiosk-new"]').trigger('click')

		dialog(wrapper).vm.$emit('save', body)
		await flushPromises()

		expect(errorText(wrapper)).toBe('You may only put dashboards you own or manage in a playlist.')
		expect(dialog(wrapper).props('open')).toBe(true)
	})

	it('reports any other save failure generically, and clears it on the next save', async () => {
		axios.post.mockRejectedValueOnce({ response: { status: 500 } })
		axios.post.mockResolvedValueOnce({ data: { ...lobby, id: 2 } })
		const wrapper = await mounted()
		await wrapper.find('[data-testid="kiosk-new"]').trigger('click')

		dialog(wrapper).vm.$emit('save', body)
		await flushPromises()
		expect(errorText(wrapper)).toBe('The playlist could not be saved.')

		dialog(wrapper).vm.$emit('save', body)
		await flushPromises()
		expect(wrapper.find('[role="alert"]').exists()).toBe(false)
	})
})

describe('KioskTab revoke', () => {
	it('dismissing the confirmation revokes nothing', async () => {
		const wrapper = await mounted()
		await wrapper.find('[data-testid="kiosk-revoke"]').trigger('click')

		wrapper.findComponent({ name: 'CnConfirmDialog' }).vm.$emit('close')
		await flushPromises()

		expect(wrapper.findComponent({ name: 'CnConfirmDialog' }).exists()).toBe(false)
		expect(axios.delete).not.toHaveBeenCalled()
	})

	it('reports a failed revoke and keeps the row', async () => {
		axios.delete.mockRejectedValue(new Error('500'))
		const wrapper = await mounted()
		await wrapper.find('[data-testid="kiosk-revoke"]').trigger('click')

		wrapper.findComponent({ name: 'CnConfirmDialog' }).vm.$emit('confirm')
		await flushPromises()

		expect(errorText(wrapper)).toBe('The playlist could not be revoked.')
		expect(wrapper.findAll('[data-testid="kiosk-row"]')).toHaveLength(1)
	})

	it('a confirm without a target does nothing', async () => {
		const wrapper = await mounted()

		await wrapper.vm.revoke()

		expect(axios.delete).not.toHaveBeenCalled()
	})
})
